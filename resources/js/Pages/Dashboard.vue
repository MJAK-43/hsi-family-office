<script setup>
import { tr } from '@/i18n';
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import {
    Users,
    Wallet,
    Landmark,
    FolderKanban,
    Vote,
    HeartHandshake,
    UserPlus,
    FilePlus,
    Coins,
    CheckSquare,
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiChartCard from '@/Components/Hsi/HsiChartCard.vue';
import HsiFlowPanel from '@/Components/Hsi/HsiFlowPanel.vue';
import { SlidersHorizontal, Eye, EyeOff } from 'lucide-vue-next';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import { useListTools } from '@/Composables/useListTools';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiActionCard from '@/Components/Hsi/HsiActionCard.vue';
import HsiAvatar from '@/Components/Hsi/HsiAvatar.vue';
import { hsiColors, hsiChartColors } from '@/theme/hsiTheme';

const page = usePage();

const props = defineProps({
    visibility: {
        type: Object,
        default: () => ({ members: false, contributions: false, funds: false, fundMovements: false, projects: false, votes: false }),
    },
    memberStats: {
        type: Object,
        default: () => ({ active: 128, newThisMonth: 5 }),
    },
    contributionStats: {
        type: Object,
        default: () => ({ validatedTotal: '0 $', validatedCount: 0, pendingCount: 0 }),
    },
    recentContributions: {
        type: Array,
        default: () => [],
    },
    fundStats: {
        type: Object,
        default: () => ({ total: '0 $', available: '0 $', allocated: '0 $' }),
    },
    fundAllocation: {
        type: Array,
        default: () => [],
    },
    recentFundMovements: {
        type: Array,
        default: () => [],
    },
    projectStats: {
        type: Object,
        default: () => ({
            submitted: 0,
            underReview: 0,
            approvedCommittee: 0,
            awaitingFunding: 0,
            funded: 0,
            requestedTotal: '0 $',
            inAnalysis: 0,
            familyProjectsBalance: '0 $',
        }),
    },
    recentProjects: {
        type: Array,
        default: () => [],
    },
    voteStats: {
        type: Object,
        default: () => ({ open: 0, newThisWeek: 0 }),
    },
    recentVotes: {
        type: Array,
        default: () => [],
    },
    hiddenWidgets: { type: Array, default: () => [] },
    flow: { type: Object, default: null },
    flowPeriod: { type: String, default: '12m' },
});

// Cartes affichées : chacun choisit ce qu'il veut voir (enregistré sur son compte).
const hidden = ref([...props.hiddenWidgets]);
const customizing = ref(false);
const widgetGroups = computed(() => [
    { title: 'Indicateurs', items: [
        { key: 'stat_members', label: 'Membres actifs', available: props.visibility.members },
        { key: 'stat_contributions', label: 'Cotisations validées', available: props.visibility.contributions },
        { key: 'stat_pending', label: 'Cotisations en attente', available: props.visibility.contributions },
        { key: 'stat_fund', label: 'Fonds disponible', available: props.visibility.funds },
        { key: 'stat_projects', label: 'Projets en analyse', available: props.visibility.projects },
        { key: 'stat_votes', label: 'Votes ouverts', available: props.visibility.votes },
    ] },
    { title: 'Cartes', items: [
        { key: 'flows', label: 'Flux financiers', available: props.visibility.funds && !!props.flow },
        { key: 'allocation', label: 'Allocation du fonds familial', available: props.visibility.funds },
        { key: 'contributions', label: 'Cotisations récentes', available: props.visibility.contributions },
        { key: 'projects', label: 'Projets récents', available: props.visibility.projects },
        { key: 'quick_actions', label: 'Actions rapides', available: true },
        { key: 'votes', label: 'Votes ouverts', available: props.visibility.votes },
    ] },
].map((g) => ({ ...g, items: g.items.filter((i) => i.available) })).filter((g) => g.items.length));
const shows = (key) => !hidden.value.includes(key);
const saveHidden = () => router.patch(route('preferences.dashboard'), { hidden: hidden.value }, { preserveScroll: true, preserveState: true, only: ['hiddenWidgets'] });
const toggleWidget = (key) => {
    hidden.value = shows(key) ? [...hidden.value, key] : hidden.value.filter((k) => k !== key);
    saveHidden();
};
const showAll = () => { hidden.value = []; saveHidden(); };


const firstName = computed(() => {
    const name = page.props.auth?.user?.name;
    if (!name) return 'Andy';
    return name.split(' ')[0];
});

const stats = computed(() => [
    {
        visible: props.visibility.members,
        key: 'stat_members',
        title: 'Membres actifs',
        value: String(props.memberStats.active),
        change: tr('+{n} ce mois', { n: props.memberStats.newThisMonth }),
        changeType: 'success',
        icon: Users,
    },
    {
        visible: props.visibility.contributions,
        key: 'stat_contributions',
        title: 'Cotisations validées',
        value: props.contributionStats.validatedTotal,
        change: tr('{n} paiements', { n: props.contributionStats.validatedCount }),
        changeType: 'success',
        icon: Wallet,
    },
    {
        visible: props.visibility.funds,
        key: 'stat_fund',
        title: 'Fonds disponible',
        value: props.fundStats.available,
        change: tr('Total : {total}', { total: props.fundStats.total }),
        changeType: 'success',
        icon: Landmark,
    },
    {
        visible: props.visibility.projects,
        key: 'stat_projects',
        title: 'Projets en analyse',
        value: String(props.projectStats.inAnalysis),
        change: props.projectStats.awaitingFunding > 0
            ? tr('{n} à financer', { n: props.projectStats.awaitingFunding })
            : tr('{n} soumis', { n: props.projectStats.submitted }),
        changeType: props.projectStats.inAnalysis > 0 || props.projectStats.awaitingFunding > 0 ? 'warning' : 'neutral',
        icon: FolderKanban,
    },
    {
        visible: props.visibility.votes,
        key: 'stat_votes',
        title: 'Votes ouverts',
        value: String(props.voteStats.open),
        change: props.voteStats.newThisWeek > 0
            ? tr('↗ {n} nouveaux', { n: props.voteStats.newThisWeek })
            : 'Aucun nouveau',
        changeType: props.voteStats.open > 0 ? 'success' : 'neutral',
        icon: Vote,
    },
    {
        visible: props.visibility.contributions,
        key: 'stat_pending',
        title: 'Cotisations en attente',
        value: String(props.contributionStats.pendingCount),
        change: 'Validation trésorier',
        changeType: props.contributionStats.pendingCount > 0 ? 'warning' : 'neutral',
        icon: HeartHandshake,
    },
].filter((stat) => stat.visible && !hidden.value.includes(stat.key)));

const cotisations = computed(() => {
    if (props.recentContributions.length) {
        return props.recentContributions;
    }
    return [
        { membre: '—', montant: '—', date: '—', statut: 'Aucune', statut_variant: 'neutral' },
    ];
});

const projets = computed(() => {
    if (props.recentProjects.length) {
        return props.recentProjects;
    }
    return [
        { projet: '—', porteur: '—', statut: 'Aucun', statut_variant: 'neutral' },
    ];
});

const votes = computed(() => {
    if (props.recentVotes.length) {
        return props.recentVotes;
    }
    return [
        { sujet: '—', type: '—', echeance: '—', participation: 0, statut: 'Aucun', statut_variant: 'neutral' },
    ];
});

// Listes du tableau de bord : recherche, filtre et pagination dans chaque carte.
const uniq = (rows, key) => [...new Set((rows ?? []).map((r) => r[key]).filter(Boolean))].sort();
const cotisationList = useListTools(() => cotisations.value, { searchKeys: ['membre', 'montant'], filters: { statut: (r, v) => r.statut === v }, perPage: 5 });
const projetList = useListTools(() => projets.value, { searchKeys: ['projet', 'porteur'], filters: { statut: (r, v) => r.statut === v }, perPage: 5 });
const voteList = useListTools(() => votes.value, { searchKeys: ['sujet', 'type'], filters: { type: (r, v) => r.type === v }, perPage: 5 });

const quickActions = computed(() => {
    const perms = page.props.auth?.permissions ?? [];
    const actions = [];
    if (perms.includes('create members')) {
        actions.push({ title: 'Ajouter un membre', icon: UserPlus, href: route('members.create') });
    }
    if (perms.includes('create projects')) {
        actions.push({ title: 'Déposer un projet', icon: FilePlus, href: route('projects.create') });
    }
    if (perms.includes('submit contribution payments')) {
        actions.push({ title: 'Ajouter une cotisation', icon: Coins, href: route('contributions.create') });
    }
    if (perms.includes('create votes')) {
        actions.push({ title: 'Créer un vote', icon: CheckSquare, href: route('votes.create') });
    }
    return actions;
});

const projetStatusVariant = (row) => row.statut_variant ?? 'neutral';

const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'Inter, sans-serif',
    },
    labels: props.fundAllocation.length
        ? props.fundAllocation.map((a) => tr(a.name))
        : [tr('Aucune donnée')],
    colors: hsiChartColors,
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: hsiColors.slate },
        markers: { radius: 12 },
    },
    dataLabels: {
        enabled: true,
        formatter: (val) => `${Math.round(val)}%`,
        style: { fontSize: '11px', fontWeight: 600 },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: tr('Fonds total'),
                        fontSize: '11px',
                        color: hsiColors.slateLight,
                        formatter: () => props.fundStats.total,
                    },
                    value: {
                        fontSize: '16px',
                        fontWeight: 700,
                        color: hsiColors.navy,
                    },
                },
            },
        },
    },
    stroke: { width: 2, colors: [hsiColors.white] },
    tooltip: {
        y: { formatter: (val) => `${Math.round(val)}%` },
    },
}));

const chartSeries = computed(() => {
    if (!props.fundAllocation.length) return [1];
    return props.fundAllocation.map((a) => a.percent || a.value);
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Bienvenue, "
            :accent="firstName"
            subtitle="Vue d'ensemble de HSI Family Office"
        >
            <template #actions>
                <button type="button" class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition" :class="customizing ? 'border-hsi-navy bg-hsi-navy text-white' : 'border-hsi-border bg-white text-hsi-navy hover:bg-hsi-muted'" :aria-expanded="customizing" @click="customizing = !customizing">
                    <SlidersHorizontal class="h-4 w-4" aria-hidden="true" /> Personnaliser
                </button>
            </template>
        </HsiPageHeader>

        <div v-if="customizing" class="mb-8 rounded-3xl border border-hsi-gold/40 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-heading text-lg font-semibold text-hsi-navy">Cartes affichées</h2>
                    <p class="text-sm text-hsi-slate">Cliquez sur une carte pour l’afficher ou la masquer. Votre choix est enregistré sur votre compte.</p>
                </div>
                <button v-if="hidden.length" type="button" class="text-sm font-semibold text-hsi-navy hover:underline" @click="showAll">Tout afficher</button>
            </div>
            <div v-for="group in widgetGroups" :key="group.title" class="mb-3 last:mb-0">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">{{ group.title }}</p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="item in group.items" :key="item.key" type="button" class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition" :class="shows(item.key) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate-light line-through'" :aria-pressed="shows(item.key)" @click="toggleWidget(item.key)">
                        <component :is="shows(item.key) ? Eye : EyeOff" class="h-4 w-4" aria-hidden="true" /> {{ item.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div
            v-if="stats.length"
            class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-5 mb-8"
        >
            <HsiStatCard
                v-for="stat in stats"
                :key="stat.title"
                :icon="stat.icon"
                :title="stat.title"
                :value="stat.value"
                :change="stat.change"
                :change-type="stat.changeType"
            />
        </div>

        <!-- Grille principale : les tableaux occupent 2 colonnes sur 3 pour rester lisibles sans défilement. -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-10">
            <HsiChartCard
                v-if="flow && visibility.funds && shows('flows')"
                title="Flux financiers"
                class="lg:col-span-3"
            >
                <HsiFlowPanel :flow="flow" :preset="flowPeriod" :height="420" />
            </HsiChartCard>

            <!-- Donut chart -->
            <HsiChartCard
                v-if="visibility.funds && shows('allocation')"
                title="Allocation du fonds familial"
                class="lg:col-span-1"
            >
                <VueApexCharts
                    type="donut"
                    height="320"
                    :options="chartOptions"
                    :series="chartSeries"
                />
                <Link
                    :href="route('funds.index')"
                    class="mt-2 inline-flex text-xs font-medium text-hsi-gold hover:text-hsi-gold-light transition-colors"
                >
                    Voir le détail du fonds →
                </Link>
            </HsiChartCard>

            <!-- Cotisations récentes -->
            <HsiChartCard
                v-if="visibility.contributions && shows('contributions')"
                title="Cotisations récentes"
                class="lg:col-span-2"
            >
                <HsiListToolbar v-model:search="cotisationList.search.value" placeholder="Membre…" :active="cotisationList.active.value" class="!mb-3" @reset="cotisationList.reset()"><select v-model="cotisationList.filters.statut" class="rounded-lg border border-hsi-border bg-white px-2.5 py-2 text-xs"><option value="">Tous les statuts</option><option v-for="st in uniq(cotisations, 'statut')" :key="st" :value="st">{{ st }}</option></select></HsiListToolbar>
                <HsiDataTable
                    :columns="[
                        { key: 'membre', label: 'Membre' },
                        { key: 'montant', label: 'Montant', align: 'right' },
                        { key: 'date', label: 'Date' },
                        { key: 'statut', label: 'Statut', align: 'center' },
                    ]"
                    :rows="cotisationList.paged.value"
                    compact
                >
                    <template #cell-membre="{ row }">
                        <div class="flex items-center gap-2">
                            <HsiAvatar
                                :name="row.membre"
                                size="sm"
                            />
                            <span class="text-sm font-medium">{{ row.membre }}</span>
                        </div>
                    </template>
                    <template #cell-montant="{ value }">
                        <span class="font-semibold">{{ value }}</span>
                    </template>
                    <template #cell-statut="{ row }">
                        <HsiBadge :variant="row.statut_variant ?? 'success'">{{ row.statut }}</HsiBadge>
                    </template>
                </HsiDataTable>
                <HsiPagination v-model:page="cotisationList.page.value" v-model:per-page="cotisationList.perPage.value" :total="cotisationList.total.value" :per-page-options="[5, 10, 25]" label="cotisations" compact />
            </HsiChartCard>

            <!-- Projets récents -->
            <HsiChartCard
                v-if="visibility.projects && shows('projects')"
                title="Projets récents"
                class="lg:col-span-2"
            >
                <HsiListToolbar v-model:search="projetList.search.value" placeholder="Projet ou porteur…" :active="projetList.active.value" class="!mb-3" @reset="projetList.reset()"><select v-model="projetList.filters.statut" class="rounded-lg border border-hsi-border bg-white px-2.5 py-2 text-xs"><option value="">Tous les statuts</option><option v-for="st in uniq(projets, 'statut')" :key="st" :value="st">{{ st }}</option></select></HsiListToolbar>
                <HsiDataTable
                    :columns="[
                        { key: 'projet', label: 'Projet' },
                        { key: 'porteur', label: 'Porteur' },
                        { key: 'statut', label: 'Statut', align: 'center' },
                    ]"
                    :rows="projetList.paged.value"
                    compact
                >
                    <template #cell-statut="{ row }">
                        <HsiBadge :variant="projetStatusVariant(row)">
                            {{ row.statut }}
                        </HsiBadge>
                    </template>
                </HsiDataTable>
                <HsiPagination v-model:page="projetList.page.value" v-model:per-page="projetList.perPage.value" :total="projetList.total.value" :per-page-options="[5, 10, 25]" label="projets" compact />
                <Link
                    :href="route('projects.index')"
                    class="mt-2 inline-flex text-xs font-medium text-hsi-gold hover:text-hsi-gold-light transition-colors"
                >
                    Voir tous les projets →
                </Link>
            </HsiChartCard>

            <!-- Actions rapides -->
            <HsiChartCard
                v-if="quickActions.length && shows('quick_actions')"
                title="Actions rapides"
            >
                <div class="grid grid-cols-2 gap-3">
                    <component
                        :is="action.href && action.href !== '#' ? Link : 'div'"
                        v-for="action in quickActions"
                        :key="action.title"
                        :href="action.href !== '#' ? action.href : undefined"
                    >
                        <HsiActionCard
                            :icon="action.icon"
                            :title="action.title"
                            :show-arrow="false"
                        />
                    </component>
                </div>
            </HsiChartCard>

            <!-- Votes ouverts -->
            <HsiChartCard
                v-if="visibility.votes && shows('votes')"
                title="Votes ouverts"
                class="lg:col-span-3"
            >
                <HsiListToolbar v-model:search="voteList.search.value" placeholder="Sujet…" :active="voteList.active.value" class="!mb-3" @reset="voteList.reset()"><select v-model="voteList.filters.type" class="rounded-lg border border-hsi-border bg-white px-2.5 py-2 text-xs"><option value="">Tous les types</option><option v-for="t in uniq(votes, 'type')" :key="t" :value="t">{{ t }}</option></select></HsiListToolbar>
                <HsiDataTable
                    :columns="[
                        { key: 'sujet', label: 'Sujet' },
                        { key: 'type', label: 'Type' },
                        { key: 'echeance', label: 'Échéance' },
                        { key: 'participation', label: 'Participation' },
                        { key: 'statut', label: 'Statut', align: 'center' },
                    ]"
                    :rows="voteList.paged.value"
                    compact
                >
                    <template #cell-participation="{ row }">
                        <div class="flex items-center gap-2 min-w-[100px]">
                            <div class="flex-1 h-1.5 rounded-full bg-hsi-muted overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-hsi-gold transition-all"
                                    :style="{ width: `${row.participation}%` }"
                                />
                            </div>
                            <span class="text-xs font-medium text-hsi-slate w-8">{{ row.participation }}%</span>
                        </div>
                    </template>
                    <template #cell-statut="{ row }">
                        <HsiBadge :variant="row.statut_variant ?? 'success'">{{ row.statut }}</HsiBadge>
                    </template>
                </HsiDataTable>
                <HsiPagination v-model:page="voteList.page.value" v-model:per-page="voteList.perPage.value" :total="voteList.total.value" :per-page-options="[5, 10, 25]" label="votes" compact />
                <Link
                    :href="route('votes.index')"
                    class="mt-2 inline-flex text-xs font-medium text-hsi-gold hover:text-hsi-gold-light transition-colors"
                >
                    Voir tous les votes →
                </Link>
            </HsiChartCard>
        </div>

        <!-- Footer citation -->
        <div class="text-center py-6 border-t border-hsi-border/60">
            <p class="font-heading text-sm italic text-hsi-slate-light">
                « Préserver nos valeurs. Soutenir nos membres. Construire l'avenir ensemble. »
            </p>
        </div>
    </AuthenticatedLayout>
</template>
