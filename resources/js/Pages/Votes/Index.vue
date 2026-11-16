<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Vote, Clock, CheckCircle, AlertTriangle, Plus, Eye } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSearchInput from '@/Components/Hsi/HsiSearchInput.vue';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiViewToggle from '@/Components/Hsi/HsiViewToggle.vue';
import { useViewMode } from '@/Composables/useListTools';

const props = defineProps({
    votes: Object,
    filters: Object,
    stats: Array,
    statuses: Array,
    types: Array,
    can: Object,
});

const statIcons = [Vote, AlertTriangle, CheckCircle, Clock];
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const type = ref(props.filters.type ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let debounceTimer = null;

const applyFilters = () => {
    router.get(route('votes.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        type: type.value || undefined,
        per_page: perPage.value || undefined,
    }, { preserveState: true, replace: true });
};

watch([status, type, perPage], applyFilters);
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 350);
});

const tableRows = () => props.votes.data ?? [];
const view = useViewMode('votes');
</script>

<template>
    <Head title="Votes familiaux" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Votes familiaux"
            subtitle="Participer aux décisions collectives et suivre les consultations en cours."
        >
            <template #actions>
                <Link
                    v-if="can.create"
                    :href="route('votes.create')"
                >
                    <HsiButton>
                        <Plus class="h-4 w-4" />
                        Créer un vote
                    </HsiButton>
                </Link>
            </template>
        </HsiPageHeader>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <HsiStatCard
                v-for="(stat, i) in stats"
                :key="stat.key"
                :icon="statIcons[i] ?? Vote"
                :title="stat.label"
                :value="stat.value"
            />
        </div>

        <HsiCard
            padding="lg"
            class="mb-6"
        >
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-2">
                    <HsiSearchInput
                        v-model="search"
                        placeholder="Rechercher un vote..."
                    />
                </div>
                <select
                    v-model="status"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"
                >
                    <option value="">Tous les statuts</option>
                    <option
                        v-for="s in statuses"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </option>
                </select>
                <select
                    v-model="type"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"
                >
                    <option value="">Tous les types</option>
                    <option
                        v-for="t in types"
                        :key="t.value"
                        :value="t.value"
                    >
                        {{ t.label }}
                    </option>
                </select>
            </div>
        </HsiCard>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-hsi-slate">{{ votes.total }} vote(s)</p>
            <HsiViewToggle v-model="view" />
        </div>

        <HsiCard v-if="!tableRows().length" padding="md">
            <HsiEmptyState title="Aucun vote" description="Aucun vote ne correspond à vos critères." :icon="Vote" />
        </HsiCard>

        <div v-else-if="view === 'cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link v-for="row in tableRows()" :key="row.id" :href="route('votes.show', row.id)" class="group flex flex-col rounded-3xl border border-hsi-border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-hsi-gold/50 hover:shadow-hsiSoft">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <HsiBadge :variant="row.status_variant" size="xs">{{ row.status_label }}</HsiBadge>
                    <HsiBadge variant="neutral" size="xs">{{ row.type_label }}</HsiBadge>
                    <HsiBadge v-if="row.result_label" :variant="row.result_variant" size="xs">{{ row.result_label }}</HsiBadge>
                </div>
                <h3 class="font-heading text-lg font-semibold leading-snug text-hsi-navy group-hover:underline">{{ row.title }}</h3>
                <p v-if="row.project" class="mt-1 text-xs text-hsi-slate-light">Projet : {{ row.project }}</p>
                <div class="mt-4">
                    <div class="mb-1 flex justify-between text-xs"><span class="text-hsi-slate">Participation</span><span class="font-semibold text-hsi-navy">{{ row.ballots_count ?? 0 }} / {{ row.eligible_count ?? 0 }} · {{ row.participation }} %</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-hsi-muted"><div class="h-full rounded-full bg-hsi-gold" :style="{ width: `${Math.min(row.participation, 100)}%` }" /></div>
                </div>
                <div v-if="row.status === 'closed'" class="mt-3 flex gap-3 text-xs">
                    <span class="text-hsi-success">Pour {{ row.for_count }}</span><span class="text-hsi-danger">Contre {{ row.against_count }}</span><span class="text-hsi-slate-light">Abstention {{ row.abstain_count }}</span>
                </div>
                <div class="mt-auto flex items-center justify-between border-t border-hsi-border/70 pt-3 text-xs text-hsi-slate-light" :class="row.status === 'closed' ? 'mt-4' : 'mt-5'">
                    <span class="inline-flex items-center gap-1"><Clock class="h-3.5 w-3.5" aria-hidden="true" /><template v-if="row.status === 'scheduled' && row.opens_at">Ouvre le {{ row.opens_at }}</template><template v-else>Clôture : {{ row.closes_at_short || '—' }}</template></span>
                    <span class="inline-flex items-center gap-1 font-semibold text-hsi-navy">Ouvrir <Eye class="h-3.5 w-3.5" aria-hidden="true" /></span>
                </div>
            </Link>
        </div>

        <HsiCard v-else padding="none">
            <HsiDataTable
                :columns="[
                    { key: 'title', label: 'Sujet' },
                    { key: 'type_label', label: 'Type' },
                    { key: 'closes_at_short', label: 'Échéance' },
                    { key: 'participation', label: 'Participation' },
                    { key: 'status_label', label: 'Statut', align: 'center' },
                    { key: 'actions', label: 'Actions', align: 'right' },
                ]"
                :rows="tableRows()"
            >
                <template #cell-title="{ row }">
                    <div><p class="font-medium text-hsi-navy">{{ row.title }}</p><p v-if="row.project" class="text-xs text-hsi-slate-light">Projet : {{ row.project }}</p></div>
                </template>
                <template #cell-participation="{ row }">
                    <div class="flex min-w-[120px] items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-hsi-muted"><div class="h-full rounded-full bg-hsi-gold" :style="{ width: `${Math.min(row.participation, 100)}%` }" /></div>
                        <span class="w-10 text-xs font-medium text-hsi-slate">{{ row.participation }}%</span>
                    </div>
                </template>
                <template #cell-status_label="{ row }"><HsiBadge :variant="row.status_variant">{{ row.status_label }}</HsiBadge></template>
                <template #cell-actions="{ row }"><Link :href="route('votes.show', row.id)"><HsiButton variant="ghost" size="sm"><Eye class="h-4 w-4" /></HsiButton></Link></template>
            </HsiDataTable>
        </HsiCard>

        <HsiPagination :paginator="votes" :per-page-options="[12, 24, 48]" label="votes" @update:per-page="perPage = $event" />
    </AuthenticatedLayout>
</template>
