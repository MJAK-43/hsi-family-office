<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts/core';
import { SankeyChart } from 'echarts/charts';
import { TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import { ArrowDownLeft, ArrowUpRight, Scale } from 'lucide-vue-next';
import { tr } from '@/i18n';
import { formatMoney } from '@/currency';

echarts.use([SankeyChart, TooltipComponent, CanvasRenderer]);

/**
 * Diagramme de flux financiers : catégories d'entrées → comptes → catégories de sorties.
 * flow = { nodes: [{ id, label, kind, color }], links: [{ source, target, value }], totals, breakdown }
 */
const props = defineProps({
    flow: { type: Object, required: true },
    height: { type: Number, default: 460 },
    showTotals: { type: Boolean, default: true },
});

const el = ref(null);
let chart = null;
let observer = null;
const empty = computed(() => !props.flow?.links?.length);
// Hauteur adaptée au nombre de catégories : chaque libellé (nom + montant) garde environ 36 px.
const chartHeight = computed(() => {
    const nodes = props.flow?.nodes ?? [];
    const left = nodes.filter((n) => n.kind === 'in' || n.kind === 'reserve').length;
    const right = nodes.filter((n) => ['out', 'project', 'pending', 'balance'].includes(n.kind)).length;
    return Math.max(props.height, Math.max(left, right) * 34 + 40);
});
const money = (v) => formatMoney(v, { decimals: 0 });
// Libellés longs (titres de projets) raccourcis dans le diagramme ; le nom complet reste dans l'infobulle.
const shorten = (text, max) => (text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text);
const labelOf = computed(() => Object.fromEntries((props.flow?.nodes ?? []).map((n) => [n.id, tr(n.label)])));

// Couleurs des liens : la couleur du nœud de départ (vert pour les entrées, bleu nuit pour les comptes…).
const nodeColor = computed(() => Object.fromEntries((props.flow?.nodes ?? []).map((n) => [n.id, n.color])));
const nodeKind = computed(() => Object.fromEntries((props.flow?.nodes ?? []).map((n) => [n.id, n.kind])));
const hasKind = (kind) => (props.flow?.nodes ?? []).some((n) => n.kind === kind);

const option = computed(() => {
    const mobile = (el.value?.clientWidth ?? 800) < 640;
    return {
        tooltip: {
            trigger: 'item',
            confine: true,
            formatter: (p) => (p.dataType === 'edge'
                ? `${labelOf.value[p.data.source]} → ${labelOf.value[p.data.target]}<br><b>${money(p.data.value)}</b>`
                : `${labelOf.value[p.name]}<br><b>${money(p.value)}</b>`),
        },
        series: [{
            type: 'sankey',
            left: mobile ? 4 : 12,
            right: mobile ? 130 : 260,
            top: 12,
            bottom: 12,
            nodeWidth: mobile ? 12 : 18,
            nodeGap: mobile ? 14 : 20,
            nodeAlign: 'justify',
            layoutIterations: 64,
            draggable: false,
            emphasis: { focus: 'adjacency' },
            data: props.flow.nodes.map((n) => ({ name: n.id, itemStyle: { color: n.color, borderColor: n.color } })),
            links: props.flow.links.map((l) => ({
                ...l,
                // Versements prévus (projets validés pas encore financés) : flux gris clair.
                lineStyle: nodeKind.value[l.target] === 'pending'
                    ? { color: '#CBD5E1', opacity: 0.45 }
                    : { color: nodeColor.value[l.source] ?? '#94A3B8', opacity: 0.28 },
            })),
            lineStyle: { curveness: 0.5 },
            label: {
                color: '#0B2748',
                fontFamily: 'Inter, sans-serif',
                fontSize: mobile ? 10 : 12,
                // Une seule ligne (nom + montant) : les petites catégories, très rapprochées, restent lisibles.
                formatter: (p) => `{name|${shorten(labelOf.value[p.name] ?? p.name, mobile ? 14 : 24)}}  {value|${money(p.value)}}`,
                rich: {
                    name: { fontWeight: 600, color: '#0B2748', fontSize: mobile ? 10 : 12, lineHeight: 15 },
                    value: { color: '#64748B', fontSize: mobile ? 9 : 11, lineHeight: 14 },
                },
            },
        }],
    };
});

const render = () => {
    if (!el.value || empty.value) return;
    chart ??= echarts.init(el.value, null, { renderer: 'canvas' });
    chart.setOption(option.value, true);
};
onMounted(() => {
    render();
    observer = new ResizeObserver(() => { chart?.resize(); render(); });
    if (el.value) observer.observe(el.value);
});
watch(() => [props.flow, labelOf.value], () => {
    if (empty.value) { chart?.dispose(); chart = null; return; }
    // Le conteneur réapparaît quand des données arrivent après un état vide.
    requestAnimationFrame(render);
}, { deep: true });
onBeforeUnmount(() => { observer?.disconnect(); chart?.dispose(); chart = null; });
</script>

<template>
    <div>
        <div v-if="showTotals && !empty" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center gap-3 rounded-2xl bg-green-50 px-4 py-3">
                <ArrowDownLeft class="h-5 w-5 text-hsi-success" aria-hidden="true" />
                <div><p class="text-xs text-hsi-slate">Entrées</p><p class="font-heading text-lg font-semibold text-hsi-navy">{{ money(flow.totals.in) }}</p></div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-orange-50 px-4 py-3">
                <ArrowUpRight class="h-5 w-5 text-[#C2410C]" aria-hidden="true" />
                <div><p class="text-xs text-hsi-slate">Sorties</p><p class="font-heading text-lg font-semibold text-hsi-navy">{{ money(flow.totals.out) }}</p></div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-hsi-muted px-4 py-3">
                <Scale class="h-5 w-5 text-hsi-navy" aria-hidden="true" />
                <div><p class="text-xs text-hsi-slate">Solde de la période</p><p class="font-heading text-lg font-semibold" :class="flow.totals.net < 0 ? 'text-hsi-danger' : 'text-hsi-navy'">{{ money(flow.totals.net) }}</p></div>
            </div>
        </div>
        <div v-if="empty" class="flex h-40 items-center justify-center rounded-2xl border border-dashed border-hsi-border text-sm text-hsi-slate-light">Aucun mouvement validé sur la période.</div>
        <div v-else class="overflow-x-auto">
            <div ref="el" class="min-w-[560px]" :style="{ height: `${chartHeight}px` }" role="img" :aria-label="$tr('Diagramme des flux financiers : entrées, comptes et sorties')" />
        </div>
        <div v-if="!empty" class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-hsi-slate">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#2F9E6B]" /> Entrées</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-hsi-navy" /> Comptes du fonds</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#C2410C]" /> {{ hasKind('project') ? 'Sorties et projets financés' : 'Sorties' }}</span>
            <span v-if="hasKind('pending')" class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#CBD5E1]" /> À verser (prévu)</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#94A3B8]" /> Solde conservé</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-hsi-gold" /> Solde antérieur utilisé</span>
        </div>
    </div>
</template>
