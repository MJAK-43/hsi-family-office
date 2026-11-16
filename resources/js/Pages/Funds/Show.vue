<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import { TrendingUp, TrendingDown, List } from 'lucide-vue-next';
import { computed } from 'vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import { useListTools } from '@/Composables/useListTools';
import HsiFlowPanel from '@/Components/Hsi/HsiFlowPanel.vue';


const props = defineProps({
    account: Object,
    movements: Array,
    stats: Object,
    can: Object,
    flow: { type: Object, default: null },
    flowPeriod: { type: String, default: '12m' },
});
const list = useListTools(() => props.movements ?? [], {
    searchKeys: ['label', 'description', 'reference', 'type_label', 'amount_formatted'],
    filters: { type: (m, v) => m.type === v, direction: (m, v) => m.direction === v },
    perPage: 10,
});
const types = computed(() => [...new Map((props.movements ?? []).map((m) => [m.type, m.type_label])).entries()]);
</script>

<template>
    <Head :title="`${$tr('Poche')} — ${$tr(account.name)}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="'Poche '"
            :accent="account.name"
            :subtitle="account.type_label"
        >
            <template #actions>
                <Link
                    :href="route('funds.index')"
                    class="text-sm text-hsi-gold"
                >
                    ← Retour au fonds
                </Link>
            </template>
        </HsiPageHeader>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <HsiStatCard
                :icon="TrendingUp"
                title="Solde actuel"
                :value="account.balance_formatted"
                :change="$tr('{p}% du total', { p: account.percent })"
                change-type="success"
            />
            <HsiStatCard
                :icon="TrendingUp"
                title="Entrées"
                :value="stats.credits"
                change="—"
                change-type="success"
            />
            <HsiStatCard
                :icon="TrendingDown"
                title="Sorties"
                :value="stats.debits"
                change="—"
                change-type="danger"
            />
        </div>

        <HsiCard
            padding="lg"
            class="mb-6"
        >
            <HsiBadge
                :variant="account.type_color"
                class="mb-3"
            >
                {{ account.type_label }}
            </HsiBadge>
            <p
                v-if="account.description"
                class="text-sm text-hsi-slate"
            >
                {{ account.description }}
            </p>
        </HsiCard>

        <HsiCard v-if="flow" padding="lg" class="mb-6">
            <h3 class="mb-1 font-heading text-lg font-semibold text-hsi-navy">Flux du compte</h3>
            <p class="mb-4 text-xs text-hsi-slate-light">Ce qui entre dans « {{ account.name }} », d’où cela vient, et où l’argent repart</p>
            <HsiFlowPanel :flow="flow" :preset="flowPeriod" :height="420" />
        </HsiCard>

        <HsiCard padding="md">
            <div class="flex items-center gap-2 mb-4">
                <List class="h-5 w-5 text-hsi-gold" />
                <h3 class="font-heading text-lg font-semibold text-hsi-navy">
                    Mouvements récents
                </h3>
            </div>
            <HsiListToolbar v-model:search="list.search.value" placeholder="Libellé, référence, montant…" :active="list.active.value" :count="list.total.value" label="mouvement(s)" @reset="list.reset()">
                <select v-model="list.filters.type" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Tous les types</option><option v-for="[value, label] in types" :key="value" :value="value">{{ label }}</option></select>
                <select v-model="list.filters.direction" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Entrées et sorties</option><option value="credit">Entrées</option><option value="debit">Sorties</option></select>
            </HsiListToolbar>
            <HsiDataTable
                :columns="[
                    { key: 'movement_date', label: 'Date' },
                    { key: 'type_label', label: 'Type' },
                    { key: 'direction_label', label: 'Direction' },
                    { key: 'amount_formatted', label: 'Montant', align: 'right' },
                    { key: 'status_label', label: 'Statut', align: 'center' },
                ]"
                compact
            >
                <template #body>
                    <tr
                        v-for="m in list.paged.value"
                        :key="m.id"
                        class="border-b border-hsi-border/60"
                    >
                        <td class="px-3 py-2.5 text-sm">{{ m.movement_date }}</td>
                        <td class="px-3 py-2.5 text-sm">
                            <HsiBadge :variant="m.type_color">{{ m.type_label }}</HsiBadge>
                        </td>
                        <td class="px-3 py-2.5 text-sm">{{ m.direction_label }}</td>
                        <td class="px-3 py-2.5 text-sm text-right font-semibold">{{ m.amount_formatted }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <HsiBadge :variant="m.status_color">{{ m.status_label }}</HsiBadge>
                        </td>
                    </tr>
                </template>
            </HsiDataTable>
            <p v-if="!list.total.value" class="py-4 text-center text-sm text-hsi-slate-light">Aucun mouvement ne correspond.</p>
            <HsiPagination v-model:page="list.page.value" v-model:per-page="list.perPage.value" :total="list.total.value" label="mouvements" compact />
        </HsiCard>
    </AuthenticatedLayout>
</template>
