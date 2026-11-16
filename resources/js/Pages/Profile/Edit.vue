<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import {
    BadgeCheck,
    Briefcase,
    CalendarDays,
    Camera,
    CheckCircle2,
    Hash,
    Home,
    KeyRound,
    Loader2,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    Trash2,
    UserRound,
    Users,
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import PhoneField from '@/Components/Auth/PhoneField.vue';
import PasswordStrength from '@/Components/Auth/PasswordStrength.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    account: { type: Object, required: true },
    profile: { type: Object, default: null },
    countries: { type: Array, required: true },
    status: { type: String, default: null },
    mustVerifyEmail: { type: Boolean, default: false },
});

const { t, locale } = useI18n();

const form = useForm({
    name: props.account.name,
    email: props.account.email,
    phone: props.profile?.phone ?? '',
    country_id: props.profile?.country_id ?? '',
    city: props.profile?.city ?? '',
    address: props.profile?.address ?? '',
    profession: props.profile?.profession ?? '',
    date_of_birth: props.profile?.date_of_birth ?? '',
    gender: props.profile?.gender ?? '',
    bio: props.profile?.bio ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveProfile = () => form.patch(route('profile.update'), { preserveScroll: true });
const savePassword = () => passwordForm.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => passwordForm.reset('password', 'password_confirmation', 'current_password'),
});

// Photo de profil : envoi immédiat dès qu'un fichier est choisi.
const photoInput = ref(null);
const photoForm = useForm({ photo: null });
const photoPreview = ref(null);
const choosePhoto = () => photoInput.value?.click();
const onPhotoSelected = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    photoPreview.value = URL.createObjectURL(file);
    photoForm.photo = file;
    photoForm.post(route('profile.photo.store'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            photoForm.reset();
            event.target.value = '';
        },
        onError: () => {
            photoPreview.value = null;
        },
        onSuccess: () => {
            photoPreview.value = null;
        },
    });
};
const removePhoto = () => router.delete(route('profile.photo.destroy'), { preserveScroll: true });
const avatarSrc = computed(() => photoPreview.value || props.account.avatar_url);

const initials = computed(() => props.account.name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase());
const roleLabel = computed(() => (props.account.roles?.length ? props.account.roles.map((r) => t(`roles.${r}`)).join(' · ') : t('roles.user')));
const countryCode = computed(() => props.countries.find((c) => c.id === form.country_id)?.code ?? props.profile?.country_code ?? '');
const flag = (iso) => (iso ? iso.toUpperCase().replace(/./g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0))) : '');
const phoneDisplay = computed(() => parsePhoneNumberFromString(props.profile?.phone ?? '')?.formatInternational() ?? props.profile?.phone);
const genderLabel = computed(() => ({ female: t('fields.female'), male: t('fields.male'), other: t('fields.other') }));
const today = new Date().toISOString().slice(0, 10);
const formatDate = (iso) => (iso ? new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${iso}T12:00:00`)) : '');

const age = computed(() => {
    if (!props.profile?.date_of_birth) return null;
    const [y, m, d] = props.profile.date_of_birth.split('-').map(Number);
    const now = new Date();
    let years = now.getFullYear() - y;
    if (now.getMonth() + 1 < m || (now.getMonth() + 1 === m && now.getDate() < d)) years--;
    return years;
});

const completion = computed(() => {
    if (!props.profile) return null;
    const fields = ['phone', 'country_id', 'city', 'address', 'profession', 'date_of_birth'];
    const filled = fields.filter((f) => props.profile[f]).length + (props.account.avatar_url ? 1 : 0);
    return Math.round((filled / (fields.length + 1)) * 100);
});

const controlClass = (error) => [
    'block h-[52px] w-full rounded-2xl border bg-[#F4F6FA] px-4 text-[15px] text-hsi-navy transition focus:bg-white focus:outline-none focus:ring-4',
    error ? 'border-hsi-danger/60 focus:border-hsi-danger focus:ring-hsi-danger/15' : 'border-transparent hover:border-hsi-border focus:border-hsi-gold focus:ring-hsi-gold/20',
];
const opt = (label) => `${label} (${t('common.optional')})`;
</script>

<template>
    <Head :title="t('profile.pageTitle')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- En-tête du profil -->
            <section class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-[#071B34] via-[#0B2748] to-[#0E3360] p-6 text-white shadow-[0_24px_60px_-30px_rgba(7,27,52,0.6)] sm:p-8">
                <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-hsi-gold/15 blur-3xl" aria-hidden="true" />
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center">
                    <!-- Photo de profil -->
                    <div class="shrink-0">
                        <div class="group relative h-28 w-28">
                            <div class="h-28 w-28 overflow-hidden rounded-[28px] border-2 border-hsi-gold/50 bg-white/10 shadow-xl">
                                <img v-if="avatarSrc" :src="avatarSrc" :alt="account.name" class="h-full w-full object-cover">
                                <span v-else class="flex h-full w-full items-center justify-center font-heading text-4xl font-semibold text-hsi-gold-light">{{ initials }}</span>
                                <span v-if="photoForm.processing" class="absolute inset-0 flex items-center justify-center rounded-[28px] bg-[#071B34]/60">
                                    <Loader2 class="h-7 w-7 animate-spin" aria-hidden="true" />
                                    <span class="sr-only">{{ t('profile.photo.uploading') }}</span>
                                </span>
                            </div>
                            <button
                                type="button"
                                class="absolute -bottom-2 -right-2 flex h-10 w-10 items-center justify-center rounded-full border-2 border-[#0B2748] bg-hsi-gold text-hsi-navy shadow-lg transition hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                :aria-label="account.avatar_url ? t('profile.photo.change') : t('profile.photo.add')"
                                :disabled="photoForm.processing"
                                @click="choosePhoto"
                            >
                                <Camera class="h-5 w-5" aria-hidden="true" />
                            </button>
                            <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" @change="onPhotoSelected">
                        </div>
                        <div class="mt-4 flex flex-col gap-1 text-xs">
                            <button type="button" class="w-fit font-semibold text-hsi-gold-light underline-offset-4 hover:underline" @click="choosePhoto">
                                {{ account.avatar_url ? t('profile.photo.change') : t('profile.photo.add') }}
                            </button>
                            <button v-if="account.avatar_url" type="button" class="inline-flex w-fit items-center gap-1 text-white/60 hover:text-white" @click="removePhoto">
                                <Trash2 class="h-3.5 w-3.5" aria-hidden="true" />{{ t('profile.photo.remove') }}
                            </button>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-hsi-gold-light/80">{{ t('profile.eyebrow') }}</p>
                        <h1 class="mt-1 truncate font-heading text-3xl font-semibold sm:text-4xl">{{ account.name }}</h1>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1"><ShieldCheck class="h-4 w-4 text-hsi-gold-light" aria-hidden="true" />{{ roleLabel }}</span>
                            <span v-if="profile?.status" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1" :class="profile.status === 'active' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-400/15 text-amber-200'">
                                <BadgeCheck class="h-4 w-4" aria-hidden="true" />{{ t(`statuses.${profile.status}`) }}
                            </span>
                            <span v-if="profile?.member_since" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1"><CalendarDays class="h-4 w-4" aria-hidden="true" />{{ t('profile.memberSince', { date: profile.member_since }) }}</span>
                        </div>
                        <p v-if="photoForm.errors.photo" class="mt-3 rounded-xl bg-red-500/15 px-3 py-2 text-sm text-red-100" role="alert">{{ photoForm.errors.photo }}</p>
                        <p v-else class="mt-3 text-xs text-white/50">{{ t('profile.photo.hint') }}</p>
                    </div>

                    <div v-if="completion !== null" class="w-full rounded-2xl bg-white/10 p-4 backdrop-blur sm:w-56">
                        <div class="flex items-baseline justify-between">
                            <p class="text-sm text-white/80">{{ t('profile.completion') }}</p>
                            <p class="font-heading text-2xl font-semibold text-hsi-gold-light">{{ completion }} %</p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15" role="progressbar" :aria-valuenow="completion" aria-valuemin="0" aria-valuemax="100" :aria-label="t('profile.completion')">
                            <div class="h-full rounded-full bg-gradient-to-r from-hsi-gold to-hsi-gold-light transition-all" :style="{ width: `${completion}%` }" />
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="min-w-0 space-y-6">
                    <form class="rounded-[24px] border border-hsi-border/80 bg-white p-6 shadow-hsiSoft sm:p-8" novalidate @submit.prevent="saveProfile">
                        <header class="mb-8">
                            <h2 class="font-heading text-2xl font-semibold text-hsi-navy">{{ t('profile.personal') }}</h2>
                            <p class="mt-1 text-sm text-hsi-slate">{{ t('profile.personalHint') }}</p>
                        </header>

                        <div class="space-y-6">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <AuthField id="name" v-model="form.name" :label="t('fields.fullName')" autocomplete="name" :error="form.errors.name" required />
                                <AuthField id="email" v-model="form.email" type="email" :label="t('fields.email')" autocomplete="email" :hint="t('profile.emailHint')" :error="form.errors.email" required />
                            </div>

                            <template v-if="profile">
                                <div class="grid gap-6 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <label for="date_of_birth" class="block text-sm font-semibold text-hsi-navy">{{ t('fields.birthDate') }} <span class="font-normal text-hsi-slate">({{ t('common.optional') }})</span></label>
                                        <input id="date_of_birth" v-model="form.date_of_birth" type="date" :max="today" autocomplete="bday" :class="controlClass(form.errors.date_of_birth)">
                                        <p v-if="form.errors.date_of_birth" class="text-[13px] font-medium text-hsi-danger" role="alert">{{ form.errors.date_of_birth }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="gender" class="block text-sm font-semibold text-hsi-navy">{{ t('fields.gender') }} <span class="font-normal text-hsi-slate">({{ t('common.optional') }})</span></label>
                                        <select id="gender" v-model="form.gender" :class="controlClass(form.errors.gender)">
                                            <option value="">{{ t('fields.genderNone') }}</option>
                                            <option value="female">{{ t('fields.female') }}</option>
                                            <option value="male">{{ t('fields.male') }}</option>
                                            <option value="other">{{ t('fields.other') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <AuthField id="profession" v-model="form.profession" :label="opt(t('fields.profession'))" autocomplete="organization-title" :error="form.errors.profession" />

                                <div class="h-px bg-hsi-border/70" aria-hidden="true" />

                                <div class="grid gap-6 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <label for="country_id" class="block text-sm font-semibold text-hsi-navy">{{ t('fields.country') }}</label>
                                        <select id="country_id" v-model="form.country_id" autocomplete="country" :class="controlClass(form.errors.country_id)">
                                            <option value="">{{ t('common.choose') }}</option>
                                            <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                        <p v-if="form.errors.country_id" class="text-[13px] font-medium text-hsi-danger" role="alert">{{ form.errors.country_id }}</p>
                                    </div>
                                    <AuthField id="city" v-model="form.city" :label="t('fields.city')" autocomplete="address-level2" :error="form.errors.city" />
                                </div>

                                <PhoneField id="phone" v-model="form.phone" :label="t('fields.phone')" :suggested-country="countryCode" :error="form.errors.phone" />

                                <AuthField id="address" v-model="form.address" :label="opt(t('fields.address'))" autocomplete="street-address" :error="form.errors.address" />

                                <div class="space-y-2">
                                    <label for="bio" class="block text-sm font-semibold text-hsi-navy">{{ t('fields.bio') }} <span class="font-normal text-hsi-slate">({{ t('common.optional') }})</span></label>
                                    <textarea
                                        id="bio"
                                        v-model="form.bio"
                                        rows="4"
                                        maxlength="2000"
                                        :placeholder="t('fields.bioPlaceholder')"
                                        class="block w-full resize-y rounded-2xl border border-transparent bg-[#F4F6FA] px-4 py-3 text-[15px] text-hsi-navy placeholder:text-hsi-slate-light transition hover:border-hsi-border focus:border-hsi-gold focus:bg-white focus:outline-none focus:ring-4 focus:ring-hsi-gold/20"
                                    />
                                </div>
                            </template>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-end gap-4 border-t border-hsi-border/70 pt-6">
                            <p v-if="form.isDirty" class="text-sm text-hsi-slate">{{ t('profile.unsaved') }}</p>
                            <div class="w-full sm:w-auto sm:min-w-[260px]">
                                <AuthButton :loading="form.processing" :loading-text="t('profile.saving')" :arrow="false">{{ t('profile.saveProfile') }}</AuthButton>
                            </div>
                        </div>
                    </form>

                    <form class="rounded-[24px] border border-hsi-border/80 bg-white p-6 shadow-hsiSoft sm:p-8" novalidate @submit.prevent="savePassword">
                        <header class="mb-8 flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-hsi-gold/25 bg-hsi-gold/10 text-[#A8841F]">
                                <KeyRound class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div>
                                <h2 class="font-heading text-2xl font-semibold text-hsi-navy">{{ t('profile.passwordTitle') }}</h2>
                                <p class="mt-1 text-sm text-hsi-slate">{{ t('profile.passwordHint') }}</p>
                            </div>
                        </header>

                        <div class="space-y-6">
                            <AuthField id="current_password" v-model="passwordForm.current_password" type="password" :label="t('fields.currentPassword')" autocomplete="current-password" :error="passwordForm.errors.current_password" required />
                            <div class="grid gap-6 sm:grid-cols-2">
                                <AuthField id="new_password" v-model="passwordForm.password" type="password" :label="t('fields.newPassword')" autocomplete="new-password" :error="passwordForm.errors.password" required />
                                <AuthField
                                    id="new_password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    :label="t('fields.confirmPassword')"
                                    autocomplete="new-password"
                                    :error="passwordForm.errors.password_confirmation || (passwordForm.password_confirmation && passwordForm.password_confirmation !== passwordForm.password ? t('fields.passwordMismatch') : '')"
                                    required
                                />
                            </div>
                            <PasswordStrength :password="passwordForm.password" />
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-end gap-4 border-t border-hsi-border/70 pt-6">
                            <p v-if="passwordForm.recentlySuccessful" class="flex items-center gap-2 text-sm font-medium text-hsi-success" role="status">
                                <CheckCircle2 class="h-4 w-4" aria-hidden="true" />{{ t('profile.passwordChanged') }}
                            </p>
                            <div class="w-full sm:w-auto sm:min-w-[260px]">
                                <AuthButton :loading="passwordForm.processing" :loading-text="t('profile.changing')" :arrow="false">{{ t('profile.changePassword') }}</AuthButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Fiche récapitulative -->
                <aside class="space-y-6">
                    <div class="rounded-[24px] border border-hsi-border/80 bg-white p-6 shadow-hsiSoft lg:sticky lg:top-24">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-[#A8841F]">{{ t('profile.card') }}</h2>
                        <dl class="mt-5 space-y-4 text-sm">
                            <div class="flex items-start gap-3"><Hash class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('profile.accountNumber') }}</dt><dd class="font-semibold tabular-nums text-hsi-navy">#{{ String(account.id).padStart(4, '0') }}</dd></div></div>
                            <div class="flex items-start gap-3"><Mail class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div class="min-w-0"><dt class="text-hsi-slate">{{ t('fields.email') }}</dt><dd class="break-all font-semibold text-hsi-navy">{{ account.email }}</dd></div></div>
                            <template v-if="profile">
                                <div class="flex items-start gap-3"><Phone class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('fields.phone') }}</dt><dd class="font-semibold tabular-nums text-hsi-navy">{{ phoneDisplay || t('common.notProvided') }}</dd></div></div>
                                <div class="flex items-start gap-3"><MapPin class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('profile.residence') }}</dt><dd class="font-semibold text-hsi-navy">{{ flag(profile.country_code) }} {{ [profile.city, profile.country].filter(Boolean).join(', ') || t('common.notProvided') }}</dd></div></div>
                                <div v-if="profile.address" class="flex items-start gap-3"><Home class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('fields.address') }}</dt><dd class="font-semibold text-hsi-navy">{{ profile.address }}</dd></div></div>
                                <div class="flex items-start gap-3"><Briefcase class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('fields.profession') }}</dt><dd class="font-semibold text-hsi-navy">{{ profile.profession || t('common.notProvided') }}</dd></div></div>
                                <div class="flex items-start gap-3"><UserRound class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('profile.birth') }}</dt><dd class="font-semibold text-hsi-navy">{{ profile.date_of_birth ? formatDate(profile.date_of_birth) + (age !== null ? ` · ${t('common.years', { n: age })}` : '') : t('common.notProvided') }}<span v-if="profile.gender"> · {{ genderLabel[profile.gender] ?? profile.gender }}</span></dd></div></div>
                                <div v-if="profile.family_branch" class="flex items-start gap-3"><Users class="mt-0.5 h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><div><dt class="text-hsi-slate">{{ t('profile.branch') }}</dt><dd class="font-semibold text-hsi-navy">{{ profile.family_branch }}</dd></div></div>
                            </template>
                        </dl>

                        <p class="mt-6 rounded-2xl bg-hsi-muted px-4 py-3 text-[13px] leading-relaxed text-hsi-slate">{{ profile ? t('profile.managedByAdmin') : t('profile.noMemberCard') }}</p>
                    </div>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
