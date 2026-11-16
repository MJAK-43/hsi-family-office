<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Banknote, Wallet } from 'lucide-vue-next';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

const props = defineProps({
    project: { type: Object, required: true },
    loan: { type: Object, default: null },
    defaults: { type: Object, required: true },
    canFund: { type: Boolean, default: false },
    canRecordRepayment: { type: Boolean, default: false },
});

const isLoan = computed(() => !!props.project.type?.is_loan);
const fundForm = useForm({
    amount: props.defaults.amount,
    disbursed_on: props.defaults.disbursed_on,
    duration_months: props.defaults.duration_months,
    first_due_date: props.defaults.first_due_date,
});
const disburse = () => fundForm.post(route('projects.disburse', props.project.id), { preserveScroll: true });

const preview = computed(() => {
    const amount = parseFloat(fundForm.amount) || 0;
    const months = parseInt(fundForm.duration_months, 10) || 0;
    const rate = props.defaults.rate / 100 / 12;
    if (!amount || !months) return null;
    const payment = rate > 0 ? (amount * rate) / (1 - (1 + rate) ** -months) : amount / months;
    return { payment: payment.toFixed(2), total: (payment * months).toFixed(2) };
});

const repaymentForm = useForm({ amount: '', paid_on: new Date().toISOString().slice(0, 10), note: '' });
const repay = () => repaymentForm.post(route('projects.repayments.store', props.project.id), { preserveScroll: true, onSuccess: () => repaymentForm.reset('amount', 'note') });

const statusVariant = (status) => ({ paid: 'success', late: 'danger', partial: 'warning' }[status] ?? 'neutral');
const inputClass = 'w-full rounded-xl border border-hsi-border px-3 py-2 text-sm';
</script>

<template>
    <HsiCard v-if="canFund" padding="lg">
        <HsiSectionTitle title="Préparer le décaissement" />
        <p class="mb-4 text-sm text-hsi-slate">Le versement sera effectué après le nombre de validations prévu pour les décaissements. Plafond actuel : {{ defaults.fund_cap.toFixed(2) }} $.</p>
        <form class="space-y-3" @submit.prevent="disburse">
            <div class="grid gap-3 md:grid-cols-2">
                <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="fund-amount">Montant à verser ($)</label><input id="fund-amount" v-model="fundForm.amount" type="number" step="0.01" min="1" :max="project.requested_amount_raw" :class="inputClass" required></div>
                <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="fund-date">Date du versement</label><input id="fund-date" v-model="fundForm.disbursed_on" type="date" :class="inputClass" required></div>
                <template v-if="isLoan">
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="fund-months">Nombre d’échéances mensuelles</label><input id="fund-months" v-model="fundForm.duration_months" type="number" min="1" :max="defaults.max_duration" :class="inputClass" required></div>
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="fund-first">Première échéance</label><input id="fund-first" v-model="fundForm.first_due_date" type="date" :class="inputClass" required></div>
                </template>
            </div>
            <p v-if="isLoan && preview" class="rounded-xl bg-hsi-muted/60 px-3 py-2 text-sm text-hsi-navy">Taux annuel {{ defaults.rate }} % : {{ fundForm.duration_months }} mensualités d’environ {{ preview.payment }} $, soit {{ preview.total }} $ à rembourser.</p>
            <p v-if="fundForm.errors.fund" class="text-sm text-hsi-danger">{{ fundForm.errors.fund }}</p>
            <HsiButton type="submit" :disabled="fundForm.processing"><Wallet class="h-4 w-4" aria-hidden="true" /> Préparer le décaissement</HsiButton>
        </form>
    </HsiCard>

    <HsiCard v-if="loan" padding="lg">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <HsiSectionTitle title="Échéancier et remboursements" />
            <HsiBadge v-if="loan.repaid_at" variant="success">Soldé le {{ loan.repaid_at }}</HsiBadge>
        </div>
        <dl class="mb-4 grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
            <div><dt class="text-hsi-slate-light">Taux annuel</dt><dd class="font-semibold text-hsi-navy">{{ loan.rate }} %</dd></div>
            <div><dt class="text-hsi-slate-light">Total à rembourser</dt><dd class="font-semibold text-hsi-navy">{{ loan.total_due }}</dd></div>
            <div><dt class="text-hsi-slate-light">Déjà remboursé</dt><dd class="font-semibold text-hsi-navy">{{ loan.paid }}</dd></div>
            <div><dt class="text-hsi-slate-light">Reste dû (avec pénalités)</dt><dd class="font-semibold text-hsi-navy">{{ loan.outstanding }}</dd></div>
        </dl>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">N°</th><th>Échéance</th><th>Capital</th><th>Intérêts</th><th>Montant</th><th>Payé</th><th>Pénalité due</th><th>État</th></tr></thead>
                <tbody>
                    <tr v-for="row in loan.installments" :key="row.id" class="border-b border-hsi-border/50">
                        <td class="py-2">{{ row.number }}</td><td>{{ row.due_date }}</td><td>{{ row.principal }}</td><td>{{ row.interest }}</td>
                        <td class="font-medium">{{ row.amount }}</td><td>{{ row.paid }}</td><td :class="row.penalty_raw > 0 ? 'text-hsi-danger' : 'text-hsi-slate-light'">{{ row.penalty }}</td>
                        <td><HsiBadge :variant="statusVariant(row.status)">{{ row.status_label }}</HsiBadge><span v-if="row.reminder_level" class="ml-1 text-[11px] text-hsi-danger">rappel {{ row.reminder_level }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="loan.repayments.length" class="mt-5">
            <h3 class="mb-2 text-sm font-semibold text-hsi-navy">Remboursements reçus</h3>
            <ul class="space-y-1 text-sm">
                <li v-for="r in loan.repayments" :key="r.id" class="flex justify-between gap-3 rounded-lg bg-hsi-muted/50 px-3 py-2"><span>{{ r.paid_on }}<template v-if="r.note"> · {{ r.note }}</template></span><strong>{{ r.amount }}</strong></li>
            </ul>
        </div>

        <form v-if="canRecordRepayment && !loan.repaid_at" class="mt-5 space-y-3 border-t border-hsi-border pt-4" @submit.prevent="repay">
            <h3 class="text-sm font-semibold text-hsi-navy">Enregistrer un remboursement reçu</h3>
            <div class="grid gap-3 md:grid-cols-3">
                <input v-model="repaymentForm.amount" type="number" step="0.01" min="0.01" :max="loan.outstanding_raw" required :class="inputClass" placeholder="Montant ($)">
                <input v-model="repaymentForm.paid_on" type="date" required :class="inputClass">
                <input v-model="repaymentForm.note" type="text" maxlength="2000" :class="inputClass" placeholder="Note (facultatif)">
            </div>
            <p class="text-xs text-hsi-slate-light">Le montant règle d’abord les pénalités, puis les échéances les plus anciennes.</p>
            <p v-if="repaymentForm.errors.repayment" class="text-sm text-hsi-danger">{{ repaymentForm.errors.repayment }}</p>
            <HsiButton type="submit" :disabled="repaymentForm.processing"><Banknote class="h-4 w-4" aria-hidden="true" /> Enregistrer</HsiButton>
        </form>
    </HsiCard>
</template>
