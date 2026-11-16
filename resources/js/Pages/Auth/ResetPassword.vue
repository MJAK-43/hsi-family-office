<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import PasswordStrength from '@/Components/Auth/PasswordStrength.vue';
import { useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout :title="t('reset.title')" :subtitle="t('reset.subtitle')" :page-title="t('reset.title')">
        <template #icon>
            <ShieldCheck class="h-6 w-6" aria-hidden="true" />
        </template>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <AuthField id="email" v-model="form.email" type="email" :label="t('fields.email')" autocomplete="username" :error="form.errors.email" readonly />

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                :label="t('fields.newPassword')"
                autocomplete="new-password"
                :error="form.errors.password"
                required
                autofocus
            >
                <template #after>
                    <PasswordStrength :password="form.password" />
                </template>
            </AuthField>

            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :label="t('fields.confirmPassword')"
                autocomplete="new-password"
                :error="form.errors.password_confirmation || (form.password_confirmation && form.password_confirmation !== form.password ? t('fields.passwordMismatch') : '')"
                required
            />

            <AuthButton :loading="form.processing" :loading-text="t('reset.submitting')">{{ t('reset.submit') }}</AuthButton>
        </form>
    </AuthLayout>
</template>
