<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import ProjectForm from '@/Pages/Projects/ProjectForm.vue';

const props = defineProps({
    countries: Array,
    sectors: Array,
    projectTypes: { type: Array, default: () => [] },
    refusedProjects: { type: Array, default: () => [] },
    loanRules: { type: Object, default: () => ({}) },
    documentTypes: { type: Array, default: () => [] },
    fundAccounts: { type: Array, default: () => [] },
});

const form = useForm({
    fund_account_id: props.fundAccounts.length === 1 ? props.fundAccounts[0].id : null,
    project_type_id: props.projectTypes.length === 1 ? props.projectTypes[0].id : null,
    resubmission_of_id: null,
    repayment_plan: '',
    loan_duration_months: null,
    presentation_date: '',
    title: '',
    short_description: '',
    description: '',
    country_id: null,
    city: '',
    sector: '',
    total_budget: '',
    personal_contribution: '',
    requested_amount: '',
    currency: usePage().props.currency?.code ?? 'USD',
    expected_duration_months: null,
    expected_roi_percentage: null,
    attachments: [],
});

const submit = () => {
    form.post(route('projects.store'));
};
</script>

<template>
    <Head title="Déposer un projet" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Déposer un "
            accent="projet"
            subtitle="Proposez un projet à financer par HSI Family Office"
        />

        <HsiCard padding="lg">
            <ProjectForm
                :form="form"
                :countries="countries"
                :sectors="sectors"
                :project-types="projectTypes"
                :refused-projects="refusedProjects"
                :loan-rules="loanRules"
                :document-types="documentTypes"
                :fund-accounts="fundAccounts"
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('projects.index')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </ProjectForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
