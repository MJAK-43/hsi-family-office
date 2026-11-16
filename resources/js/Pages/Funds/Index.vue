<script setup>
import { tr } from '@/i18n';
import { currencySymbol, formatMoney } from '@/currency';
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import {
    Landmark, Wallet, ArrowLeftRight, TrendingUp, Plus, Settings, RefreshCw, Eye, Ban, Pencil, Power, FlaskConical, Trash2, ChevronDown,
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiChartCard from '@/Components/Hsi/HsiChartCard.vue';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import HsiActionCard from '@/Components/Hsi/HsiActionCard.vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiFlowPanel from '@/Components/Hsi/HsiFlowPanel.vue';
import { useListTools } from '@/Composables/useListTools';

import { hsiColors, hsiChartColors } from '@/theme/hsiTheme';

const props = defineProps({
    stats: Array,
    accounts: Array,
    allocationChart: Array,
    evolutionChart: Object,
    recentMovements: Array,
    can: Object,
    flow: { type: Object, default: null },
    flowPeriod: { type: String, default: 'all' },
    accountFlows: { type: Array, default: () => [] },
    inactiveAccounts: { type: Array, default: () => [] },
    examples: { type: Object, default: () => ({ projects: 0 }) },
    resetPhrase: { type: String, default: 'REMISE A ZERO' },
});

// Correction manuelle d'un solde (crée un ajustement tracé).
const adjusting = ref(null);
const adjustForm = useForm({ target_balance: '', reason: '' });
const openAdjust = (account) => { adjustForm.reset(); adjustForm.clearErrors(); adjustForm.target_balance = account.balance; adjusting.value = account; };
const saveAdjust = () => adjustForm.post(route('funds.accounts.adjust', adjusting.value.id), { preserveScroll: true, onSuccess: () => { adjusting.value = null; } });
const adjustDiff = computed(() => (adjusting.value ? Math.round((Number(adjustForm.target_balance || 0) - adjusting.value.balance) * 100) / 100 : 0));

// Mise en service : remise à zéro de tous les comptes.
const resetForm = useForm({ confirmation: '' });
const resetFunds = () => resetForm.post(route('funds.reset'), { preserveScroll: true, onSuccess: () => resetForm.reset() });

const money = (v) => formatMoney(v, { decimals: 0 });

// Comptes de fonds : création, modification, activation.
const colors = { gold: 'bg-hsi-gold', blue: 'bg-[#3B82F6]', green: 'bg-hsi-success', red: 'bg-hsi-danger', purple: 'bg-purple-500', slate: 'bg-hsi-slate', orange: 'bg-orange-500', teal: 'bg-teal-500' };
const accountModal = ref(null); // null | 'new' | compte
const accountForm = useForm({ name: '', description: '', color: null });
const openAccount = (account = null) => {
    accountForm.clearErrors();
    accountForm.name = account?.name ?? '';
    accountForm.description = account?.description ?? '';
    accountForm.color = account?.color ?? null;
    accountModal.value = account ?? 'new';
};
const saveAccount = () => {
    const options = { preserveScroll: true, onSuccess: () => { accountModal.value = null; } };
    accountModal.value === 'new'
        ? accountForm.post(route('funds.accounts.store'), options)
        : accountForm.put(route('funds.accounts.update', accountModal.value.id), options);
};
const toggleAccount = (account) => {
    const message = account.is_active ? tr('Désactiver le compte « {name} » ?', { name: account.name }) : tr('Réactiver le compte « {name} » ?', { name: account.name });
    if (confirm(message)) router.patch(route('funds.accounts.toggle', account.id), {}, { preserveScroll: true });
};

// Cartes de flux par compte (repliables).
const openFlows = ref(Object.fromEntries((props.accountFlows ?? []).map((f, i) => [f.account.id, i < 2])));
const toggleFlow = (id) => { openFlows.value[id] = !openFlows.value[id]; };

// Données d'exemple
const createExamples = () => {
    if (confirm(tr('Créer 2 projets d’exemple par compte de fonds (avec des apports et versements fictifs marqués « Exemple — ») ?'))) router.post(route('funds.examples.store'), {}, { preserveScroll: true });
};
const removeExamples = () => {
    if (confirm(tr('Supprimer toutes les données d’exemple (projets, apports et versements fictifs) ? Les soldes seront recalculés.'))) router.delete(route('funds.examples.destroy'), { preserveScroll: true });
};

// Derniers mouvements : recherche, filtres et pagination dans la page.
const recentList = useListTools(() => props.recentMovements ?? [], {
    searchKeys: ['label', 'description', 'account', 'reference', 'type_label', 'amount_formatted'],
    filters: { account: (m, v) => m.account === v, type: (m, v) => m.type === v, direction: (m, v) => m.direction === v },
    perPage: 10,
});
const movementAccounts = computed(() => [...new Set((props.recentMovements ?? []).map((m) => m.account).filter(Boolean))].sort());
const movementTypes = computed(() => [...new Map((props.recentMovements ?? []).map((m) => [m.type, m.type_label])).entries()]);

const statIcons = [Landmark, Wallet, TrendingUp, ArrowLeftRight, TrendingUp];

const donutOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'Inter, sans-serif' },
    labels: props.allocationChart.map((a) => tr(a.name)),
    colors: hsiChartColors,
    legend: { position: 'bottom', fontSize: '12px', labels: { colors: hsiColors.slate } },
    dataLabels: { enabled: true, formatter: (v) => `${Math.round(v)}%` },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        formatter: () => props.stats[0]?.value ?? `0 ${currencySymbol()}`,
                    },
                },
            },
        },
    },
}));

const donutSeries = computed(() => props.allocationChart.map((a) => a.value || 0.01));

const lineOptions = computed(() => ({
    chart: { type: 'area', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
    stroke: { curve: 'smooth', width: 2 },
    colors: [hsiColors.gold],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: { categories: props.evolutionChart.labels ?? [], labels: { style: { fontSize: '11px' } } },
    yaxis: { labels: { formatter: (v) => `${Math.round(v)} ${currencySymbol()}` } },
    dataLabels: { enabled: false },
}));

const lineSeries = computed(() => [{
    name: tr('Évolution cumulative'),
    data: props.evolutionChart.values ?? [],
}]);

const recalculate = () => {
    if (confirm(tr('Recalculer tous les soldes du fonds ?'))) {
        router.post(route('funds.recalculate'));
    }
};

const cancelMovement = (id) => {
    const reason = prompt(tr('Motif d’annulation (min. 5 caractères) :'));
    if (reason && reason.length >= 5) {
        router.patch(route('funds.movements.cancel', id), { cancellation_reason: reason });
    }
};
</script>

<template>
    <Head title="Fonds familial" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Fonds familial"
            subtitle="Vue patrimoniale des ressources HSI Family Office"
        >
            <template #actions>
                <HsiButton v-if="can.manageAccounts" @click="openAccount()"><Plus class="h-4 w-4" /> Nouveau compte de fonds</HsiButton>
            </template>
        </HsiPageHeader>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
            <HsiStatCard
                v-for="(stat, i) in stats"
                :key="stat.title"
                :icon="statIcons[i]"
                :title="stat.title"
                :value="stat.value"
                :change="stat.change"
                :change-type="stat.changeType"
            />
        </div>

        <HsiCard v-if="flow" padding="lg" class="mb-6">
            <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-heading text-lg font-semibold text-hsi-navy">Flux financiers — tous les comptes</h3>
                <p class="text-xs text-hsi-slate-light">D’où vient l’argent, dans quel compte il passe, et où il va</p>
            </div>
            <HsiFlowPanel :flow="flow" :preset="flowPeriod" :reload-only="['flow', 'flowPeriod', 'accountFlows']" />
        </HsiCard>

        <div v-if="accountFlows.length" class="mb-8">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="font-heading text-lg font-semibold text-hsi-navy">Flux détaillés par compte de fonds</h3>
                    <p class="text-sm text-hsi-slate">Chaque compte : ce qui l’alimente, les projets qu’il a financés et ceux validés qui attendent leur versement. Même période que ci-dessus.</p>
                </div>
                <div v-if="can.manageExamples" class="flex flex-wrap items-center gap-2">
                    <span v-if="examples.projects" class="text-xs text-hsi-slate-light">{{ examples.projects }} projet(s) d’exemple</span>
                    <HsiButton v-if="!examples.projects" size="sm" variant="outline" @click="createExamples"><FlaskConical class="h-4 w-4" /> Créer des données d’exemple</HsiButton>
                    <HsiButton v-else size="sm" variant="ghost" @click="removeExamples"><Trash2 class="h-4 w-4 text-hsi-danger" /> Supprimer les données d’exemple</HsiButton>
                </div>
            </div>
            <div class="space-y-4">
                <HsiCard v-for="item in accountFlows" :key="item.account.id" padding="lg">
                    <button type="button" class="flex w-full flex-wrap items-center justify-between gap-3 text-left" :aria-expanded="!!openFlows[item.account.id]" @click="toggleFlow(item.account.id)">
                        <span class="flex items-center gap-3">
                            <span class="h-3 w-3 rounded-full" :class="colors[item.account.color] ?? 'bg-hsi-navy'" />
                            <span>
                                <span class="block font-heading text-lg font-semibold text-hsi-navy">{{ item.account.name }}</span>
                                <span class="block text-xs text-hsi-slate-light">Solde {{ item.account.balance_formatted }} · {{ item.flow.projects?.length ?? 0 }} projet(s) validé(s)</span>
                            </span>
                        </span>
                        <span class="flex items-center gap-3 text-xs text-hsi-slate">
                            <span class="hidden sm:inline">Entrées <strong class="text-hsi-success">{{ money(item.flow.totals.in) }}</strong> · Sorties <strong class="text-[#C2410C]">{{ money(item.flow.totals.out) }}</strong><template v-if="item.flow.totals.planned"> · À verser <strong>{{ money(item.flow.totals.planned) }}</strong></template></span>
                            <ChevronDown class="h-5 w-5 transition" :class="openFlows[item.account.id] ? 'rotate-180' : ''" aria-hidden="true" />
                        </span>
                    </button>
                    <div v-if="openFlows[item.account.id]" class="mt-5 border-t border-hsi-border pt-5">
                        <HsiFlowPanel :flow="item.flow" :with-period="false" :with-breakdown="false" :height="380" />
                        <div class="mt-4 text-right"><Link :href="route('funds.show', item.account.id)" class="text-sm font-semibold text-hsi-navy hover:underline">Ouvrir le compte →</Link></div>
                    </div>
                </HsiCard>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
            <HsiChartCard title="Répartition du fonds">
                <VueApexCharts
                    type="donut"
                    height="300"
                    :options="donutOptions"
                    :series="donutSeries"
                />
            </HsiChartCard>
            <HsiChartCard title="Évolution du fonds">
                <VueApexCharts
                    type="area"
                    height="300"
                    :options="lineOptions"
                    :series="lineSeries"
                />
            </HsiChartCard>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            <div class="xl:col-span-2">
                <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                    Comptes du fonds
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <HsiCard
                        v-for="account in accounts"
                        :key="account.id"
                        padding="md"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2"><span v-if="account.color" class="h-2.5 w-2.5 rounded-full" :class="colors[account.color]" /><HsiBadge :variant="account.type_color">{{ account.type_label }}</HsiBadge></div>
                                <h4 class="font-heading font-semibold text-hsi-navy mt-2">{{ account.name }}</h4>
                                <p v-if="account.description" class="line-clamp-2 text-xs text-hsi-slate-light">{{ account.description }}</p>
                            </div>
                            <span class="text-xs text-hsi-slate-light">{{ account.percent }}%</span>
                        </div>
                        <p class="text-2xl font-bold text-hsi-navy">{{ account.balance_formatted }}</p>
                        <p class="mb-3 text-xs text-hsi-slate-light">{{ account.projects_count ?? 0 }} projet(s) rattaché(s)</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('funds.show', account.id)">
                                <HsiButton variant="outline" size="sm"><Eye class="h-4 w-4" /> Voir détail</HsiButton>
                            </Link>
                            <template v-if="can.manageAccounts">
                                <button type="button" class="rounded-lg p-1.5 text-hsi-slate hover:bg-hsi-muted" :title="$tr('Modifier')" @click="openAccount(account)"><Pencil class="h-4 w-4" /></button>
                                <button v-if="can.adjustBalance" type="button" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-hsi-slate hover:bg-hsi-muted" :title="$tr('Corriger le solde')" @click="openAdjust(account)">Corriger le solde</button>
                                <button v-if="account.slug !== 'treasury'" type="button" class="rounded-lg p-1.5 text-hsi-slate hover:bg-hsi-muted" :title="$tr('Désactiver')" @click="toggleAccount(account)"><Power class="h-4 w-4" /></button>
                            </template>
                        </div>
                    </HsiCard>
                </div>
                <div v-if="inactiveAccounts.length" class="mt-4 rounded-2xl border border-dashed border-hsi-border p-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">Comptes désactivés</p>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="account in inactiveAccounts" :key="account.id" class="inline-flex items-center gap-2 rounded-xl bg-hsi-muted px-3 py-1.5 text-sm text-hsi-slate">
                            {{ account.name }}
                            <button v-if="can.manageAccounts" type="button" class="text-xs font-semibold text-[#1D4ED8] hover:underline" @click="toggleAccount(account)">Réactiver</button>
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                    Actions rapides
                </h3>
                <div class="grid grid-cols-1 gap-3">
                    <Link
                        v-if="can.createMovement"
                        :href="route('funds.movements.create')"
                    >
                        <HsiActionCard
                            :icon="Plus"
                            title="Ajouter un mouvement"
                        />
                    </Link>
                    <Link
                        v-if="can.transfer"
                        :href="route('funds.transfer.form')"
                    >
                        <HsiActionCard
                            :icon="ArrowLeftRight"
                            title="Faire un transfert"
                        />
                    </Link>
                    <Link
                        v-if="can.manageRules"
                        :href="route('fund-allocation-rules.index')"
                    >
                        <HsiActionCard
                            :icon="Settings"
                            title="Règles d'allocation"
                        />
                    </Link>
                    <HsiActionCard
                        v-if="can.recalculate"
                        :icon="RefreshCw"
                        title="Recalculer les soldes"
                        @click="recalculate"
                    />
                </div>
            </div>
        </div>

        <Modal :show="!!adjusting" max-width="md" @close="adjusting = null">
            <form v-if="adjusting" class="space-y-4 p-6" @submit.prevent="saveAdjust">
                <h2 class="font-heading text-lg font-semibold text-hsi-navy">Corriger le solde — {{ adjusting.name }}</h2>
                <p class="text-sm text-hsi-slate">Solde actuel : <strong>{{ adjusting.balance_formatted }}</strong>. L’écart est enregistré comme un ajustement, avec sa raison, dans l’historique du compte et le journal.</p>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="adj-balance">Nouveau solde ({{ $page.props.currency.symbol }}) *</label>
                    <input id="adj-balance" v-model="adjustForm.target_balance" type="number" step="0.01" min="0" required class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm">
                    <p v-if="adjustDiff" class="mt-1 text-xs" :class="adjustDiff > 0 ? 'text-hsi-success' : 'text-hsi-danger'">{{ adjustDiff > 0 ? '+' : '' }}{{ adjustDiff }} {{ $page.props.currency.symbol }}</p>
                    <p v-if="adjustForm.errors.target_balance" class="mt-1 text-xs text-hsi-danger">{{ adjustForm.errors.target_balance }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="adj-reason">Raison *</label>
                    <input id="adj-reason" v-model="adjustForm.reason" required minlength="5" maxlength="500" class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm" :placeholder="$tr('ex. Rapprochement avec le relevé bancaire de mars')">
                    <p v-if="adjustForm.errors.reason" class="mt-1 text-xs text-hsi-danger">{{ adjustForm.errors.reason }}</p>
                </div>
                <div class="flex justify-end gap-2"><HsiButton type="button" variant="ghost" @click="adjusting = null">Annuler</HsiButton><HsiButton type="submit" :disabled="adjustForm.processing || !adjustDiff">Enregistrer la correction</HsiButton></div>
            </form>
        </Modal>

        <Modal :show="!!accountModal" max-width="lg" @close="accountModal = null">
            <form class="space-y-4 p-6" @submit.prevent="saveAccount">
                <h2 class="font-heading text-lg font-semibold text-hsi-navy">{{ accountModal === 'new' ? 'Nouveau compte de fonds' : 'Modifier le compte' }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="acc-name">Nom *</label>
                    <input id="acc-name" v-model="accountForm.name" required maxlength="120" class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm" :placeholder="$tr('ex. Fonds d’urgence')">
                    <p v-if="accountForm.errors.name" class="mt-1 text-xs text-hsi-danger">{{ accountForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="acc-desc">Description</label>
                    <textarea id="acc-desc" v-model="accountForm.description" rows="2" maxlength="1000" class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm" />
                </div>
                <div>
                    <p class="mb-1 text-sm font-medium text-hsi-slate">Couleur</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="(cls, key) in colors" :key="key" type="button" class="h-7 w-7 rounded-full ring-offset-2 transition" :class="[cls, accountForm.color === key ? 'ring-2 ring-hsi-navy' : '']" :aria-label="key" @click="accountForm.color = key" />
                    </div>
                </div>
                <p v-if="accountModal === 'new'" class="rounded-xl bg-hsi-muted/60 px-3 py-2 text-xs text-hsi-slate">Le compte démarre avec un solde nul. Alimentez-le par un virement, ou ajoutez-le aux règles d’allocation pour qu’il reçoive une part des cotisations.</p>
                <div class="flex justify-end gap-2"><HsiButton type="button" variant="ghost" @click="accountModal = null">Annuler</HsiButton><HsiButton type="submit" :disabled="accountForm.processing">{{ accountModal === 'new' ? 'Créer le compte' : 'Enregistrer' }}</HsiButton></div>
            </form>
        </Modal>

        <HsiCard padding="md">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-heading text-lg font-semibold text-hsi-navy">
                    Derniers mouvements
                </h3>
                <Link
                    v-if="can.viewMovements"
                    :href="route('funds.movements')"
                    class="text-sm text-hsi-gold hover:text-hsi-gold-light"
                >
                    Voir tout →
                </Link>
            </div>
            <HsiListToolbar v-model:search="recentList.search.value" placeholder="Libellé, poche, référence…" :active="recentList.active.value" :count="recentList.total.value" label="mouvement(s)" @reset="recentList.reset()">
                <select v-model="recentList.filters.account" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Toutes les poches</option><option v-for="a in movementAccounts" :key="a" :value="a">{{ a }}</option></select>
                <select v-model="recentList.filters.type" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Tous les types</option><option v-for="[value, label] in movementTypes" :key="value" :value="value">{{ label }}</option></select>
                <select v-model="recentList.filters.direction" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Entrées et sorties</option><option value="credit">Entrées</option><option value="debit">Sorties</option></select>
            </HsiListToolbar>
            <HsiDataTable
                :columns="[
                    { key: 'movement_date', label: 'Date' },
                    { key: 'account', label: 'Poche' },
                    { key: 'type_label', label: 'Type' },
                    { key: 'direction_label', label: 'Direction' },
                    { key: 'amount_formatted', label: 'Montant', align: 'right' },
                    { key: 'status_label', label: 'Statut', align: 'center' },
                ]"
                compact
            >
                <template #body>
                    <tr
                        v-for="row in recentList.paged.value"
                        :key="row.id"
                        class="border-b border-hsi-border/60 last:border-0"
                    >
                        <td class="px-3 py-2.5 text-sm">{{ row.movement_date }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.account }}</td>
                        <td class="px-3 py-2.5 text-sm">
                            <HsiBadge :variant="row.type_color">{{ row.type_label }}</HsiBadge>
                        </td>
                        <td class="px-3 py-2.5 text-sm">{{ row.direction_label }}</td>
                        <td class="px-3 py-2.5 text-sm text-right font-semibold">{{ row.amount_formatted }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <HsiBadge :variant="row.status_color">{{ row.status_label }}</HsiBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <button
                                v-if="can.cancel && row.status === 'validated'"
                                type="button"
                                class="text-hsi-danger hover:bg-red-50 p-1.5 rounded-lg"
                                @click="cancelMovement(row.id)"
                            >
                                <Ban class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                </template>
            </HsiDataTable>
            <p v-if="!recentList.total.value" class="py-4 text-center text-sm text-hsi-slate-light">Aucun mouvement ne correspond.</p>
            <HsiPagination v-model:page="recentList.page.value" v-model:per-page="recentList.perPage.value" :total="recentList.total.value" label="mouvements" compact />
        </HsiCard>
        <HsiCard v-if="can.resetFunds" padding="lg" class="mt-8 border border-red-200">
            <h3 class="font-heading text-lg font-semibold text-hsi-danger">Mise en service : remettre les comptes à zéro</h3>
            <p class="mt-1 text-sm text-hsi-slate">À utiliser une seule fois, au démarrage réel du fonds. Les données d’exemple sont supprimées et tous les mouvements existants sont archivés : chaque compte repart à 0. L’opération est inscrite au journal d’audit.</p>
            <p class="mt-1 text-xs text-hsi-slate-light">Les cotisations, projets et membres de test restent dans l’application : seuls les comptes et leurs mouvements sont remis à zéro.</p>
            <form class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="resetFunds">
                <input v-model="resetForm.confirmation" type="text" class="rounded-xl border border-hsi-border px-3 py-2.5 text-sm sm:w-72" :placeholder="$tr('Tapez {phrase}', { phrase: resetPhrase })" :aria-label="$tr('Confirmation')">
                <HsiButton type="submit" variant="danger" :disabled="resetForm.processing || resetForm.confirmation !== resetPhrase">Remettre tous les comptes à zéro</HsiButton>
            </form>
            <p v-if="resetForm.errors.confirmation" class="mt-1 text-xs text-hsi-danger">{{ resetForm.errors.confirmation }}</p>
        </HsiCard>
    </AuthenticatedLayout>
</template>
