<script setup>
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { LogOut, MailCheck } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const props = defineProps({
    status: { type: String },
});

const { t } = useI18n();
const page = usePage();
const email = computed(() => page.props.auth?.user?.email ?? t('verify.yourAddress'));

const form = useForm({});
const submit = () => form.post(route('verification.send'));
const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <AuthLayout :title="t('verify.title')" :page-title="t('verify.pageTitle')">
        <template #icon>
            <MailCheck class="h-6 w-6" aria-hidden="true" />
        </template>

        <p class="text-[15px] leading-relaxed text-hsi-slate">
            {{ t('verify.sentTo') }}
            <span class="font-semibold text-hsi-navy">{{ email }}</span>.
            {{ t('verify.click') }}
        </p>

        <AuthNotice v-if="verificationLinkSent" class="mt-6">{{ t('verify.resent') }}</AuthNotice>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
            <AuthButton :loading="form.processing" :loading-text="t('forgot.submitting')">{{ t('verify.resend') }}</AuthButton>
        </form>

        <p class="mt-6 text-sm text-hsi-slate">
            {{ t('verify.nothing') }}
            <a href="mailto:contact@inovixora.com" class="font-semibold text-hsi-navy underline-offset-4 hover:underline">contact@inovixora.com</a>.
        </p>

        <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="mt-6 inline-flex items-center gap-2 rounded text-sm font-semibold text-hsi-slate hover:text-hsi-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
        >
            <LogOut class="h-4 w-4" aria-hidden="true" />
            {{ t('verify.logout') }}
        </Link>
    </AuthLayout>
</template>
