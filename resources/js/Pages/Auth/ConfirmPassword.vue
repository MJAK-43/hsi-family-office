<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthLayout :title="t('confirm.title')" :subtitle="t('confirm.subtitle')" :page-title="t('confirm.pageTitle')">
        <template #icon>
            <ShieldCheck class="h-6 w-6" aria-hidden="true" />
        </template>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <AuthField id="password" v-model="form.password" type="password" :label="t('fields.password')" autocomplete="current-password" :error="form.errors.password" required autofocus />

            <AuthButton :loading="form.processing" :loading-text="t('confirm.submitting')">{{ t('confirm.submit') }}</AuthButton>
        </form>
    </AuthLayout>
</template>
