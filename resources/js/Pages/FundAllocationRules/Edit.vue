<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import FundAllocationRuleForm from '@/Pages/FundAllocationRules/Form.vue';

const props = defineProps({
    rule: Object,
    accounts: Array,
});

const form = useForm({
    name: props.rule.name,
    description: props.rule.description ?? '',
    is_default: props.rule.is_default,
    is_active: props.rule.is_active,
    items: props.rule.items_form?.length
        ? props.rule.items_form
        : [{ fund_account_id: '', percentage: '' }],
});

const submit = () => form.put(route('fund-allocation-rules.update', props.rule.id));
</script>

<template>
    <Head :title="`${$tr('Modifier')} — ${rule.name}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Modifier la règle "
            :accent="rule.name"
        />

        <HsiCard padding="lg">
            <FundAllocationRuleForm
                :form="form"
                :accounts="accounts"
                is-edit
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('fund-allocation-rules.index')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </FundAllocationRuleForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
