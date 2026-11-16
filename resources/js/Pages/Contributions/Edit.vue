<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import ContributionForm from '@/Pages/Contributions/ContributionForm.vue';

const props = defineProps({
    payment: Object,
    plans: Array,
    paymentMethods: Array,
    members: Array,
    canSelectMember: Boolean,
    rules: { type: Object, default: () => ({}) },
    quarterDues: { type: [Object, Array], default: () => ({}) },
    treasurersByMember: { type: [Object, Array], default: () => ({}) },
    regulatoryPlanId: { type: Number, default: null },
    currentQuarter: { type: Number, default: 1 },
});

const isQuarterly = props.payment.kind === 'quarterly';
const form = useForm({
    kind: isQuarterly || props.payment.kind === 'voluntary' ? props.payment.kind : null,
    quarter: props.payment.quarter ?? props.currentQuarter,
    user_id: props.payment.user_id,
    contribution_plan_id: props.payment.contribution_plan_id,
    amount: props.payment.amount,
    currency: props.payment.currency,
    payment_month: props.payment.payment_month,
    payment_year: props.payment.payment_year,
    payment_method: props.payment.payment_method,
    reference: props.payment.reference === '—' ? '' : props.payment.reference,
    paid_at: props.payment.paid_at_raw ?? '',
    notes: props.payment.notes ?? '',
    proof: null,
});

const submit = () => {
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(route('contributions.update', props.payment.id), { forceFormData: true });
};
</script>

<template>
    <Head :title="`${$tr('Modifier cotisation')} — ${payment.member.name}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Modifier la "
            accent="cotisation"
            :subtitle="$tr('Cotisation de {name}', { name: payment.member.name })"
        />

        <HsiCard padding="lg">
            <ContributionForm
                :form="form"
                :plans="plans"
                :payment-methods="paymentMethods"
                :members="members"
                :can-select-member="canSelectMember"
                :rules="rules"
                :quarter-dues="quarterDues"
                :treasurers-by-member="treasurersByMember"
                is-edit
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('contributions.show', payment.id)">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </ContributionForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
