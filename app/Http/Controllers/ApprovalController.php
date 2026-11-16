<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\FundAccount;
use App\Models\TransactionType;
use App\Services\ApprovalService;
use App\Support\ProjectFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Transactions en attente de validation et historique des validations. */
class ApprovalController extends Controller
{
    /** Types prévus par le règlement qui se saisissent comme une transaction ordinaire. */
    public const GENERIC_SYSTEM_TYPES = ['bank_income', 'manual_expense', 'internal_transfer'];

    public function __construct(private ApprovalService $approvals) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('approve transactions') || ApprovalRequest::where('requested_by', $user->id)->exists(), 403);

        $format = fn (ApprovalRequest $r) => [
            'id' => $r->id, 'title' => $r->title, 'type' => $r->type?->name, 'type_code' => $r->type?->code,
            'amount' => ProjectFormatter::money($r->amount), 'status' => $r->status, 'status_label' => ApprovalRequest::STATUSES[$r->status] ?? $r->status,
            'approvals' => $r->decisions->where('decision', 'approve')->count(), 'required' => $r->required_approvals,
            'approver_roles' => $r->approver_roles, 'requested_by' => $r->requester?->name, 'beneficiary' => $r->beneficiary?->name,
            'decisions' => $r->decisions->map(fn ($d) => ['user' => $d->user?->name, 'decision' => $d->decision, 'comment' => $d->comment, 'date' => $d->created_at?->timezone('Europe/Paris')->format('d/m/Y H:i')])->values(),
            'can_approve' => $this->approvals->canApprove($user, $r),
            'can_cancel' => $r->status === 'pending' && $r->requested_by === $user->id,
            'can_retry' => $r->status === 'failed' && $user->hasAnyRole($r->approver_roles ?: []),
            'failure_reason' => $r->failure_reason, 'rejected_reason' => $r->rejected_reason,
            'subject_url' => $r->subject_type === (new \App\Models\Project)->getMorphClass() ? route('projects.show', $r->subject_id) : null,
            'created_at' => $r->created_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
            'executed_at' => $r->executed_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
        ];
        $base = ApprovalRequest::query()->with(['type', 'decisions.user', 'requester', 'beneficiary'])
            ->when(! $user->can('approve transactions'), fn ($q) => $q->where('requested_by', $user->id));

        return Inertia::render('Approvals/Index', [
            'pending' => (clone $base)->whereIn('status', ['pending', 'failed'])->latest()->get()->map($format),
            'history' => (clone $base)->whereNotIn('status', ['pending', 'failed'])->latest()->limit(1000)->get()->map($format),
            'types' => TransactionType::where('is_active', true)->where(fn ($q) => $q->where('is_system', false)->orWhereIn('code', self::GENERIC_SYSTEM_TYPES))->orderBy('name')
                ->get(['id', 'code', 'name', 'direction', 'required_approvals']),
            'accounts' => FundAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'current_balance']),
            'canCreate' => $user->can('approve transactions'),
        ]);
    }

    /** Nouvelle transaction d'un type créé par l'administration (frais bancaires, dons reçus…). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('approve transactions'), 403);
        $data = $request->validate([
            'transaction_type_id' => ['required', 'exists:transaction_types,id'],
            'title' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fund_account_id' => ['nullable', 'exists:fund_accounts,id'],
            'to_fund_account_id' => ['nullable', 'exists:fund_accounts,id', 'different:fund_account_id'],
            'movement_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $type = TransactionType::findOrFail($data['transaction_type_id']);
        abort_if($type->is_system && ! in_array($type->code, self::GENERIC_SYSTEM_TYPES, true), 422, 'Ce type de transaction se crée depuis son propre écran.');
        if ($type->direction === 'internal' && (empty($data['fund_account_id']) || empty($data['to_fund_account_id']))) {
            return back()->withErrors(['to_fund_account_id' => 'Choisissez le compte de départ et le compte d’arrivée.']);
        }
        if ($type->direction !== 'internal' && empty($data['fund_account_id'])) {
            return back()->withErrors(['fund_account_id' => 'Choisissez le compte du fonds concerné.']);
        }
        try {
            $result = $this->approvals->request($type->code, $data['title'], $request->user(), (float) $data['amount'], [
                'fund_account_id' => $data['fund_account_id'] ?? null,
                'from_fund_account_id' => $data['fund_account_id'] ?? null,
                'to_fund_account_id' => $data['to_fund_account_id'] ?? null,
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
            ]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', $result->status === 'executed' ? 'Transaction enregistrée.' : 'Transaction soumise à validation.');
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        try {
            $result = $this->approvals->approve($approvalRequest, $request->user(), $data['comment'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('success', $result->status === 'executed'
            ? 'Transaction validée et exécutée.'
            : 'Validation enregistrée ('.$result->approvalsCount().'/'.$result->required_approvals.').');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        try {
            $this->approvals->reject($approvalRequest, $request->user(), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('success', 'Transaction refusée.');
    }

    public function retry(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        try {
            $this->approvals->retry($approvalRequest, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('success', 'Transaction exécutée.');
    }
}
