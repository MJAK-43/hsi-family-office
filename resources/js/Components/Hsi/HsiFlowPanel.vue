<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { formatMoney } from '@/currency';
import HsiSankey from '@/Components/Hsi/HsiSankey.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import { useListTools } from '@/Composables/useListTools';

/**
 * Flux financiers : choix de la période (facultatif), diagramme, détail par catégorie et,
 * pour un compte de fonds, les projets qu'il finance (filtrables : à financer, en cours, terminés).
 * La période est transmise au serveur (flow_period, flow_from, flow_to) ; seules les props listées sont rechargées.
 */
const props = defineProps({
    flow: { type: Object, required: true },
    preset: { type: String, default: 'all' },
    withPeriod: { type: Boolean, default: true },
    withBreakdown: { type: Boolean, default: true },
    height: { type: Number, default: 480 },
    reloadOnly: { type: Array, default: () => ['flow', 'flowPeriod'] },
});

const presets = [
    { value: 'all', label: 'Depuis le début' }, { value: '12m', label: '12 derniers mois' },
    { value: 'quarter', label: 'Trimestre en cours' }, { value: 'year', label: 'Année en cours' },
    { value: 'last_year', label: 'Année précédente' }, { value: 'custom', label: 'Période personnalisée' },
];
const period = ref(props.preset);
const from = ref(props.flow.period?.from_raw ?? '');
const to = ref(props.flow.period?.to_raw ?? '');
const loading = ref(false);

const reload = () => {
    const params = new URLSearchParams(window.location.search);
    params.set('flow_period', period.value);
    if (period.value === 'custom') { params.set('flow_from', from.value); params.set('flow_to', to.value); } else { params.delete('flow_from'); params.delete('flow_to'); }
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
        only: props.reloadOnly, preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; },
    });
};
const choosePreset = () => { if (period.value !== 'custom') reload(); };
const money = (v) => formatMoney(v, { decimals: 0 });
const share = (value, total) => (total > 0 ? Math.round((value / total) * 100) : 0);

// Projets du compte : filtre par étape, appliqué au diagramme et à la liste.
const stages = [
    { value: '', label: 'Tous les projets' }, { value: 'pending', label: 'À financer' },
    { value: 'in_progress', label: 'Financés, en cours' }, { value: 'completed', label: 'Terminés' },
];
const stageMeta = {
    pending: { label: 'À financer', variant: 'warning' },
    in_progress: { label: 'En cours', variant: 'info' },
    completed: { label: 'Terminé', variant: 'success' },
};
const projects = computed(() => props.flow.projects ?? []);
const hasProjects = computed(() => projects.value.length > 0);
const stage = ref('');
const stageCount = (value) => (value ? projects.value.filter((p) => p.stage === value).length : projects.value.length);
const visibleFlow = computed(() => {
    if (!stage.value) return props.flow;
    const hidden = new Set(props.flow.nodes.filter((n) => n.stage && n.stage !== stage.value).map((n) => n.id));
    const links = props.flow.links.filter((l) => !hidden.has(l.target));
    const used = new Set(links.flatMap((l) => [l.source, l.target]));
    return { ...props.flow, links, nodes: props.flow.nodes.filter((n) => used.has(n.id)) };
});
const projectList = useListTools(() => projects.value, {
    searchKeys: ['title', 'owner', 'type'],
    filters: { stage: (p, v) => p.stage === v },
    perPage: 5,
});
const setStage = (value) => { stage.value = value; projectList.filters.stage = value; };

const inBreakdown = computed(() => props.flow.breakdown?.in ?? []);
const outBreakdown = computed(() => props.flow.breakdown?.out ?? []);
const selectClass = 'rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm text-hsi-navy';
</script>

<template>
    <div :class="loading ? 'opacity-60 transition' : 'transition'">
        <div v-if="withPeriod" class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <select v-model="period" :class="selectClass" :aria-label="$tr('Période')" @change="choosePreset">
                <option v-for="p in presets" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <template v-if="period === 'custom'">
                <label class="inline-flex items-center gap-1.5 text-xs text-hsi-slate">Du <input v-model="from" type="date" class="rounded-xl border border-hsi-border px-2 py-1.5 text-sm"></label>
                <label class="inline-flex items-center gap-1.5 text-xs text-hsi-slate">au <input v-model="to" type="date" class="rounded-xl border border-hsi-border px-2 py-1.5 text-sm"></label>
                <button type="button" class="rounded-xl bg-hsi-navy px-3 py-2 text-xs font-semibold text-white" @click="reload">Afficher</button>
            </template>
            <span class="text-xs text-hsi-slate-light sm:ml-auto">Du {{ flow.period.from }} au {{ flow.period.to }}</span>
        </div>

        <div v-if="hasProjects" class="mb-4 flex flex-wrap gap-2" role="group" :aria-label="$tr('Projets affichés')">
            <button v-for="s in stages" :key="s.value" type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition" :class="stage === s.value ? 'border-hsi-navy bg-hsi-navy text-white' : 'border-hsi-border bg-white text-hsi-slate hover:border-hsi-gold'" :aria-pressed="stage === s.value" @click="setStage(s.value)">
                {{ s.label }} <span class="rounded-full px-1.5 text-[10px]" :class="stage === s.value ? 'bg-white/20' : 'bg-hsi-muted'">{{ stageCount(s.value) }}</span>
            </button>
        </div>

        <HsiSankey :flow="visibleFlow" :height="height" />
        <p v-if="flow.totals?.planned > 0 && stage !== 'in_progress' && stage !== 'completed'" class="mt-2 text-xs text-hsi-slate">
            En gris clair : {{ money(flow.totals.planned) }} à verser aux projets validés qui n’ont pas encore reçu leur financement (prévu, pas encore sorti du compte).
        </p>

        <!-- Projets financés par ce compte -->
        <div v-if="hasProjects" class="mt-6">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-hsi-navy">Projets financés par ce compte</h4>
                <input v-model="projectList.search.value" type="search" class="w-full rounded-xl border border-hsi-border px-3 py-1.5 text-sm sm:w-56" :placeholder="$tr('Rechercher un projet…')">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] text-sm">
                    <thead><tr class="border-b border-hsi-border text-left text-[11px] uppercase tracking-wider text-hsi-slate-light"><th class="py-2">Projet</th><th>Étape</th><th class="text-right">Validé</th><th class="text-right">Reçu</th><th>Versé le</th></tr></thead>
                    <tbody>
                        <tr v-for="p in projectList.paged.value" :key="p.id" class="border-b border-hsi-border/50">
                            <td class="py-2"><Link :href="p.url" class="font-medium text-hsi-navy hover:underline">{{ p.title }}</Link><p class="text-xs text-hsi-slate-light">{{ p.owner }}<template v-if="p.type"> · {{ p.type }}</template></p></td>
                            <td><HsiBadge :variant="stageMeta[p.stage]?.variant ?? 'neutral'" size="xs">{{ stageMeta[p.stage]?.label ?? p.status_label }}</HsiBadge></td>
                            <td class="text-right">{{ p.requested }}</td>
                            <td class="text-right font-semibold" :class="p.received_raw > 0 ? 'text-hsi-navy' : 'text-hsi-slate-light'">{{ p.received_raw > 0 ? p.received : '—' }}<span v-if="p.repaid" class="block text-[11px] font-normal text-hsi-success">remboursé {{ p.repaid }}</span></td>
                            <td class="text-hsi-slate">{{ p.funded_on || '—' }}</td>
                        </tr>
                        <tr v-if="!projectList.total.value"><td colspan="5" class="py-3 text-center text-hsi-slate-light">Aucun projet pour ce filtre.</td></tr>
                    </tbody>
                </table>
            </div>
            <HsiPagination v-model:page="projectList.page.value" v-model:per-page="projectList.perPage.value" :total="projectList.total.value" :per-page-options="[5, 10, 25]" label="projets" compact />
        </div>

        <div v-if="withBreakdown && (inBreakdown.length || outBreakdown.length)" class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
                <h4 class="mb-2 text-sm font-semibold text-hsi-navy">Entrées par catégorie</h4>
                <ul class="space-y-2">
                    <li v-for="row in inBreakdown" :key="row.label" class="text-sm">
                        <div class="flex justify-between gap-3"><span class="text-hsi-slate">{{ row.label }}</span><span class="font-semibold text-hsi-navy">{{ money(row.total) }} <span class="text-xs font-normal text-hsi-slate-light">· {{ share(row.total, flow.totals.in) }} %</span></span></div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-hsi-muted"><div class="h-full rounded-full bg-[#2F9E6B]" :style="{ width: `${share(row.total, flow.totals.in)}%` }" /></div>
                    </li>
                    <li v-if="!inBreakdown.length" class="text-sm text-hsi-slate-light">Aucune entrée.</li>
                </ul>
            </div>
            <div>
                <h4 class="mb-2 text-sm font-semibold text-hsi-navy">Sorties par catégorie</h4>
                <ul class="space-y-2">
                    <li v-for="row in outBreakdown" :key="row.label" class="text-sm">
                        <div class="flex justify-between gap-3"><span class="text-hsi-slate">{{ row.label }}</span><span class="font-semibold text-hsi-navy">{{ money(row.total) }} <span class="text-xs font-normal text-hsi-slate-light">· {{ share(row.total, flow.totals.out) }} %</span></span></div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-hsi-muted"><div class="h-full rounded-full bg-[#C2410C]" :style="{ width: `${share(row.total, flow.totals.out)}%` }" /></div>
                    </li>
                    <li v-if="!outBreakdown.length" class="text-sm text-hsi-slate-light">Aucune sortie.</li>
                </ul>
            </div>
        </div>
    </div>
</template>
