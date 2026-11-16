<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import ProjectForm from '@/Pages/Projects/ProjectForm.vue';

const props = defineProps({
    project: Object,
    countries: Array,
    sectors: Array,
    projectTypes: { type: Array, default: () => [] },
    refusedProjects: { type: Array, default: () => [] },
    loanRules: { type: Object, default: () => ({}) },
    documentTypes: { type: Array, default: () => [] },
    fundAccounts: { type: Array, default: () => [] },
});

const form = useForm({
    fund_account_id: props.project.fund_account_id ?? null,
    project_type_id: props.project.project_type_id,
    resubmission_of_id: props.project.resubmission_of_id ?? null,
    repayment_plan: props.project.repayment_plan ?? '',
    loan_duration_months: props.project.loan_duration_months,
    presentation_date: props.project.presentation_date_raw ?? '',
    title: props.project.title,
    short_description: props.project.short_description ?? '',
    description: props.project.description ?? '',
    country_id: props.project.country_id ?? null,
    city: props.project.city ?? '',
    sector: props.project.sector_value,
    total_budget: props.project.total_budget_raw,
    personal_contribution: props.project.personal_contribution_raw,
    requested_amount: props.project.requested_amount_raw,
    currency: props.project.currency,
    expected_duration_months: props.project.expected_duration_months,
    expected_roi_percentage: props.project.expected_roi_percentage,
    attachments: [],
});

const submit = () => {
    // Avec des fichiers, la mise à jour passe en multipart (POST + _method=PUT).
    if (form.attachments.length) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('projects.update', props.project.id), { forceFormData: true });
    } else {
        form.transform((data) => data).put(route('projects.update', props.project.id));
    }
};
</script>

<template>
    <Head :title="`${$tr('Modifier')} — ${project.title}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="'Modifier '"
            :accent="project.title"
            subtitle="Mettez à jour les informations du projet"
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
                is-edit
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('projects.show', project.id)">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </ProjectForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
