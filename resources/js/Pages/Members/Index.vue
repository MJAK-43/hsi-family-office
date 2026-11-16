<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Briefcase, CalendarDays, Clock, Earth, MapPin, Pencil, RotateCcw, Search, UserPlus, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    members: Object,
    filters: Object,
    stats: Array,
    countries: Array,
    branches: Array,
    roles: Array,
    statuses: Array,
    countryStats: Array,
    branchStats: Array,
});

const { t, tm } = useI18n();
const statIcons = [Users, UserPlus, Earth, Clock];

const search = ref(props.filters.search ?? '');
const countryId = ref(props.filters.country_id ?? '');
const branchId = ref(props.filters.family_branch_id ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? 'active');
const perPage = ref(Number(props.filters.per_page ?? 12));

let debounceTimer = null;
const applyFilters = () => {
    router.get(route('members.index'), {
        search: search.value || undefined,
        country_id: countryId.value || undefined,
        family_branch_id: branchId.value || undefined,
        role: role.value || undefined,
        status: status.value === 'active' ? undefined : status.value,
        per_page: perPage.value === 12 ? undefined : perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};
watch([countryId, branchId, role, status, perPage], applyFilters);
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 350);
});

const resetFilters = () => {
    search.value = '';
    countryId.value = '';
    branchId.value = '';
    role.value = '';
    status.value = 'active';
    perPage.value = 12;
    applyFilters();
};

const initials = (name) => (name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
const flag = (iso) => (iso ? iso.toUpperCase().replace(/./g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0))) : '');
// Dégradés de couverture pour les membres sans photo, choisis de façon stable selon l'identifiant.
const covers = [
    'from-[#071B34] via-[#0B2748] to-[#1E3A8A]',
    'from-[#0B2748] via-[#1D3B63] to-[#A8841F]',
    'from-[#14213D] via-[#0B2748] to-[#0F766E]',
    'from-[#0B2748] via-[#3B2F63] to-[#071B34]',
];
const cover = (id) => covers[id % covers.length];
const roleLabel = (key) => (key ? t(`roles.${key}`) : t('roles.user'));
const selectClass = 'h-11 rounded-xl border border-hsi-border bg-white px-3 text-sm text-hsi-navy focus:border-hsi-gold focus:ring-2 focus:ring-hsi-gold/20';
</script>

<template>
    <Head :title="t('members.pageTitle')" />

    <AuthenticatedLayout>
        <HsiPageHeader :title="t('members.title')" :accent="t('members.accent')" :subtitle="t('members.subtitle')">
            <template #actions>
                <Link v-if="$page.props.auth?.permissions?.includes('create members')" :href="route('members.create')">
                    <HsiButton>
                        <UserPlus class="h-4 w-4" aria-hidden="true" />
                        {{ t('members.add') }}
                    </HsiButton>
                </Link>
            </template>
        </HsiPageHeader>

        <!-- Chiffres clés -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <HsiStatCard
                v-for="(stat, i) in stats"
                :key="stat.title"
                :icon="statIcons[i]"
                :title="tm('members.stats')[i] ?? stat.title"
                :value="stat.value"
                :change="stat.change"
                :change-type="stat.changeType"
            />
        </div>

        <!-- Filtres -->
        <div class="sticky top-20 z-20 -mx-4 mb-6 border-y border-hsi-border/60 bg-[#F6F4EE]/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center gap-3">
                <label class="relative min-w-[220px] flex-1">
                    <span class="sr-only">{{ t('members.search') }}</span>
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-hsi-slate-light" aria-hidden="true" />
                    <input v-model="search" type="search" :placeholder="t('members.search')" class="h-11 w-full rounded-xl border border-hsi-border bg-white pl-10 pr-3 text-sm text-hsi-navy placeholder:text-hsi-slate-light focus:border-hsi-gold focus:ring-2 focus:ring-hsi-gold/20">
                </label>
                <select v-model="countryId" :class="selectClass" :aria-label="t('members.allCountries')">
                    <option value="">{{ t('members.allCountries') }}</option>
                    <option v-for="c in countries" :key="c.id" :value="c.id">{{ flag(c.code) }} {{ c.name }}</option>
                </select>
                <select v-if="branches.length" v-model="branchId" :class="selectClass" :aria-label="t('members.allBranches')">
                    <option value="">{{ t('members.allBranches') }}</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="role" :class="selectClass" :aria-label="t('members.allRoles')">
                    <option value="">{{ t('members.allRoles') }}</option>
                    <option v-for="r in roles" :key="r.name" :value="r.name">{{ roleLabel(r.name) }}</option>
                </select>
                <select v-model="status" :class="selectClass" :aria-label="t('members.allStatuses')">
                    <option value="all">{{ t('members.allStatuses') }}</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ t(`statuses.${s.value}`) }}</option>
                </select>
                <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl px-3 text-sm font-medium text-hsi-slate transition hover:bg-white hover:text-hsi-navy" @click="resetFilters">
                    <RotateCcw class="h-4 w-4" aria-hidden="true" />{{ t('members.reset') }}
                </button>
            </div>
        </div>

        <HsiCard v-if="!members.data.length" padding="md">
            <HsiEmptyState :title="t('members.emptyTitle')" :description="t('members.emptyText')" :icon="Users" />
        </HsiCard>

        <!-- Cartes des membres -->
        <ul v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            <li v-for="member in members.data" :key="member.id">
                <article class="group flex h-full flex-col overflow-hidden rounded-[28px] border border-hsi-border/70 bg-white shadow-[0_10px_30px_-18px_rgba(7,27,52,0.35)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_28px_50px_-24px_rgba(7,27,52,0.45)] motion-reduce:transform-none">
                    <!-- Photo -->
                    <Link :href="route('members.show', member.id)" class="relative block aspect-[4/5] overflow-hidden focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-hsi-gold" :aria-label="`${t('members.view')} — ${member.name}`">
                        <img
                            v-if="member.avatar_url"
                            :src="member.avatar_url"
                            :alt="member.name"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transform-none"
                            loading="lazy"
                        >
                        <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br" :class="cover(member.id)">
                            <span class="font-heading text-7xl font-semibold text-white/90 drop-shadow">{{ initials(member.name) }}</span>
                            <span class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(212,175,55,0.25),transparent_55%)]" aria-hidden="true" />
                        </div>

                        <!-- Badges -->
                        <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-hsi-navy shadow-sm backdrop-blur">
                            {{ roleLabel(member.role) }}<template v-if="member.roles?.length > 1"> +{{ member.roles.length - 1 }}</template>
                        </span>
                        <span
                            class="absolute right-4 top-4 inline-flex items-center gap-1.5 rounded-full bg-[#071B34]/60 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur"
                        >
                            <span class="h-2 w-2 rounded-full" :class="member.status === 'active' ? 'bg-emerald-400' : member.status === 'pending' ? 'bg-amber-400' : 'bg-slate-400'" aria-hidden="true" />
                            {{ t(`statuses.${member.status}`) }}
                        </span>

                        <!-- Identité sur la photo -->
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#071B34] via-[#071B34]/70 to-transparent px-5 pb-5 pt-20 text-white">
                            <h2 class="font-heading text-2xl font-semibold leading-tight">
                                {{ member.name }}<span v-if="member.age" class="font-sans text-xl font-normal text-white/80">, {{ member.age }}</span>
                            </h2>
                            <p v-if="member.city || member.country" class="mt-1 flex items-center gap-1.5 text-sm text-white/85">
                                <MapPin class="h-4 w-4 shrink-0 text-hsi-gold-light" aria-hidden="true" />
                                <span class="truncate">{{ [member.city, member.country?.name].filter(Boolean).join(', ') }}</span>
                                <span v-if="member.country_code" aria-hidden="true">{{ flag(member.country_code) }}</span>
                            </p>
                        </div>
                    </Link>

                    <!-- Détails -->
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <p class="flex items-center gap-2 text-sm font-medium text-hsi-navy">
                            <Briefcase class="h-4 w-4 shrink-0 text-[#A8841F]" aria-hidden="true" />
                            <span class="truncate">{{ member.profession || t('common.notProvided') }}</span>
                        </p>
                        <p v-if="member.bio_excerpt" class="line-clamp-2 text-sm leading-relaxed text-hsi-slate">{{ member.bio_excerpt }}</p>
                        <p v-if="member.member_since" class="flex items-center gap-2 text-xs text-hsi-slate-light">
                            <CalendarDays class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />{{ t('members.since', { date: member.member_since }) }}
                        </p>

                        <div class="mt-auto flex items-center gap-2 pt-2">
                            <Link
                                :href="route('members.show', member.id)"
                                class="inline-flex h-10 flex-1 items-center justify-center rounded-xl bg-hsi-navy text-sm font-semibold text-white transition hover:bg-hsi-navy-soft focus:outline-none focus-visible:ring-4 focus-visible:ring-hsi-gold/40"
                            >
                                {{ t('members.view') }}
                            </Link>
                            <Link
                                v-if="$page.props.auth?.permissions?.includes('edit members')"
                                :href="route('members.edit', member.id)"
                                class="flex h-10 w-10 items-center justify-center rounded-xl border border-hsi-border text-hsi-slate transition hover:border-hsi-navy/30 hover:text-hsi-navy"
                                :aria-label="`${t('members.edit')} — ${member.name}`"
                            >
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                            </Link>
                        </div>
                    </div>
                </article>
            </li>
        </ul>

        <!-- Pagination -->
        <div v-if="members.data.length" class="mt-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-hsi-slate">{{ t('members.showing', { from: members.from, to: members.to, total: members.total }) }}</p>
            <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
                <Link
                    v-for="link in members.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    preserve-scroll
                    :class="[
                        'min-w-[36px] rounded-lg px-3 py-1.5 text-center text-sm transition-colors',
                        link.active ? 'bg-hsi-navy text-white' : 'text-hsi-slate hover:bg-white',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </nav>
            <select v-model.number="perPage" class="h-9 rounded-lg border border-hsi-border bg-white px-2 text-sm">
                <option v-for="n in [12, 24, 48]" :key="n" :value="n">{{ t('members.perPage', { n }) }}</option>
            </select>
        </div>

        <!-- Répartition -->
        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <HsiCard v-if="countryStats.length" padding="md">
                <h3 class="mb-4 font-heading text-lg font-semibold text-hsi-navy">{{ t('members.byCountry') }}</h3>
                <div class="space-y-3">
                    <div v-for="item in countryStats" :key="item.name">
                        <div class="mb-1 flex justify-between text-sm">
                            <span>{{ item.flag_emoji }} {{ item.name }}</span>
                            <span class="text-hsi-slate-light">{{ item.count }} · {{ item.percent }} %</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-hsi-muted">
                            <div class="h-full rounded-full bg-hsi-gold" :style="{ width: `${item.percent}%` }" />
                        </div>
                    </div>
                </div>
            </HsiCard>
            <HsiCard v-if="branchStats.length" padding="md">
                <h3 class="mb-4 font-heading text-lg font-semibold text-hsi-navy">{{ t('members.byBranch') }}</h3>
                <div class="space-y-3">
                    <div v-for="item in branchStats" :key="item.name">
                        <div class="mb-1 flex justify-between text-sm">
                            <span>{{ item.name }}</span>
                            <span class="text-hsi-slate-light">{{ item.count }} · {{ item.percent }} %</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-hsi-muted">
                            <div class="h-full rounded-full bg-hsi-navy" :style="{ width: `${item.percent}%` }" />
                        </div>
                    </div>
                </div>
            </HsiCard>
        </div>

        <p class="mt-10 border-t border-hsi-border/60 py-8 text-center font-heading text-sm italic text-hsi-slate-light">{{ t('members.quote') }}</p>
    </AuthenticatedLayout>
</template>
