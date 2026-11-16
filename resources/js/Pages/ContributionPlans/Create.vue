<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import ContributionPlanForm from '@/Pages/ContributionPlans/ContributionPlanForm.vue';

defineProps({
    countries: Array,
    frequencies: Array,
    currencies: Array,
});

const form = useForm({
    name: '',
    description: '',
    amount: 0,
    currency: usePage().props.currency?.code ?? 'USD',
    frequency: 'monthly',
    country_id: null,
    is_default: false,
    is_active: true,
    auto_generate: true,
    starts_on: '',
    due_day: 5,
    grace_days: 5,
});

const submit = () => {
    form.post(route('contribution-plans.store'));
};
</script>

<template>
    <Head title="Nouveau plan de cotisation" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Nouveau plan de "
            accent="cotisation"
            subtitle="Définir un montant et une fréquence"
        />

        <HsiCard padding="lg">
            <ContributionPlanForm
                :form="form"
                :countries="countries"
                :frequencies="frequencies"
                :currencies="currencies"
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
