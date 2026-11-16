<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import ContributionPlanForm from '@/Pages/ContributionPlans/ContributionPlanForm.vue';

const props = defineProps({
    plan: Object,
    countries: Array,
    frequencies: Array,
    currencies: Array,
});

const form = useForm({
    name: props.plan.name,
    description: props.plan.description ?? '',
    amount: props.plan.amount,
    currency: props.plan.currency,
    frequency: props.plan.frequency,
    country_id: props.plan.country_id,
    is_default: props.plan.is_default,
    is_active: props.plan.is_active,
    auto_generate: props.plan.auto_generate,
    starts_on: props.plan.starts_on ?? '',
    due_day: props.plan.due_day ?? 5,
    grace_days: props.plan.grace_days ?? 5,
});

const submit = () => {
    form.put(route('contribution-plans.update', props.plan.id));
};
</script>

<template>
    <Head :title="`${$tr('Modifier')} — ${plan.name}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Modifier le plan "
            :accent="plan.name"
            subtitle="Mettre à jour les paramètres du plan"
        />

        <HsiCard padding="lg">
            <ContributionPlanForm
                :form="form"
                :countries="countries"
                :frequencies="frequencies"
                :currencies="currencies"
                is-edit
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('contribution-plans.index')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </ContributionPlanForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
