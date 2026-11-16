<script setup>
import { tr } from '@/i18n';
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Wallet, CheckCircle, Clock, XCircle, AlertTriangle, Plus, Settings, Eye, Pencil, Trash2, Check, Ban } from 'lucide-vue-next';
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
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiViewToggle from '@/Components/Hsi/HsiViewToggle.vue';
import { useListTools, useViewMode } from '@/Composables/useListTools';
import { formatMoney } from '@/currency';

const props = defineProps({
    payments: Object,
    filters: Object,
    stats: Array,
    countries: Array,
    branches: Array,
    statuses: Array,
    paymentMethods: Array,
    years: Array,
    can: Object,
    dues: Array,
    register: { type: Object, default: null },
    rules: { type: Object, default: () => ({}) },
    zones: { type: Array, default: () => [] },
});

const statIcons = [Wallet, CheckCircle, Clock, XCircle, AlertTriangle];
const view = useViewMode('contributions');
const money = (v) => formatMoney(v, { decimals: 2 });

// Registre trimestriel
const quarterMeta = {
    paid: { label: 'Payé', variant: 'success', chip: 'border-green-200 bg-green-50 text-hsi-success', card: 'border-green-200 bg-green-50/50' },
    paid_late: { label: 'Payé en retard', variant: 'warning', chip: 'border-amber-200 bg-amber-50 text-hsi-warning', card: 'border-amber-200 bg-amber-50/50' },
    submitted: { label: 'À confirmer', variant: 'info', chip: 'border-blue-200 bg-blue-50 text-hsi-info', card: 'border-blue-200 bg-blue-50/50' },
    due: { label: 'À payer', variant: 'neutral', chip: 'border-hsi-border bg-white text-hsi-navy', card: 'border-hsi-border' },
    late: { label: 'En retard', variant: 'danger', chip: 'border-red-200 bg-red-50 text-hsi-danger', card: 'border-red-200 bg-red-50/50' },
    waived: { label: 'Exonéré', variant: 'neutral', chip: 'border-hsi-border bg-hsi-muted text-hsi-slate', card: 'border-hsi-border bg-hsi-muted/50' },
    upcoming: { label: 'À venir', variant: 'neutral', chip: 'border-dashed border-hsi-border text-hsi-slate-light', card: 'border-dashed border-hsi-border' },
    none: { label: '—', variant: 'neutral', chip: 'border-hsi-border text-hsi-slate-light', card: 'border-hsi-border' },
};
const registerList = useListTools(() => props.register?.rows ?? [], {
    searchKeys: ['name', 'zone'],
    filters: {
        zone: (r, v) => r.zone === v,
        state: (r, v) => (v === 'late' ? r.late_count > 0 : v === 'penalty' ? !!r.penalties : r.late_count === 0 && !r.penalties),
    },
    perPage: 10,
});
const registerZones = computed(() => [...new Set((props.register?.rows ?? []).map((r) => r.zone).filter(Boolean))].sort());
const changeRegisterYear = (year) => router.get(route('contributions.index'), { ...props.filters, register_year: year }, { only: ['register'], preserveState: true, preserveScroll: true, replace: true });
const dueList = useListTools(() => props.dues ?? [], {
    searchKeys: ['member', 'plan', 'period'],
    filters: {
        status: (due, value) => due.status === value,
        penalty: (due, value) => (value === 'yes' ? !!(due.penalty || due.carried_penalty) : !due.penalty && !due.carried_penalty),
    },
    perPage: 10,
});
const dueStatuses = computed(() => [...new Map((props.dues ?? []).map((d) => [d.status, { value: d.status, label: d.status_label }])).values()]);

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const paymentMethod = ref(props.filters.payment_method ?? '');
const paymentMonth = ref(props.filters.payment_month ?? '');
const paymentYear = ref(props.filters.payment_year ?? '');
const countryId = ref(props.filters.country_id ?? '');
const branchId = ref(props.filters.family_branch_id ?? '');
const zoneId = ref(props.filters.zone_id ?? '');
const perPage = ref(props.filters.per_page ?? 10);

const months = [
    { value: 1, label: 'Janvier' }, { value: 2, label: 'Février' }, { value: 3, label: 'Mars' },
    { value: 4, label: 'Avril' }, { value: 5, label: 'Mai' }, { value: 6, label: 'Juin' },
    { value: 7, label: 'Juillet' }, { value: 8, label: 'Août' }, { value: 9, label: 'Septembre' },
    { value: 10, label: 'Octobre' }, { value: 11, label: 'Novembre' }, { value: 12, label: 'Décembre' },
];

let debounceTimer = null;

const applyFilters = () => {
    router.get(route('contributions.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        payment_method: paymentMethod.value || undefined,
        payment_month: paymentMonth.value || undefined,
        payment_year: paymentYear.value || undefined,
        country_id: countryId.value || undefined,
        family_branch_id: branchId.value || undefined,
        zone_id: zoneId.value || undefined,
        per_page: perPage.value || undefined,
    }, { preserveState: true, replace: true });
};

watch([status, paymentMethod, paymentMonth, paymentYear, countryId, branchId, zoneId, perPage], applyFilters);

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 350);
});

const resetFilters = () => {
    search.value = '';
    status.value = '';
    paymentMethod.value = '';
    paymentMonth.value = '';
    paymentYear.value = '';
    countryId.value = '';
    branchId.value = '';
    zoneId.value = '';
    perPage.value = 10;
    applyFilters();
};

const validatePayment = (id) => {
    if (confirm(tr('Valider cette cotisation ?'))) {
        router.patch(route('contributions.validate', id));
    }
};

const rejectPayment = (id) => {
    const reason = prompt(tr('Motif du refus (min. 5 caractères) :'));
    if (reason && reason.length >= 5) {
        router.patch(route('contributions.reject', id), { rejection_reason: reason });
    }
};

const destroyPayment = (id) => {
    if (confirm(tr('Supprimer définitivement cette cotisation ?'))) {
        router.delete(route('contributions.destroy', id));
    }
};

const canEditRow = (row) => {
    if (row.status === 'validated') return false;
    if (props.can.edit) return true;
    return props.can.create && ['pending', 'rejected', 'late'].includes(row.status);
};

const isActionable = (row) => ['pending', 'late'].includes(row.status);

const processDues = () => router.post(route('contributions.process-dues'));
const waivePenalty = (due) => {
    const reason = prompt(tr('Justificatif d’erreur bancaire ou d’échec de virement :'));
    if (reason?.length >= 5) router.patch(route('contributions.dues.waive-penalty', due.id), { reason });
};
const waiveDue = (due) => {
    const reason = prompt(tr('Motif de l’exonération :'));
    if (reason?.length >= 5) router.patch(route('contributions.dues.waive', due.id), { reason });
};
</script>

<template>
    <Head title="Cotisations" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Cotisations"
            subtitle="Suivi des contributions des membres HSI Family Office"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.managePlans"
                        :href="route('contribution-plans.index')"
                    >
                        <HsiButton variant="outline">
                            <Settings class="h-4 w-4" />
                            Plans de cotisation
                        </HsiButton>
                    </Link>
                    <Link
                        v-if="can.create"
                        :href="route('contributions.create')"
                    >
                        <HsiButton>
                            <Plus class="h-4 w-4" />
                            Soumettre une cotisation
                        </HsiButton>
                    </Link>
                </div>
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

        <!-- Registre trimestriel (section 2.2) / ma situation -->
        <HsiCard v-if="register" padding="md" class="mb-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-hsi-navy">{{ register.own_only ? 'Ma situation' : 'Registre trimestriel' }} {{ register.year }}</h2>
                    <p class="text-xs text-hsi-slate-light">Contribution de {{ money(rules.quarterly_amount) }} par trimestre, à verser avant le {{ rules.due_day }} du premier mois.</p>
                </div>
                <select :value="register.year" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm" :aria-label="$tr('Année')" @change="changeRegisterYear($event.target.value)">
                    <option v-for="y in register.years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <div v-if="register.own_only && register.rows.length" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div v-for="q in register.rows[0].quarters" :key="q.quarter" class="rounded-2xl border p-4" :class="quarterMeta[q.state].card">
                    <div class="flex items-center justify-between"><span class="font-heading text-xl font-semibold text-hsi-navy">T{{ q.quarter }}</span><HsiBadge :variant="quarterMeta[q.state].variant" size="xs">{{ quarterMeta[q.state].label }}</HsiBadge></div>
                    <p class="mt-2 text-xs text-hsi-slate">Avant le {{ q.due_date }}</p>
                    <p v-if="q.paid_at" class="text-xs text-hsi-slate">Payé le {{ q.paid_at }}</p>
                    <p v-if="q.penalty" class="text-xs font-semibold text-hsi-danger">Pénalité : {{ q.penalty }}</p>
                </div>
            </div>

            <template v-else>
                <HsiListToolbar v-model:search="registerList.search.value" placeholder="Nom du membre…" :active="registerList.active.value" :count="registerList.total.value" label="membre(s)" @reset="registerList.reset()">
                    <select v-model="registerList.filters.zone" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Toutes les zones</option><option v-for="z in registerZones" :key="z" :value="z">{{ z }}</option></select>
                    <select v-model="registerList.filters.state" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Tous</option><option value="late">Avec un retard</option><option value="penalty">Avec pénalités dues</option><option value="ok">À jour</option></select>
                </HsiListToolbar>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead><tr class="border-b border-hsi-border text-left text-[11px] uppercase tracking-wider text-hsi-slate-light"><th class="py-2.5">Membre</th><th>Zone</th><th v-for="q in 4" :key="q" class="text-center">T{{ q }}</th><th class="text-right">Payé</th><th class="text-right">Pénalités dues</th></tr></thead>
                        <tbody>
                            <tr v-for="row in registerList.paged.value" :key="row.id" class="border-b border-hsi-border/50">
                                <td class="py-2.5 font-medium text-hsi-navy">{{ row.name }}</td>
                                <td class="text-hsi-slate">{{ row.zone || '—' }}</td>
                                <td v-for="q in row.quarters" :key="q.quarter" class="px-1 text-center">
                                    <span class="inline-flex min-w-[5.5rem] justify-center rounded-full border px-2 py-1 text-[11px] font-semibold" :class="quarterMeta[q.state].chip" :title="[q.amount, q.paid_at ? `payé le ${q.paid_at}` : `avant le ${q.due_date}`, q.penalty ? `pénalité ${q.penalty}` : ''].filter(Boolean).join(' · ')">{{ quarterMeta[q.state].label }}</span>
                                </td>
                                <td class="text-right font-semibold">{{ row.paid_total }}</td>
                                <td class="text-right" :class="row.penalties ? 'font-semibold text-hsi-danger' : 'text-hsi-slate-light'">{{ row.penalties || '—' }}</td>
                            </tr>
                            <tr v-if="!registerList.total.value"><td colspan="8" class="py-4 text-center text-hsi-slate-light">Aucun membre ne correspond.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-hsi-slate">
                    <span v-for="(meta, key) in quarterMeta" :key="key" class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border" :class="meta.chip" />{{ meta.label }}</span>
                </div>
                <HsiPagination v-model:page="registerList.page.value" v-model:per-page="registerList.perPage.value" :total="registerList.total.value" label="membres" compact />
            </template>
        </HsiCard>

        <HsiCard v-if="dues?.length || can.managePlans" padding="md" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="font-semibold text-hsi-navy">Échéances à traiter</h2>
                    <p class="text-xs text-hsi-slate-light">Échéances générées automatiquement et retards en cours</p>
                </div>
                <HsiButton v-if="can.managePlans" variant="outline" @click="processDues">
                    Actualiser les échéances
                </HsiButton>
            </div>
            <template v-if="dues?.length">
                <HsiListToolbar v-model:search="dueList.search.value" placeholder="Membre ou plan…" :active="dueList.active.value" :count="dueList.total.value" label="échéance(s)" @reset="dueList.reset()">
                    <select v-model="dueList.filters.status" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Tous les statuts</option><option v-for="st in dueStatuses" :key="st.value" :value="st.value">{{ st.label }}</option></select>
                    <select v-model="dueList.filters.penalty" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Avec ou sans pénalité</option><option value="yes">Avec pénalité</option><option value="no">Sans pénalité</option></select>
                </HsiListToolbar>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">Membre</th><th>Plan / période</th><th>Échéance</th><th>Montant</th><th>Pénalité</th><th>Total dû</th><th>Statut</th><th /></tr></thead>
                        <tbody>
                            <tr v-for="due in dueList.paged.value" :key="due.id" class="border-b border-hsi-border/50">
                                <td class="py-2">{{ due.member }}</td><td>{{ due.plan }} — {{ due.period }}</td><td>{{ due.due_date }}</td><td>{{ due.amount }}</td>
                                <td><span v-if="due.penalty" :class="due.penalty_waived ? 'line-through text-hsi-slate-light' : 'text-hsi-danger'">{{ due.penalty }}</span><span v-if="due.carried_penalty" class="block text-[11px] text-hsi-slate-light">+ {{ due.carried_penalty }} reportée</span><span v-if="!due.penalty && !due.carried_penalty">—</span></td>
                                <td class="font-medium">{{ due.total }}</td>
                                <td><HsiBadge :variant="due.status === 'late' ? 'danger' : 'warning'">{{ due.status_label }}</HsiBadge></td>
                                <td class="text-right"><button v-if="can.managePlans && due.penalty && !due.penalty_waived" class="mr-2 text-xs text-hsi-gold hover:underline" @click="waivePenalty(due)">Annuler la pénalité</button><button v-if="can.managePlans" class="text-xs text-hsi-gold hover:underline" @click="waiveDue(due)">Exonérer</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!dueList.total.value" class="py-4 text-center text-sm text-hsi-slate-light">Aucune échéance ne correspond à ces filtres.</p>
                <HsiPagination v-model:page="dueList.page.value" v-model:per-page="dueList.perPage.value" :total="dueList.total.value" label="échéances" compact />
            </template>
            <p v-else class="text-sm text-hsi-slate-light">Aucune échéance impayée.</p>
        </HsiCard>

        <HsiCard padding="md">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 mb-6">
                <div class="xl:col-span-2">
                    <HsiSearchInput
                        v-model="search"
                        placeholder="Rechercher un membre..."
                    />
                </div>
                <select
                    v-model="status"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy"
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
                    v-model="paymentMethod"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy"
                >
                    <option value="">Toutes les méthodes</option>
                    <option
                        v-for="m in paymentMethods"
                        :key="m.value"
                        :value="m.value"
                    >
                        {{ m.label }}
                    </option>
                </select>
                <select
                    v-model="paymentMonth"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy"
                >
                    <option value="">Tous les mois</option>
                    <option
                        v-for="m in months"
                        :key="m.value"
                        :value="m.value"
                    >
                        {{ m.label }}
                    </option>
                </select>
                <select
                    v-model="paymentYear"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy"
                >
                    <option value="">Toutes les années</option>
                    <option
                        v-for="y in years"
                        :key="y"
                        :value="y"
                    >
                        {{ y }}
                    </option>
                </select>
                <select
                    v-model="countryId"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy"
                >
                    <option value="">Tous les pays</option>
                    <option
                        v-for="c in countries"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.flag_emoji }} {{ c.name }}
                    </option>
                </select>
                <select v-if="zones.length" v-model="zoneId" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy">
                    <option value="">Toutes les zones</option>
                    <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                </select>
                <select
                    v-model="branchId"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy"
                >
                    <option value="">Toutes les branches</option>
                    <option
                        v-for="b in branches"
                        :key="b.id"
                        :value="b.id"
                    >
                        {{ b.name }}
                    </option>
                </select>
            </div>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-hsi-slate">{{ payments.total }} cotisation(s)</p>
                <div class="flex items-center gap-3">
                    <button type="button" class="text-xs text-hsi-slate-light transition-colors hover:text-hsi-gold" @click="resetFilters">Réinitialiser les filtres</button>
                    <HsiViewToggle v-model="view" />
                </div>
            </div>

            <HsiEmptyState
                v-if="!payments.data.length"
                title="Aucune cotisation trouvée"
                description="Modifiez vos filtres ou soumettez une nouvelle cotisation."
                :icon="Wallet"
            />

            <div v-else-if="view === 'cards'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="row in payments.data" :key="row.id" class="flex flex-col rounded-2xl border border-hsi-border bg-white p-4 transition hover:border-hsi-gold/50 hover:shadow-hsiSoft">
                    <div class="flex items-start gap-3">
                        <HsiAvatar :name="row.member.name" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-hsi-navy">{{ row.member.name }}</p>
                            <p class="text-xs text-hsi-slate-light">{{ row.period }}</p>
                        </div>
                        <HsiBadge :variant="row.status_variant" size="xs">{{ row.status_label }}</HsiBadge>
                    </div>
                    <p class="mt-4 font-heading text-2xl font-semibold text-hsi-navy">{{ row.amount_formatted }}</p>
                    <p v-if="row.confirmation_deadline" class="mt-1 text-xs font-semibold" :class="row.confirmation_overdue ? 'text-hsi-danger' : 'text-hsi-warning'">{{ row.confirmation_overdue ? $tr('Confirmation en retard (prévue le {date})', { date: row.confirmation_deadline }) : $tr('À confirmer avant le {date}', { date: row.confirmation_deadline }) }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                        <dt class="text-hsi-slate-light">Méthode</dt><dd class="truncate text-right text-hsi-navy">{{ row.payment_method_label || '—' }}</dd>
                        <dt class="text-hsi-slate-light">Référence</dt><dd class="truncate text-right text-hsi-navy">{{ row.reference || '—' }}</dd>
                        <dt class="text-hsi-slate-light">Soumise le</dt><dd class="text-right text-hsi-navy">{{ row.submitted_at ?? '—' }}</dd>
                        <dt class="text-hsi-slate-light">Validée par</dt><dd class="truncate text-right text-hsi-navy">{{ row.validated_by || '—' }}</dd>
                    </dl>
                    <div class="mt-auto flex items-center justify-end gap-1 border-t border-hsi-border/70 pt-3" :class="'mt-4'">
                        <Link :href="route('contributions.show', row.id)" class="flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-hsi-navy hover:bg-hsi-muted"><Eye class="h-4 w-4" /> Voir</Link>
                        <Link v-if="canEditRow(row)" :href="route('contributions.edit', row.id)" class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted" :title="$tr('Modifier')"><Pencil class="h-4 w-4" /></Link>
                        <button v-if="can.validate && isActionable(row)" type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-success hover:bg-green-50" :title="$tr('Valider')" @click="validatePayment(row.id)"><Check class="h-4 w-4" /></button>
                        <button v-if="can.reject && isActionable(row)" type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-danger hover:bg-red-50" :title="$tr('Refuser')" @click="rejectPayment(row.id)"><Ban class="h-4 w-4" /></button>
                        <button v-if="can.delete" type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-danger hover:bg-red-50" :title="$tr('Supprimer')" @click="destroyPayment(row.id)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </article>
            </div>

            <HsiDataTable
                v-else
                :columns="[
                    { key: 'member', label: 'Membre' },
                    { key: 'amount', label: 'Montant', align: 'right' },
                    { key: 'period', label: 'Période' },
                    { key: 'method', label: 'Méthode' },
                    { key: 'reference', label: 'Référence' },
                    { key: 'status', label: 'Statut', align: 'center' },
                    { key: 'submitted_at', label: 'Date soumission' },
                    { key: 'validated_by', label: 'Validé par' },
                ]"
                compact
            >
                <template #body>
                    <tr
                        v-for="row in payments.data"
                        :key="row.id"
                        class="border-b border-hsi-border/60 last:border-0 hover:bg-hsi-muted/40 transition-colors"
                    >
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <HsiAvatar
                                    :name="row.member.name"
                                    size="sm"
                                />
                                <span class="text-sm font-medium">{{ row.member.name }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-right font-semibold">{{ row.amount_formatted }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.period }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.payment_method_label }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.reference }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <HsiBadge :variant="row.status_variant">{{ row.status_label }}</HsiBadge>
                        </td>
                        <td class="px-3 py-2.5 text-sm">{{ row.submitted_at ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.validated_by }}</td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    :href="route('contributions.show', row.id)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted transition-colors"
                                    title="Voir"
                                >
                                    <Eye class="h-4 w-4" />
                                </Link>
                                <Link
                                    v-if="canEditRow(row)"
                                    :href="route('contributions.edit', row.id)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted transition-colors"
                                    title="Modifier"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                                <button
                                    v-if="can.validate && isActionable(row)"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-success hover:bg-green-50 transition-colors"
                                    title="Valider"
                                    @click="validatePayment(row.id)"
                                >
                                    <Check class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="can.reject && isActionable(row)"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-danger hover:bg-red-50 transition-colors"
                                    title="Refuser"
                                    @click="rejectPayment(row.id)"
                                >
                                    <Ban class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="can.delete"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-danger hover:bg-red-50 transition-colors"
                                    title="Supprimer"
                                    @click="destroyPayment(row.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </HsiDataTable>

            <HsiPagination :paginator="payments" :per-page-options="[12, 24, 48]" label="cotisations" @update:per-page="perPage = $event" />
        </HsiCard>
    </AuthenticatedLayout>
</template>
