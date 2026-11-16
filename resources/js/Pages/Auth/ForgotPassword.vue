<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

defineProps({
    status: { type: String },
});

const { t } = useI18n();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout :title="t('forgot.title')" :subtitle="t('forgot.subtitle')" :page-title="t('forgot.title')">
        <template #icon>
            <KeyRound class="h-6 w-6" aria-hidden="true" />
        </template>

        <AuthNotice v-if="status" class="mb-6">{{ status }} {{ t('forgot.spam') }}</AuthNotice>
        <form class="space-y-6" novalidate @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                type="email"
                :label="t('fields.email')"
                :placeholder="t('fields.emailPlaceholder')"
                autocomplete="username"
                :error="form.errors.email"
                required
                autofocus
            />

            <AuthButton :loading="form.processing" :loading-text="t('forgot.submitting')">{{ t('forgot.submit') }}</AuthButton>
        </form>

        <Link
            :href="route('login')"
            class="mt-6 inline-flex items-center gap-2 rounded text-sm font-semibold text-hsi-slate hover:text-hsi-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
        >
            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
            {{ t('common.backToLogin') }}
        </Link>
    </AuthLayout>
</template>
