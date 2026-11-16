<?php

namespace App\Http\Controllers;

use App\Enums\FundMovementType;
use App\Models\AuditReview;
use App\Models\FundAccount;
use App\Models\ProjectType;
use App\Models\SharedReport;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\FundNotification;
use App\Services\ReportExporter;
use App\Services\ReportService;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transparence et rapports (section 6) : tableau de bord filtrable, exports Excel/PDF, relevés,
 * rapports partagés avec les membres et vérification annuelle des comptes.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private ReportExporter $exporter) {}

    private function canViewFull(User $user): bool
    {
        return $user->can('view reports') || $this->isAuditor($user);
    }

    private function isAuditor(User $user): bool
    {
        return AuditReview::where('status', 'open')->whereHas('reviewers', fn ($q) => $q->whereKey($user->id))->exists();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $full = $this->canViewFull($user);
        $filters = $this->reports->normalizeFilters($request->only(['from', 'to', 'zone_id', 'project_type_id', 'user_id', 'fund_account_id', 'movement_type']));

        return Inertia::render('Reports/Index', [
            'full' => $full,
            'report' => $full ? $this->reports->full($filters) : null,
            'filters' => [
                'from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString(),
                'zone_id' => $filters['zone_id'], 'project_type_id' => $filters['project_type_id'], 'user_id' => $filters['user_id'],
                'fund_account_id' => $filters['fund_account_id'], 'movement_type' => $filters['movement_type'],
            ],
            'options' => $full ? [
                'zones' => Zone::orderBy('name')->get(['id', 'name']),
                'projectTypes' => ProjectType::orderBy('name')->get(['id', 'name']),
                'members' => User::whereHas('memberProfile')->orderBy('name')->get(['id', 'name']),
                'accounts' => FundAccount::orderBy('name')->get(['id', 'name']),
                'movementTypes' => FundMovementType::options(),
                'sections' => collect(ReportExporter::SECTIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            ] : null,
            'shared' => SharedReport::query()->whereNotNull('shared_at')->latest('period_end')->limit(50)->get()->map(fn (SharedReport $r) => [
                'id' => $r->id, 'title' => $r->title, 'kind' => $r->kind, 'period' => $r->period_start->format('d/m/Y').' – '.$r->period_end->format('d/m/Y'),
                'shared_at' => $r->shared_at?->timezone('Europe/Paris')->format('d/m/Y'),
            ]),
            'audits' => AuditReview::query()->with('reviewers:id,name', 'confirmer:id,name')->latest('year')->get()->map(fn (AuditReview $a) => [
                'id' => $a->id, 'year' => $a->year, 'status' => $a->status, 'status_label' => AuditReview::STATUSES[$a->status] ?? $a->status,
                'reviewers' => $a->reviewers->pluck('name'), 'conclusion' => $a->conclusion,
                'confirmed_at' => $a->confirmed_at?->timezone('Europe/Paris')->format('d/m/Y'), 'confirmed_by' => $a->confirmer?->name,
                'can_confirm' => $a->status === 'open' && $a->reviewers->contains('id', $user->id),
            ]),
            'auditCandidates' => $user->can('manage reports')
                ? User::whereHas('memberProfile', fn ($q) => $q->where('status', 'active'))->whereDoesntHave('roles', fn ($q) => $q->where('name', 'treasurer'))->orderBy('name')->get(['id', 'name'])
                : [],
            'can' => ['manage' => $user->can('manage reports'), 'export' => $full],
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($this->canViewFull($request->user()), 403);
        $data = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
            'sections' => ['nullable', 'array'], 'sections.*' => ['in:'.implode(',', array_keys(ReportExporter::SECTIONS))],
            'period' => ['nullable', 'in:custom,day,month,year'],
        ]);
        $input = $request->only(['from', 'to', 'zone_id', 'project_type_id', 'user_id', 'fund_account_id', 'movement_type']);
        // Relevés journalier, mensuel ou annuel : la période se cale sur la date de fin choisie.
        $end = CarbonImmutable::parse($input['to'] ?? now());
        $title = 'Rapport du fonds';
        if (($data['period'] ?? 'custom') !== 'custom') {
            [$input['from'], $input['to'], $title] = match ($data['period']) {
                'day' => [$end->toDateString(), $end->toDateString(), 'Relevé journalier'],
                'month' => [$end->startOfMonth()->toDateString(), $end->endOfMonth()->toDateString(), 'Relevé mensuel'],
                'year' => [$end->startOfYear()->toDateString(), $end->endOfYear()->toDateString(), 'Relevé annuel'],
            };
        }
        $report = $this->reports->full($this->reports->normalizeFilters($input));
        $sections = $data['sections'] ?? array_keys(ReportExporter::SECTIONS);
        activity('reports')->causedBy($request->user())->event('report.exported')
            ->withProperties(['format' => $data['format'], 'sections' => $sections, 'period' => $report['period']])->log('Rapport exporté');

        return $data['format'] === 'xlsx'
            ? $this->exporter->xlsx($report, $title, $sections)
            : $this->exporter->pdf($report, $title, $sections);
    }

    /** Fige un rapport (ex. trimestriel) et le partage avec tous les membres. */
    public function share(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage reports'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'kind' => ['required', 'in:quarterly,monthly,annual,custom'],
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);
        $report = $this->reports->full($this->reports->normalizeFilters(['from' => $data['from'], 'to' => $data['to']]));
        $shared = SharedReport::create([
            'title' => $data['title'], 'kind' => $data['kind'], 'period_start' => $data['from'], 'period_end' => $data['to'],
            'data' => $report, 'comment' => $data['comment'] ?? null, 'created_by' => $request->user()->id, 'shared_at' => now(),
        ]);
        activity('reports')->performedOn($shared)->causedBy($request->user())->event('report.shared')->log('Rapport partagé : '.$shared->title);
        Notifier::send(Notifier::activeMembers(), new FundNotification(
            ['Nouveau rapport du fonds : :title', ['title' => $shared->title]],
            [['Période du :from au :to.', ['from' => $shared->period_start->format('d/m/Y'), 'to' => $shared->period_end->format('d/m/Y')]],
             ['Total des cotisations, retards et pénalités, solde du compte et état des remboursements.']],
            route('reports.shared', $shared), 'Lire le rapport', 'report',
        ));

        return back()->with('success', 'Rapport partagé avec tous les membres.');
    }

    public function shared(SharedReport $sharedReport): Response
    {
        abort_unless($sharedReport->shared_at, 404);

        return Inertia::render('Reports/Shared', [
            'shared' => [
                'id' => $sharedReport->id, 'title' => $sharedReport->title, 'comment' => $sharedReport->comment,
                'period' => $sharedReport->period_start->format('d/m/Y').' – '.$sharedReport->period_end->format('d/m/Y'),
                'shared_at' => $sharedReport->shared_at?->timezone('Europe/Paris')->format('d/m/Y'),
                'author' => $sharedReport->creator?->name,
            ],
            'report' => $sharedReport->data,
        ]);
    }

    public function sharedPdf(SharedReport $sharedReport)
    {
        abort_unless($sharedReport->shared_at, 404);

        return $this->exporter->pdf($sharedReport->data, $sharedReport->title, ['summary', 'members', 'loans', 'movements'], $sharedReport->comment);
    }

    public function storeAudit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage reports'), 403);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100', 'unique:audit_reviews,year'],
            'reviewer_ids' => ['required', 'array', 'min:1', 'max:2'],
            'reviewer_ids.*' => ['integer', 'exists:users,id'],
        ], ['reviewer_ids.max' => 'Un ou deux membres au maximum.', 'year.unique' => 'Une vérification existe déjà pour cette année.']);
        $treasurers = User::role('treasurer')->whereIn('id', $data['reviewer_ids'])->exists();
        if ($treasurers) {
            return back()->withErrors(['reviewer_ids' => 'Les vérificateurs doivent être des membres autres que les trésoriers.']);
        }
        $audit = AuditReview::create(['year' => $data['year'], 'status' => 'open', 'created_by' => $request->user()->id]);
        $audit->reviewers()->sync($data['reviewer_ids']);
        activity('reports')->performedOn($audit)->causedBy($request->user())->event('audit.created')
            ->withProperties(['reviewers' => $data['reviewer_ids']])->log('Vérification annuelle des comptes ouverte pour '.$audit->year);
        Notifier::send(User::whereIn('id', $data['reviewer_ids'])->get(), new FundNotification(
            ['Vous vérifiez les comptes de :year', ['year' => $audit->year]],
            [['Le groupe vous a choisi(e) pour examiner les registres et relevés de :year.', ['year' => $audit->year]],
             ['Vous avez accès aux rapports, relevés et exports. Confirmez par écrit avant fin février.']],
            route('reports.index', ['from' => $audit->year.'-01-01', 'to' => $audit->year.'-12-31']), 'Ouvrir les rapports', 'report',
        ));

        return back()->with('success', 'Vérification annuelle ouverte.');
    }

    public function confirmAudit(Request $request, AuditReview $auditReview): RedirectResponse
    {
        abort_unless($auditReview->status === 'open' && $auditReview->reviewers()->whereKey($request->user()->id)->exists(), 403);
        $data = $request->validate([
            'status' => ['required', 'in:confirmed,issues'],
            'conclusion' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $auditReview->update([...$data, 'confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);
        activity('reports')->performedOn($auditReview)->causedBy($request->user())->event('audit.'.$data['status'])
            ->withProperties(['conclusion' => $data['conclusion']])->log('Vérification des comptes '.$auditReview->year.' : '.AuditReview::STATUSES[$data['status']]);
        Notifier::send(Notifier::activeMembers(), new FundNotification(
            ['Vérification des comptes :year : :status', ['year' => $auditReview->year, 'status' => __(AuditReview::STATUSES[$data['status']])]],
            [['Conclusion de :name : :text', ['name' => $request->user()->name, 'text' => $data['conclusion']]]],
            route('reports.index'), 'Voir les rapports', 'report',
        ));

        return back()->with('success', 'Conclusion enregistrée et partagée avec les membres.');
    }
}
