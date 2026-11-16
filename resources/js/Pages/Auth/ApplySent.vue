<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, MailCheck } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const { t, tm } = useI18n();
</script>

<template>
    <AuthLayout :title="t('applySent.title')" :subtitle="t('applySent.subtitle')" :page-title="t('applySent.pageTitle')">
        <template #icon>
            <MailCheck class="h-6 w-6" aria-hidden="true" />
        </template>

        <ol class="space-y-5">
            <li v-for="(step, index) in tm('applySent.steps')" :key="step.title" class="flex gap-4">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-hsi-navy text-sm font-semibold text-white">{{ index + 1 }}</span>
                <div>
                    <p class="font-semibold text-hsi-navy">{{ step.title }}</p>
                    <p class="mt-0.5 text-sm leading-relaxed text-hsi-slate">{{ step.text }}</p>
                </div>
            </li>
        </ol>

        <p class="mt-8 rounded-2xl bg-hsi-muted px-4 py-3 text-sm text-hsi-slate">
            {{ t('applySent.noEmail') }}
            <a href="mailto:contact@inovixora.com" class="font-semibold text-hsi-navy underline-offset-4 hover:underline">contact@inovixora.com</a>.
        </p>

        <Link
            :href="route('login')"
            class="mt-6 inline-flex items-center gap-2 rounded text-sm font-semibold text-hsi-slate hover:text-hsi-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
        >
            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
            {{ t('common.backToLogin') }}
        </Link>
    </AuthLayout>
</template>
