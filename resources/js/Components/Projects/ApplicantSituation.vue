<script setup>
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, MinusCircle, UserRound } from 'lucide-vue-next';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

const props = defineProps({
    situation: { type: Object, required: true },
    checks: { type: Object, default: null },
});

const checkLabels = {
    loans_open: 'Prêts ouverts',
    contributing_member: 'Cotise au fonds',
    seniority: 'Ancienneté de cotisation',
    contributions_up_to_date: 'Cotisations à jour',
    no_ongoing_loan: 'Aucun prêt en cours',
    no_pending_penalties: 'Aucune pénalité impayée',
    amount_within_fund_cap: 'Montant ≤ plafond du fonds',
    loan_duration: 'Durée de remboursement',
    refusal_delay: 'Délai après un refus',
    notice_period: 'Délai de prévenance',
    personal_contribution: 'Apport personnel',
};
const checkList = computed(() => Object.entries(props.checks ?? {})
    .filter(([, check]) => check.result !== 'na')
    .map(([key, check]) => ({ key, label: checkLabels[key] ?? key, ...check })));
</script>

<template>
    <HsiCard padding="lg">
        <HsiSectionTitle title="Situation du demandeur" />
        <div class="flex items-center gap-4">
            <img v-if="situation.avatar_url" :src="situation.avatar_url" alt="" class="h-14 w-14 rounded-2xl object-cover">
            <div v-else class="flex h-14 w-14 items-center justify-center rounded-2xl bg-hsi-muted"><UserRound class="h-6 w-6 text-hsi-slate" aria-hidden="true" /></div>
            <div>
                <p class="font-semibold text-hsi-navy">{{ situation.name }}</p>
                <p class="text-sm text-hsi-slate">{{ situation.email }}</p>
                <p class="text-xs text-hsi-slate-light">Membre depuis le {{ situation.member_since }} · {{ situation.status }}<template v-if="situation.zone"> · Zone {{ situation.zone }}</template></p>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-3">
            <div><dt class="text-hsi-slate-light">Cotisations validées</dt><dd class="font-semibold text-hsi-navy">{{ situation.total_contributions }} <span class="font-normal text-hsi-slate">({{ situation.contributions_count }})</span></dd></div>
            <div><dt class="text-hsi-slate-light">Ancienneté de cotisation</dt><dd class="font-semibold text-hsi-navy">{{ situation.contribution_months }} mois</dd></div>
            <div><dt class="text-hsi-slate-light">Part du fonds</dt><dd class="font-semibold text-hsi-navy">{{ situation.share_percent }} %</dd></div>
            <div><dt class="text-hsi-slate-light">Cotisations en retard</dt><dd class="font-semibold" :class="situation.late_dues ? 'text-hsi-danger' : 'text-hsi-navy'">{{ situation.late_dues }}</dd></div>
            <div><dt class="text-hsi-slate-light">Prêts en cours</dt><dd class="font-semibold" :class="situation.ongoing_loans ? 'text-hsi-danger' : 'text-hsi-navy'">{{ situation.ongoing_loans }}<template v-if="situation.ongoing_loans"> · reste {{ situation.outstanding_loans }}</template></dd></div>
            <div><dt class="text-hsi-slate-light">Pénalités en cours</dt><dd class="font-semibold text-hsi-navy">{{ situation.contribution_penalties }} + {{ situation.loan_penalties }}</dd></div>
            <div><dt class="text-hsi-slate-light">Solde du fonds</dt><dd class="font-semibold text-hsi-navy">{{ situation.fund_balance }}</dd></div>
            <div><dt class="text-hsi-slate-light">Plafond ({{ situation.fund_cap_percent }} % du solde)</dt><dd class="font-semibold text-hsi-navy">{{ situation.fund_cap }}</dd></div>
            <div><dt class="text-hsi-slate-light">Autres demandes en cours</dt><dd class="font-semibold text-hsi-navy">{{ situation.other_requests }}</dd></div>
        </dl>
        <p v-if="situation.last_refusal" class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Dernier refus : « {{ situation.last_refusal.title }} » le {{ situation.last_refusal.date }}.
        </p>

        <ul v-if="checkList.length" class="mt-5 space-y-2 border-t border-hsi-border pt-4">
            <li v-for="check in checkList" :key="check.key" class="flex items-start gap-2 text-sm">
                <CheckCircle2 v-if="check.result === 'pass'" class="mt-0.5 h-4 w-4 shrink-0 text-hsi-success" aria-hidden="true" />
                <AlertTriangle v-else-if="check.result === 'fail'" class="mt-0.5 h-4 w-4 shrink-0 text-hsi-danger" aria-hidden="true" />
                <MinusCircle v-else class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" />
                <span><strong class="text-hsi-navy">{{ check.label }}</strong> — <span class="text-hsi-slate">{{ check.detail }}</span></span>
            </li>
        </ul>
    </HsiCard>
</template>
