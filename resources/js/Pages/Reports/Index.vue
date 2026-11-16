<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import { FileSpreadsheet, FileText, LayoutGrid, Share2, ShieldCheck } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';
import { tr } from '@/i18n';
import { formatMoney, currencySymbol } from '@/currency';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiSankey from '@/Components/Hsi/HsiSankey.vue';
import { useListTools } from '@/Composables/useListTools';

const props = defineProps({
    full: Boolean, report: Object, filters: Object, options: Object,
    shared: Array, audits: Array, auditCandidates: Array, can: Object,
});

const money = (v) => formatMoney(v, { decimals: 2 });

/* ---------- Tableaux : recherche, filtres et pagination ---------- */
const uniq = (rows, key) => [...new Set((rows ?? []).map((r) => r[key]).filter(Boolean))].sort();
const loanList = useListTools(() => props.report?.loans ?? [], { searchKeys: ['title', 'borrower'], filters: { status: (l, v) => l.status === v }, perPage: 10 });
const memberList = useListTools(() => props.report?.members ?? [], {
    searchKeys: ['name', 'zone'],
    filters: { zone: (m, v) => m.zone === v, penalties: (m, v) => (v === 'yes' ? m.penalties > 0 : m.penalties <= 0) },
    perPage: 10,
});
const statementList = useListTools(() => props.report?.statement?.rows ?? [], {
    searchKeys: ['label', 'account', 'type', 'reference', 'date'],
    filters: { account: (r, v) => r.account === v, type: (r, v) => r.type === v, direction: (r, v) => (v === 'credit' ? r.credit > 0 : r.debit > 0) },
    perPage: 25,
});
const sharedList = useListTools(() => props.shared ?? [], { searchKeys: ['title', 'comment', 'author'], perPage: 5 });
const auditList = useListTools(() => props.audits ?? [], { searchKeys: ['title', 'year', 'status_label', 'reviewers'], perPage: 5 });
const inputClass = 'rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm text-hsi-navy';

/* ---------- Filtres ---------- */
const filters = reactive({ ...props.filters });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('reports.index'), Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true }), 350);
});
const resetFilters = () => { Object.assign(filters, { from: '', to: '', zone_id: null, project_type_id: null, user_id: null, fund_account_id: null, movement_type: null }); };

/* ---------- Cartes affichées (mémorisées dans ce navigateur) ---------- */
const widgets = [
    { key: 'kpis', label: 'Indicateurs clés' },
    { key: 'flows', label: 'Flux financiers' },
    { key: 'monthly', label: 'Évolution mensuelle' },
    { key: 'movements', label: 'Mouvements par type' },
    { key: 'projects', label: 'Projets par type et statut' },
    { key: 'accounts', label: 'Soldes des comptes' },
    { key: 'loans', label: 'Prêts et remboursements' },
    { key: 'members', label: 'Registre des membres' },
    { key: 'statement', label: 'Relevé de compte' },
];
const stored = (() => { try { return JSON.parse(localStorage.getItem('hsi-report-widgets-v2') || 'null'); } catch { return null; } })();
const visible = ref(stored ?? widgets.map((w) => w.key));
const showPicker = ref(false);
watch(visible, (v) => { try { localStorage.setItem('hsi-report-widgets-v2', JSON.stringify(v)); } catch { /* stockage indisponible */ } }, { deep: true });
const shown = (key) => visible.value.includes(key);

/* ---------- Graphiques ---------- */
const palette = ['#0B2748', '#D4AF37', '#2563EB', '#0F766E', '#B45309', '#7C3AED', '#DC2626', '#64748B'];
const monthlyChart = computed(() => ({
    series: [
        { name: tr('Cotisations'), data: props.report?.monthly.map((m) => m.contributions) ?? [] },
        { name: tr('Décaissements'), data: props.report?.monthly.map((m) => m.disbursements) ?? [] },
        { name: tr('Remboursements'), data: props.report?.monthly.map((m) => m.repayments) ?? [] },
    ],
    options: {
        chart: { type: 'bar', toolbar: { show: true }, fontFamily: 'inherit' },
        colors: ['#D4AF37', '#0B2748', '#0F766E'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: props.report?.monthly.map((m) => m.label) ?? [] },
        yaxis: { labels: { formatter: (v) => `${Math.round(v)} ${currencySymbol()}` } },
        legend: { position: 'top' },
    },
}));
const donut = (items, valueKey) => ({
    series: items.map((i) => i[valueKey]),
    options: { chart: { type: 'donut', fontFamily: 'inherit' }, labels: items.map((i) => tr(i.label)), colors: palette, legend: { position: 'bottom' }, dataLabels: { enabled: false } },
});
const movementsChart = computed(() => donut(props.report?.movements_by_type ?? [], 'total'));
const typesChart = computed(() => donut(props.report?.projects.by_type ?? [], 'count'));
const statusChart = computed(() => ({
    series: [{ name: tr('Projets'), data: (props.report?.projects.by_status ?? []).map((s) => s.count) }],
    options: { chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false } }, colors: ['#0B2748'], plotOptions: { bar: { horizontal: true, borderRadius: 4 } }, dataLabels: { enabled: false }, xaxis: { categories: (props.report?.projects.by_status ?? []).map((s) => tr(s.label)) } },
}));

/* ---------- Exports ---------- */
const exportSections = ref(['summary', 'statement', 'members', 'loans', 'movements']);
const period = ref('custom');
const exportUrl = (format) => route('reports.export', { ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), format, period: period.value, sections: exportSections.value });

/* ---------- Partage et vérification ---------- */
const shareForm = useForm({ title: '', kind: 'quarterly', from: props.filters.from, to: props.filters.to, comment: '' });
const share = () => shareForm.post(route('reports.share'), { preserveScroll: true, onSuccess: () => shareForm.reset('title', 'comment') });
const auditForm = useForm({ year: new Date().getFullYear() - 1, reviewer_ids: [] });
const createAudit = () => auditForm.post(route('reports.audits.store'), { preserveScroll: true });
const confirmForm = useForm({ status: 'confirmed', conclusion: '' });
const confirmAudit = (audit) => confirmForm.patch(route('reports.audits.confirm', audit.id), { preserveScroll: true });

const kpiCards = computed(() => {
    const k = props.report?.kpis ?? {};
    return [
        { label: 'Cotisations reçues', value: money(k.contributions), hint: `${k.contributions_count ?? 0} ${tr('versement(s)')}` },
        { label: 'Solde total du compte', value: money(k.balance) },
        { label: 'Reste dû sur les prêts', value: money(k.outstanding), hint: `${k.active_loans ?? 0} ${tr('prêt(s) en cours')}` },
        { label: 'Remboursements reçus', value: money(k.repayments) },
        { label: 'Décaissements', value: money(k.disbursed) },
        { label: 'Pénalités appliquées', value: money((k.contribution_penalties ?? 0) + (k.loan_penalties ?? 0)), hint: `${k.late_contributions ?? 0} ${tr('cotisation(s) et')} ${k.late_installments ?? 0} ${tr('échéance(s) en retard')}` },
        { label: 'Membres actifs', value: String(k.active_members ?? 0) },
    ];
});
</script>

<template>
    <Head title="Rapports" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Rapports et " accent="transparence" subtitle="Section 6 du règlement : cotisations, retards et pénalités, solde du compte, état des remboursements" />

        <template v-if="full">
            <!-- Filtres -->
            <HsiCard padding="md" class="mb-6">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <label class="text-xs text-hsi-slate">Du<input v-model="filters.from" type="date" :class="[inputClass, 'mt-1 w-full']"></label>
                    <label class="text-xs text-hsi-slate">Au<input v-model="filters.to" type="date" :class="[inputClass, 'mt-1 w-full']"></label>
                    <label class="text-xs text-hsi-slate">Zone<select v-model="filters.zone_id" :class="[inputClass, 'mt-1 w-full']"><option :value="null">Toutes</option><option v-for="z in options.zones" :key="z.id" :value="z.id">{{ z.name }}</option></select></label>
                    <label class="text-xs text-hsi-slate">Type de projet<select v-model="filters.project_type_id" :class="[inputClass, 'mt-1 w-full']"><option :value="null">Tous</option><option v-for="t in options.projectTypes" :key="t.id" :value="t.id">{{ t.name }}</option></select></label>
                    <label class="text-xs text-hsi-slate">Membre<select v-model="filters.user_id" :class="[inputClass, 'mt-1 w-full']"><option :value="null">Tous</option><option v-for="m in options.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></label>
                    <label class="text-xs text-hsi-slate">Compte<select v-model="filters.fund_account_id" :class="[inputClass, 'mt-1 w-full']"><option :value="null">Tous</option><option v-for="a in options.accounts" :key="a.id" :value="a.id">{{ a.name }}</option></select></label>
                    <label class="text-xs text-hsi-slate">Mouvement<select v-model="filters.movement_type" :class="[inputClass, 'mt-1 w-full']"><option :value="null">Tous</option><option v-for="t in options.movementTypes" :key="t.value" :value="t.value">{{ t.label }}</option></select></label>
                    <div class="col-span-2 flex flex-wrap items-end gap-2 md:col-span-1">
                        <HsiButton size="sm" variant="ghost" @click="resetFilters">Réinitialiser</HsiButton>
                        <HsiButton size="sm" variant="outline" :aria-expanded="showPicker" @click="showPicker = !showPicker"><LayoutGrid class="h-4 w-4" aria-hidden="true" /> Cartes</HsiButton>
                    </div>
                </div>
                <div v-if="showPicker" class="mt-4 flex flex-wrap gap-2 border-t border-hsi-border pt-4">
                    <label v-for="w in widgets" :key="w.key" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-1.5 text-sm" :class="shown(w.key) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate'">
                        <input v-model="visible" type="checkbox" :value="w.key" class="h-4 w-4 rounded"> {{ w.label }}
                    </label>
                </div>
            </HsiCard>

            <!-- Exports -->
            <HsiCard padding="md" class="mb-6">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-xs text-hsi-slate">Relevé
                        <select v-model="period" :class="[inputClass, 'mt-1 block']">
                            <option value="custom">Période choisie</option>
                            <option value="day">Journalier (date de fin)</option>
                            <option value="month">Mensuel (mois de la date de fin)</option>
                            <option value="year">Annuel (année de la date de fin)</option>
                        </select>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="s in options.sections" :key="s.value" class="inline-flex items-center gap-1.5 text-sm text-hsi-navy"><input v-model="exportSections" type="checkbox" :value="s.value" class="h-4 w-4 rounded"> {{ s.label }}</label>
                    </div>
                    <div class="ml-auto flex gap-2">
                        <a :href="exportUrl('xlsx')"><HsiButton variant="outline"><FileSpreadsheet class="h-4 w-4" aria-hidden="true" /> Excel</HsiButton></a>
                        <a :href="exportUrl('pdf')"><HsiButton variant="outline"><FileText class="h-4 w-4" aria-hidden="true" /> PDF</HsiButton></a>
                    </div>
                </div>
            </HsiCard>

            <div v-if="shown('kpis')" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-7">
                <div v-for="card in kpiCards" :key="card.label" class="rounded-2xl border border-hsi-border bg-white p-4">
                    <p class="text-xs text-hsi-slate-light">{{ card.label }}</p>
                    <p class="mt-1 font-heading text-xl font-semibold text-hsi-navy">{{ card.value }}</p>
                    <p v-if="card.hint" class="mt-0.5 text-[11px] text-hsi-slate-light">{{ card.hint }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <HsiCard v-if="shown('flows') && report.flows" padding="lg" class="xl:col-span-2">
                    <HsiSectionTitle title="Flux financiers" />
                    <p class="mb-4 text-sm text-hsi-slate">Entrées par catégorie → comptes du fonds → sorties par catégorie, selon les filtres ci-dessus.</p>
                    <HsiSankey :flow="report.flows" :height="460" />
                </HsiCard>
                <HsiCard v-if="shown('monthly')" padding="lg" class="xl:col-span-2">
                    <HsiSectionTitle title="Évolution mensuelle" />
                    <VueApexCharts type="bar" height="300" :options="monthlyChart.options" :series="monthlyChart.series" />
                </HsiCard>
                <HsiCard v-if="shown('movements')" padding="lg">
                    <HsiSectionTitle title="Mouvements par type" />
                    <VueApexCharts v-if="report.movements_by_type.length" type="donut" height="280" :options="movementsChart.options" :series="movementsChart.series" />
                    <p v-else class="text-sm text-hsi-slate-light">Aucun mouvement sur la période.</p>
                </HsiCard>
                <HsiCard v-if="shown('projects')" padding="lg">
                    <HsiSectionTitle title="Projets par type et statut" />
                    <template v-if="report.projects.by_type.length">
                        <VueApexCharts type="donut" height="220" :options="typesChart.options" :series="typesChart.series" />
                        <VueApexCharts type="bar" height="200" :options="statusChart.options" :series="statusChart.series" />
                    </template>
                    <p v-else class="text-sm text-hsi-slate-light">Aucun projet sur la période.</p>
                </HsiCard>
                <HsiCard v-if="shown('accounts')" padding="lg">
                    <HsiSectionTitle title="Soldes des comptes" />
                    <ul class="space-y-2 text-sm">
                        <li v-for="a in report.accounts" :key="a.id" class="flex justify-between rounded-lg bg-hsi-muted/50 px-3 py-2"><span>{{ a.name }}</span><strong>{{ money(a.balance) }}</strong></li>
                    </ul>
                </HsiCard>
                <HsiCard v-if="shown('loans')" padding="lg" class="xl:col-span-2">
                    <HsiSectionTitle title="Prêts et remboursements" />
                    <HsiListToolbar v-model:search="loanList.search.value" placeholder="Projet ou emprunteur…" :active="loanList.active.value" :count="loanList.total.value" label="prêt(s)" @reset="loanList.reset()">
                        <select v-model="loanList.filters.status" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm"><option value="">Tous les états</option><option v-for="st in uniq(report.loans, 'status')" :key="st" :value="st">{{ st }}</option></select>
                    </HsiListToolbar>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">Projet</th><th>Emprunteur</th><th>Versé le</th><th class="text-right">Montant</th><th class="text-right">Remboursé</th><th class="text-right">Reste dû</th><th class="text-right">Pénalités</th><th>Prochaine échéance</th><th>État</th></tr></thead>
                            <tbody>
                                <tr v-for="l in loanList.paged.value" :key="l.id" class="border-b border-hsi-border/50">
                                    <td class="py-2"><Link :href="route('projects.show', l.id)" class="font-medium text-hsi-navy hover:underline">{{ l.title }}</Link></td>
                                    <td>{{ l.borrower }}</td><td>{{ l.funded_at }}</td><td class="text-right">{{ money(l.amount) }}</td><td class="text-right">{{ money(l.paid) }}</td>
                                    <td class="text-right font-semibold">{{ money(l.remaining) }}</td><td class="text-right">{{ money(l.penalties) }}</td><td>{{ l.next_due || '—' }}</td>
                                    <td><HsiBadge :variant="l.status === 'En retard' ? 'danger' : (l.status === 'Soldé' ? 'success' : 'info')">{{ l.status }}</HsiBadge></td>
                                </tr>
                                <tr v-if="!loanList.total.value"><td colspan="9" class="py-4 text-center text-hsi-slate-light">Aucun prêt.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <HsiPagination v-model:page="loanList.page.value" v-model:per-page="loanList.perPage.value" :total="loanList.total.value" label="prêts" compact />
                </HsiCard>
                <HsiCard v-if="shown('members')" padding="lg" class="xl:col-span-2">
                    <HsiSectionTitle title="Registre des membres" />
                    <HsiListToolbar v-model:search="memberList.search.value" placeholder="Nom du membre…" :active="memberList.active.value" :count="memberList.total.value" label="membre(s)" @reset="memberList.reset()">
                        <select v-model="memberList.filters.zone" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm"><option value="">Toutes les zones</option><option v-for="z in uniq(report.members, 'zone')" :key="z" :value="z">{{ z }}</option></select>
                        <select v-model="memberList.filters.penalties" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm"><option value="">Avec ou sans pénalités</option><option value="yes">Pénalités dues</option><option value="no">À jour</option></select>
                    </HsiListToolbar>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">Membre</th><th>Zone</th><th class="text-right">Cotisations</th><th class="text-right">Versements</th><th>Dernière</th><th class="text-right">Part du fonds</th><th class="text-right">Prêt restant</th><th class="text-right">Pénalités dues</th></tr></thead>
                            <tbody>
                                <tr v-for="m in memberList.paged.value" :key="m.id" class="border-b border-hsi-border/50">
                                    <td class="py-2 font-medium text-hsi-navy">{{ m.name }}</td><td>{{ m.zone }}</td><td class="text-right">{{ money(m.contributions) }}</td><td class="text-right">{{ m.contributions_count }}</td>
                                    <td>{{ m.last_contribution || '—' }}</td><td class="text-right font-semibold">{{ m.share_percent }} %</td><td class="text-right">{{ money(m.loans_outstanding) }}</td>
                                    <td class="text-right" :class="m.penalties > 0 ? 'text-hsi-danger' : ''">{{ money(m.penalties) }}</td>
                                </tr>
                                <tr v-if="!memberList.total.value"><td colspan="8" class="py-4 text-center text-hsi-slate-light">Aucun membre ne correspond.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <HsiPagination v-model:page="memberList.page.value" v-model:per-page="memberList.perPage.value" :total="memberList.total.value" label="membres" compact />
                </HsiCard>
                <HsiCard v-if="shown('statement')" padding="lg" class="xl:col-span-2">
                    <HsiSectionTitle :title="`${$tr('Relevé de compte')} — ${report.statement.account}`" />
                    <p class="mb-3 text-sm text-hsi-slate">Solde d’ouverture {{ money(report.statement.opening) }} · crédits {{ money(report.statement.credits) }} · débits {{ money(report.statement.debits) }} · solde de clôture <strong class="text-hsi-navy">{{ money(report.statement.closing) }}</strong></p>
                    <HsiListToolbar v-model:search="statementList.search.value" placeholder="Libellé, référence, date…" :active="statementList.active.value" :count="statementList.total.value" label="ligne(s)" @reset="statementList.reset()">
                        <select v-model="statementList.filters.account" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm"><option value="">Tous les comptes</option><option v-for="a in uniq(report.statement.rows, 'account')" :key="a" :value="a">{{ a }}</option></select>
                        <select v-model="statementList.filters.type" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm"><option value="">Tous les types</option><option v-for="t in uniq(report.statement.rows, 'type')" :key="t" :value="t">{{ t }}</option></select>
                        <select v-model="statementList.filters.direction" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm"><option value="">Crédits et débits</option><option value="credit">Crédits</option><option value="debit">Débits</option></select>
                    </HsiListToolbar>
                    <div class="max-h-[560px] overflow-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="sticky top-0 bg-white"><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">Date</th><th>Compte</th><th>Type</th><th>Libellé</th><th class="text-right">Crédit</th><th class="text-right">Débit</th><th class="text-right">Solde</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in statementList.paged.value" :key="i" class="border-b border-hsi-border/50">
                                    <td class="py-1.5">{{ r.date }}</td><td>{{ r.account }}</td><td>{{ r.type }}</td><td translate="no">{{ r.label }}</td>
                                    <td class="text-right text-hsi-success">{{ r.credit ? money(r.credit) : '' }}</td><td class="text-right text-hsi-danger">{{ r.debit ? money(r.debit) : '' }}</td><td class="text-right font-medium">{{ money(r.balance) }}</td>
                                </tr>
                                <tr v-if="!statementList.total.value"><td colspan="7" class="py-4 text-center text-hsi-slate-light">Aucun mouvement sur la période.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <HsiPagination v-model:page="statementList.page.value" v-model:per-page="statementList.perPage.value" :total="statementList.total.value" :per-page-options="[25, 50, 100, 250]" label="lignes" compact />
                </HsiCard>
            </div>
        </template>

        <!-- Rapports partagés : visibles par tous les membres -->
        <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <HsiCard padding="lg">
                <HsiSectionTitle title="Rapports partagés avec les membres" />
                <HsiListToolbar v-if="shared.length > 5" v-model:search="sharedList.search.value" placeholder="Rechercher un rapport…" :active="sharedList.active.value" @reset="sharedList.reset()" />
                <ul class="space-y-2">
                    <li v-for="r in sharedList.paged.value" :key="r.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-hsi-border p-3">
                        <div><Link :href="route('reports.shared', r.id)" class="font-medium text-hsi-navy hover:underline">{{ r.title }}</Link><p class="text-xs text-hsi-slate-light">{{ r.period }} · partagé le {{ r.shared_at }}</p></div>
                        <a :href="route('reports.shared.pdf', r.id)" class="text-sm font-semibold text-[#1D4ED8] hover:underline">PDF</a>
                    </li>
                    <li v-if="!shared.length" class="text-sm text-hsi-slate-light">Aucun rapport partagé pour l’instant.</li>
                </ul>
                <HsiPagination v-model:page="sharedList.page.value" v-model:per-page="sharedList.perPage.value" :total="sharedList.total.value" :per-page-options="[5, 10, 25]" label="rapports" compact />
                <form v-if="can.manage" class="mt-5 space-y-3 border-t border-hsi-border pt-4" @submit.prevent="share">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-hsi-navy"><Share2 class="h-4 w-4" aria-hidden="true" /> Partager un rapport (ex. trimestriel)</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="shareForm.title" required maxlength="180" :class="inputClass" placeholder="Titre, ex. Rapport du 1er trimestre 2027">
                        <select v-model="shareForm.kind" :class="inputClass"><option value="quarterly">Trimestriel</option><option value="monthly">Mensuel</option><option value="annual">Annuel</option><option value="custom">Autre</option></select>
                        <input v-model="shareForm.from" type="date" required :class="inputClass">
                        <input v-model="shareForm.to" type="date" required :class="inputClass">
                    </div>
                    <textarea v-model="shareForm.comment" rows="2" maxlength="5000" :class="[inputClass, 'w-full']" placeholder="Commentaire des trésoriers (facultatif)" />
                    <p v-if="shareForm.errors.title || shareForm.errors.to" class="text-sm text-hsi-danger">{{ shareForm.errors.title || shareForm.errors.to }}</p>
                    <HsiButton type="submit" :disabled="shareForm.processing">Partager avec tous les membres</HsiButton>
                </form>
            </HsiCard>

            <HsiCard padding="lg">
                <HsiSectionTitle title="Vérification annuelle des comptes" />
                <p class="mb-3 text-sm text-hsi-slate">Avant fin février, 1 ou 2 membres (autres que les trésoriers), choisis par vote, examinent les registres de l’année précédente et confirment par écrit.</p>
                <ul class="space-y-3">
                    <li v-for="a in auditList.paged.value" :key="a.id" class="rounded-xl border border-hsi-border p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium text-hsi-navy"><ShieldCheck class="mr-1 inline h-4 w-4" aria-hidden="true" />Comptes {{ a.year }} — {{ a.reviewers.join(', ') }}</p>
                            <HsiBadge :variant="a.status === 'confirmed' ? 'success' : (a.status === 'issues' ? 'danger' : 'warning')">{{ a.status_label }}</HsiBadge>
                        </div>
                        <p v-if="a.conclusion" class="mt-2 whitespace-pre-wrap text-sm text-hsi-slate">« {{ a.conclusion }} » — {{ a.confirmed_by }}, {{ a.confirmed_at }}</p>
                        <form v-if="a.can_confirm" class="mt-3 space-y-2" @submit.prevent="confirmAudit(a)">
                            <div class="flex flex-wrap gap-3 text-sm">
                                <label class="inline-flex items-center gap-2"><input v-model="confirmForm.status" type="radio" value="confirmed"> Les comptes sont exacts et complets</label>
                                <label class="inline-flex items-center gap-2"><input v-model="confirmForm.status" type="radio" value="issues"> J’ai relevé des anomalies</label>
                            </div>
                            <textarea v-model="confirmForm.conclusion" rows="3" required minlength="10" :class="[inputClass, 'w-full']" placeholder="Votre conclusion écrite" />
                            <p v-if="confirmForm.errors.conclusion" class="text-sm text-hsi-danger">{{ confirmForm.errors.conclusion }}</p>
                            <HsiButton type="submit" size="sm" :disabled="confirmForm.processing">Envoyer ma conclusion</HsiButton>
                        </form>
                    </li>
                    <li v-if="!audits.length" class="text-sm text-hsi-slate-light">Aucune vérification pour l’instant.</li>
                </ul>
                <HsiPagination v-model:page="auditList.page.value" v-model:per-page="auditList.perPage.value" :total="auditList.total.value" :per-page-options="[5, 10, 25]" label="vérifications" compact />
                <form v-if="can.manage" class="mt-5 space-y-3 border-t border-hsi-border pt-4" @submit.prevent="createAudit">
                    <h3 class="text-sm font-semibold text-hsi-navy">Ouvrir une vérification (après le vote du groupe)</h3>
                    <input v-model="auditForm.year" type="number" min="2020" max="2100" required :class="inputClass">
                    <div class="flex flex-wrap gap-2">
                        <label v-for="m in auditCandidates" :key="m.id" class="inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-sm" :class="auditForm.reviewer_ids.includes(m.id) ? 'border-hsi-gold bg-hsi-gold/10' : 'border-hsi-border'">
                            <input v-model="auditForm.reviewer_ids" type="checkbox" :value="m.id" :disabled="!auditForm.reviewer_ids.includes(m.id) && auditForm.reviewer_ids.length >= 2" class="h-4 w-4 rounded"> {{ m.name }}
                        </label>
                    </div>
                    <p v-if="auditForm.errors.reviewer_ids || auditForm.errors.year" class="text-sm text-hsi-danger">{{ auditForm.errors.reviewer_ids || auditForm.errors.year }}</p>
                    <HsiButton type="submit" size="sm" :disabled="auditForm.processing || !auditForm.reviewer_ids.length">Ouvrir la vérification</HsiButton>
                </form>
            </HsiCard>
        </div>
    </AuthenticatedLayout>
</template>
