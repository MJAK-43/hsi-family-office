<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import PasswordStrength from '@/Components/Auth/PasswordStrength.vue';
import PhoneField from '@/Components/Auth/PhoneField.vue';
import GoogleButton from '@/Components/Auth/GoogleButton.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, UserPlus } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const { t } = useI18n();

const props = defineProps({
    countries: { type: Array, required: true },
    googleEnabled: { type: Boolean, default: false },
    // Identité Google vérifiée (email, prénom, nom), ou null.
    google: { type: Object, default: null },
});

const form = useForm({
    first_name: props.google?.first_name ?? '',
    last_name: props.google?.last_name ?? '',
    email: props.google?.email ?? '',
    password: '',
    password_confirmation: '',
    phone: '',
    country_id: '',
    city: '',
    address: '',
    profession: '',
    date_of_birth: '',
    consent: false,
    website: '',
});

// Le pays de résidence pré-remplit l'indicatif téléphonique.
const selectedCountryCode = computed(() => props.countries.find((c) => c.id === form.country_id)?.code ?? '');

const submit = () => {
    form.post(route('membership.store'), {
        preserveScroll: true,
        onError: () => form.reset('password', 'password_confirmation'),
    });
};

const controlClass = (error) => [
    'block h-[52px] w-full rounded-2xl border bg-[#F4F6FA] px-4 text-[15px] text-hsi-navy transition focus:bg-white focus:outline-none focus:ring-4',
    error ? 'border-hsi-danger/60 focus:border-hsi-danger focus:ring-hsi-danger/15' : 'border-transparent hover:border-hsi-border focus:border-hsi-gold focus:ring-hsi-gold/20',
];

const forgetGoogle = () => router.post(route('google.forget'));

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <AuthLayout
        :title="t('apply.title')"
        :subtitle="t('apply.subtitle')"
        :page-title="t('apply.pageTitle')"
        wide
    >
        <template #icon>
            <UserPlus class="h-6 w-6" aria-hidden="true" />
        </template>

        <AuthNotice v-if="google" tone="info" class="mb-8">
            {{ t('google.applyLinked', { email: google.email }) }}
            <button type="button" class="ml-1 font-semibold underline underline-offset-4 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold" @click="forgetGoogle">
                {{ t('google.useOther') }}
            </button>
        </AuthNotice>

        <template v-else-if="googleEnabled">
            <GoogleButton :label="t('google.applyWith')" />
            <p class="mt-3 text-center text-[13px] text-hsi-slate">{{ t('google.applyHint') }}</p>
            <div class="my-8 flex items-center gap-4 text-xs font-semibold uppercase tracking-[0.18em] text-hsi-slate-light" aria-hidden="true">
                <span class="h-px flex-1 bg-hsi-border" />
                {{ t('google.orForm') }}
                <span class="h-px flex-1 bg-hsi-border" />
            </div>
        </template>

        <form class="space-y-10" novalidate @submit.prevent="submit">
            <!-- Champ piège pour les robots, invisible pour les personnes -->
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="website">Site web</label>
                <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <!-- 1. Identité -->
            <fieldset class="space-y-6">
                <legend class="mb-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#A8841F]">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-hsi-navy text-[11px] text-white">1</span>
                    {{ t('apply.identity') }}
                </legend>
                <div class="grid gap-6 sm:grid-cols-2">
                    <AuthField id="first_name" v-model="form.first_name" :label="t('fields.firstName')" autocomplete="given-name" :error="form.errors.first_name" required autofocus />
                    <AuthField id="last_name" v-model="form.last_name" :label="t('fields.lastName')" autocomplete="family-name" :error="form.errors.last_name" required />
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="date_of_birth" class="block text-sm font-semibold text-hsi-navy">{{ t('fields.birthDate') }} <span class="font-normal text-hsi-slate">({{ t('common.optional') }})</span></label>
                        <input id="date_of_birth" v-model="form.date_of_birth" type="date" :max="today" autocomplete="bday" :class="controlClass(form.errors.date_of_birth)" :aria-invalid="form.errors.date_of_birth ? 'true' : 'false'">
                        <p v-if="form.errors.date_of_birth" class="flex items-start gap-1.5 text-[13px] font-medium text-hsi-danger" role="alert"><AlertCircle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />{{ form.errors.date_of_birth }}</p>
                    </div>
                    <AuthField id="profession" v-model="form.profession" :label="`${t('fields.profession')} (${t('common.optional')})`" autocomplete="organization-title" :error="form.errors.profession" />
                </div>
            </fieldset>

            <!-- 2. Coordonnées -->
            <fieldset class="space-y-6">
                <legend class="mb-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#A8841F]">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-hsi-navy text-[11px] text-white">2</span>
                    {{ t('apply.contact') }}
                </legend>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="country_id" class="block text-sm font-semibold text-hsi-navy">{{ t('fields.country') }}</label>
                        <select id="country_id" v-model="form.country_id" autocomplete="country" :class="controlClass(form.errors.country_id)" :aria-invalid="form.errors.country_id ? 'true' : 'false'" required>
                            <option value="" disabled>{{ t('common.choose') }}</option>
                            <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="form.errors.country_id" class="flex items-start gap-1.5 text-[13px] font-medium text-hsi-danger" role="alert"><AlertCircle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />{{ form.errors.country_id }}</p>
                    </div>
                    <AuthField id="city" v-model="form.city" :label="t('fields.city')" autocomplete="address-level2" :error="form.errors.city" required />
                </div>
                <PhoneField
                    id="phone"
                    v-model="form.phone"
                    :label="t('fields.phone')"
                    :suggested-country="selectedCountryCode"
                    :error="form.errors.phone"
                    required
                />
                <AuthField id="address" v-model="form.address" :label="`${t('fields.address')} (${t('common.optional')})`" autocomplete="street-address" :error="form.errors.address" />
            </fieldset>

            <!-- 3. Accès -->
            <fieldset class="space-y-6">
                <legend class="mb-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#A8841F]">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-hsi-navy text-[11px] text-white">3</span>
                    {{ t('apply.credentials') }}
                </legend>
                <AuthField
                    id="email"
                    v-model="form.email"
                    type="email"
                    :label="t('fields.email')"
                    :placeholder="t('fields.emailPlaceholder')"
                    autocomplete="email"
                    :hint="google ? t('google.emailLocked') : t('apply.emailHint')"
                    :error="form.errors.email"
                    :readonly="!!google"
                    required
                />
                <p v-if="google" class="text-[13px] text-hsi-slate">{{ t('google.noPassword') }}</p>
                <div v-if="!google" class="grid gap-6 sm:grid-cols-2">
                    <AuthField id="password" v-model="form.password" type="password" :label="t('fields.password')" autocomplete="new-password" :error="form.errors.password" required />
                    <AuthField
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        :label="t('fields.confirmPassword')"
                        autocomplete="new-password"
                        :error="form.errors.password_confirmation || (form.password_confirmation && form.password_confirmation !== form.password ? t('fields.passwordMismatch') : '')"
                        required
                    />
                </div>
                <PasswordStrength v-if="!google" :password="form.password" />
                <p class="text-[13px] text-hsi-slate">{{ t('apply.onlyIfAccepted') }}</p>
            </fieldset>

            <div class="space-y-2">
                <label for="consent" class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-hsi-slate">
                    <input
                        id="consent"
                        v-model="form.consent"
                        type="checkbox"
                        class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-hsi-border text-hsi-navy focus:ring-2 focus:ring-hsi-gold/40 focus:ring-offset-0"
                        :aria-invalid="form.errors.consent ? 'true' : 'false'"
                    >
                    <span>
                        {{ t('apply.consent') }}
                    </span>
                </label>
                <p v-if="form.errors.consent" class="flex items-start gap-1.5 text-[13px] font-medium text-hsi-danger" role="alert">
                    <AlertCircle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />{{ form.errors.consent }}
                </p>
            </div>

            <AuthButton :loading="form.processing" :loading-text="t('apply.submitting')">{{ t('apply.submit') }}</AuthButton>
        </form>

        <Link
            :href="route('login')"
            class="mt-6 inline-flex items-center gap-2 rounded text-sm font-semibold text-hsi-slate hover:text-hsi-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
        >
            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
            {{ t('apply.haveAccount') }}
        </Link>
    </AuthLayout>
</template>
