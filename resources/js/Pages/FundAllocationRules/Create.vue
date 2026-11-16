<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import FundAllocationRuleForm from '@/Pages/FundAllocationRules/Form.vue';

defineProps({ accounts: Array });

const form = useForm({
    name: '',
    description: '',
    is_default: false,
    is_active: true,
    items: [{ fund_account_id: '', percentage: '' }],
});

const submit = () => form.post(route('fund-allocation-rules.store'));
</script>

<template>
    <Head title="Nouvelle règle d'allocation" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Nouvelle règle "
            accent="d'allocation"
        />

        <HsiCard padding="lg">
            <FundAllocationRuleForm
                :form="form"
                :accounts="accounts"
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
