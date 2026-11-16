<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { FolderKanban, Send, CheckCircle, Clock, ThumbsUp, DollarSign, Plus, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSearchInput from '@/Components/Hsi/HsiSearchInput.vue';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import HsiAvatar from '@/Components/Hsi/HsiAvatar.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiSearchSelect from '@/Components/Hsi/HsiSearchSelect.vue';
import HsiViewToggle from '@/Components/Hsi/HsiViewToggle.vue';
import { useViewMode } from '@/Composables/useListTools';

const props = defineProps({
    projects: Object,
    filters: Object,
    stats: Array,
    countries: Array,
    sectors: Array,
    statuses: Array,
    owners: Array,
    can: Object,
    projectTypes: { type: Array, default: () => [] },
    fundAccounts: { type: Array, default: () => [] },
});

const statIcons = [Send, Clock, ThumbsUp, DollarSign, FolderKanban];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const sector = ref(props.filters.sector ?? '');
const projectType = ref(props.filters.project_type_id ?? '');
const fundAccountId = ref(props.filters.fund_account_id ?? '');
const countryId = ref(props.filters.country_id ?? '');
const ownerId = ref(props.filters.owner_id ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let debounceTimer = null;

const applyFilters = () => {
    router.get(route('projects.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        sector: sector.value || undefined,
        project_type_id: projectType.value || undefined,
        fund_account_id: fundAccountId.value || undefined,
        country_id: countryId.value || undefined,
        owner_id: ownerId.value || undefined,
        per_page: perPage.value || undefined,
    }, { preserveState: true, replace: true });
};

watch([status, sector, projectType, fundAccountId, countryId, ownerId, perPage], applyFilters);

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 350);
});

const resetFilters = () => {
    search.value = '';
    status.value = '';
    sector.value = '';
    projectType.value = '';
    fundAccountId.value = '';
    countryId.value = '';
    ownerId.value = '';
    perPage.value = 10;
    applyFilters();
};

const destroyProject = (id) => {
    if (confirm('Supprimer ce projet ?')) {
        router.delete(route('projects.destroy', id));
    }
};

const tableRows = () => props.projects.data ?? [];
const statusOptions = computed(() => props.statuses.map((x) => ({ value: x.value, label: x.label })));
const fundAccountOptions = computed(() => props.fundAccounts.map((x) => ({ value: x.id, label: x.name })));
const typeOptions = computed(() => props.projectTypes.map((x) => ({ value: x.id, label: x.name })));
const sectorOptions = computed(() => props.sectors.map((x) => ({ value: x.value, label: x.label })));
const countryOptions = computed(() => props.countries.map((c) => ({ value: c.id, label: `${c.flag_emoji ?? ''} ${c.name}`.trim() })));
const ownerOptions = computed(() => props.owners.map((o) => ({ value: o.id, label: o.name })));
const view = useViewMode('projects');
</script>

<template>
    <Head title="Projets familiaux" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Projets familiaux"
            subtitle="Déposer, analyser et suivre les projets proposés par les membres."
        >
            <template #actions>
                <Link
                    v-if="$page.props.auth.permissions.includes('manage project types')"
                    :href="route('project-types.index')"
                >
                    <HsiButton variant="outline">Types de projets et critères</HsiButton>
                </Link>
                <Link
                    v-if="can.create"
                    :href="route('projects.create')"
                >
                    <HsiButton>
                        <Plus class="h-4 w-4" />
                        Déposer un projet
                    </HsiButton>
                </Link>
            </template>
        </HsiPageHeader>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
            <HsiStatCard
                v-for="(stat, i) in stats"
                :key="stat.key"
                :icon="statIcons[i] ?? FolderKanban"
                :title="stat.label"
                :value="stat.value"
            />
        </div>

        <HsiCard
            padding="lg"
            class="mb-6"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="lg:col-span-2">
                    <HsiSearchInput
                        v-model="search"
                        placeholder="Rechercher un projet ou porteur..."
                    />
                </div>
                <HsiSearchSelect v-model="status" :options="statusOptions" placeholder="Tous les statuts" clearable />
                <HsiSearchSelect v-model="projectType" :options="typeOptions" placeholder="Tous les types" clearable />
                <HsiSearchSelect v-model="fundAccountId" :options="fundAccountOptions" placeholder="Tous les comptes de fonds" clearable />
                <HsiSearchSelect v-model="sector" :options="sectorOptions" placeholder="Tous les secteurs" clearable />
                <HsiSearchSelect v-model="countryId" :options="countryOptions" placeholder="Tous les pays" clearable />
                <HsiSearchSelect v-if="can.viewAll && owners.length" v-model="ownerId" :options="ownerOptions" placeholder="Tous les porteurs" clearable />
            </div>
            <button
                class="mt-3 text-xs text-hsi-slate hover:text-hsi-navy"
                @click="resetFilters"
            >
                Réinitialiser les filtres
            </button>
        </HsiCard>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-hsi-slate">{{ projects.total }} projet(s)</p>
            <HsiViewToggle v-model="view" />
        </div>

        <HsiCard v-if="!tableRows().length" padding="md">
            <HsiEmptyState title="Aucun projet" description="Aucun projet ne correspond à vos critères." :icon="FolderKanban" />
        </HsiCard>

        <div v-else-if="view === 'cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="row in tableRows()" :key="row.id" class="group flex flex-col rounded-3xl border border-hsi-border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-hsi-gold/50 hover:shadow-hsiSoft">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <HsiBadge :variant="row.type?.is_loan ? 'gold' : 'info'" size="xs">{{ row.type?.name ?? row.sector }}</HsiBadge>
                    <HsiBadge :variant="row.status_variant" size="xs">{{ row.status_label }}</HsiBadge>
                    <HsiBadge v-if="row.is_example" variant="neutral" size="xs">Exemple</HsiBadge>
                </div>
                <p v-if="row.fund_account" class="-mt-1 mb-2 text-xs text-hsi-slate-light">Compte : {{ row.fund_account }}</p>
                <Link :href="route('projects.show', row.id)" class="font-heading text-lg font-semibold leading-snug text-hsi-navy group-hover:underline">{{ row.title }}</Link>
                <p v-if="row.short_description" class="mt-1 line-clamp-2 text-sm text-hsi-slate">{{ row.short_description }}</p>
                <div class="mt-4 rounded-2xl bg-hsi-muted/60 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-hsi-slate-light">{{ row.type?.is_loan ? 'Montant du prêt' : 'Demandé à HSI' }}</p>
                    <p class="font-heading text-2xl font-semibold text-hsi-navy">{{ row.requested_amount }}</p>
                    <p v-if="!row.type?.is_loan" class="text-xs text-hsi-slate-light">Budget total {{ row.total_budget }} · apport {{ row.personal_contribution }}</p>
                </div>
                <div class="mt-4 flex items-center gap-2 text-sm">
                    <HsiAvatar :name="row.owner" size="sm" />
                    <span class="min-w-0 flex-1 truncate text-hsi-navy">{{ row.owner }}</span>
                    <span v-if="row.country !== '—'" class="shrink-0 text-xs text-hsi-slate-light">{{ row.country_flag }} {{ row.country }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-hsi-border/70 pt-3 text-xs text-hsi-slate-light">
                    <span><template v-if="row.submitted_at">Déposé le {{ row.submitted_at }}</template><template v-else-if="row.status === 'draft'">Brouillon</template><template v-if="row.committee_score"> · score {{ row.committee_score }}</template></span>
                    <div class="flex gap-1">
                        <Link :href="route('projects.show', row.id)" class="rounded-lg p-1.5 text-hsi-slate hover:bg-hsi-muted" :title="$tr('Ouvrir')"><Eye class="h-4 w-4" /></Link>
                        <Link v-if="row.status === 'draft' || row.status === 'needs_information'" :href="route('projects.edit', row.id)" class="rounded-lg p-1.5 text-hsi-slate hover:bg-hsi-muted" :title="$tr('Modifier')"><Pencil class="h-4 w-4" /></Link>
                        <button v-if="can.delete" type="button" class="rounded-lg p-1.5 text-hsi-danger hover:bg-red-50" :title="$tr('Supprimer')" @click="destroyProject(row.id)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </div>
            </article>
        </div>

        <HsiCard v-else padding="none">
            <HsiDataTable
                :columns="[
                    { key: 'title', label: 'Projet' },
                    { key: 'owner', label: 'Porteur' },
                    { key: 'type_name', label: 'Type' },
                    { key: 'country', label: 'Pays' },
                    { key: 'total_budget', label: 'Budget total', align: 'right' },
                    { key: 'personal_contribution', label: 'Apport pers.', align: 'right' },
                    { key: 'requested_amount', label: 'Demandé HSI', align: 'right' },
                    { key: 'status_label', label: 'Statut', align: 'center' },
                    { key: 'committee_score', label: 'Score', align: 'center' },
                    { key: 'actions', label: 'Actions', align: 'right' },
                ]"
                :rows="tableRows()"
            >
                <template #cell-title="{ row }">
                    <div>
                        <p class="font-medium text-hsi-navy">{{ row.title }}</p>
                        <p v-if="row.short_description" class="max-w-[200px] truncate text-xs text-hsi-slate-light">{{ row.short_description }}</p>
                    </div>
                </template>
                <template #cell-owner="{ row }">
                    <div class="flex items-center gap-2"><HsiAvatar :name="row.owner" size="sm" /><span class="text-sm">{{ row.owner }}</span></div>
                </template>
                <template #cell-type_name="{ row }"><span class="text-sm">{{ row.type?.name ?? row.sector }}</span></template>
                <template #cell-country="{ row }"><span>{{ row.country_flag }} {{ row.country }}</span></template>
                <template #cell-status_label="{ row }"><HsiBadge :variant="row.status_variant">{{ row.status_label }}</HsiBadge></template>
                <template #cell-committee_score="{ row }"><span class="font-semibold">{{ row.committee_score ?? '—' }}</span></template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <Link :href="route('projects.show', row.id)"><HsiButton variant="ghost" size="sm"><Eye class="h-4 w-4" /></HsiButton></Link>
                        <Link v-if="row.status === 'draft' || row.status === 'needs_information'" :href="route('projects.edit', row.id)"><HsiButton variant="ghost" size="sm"><Pencil class="h-4 w-4" /></HsiButton></Link>
                        <HsiButton v-if="can.delete" variant="ghost" size="sm" @click="destroyProject(row.id)"><Trash2 class="h-4 w-4 text-hsi-danger" /></HsiButton>
                    </div>
                </template>
            </HsiDataTable>
        </HsiCard>

        <HsiPagination :paginator="projects" :per-page-options="[12, 24, 48]" label="projets" @update:per-page="perPage = $event" />
    </AuthenticatedLayout>
</template>
