<?php

namespace App\Http\Controllers;

use App\Models\FundAccount;
use App\Models\WithdrawalInstallment;
use App\Models\WithdrawalRequest;
use App\Services\EligibilityService;
use App\Services\WithdrawalService;
use App\Support\FundRules;
use App\Support\ProjectFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Retrait d'un membre (section 8). */
class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $withdrawals) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $manage = $user->can('manage withdrawals');
        $format = fn (WithdrawalRequest $w) => [
            'id' => $w->id, 'member' => $w->user?->name, 'user_id' => $w->user_id, 'reason' => $w->reason,
            'requested_on' => $w->requested_on->format('d/m/Y'), 'effective_date' => $w->effective_date->format('d/m/Y'),
            'effective_date_raw' => $w->effective_date->toDateString(),
            'schedule_deadline' => $w->requested_on->copy()->addWeeks(2)->format('d/m/Y'),
            'late_schedule' => $w->status === 'requested' && now()->gt($w->requested_on->copy()->addWeeks(2)),
            'total_contributions' => ProjectFormatter::money($w->total_contributions),
            'refund_percent' => (float) $w->refund_percent, 'refund_amount' => ProjectFormatter::money($w->refund_amount),
            'refund_amount_raw' => (float) $w->refund_amount, 'funds_engaged_in_loans' => $w->funds_engaged_in_loans,
            'status' => $w->status, 'status_label' => WithdrawalRequest::STATUSES[$w->status] ?? $w->status, 'notes' => $w->notes,
            'installments' => $w->installments->map(fn ($i) => [
                'id' => $i->id, 'due_date' => $i->due_date->format('d/m/Y'), 'due_date_raw' => $i->due_date->toDateString(),
                'amount' => ProjectFormatter::money($i->amount), 'amount_raw' => (float) $i->amount,
                'paid_at' => $i->paid_at?->format('d/m/Y'), 'pending_approval' => (bool) $i->approval_request_id && ! $i->paid_at,
            ])->values(),
        ];

        $eligibility = app(EligibilityService::class);

        return Inertia::render('Withdrawals/Index', [
            'mine' => WithdrawalRequest::with('installments', 'user')->where('user_id', $user->id)->latest()->get()->map($format),
            'all' => $manage ? WithdrawalRequest::with('installments', 'user')->latest()->get()->map($format) : [],
            'preview' => [
                'total' => ProjectFormatter::money($eligibility->totalContributions($user)),
                'refund' => ProjectFormatter::money($eligibility->totalContributions($user) * FundRules::get('withdrawal_refund_percent') / 100),
                'percent' => FundRules::get('withdrawal_refund_percent'),
                'notice_months' => FundRules::get('withdrawal_notice_months'),
                'effective_date' => now()->addMonthsNoOverflow(FundRules::get('withdrawal_notice_months'))->format('d/m/Y'),
                'max_months' => FundRules::get('withdrawal_max_refund_months'),
            ],
            'accounts' => $manage ? FundAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'current_balance']) : [],
            'canManage' => $manage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:5000'], 'confirm' => ['accepted']],
            ['reason.required' => 'Expliquez la raison de votre départ.', 'confirm.accepted' => 'Confirmez que vous avez compris les conditions de départ.']);
        try {
            $this->withdrawals->request($request->user(), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Demande de retrait enregistrée. Le calendrier de remboursement vous sera communiqué sous deux semaines.');
    }

    public function schedule(Request $request, WithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        abort_unless($request->user()->can('manage withdrawals'), 403);
        $data = $request->validate([
            'installments' => ['required', 'array', 'min:1', 'max:12'],
            'installments.*.due_date' => ['required', 'date', 'after_or_equal:today'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'funds_engaged_in_loans' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $this->withdrawals->schedule($withdrawalRequest, $request->user(), $data['installments'], (bool) ($data['funds_engaged_in_loans'] ?? false), $data['notes'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['installments' => $e->getMessage()]);
        }

        return back()->with('success', 'Calendrier communiqué au membre.');
    }

    public function pay(Request $request, WithdrawalInstallment $withdrawalInstallment): RedirectResponse
    {
        abort_unless($request->user()->can('manage withdrawals'), 403);
        $data = $request->validate(['fund_account_id' => ['required', 'exists:fund_accounts,id']]);
        try {
            $result = $this->withdrawals->requestPayment($withdrawalInstallment, $request->user(), (int) $data['fund_account_id']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', $result->status === 'executed' ? 'Versement effectué.' : 'Versement soumis à validation.');
    }

    public function cancel(Request $request, WithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        abort_unless($withdrawalRequest->user_id === $request->user()->id || $request->user()->can('manage withdrawals'), 403);
        abort_if($withdrawalRequest->installments()->whereNotNull('paid_at')->exists() || $withdrawalRequest->status === 'completed', 422, 'Des versements ont déjà eu lieu.');
        $withdrawalRequest->update(['status' => 'cancelled']);
        activity('members')->performedOn($withdrawalRequest)->causedBy($request->user())->event('withdrawal.cancelled')->log('Demande de retrait annulée');

        return back()->with('success', 'Demande de retrait annulée.');
    }
}
