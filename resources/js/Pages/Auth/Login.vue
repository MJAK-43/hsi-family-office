<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import GoogleButton from '@/Components/Auth/GoogleButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Lock, UserPlus } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    googleEnabled: { type: Boolean, default: false },
});

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout :title="t('login.title')" :subtitle="t('login.subtitle')" :page-title="t('login.pageTitle')">
        <template #icon>
            <Lock class="h-6 w-6" aria-hidden="true" />
        </template>

        <AuthNotice v-if="status" class="mb-6">{{ status }}</AuthNotice>
        <template v-if="googleEnabled">
            <GoogleButton :label="t('google.continue')" />
            <div class="my-6 flex items-center gap-4 text-xs font-semibold uppercase tracking-[0.18em] text-hsi-slate-light" aria-hidden="true">
                <span class="h-px flex-1 bg-hsi-border" />
                {{ t('google.or') }}
                <span class="h-px flex-1 bg-hsi-border" />
            </div>
        </template>

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

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                :label="t('fields.password')"
                :placeholder="t('fields.passwordPlaceholder')"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label for="remember" class="inline-flex cursor-pointer select-none items-center gap-3 text-sm text-hsi-slate">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-5 w-5 rounded-md border-hsi-border text-hsi-navy focus:ring-2 focus:ring-hsi-gold/40 focus:ring-offset-0"
                    >
                    {{ t('login.remember') }}
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded text-sm font-semibold text-[#1D4ED8] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
                >
                    {{ t('login.forgot') }}
                </Link>
            </div>

            <AuthButton :loading="form.processing" :loading-text="t('login.submitting')">{{ t('login.submit') }}</AuthButton>
        </form>

        <div class="my-8 flex items-center gap-4 text-xs font-semibold uppercase tracking-[0.18em] text-hsi-slate-light" aria-hidden="true">
            <span class="h-px flex-1 bg-hsi-border" />
            {{ t('login.notMember') }}
            <span class="h-px flex-1 bg-hsi-border" />
        </div>

        <Link
            :href="route('membership.apply')"
            class="group inline-flex h-[54px] w-full items-center justify-center gap-2 rounded-2xl border border-hsi-gold/50 bg-hsi-gold/[0.06] px-6 text-[15px] font-semibold text-hsi-navy transition hover:border-hsi-gold hover:bg-hsi-gold/[0.12] focus:outline-none focus-visible:ring-4 focus-visible:ring-hsi-gold/30"
        >
            <UserPlus class="h-5 w-5 text-[#A8841F]" aria-hidden="true" />
            {{ t('login.apply') }}
        </Link>
        <p class="mt-3 text-center text-[13px] text-hsi-slate">{{ t('login.applyHint') }}</p>
    </AuthLayout>
</template>
