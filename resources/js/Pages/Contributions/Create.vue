<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import ContributionForm from '@/Pages/Contributions/ContributionForm.vue';

const props = defineProps({
    plans: Array,
    paymentMethods: Array,
    members: Array,
    canSelectMember: Boolean,
    defaultCurrency: String,
    defaultMonth: Number,
    defaultYear: Number,
    rules: { type: Object, default: () => ({}) },
    quarterDues: { type: [Object, Array], default: () => ({}) },
    treasurersByMember: { type: [Object, Array], default: () => ({}) },
    regulatoryPlanId: { type: Number, default: null },
    currentQuarter: { type: Number, default: 1 },
});

const form = useForm({
    kind: 'quarterly',
    quarter: props.currentQuarter ?? 1,
    user_id: null,
    contribution_plan_id: null,
    amount: '',
    currency: props.defaultCurrency ?? 'USD',
    payment_month: props.defaultMonth ?? null,
    payment_year: props.defaultYear ?? null,
    payment_method: '',
    reference: '',
    paid_at: new Date().toISOString().slice(0, 10),
    notes: '',
    proof: null,
});

const submit = () => {
    form.post(route('contributions.store'), { forceFormData: true });
};
</script>

<template>
    <Head title="Soumettre une cotisation" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Soumettre une "
            accent="cotisation"
            subtitle="Déclarez votre paiement et joignez une preuve"
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
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('contributions.index')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </ContributionForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
