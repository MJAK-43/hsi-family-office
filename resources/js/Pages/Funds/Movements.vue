<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import { Plus, Ban } from 'lucide-vue-next';

const props = defineProps({
    movements: Object,
    filters: Object,
    accounts: Array,
    types: Array,
    statuses: Array,
    can: Object,
});

const fundAccountId = ref(props.filters.fund_account_id ?? '');
const type = ref(props.filters.type ?? '');
const direction = ref(props.filters.direction ?? '');
const status = ref(props.filters.status ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const applyFilters = () => {
    router.get(route('funds.movements'), {
        fund_account_id: fundAccountId.value || undefined,
        type: type.value || undefined,
        direction: direction.value || undefined,
        status: status.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
};

watch([fundAccountId, type, direction, status, dateFrom, dateTo], applyFilters);

const cancelMovement = (id) => {
    const reason = prompt('Motif d\'annulation :');
    if (reason && reason.length >= 5) {
        router.patch(route('funds.movements.cancel', id), { cancellation_reason: reason });
    }
};
</script>

<template>
    <Head title="Mouvements du fonds" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Mouvements "
            accent="financiers"
            subtitle="Historique complet des opérations du fonds familial"
        >
            <template #actions>
                <Link
                    v-if="can.createMovement"
                    :href="route('funds.movements.create')"
                >
                    <HsiButton>
                        <Plus class="h-4 w-4" />
                        Ajouter
                    </HsiButton>
                </Link>
            </template>
        </HsiPageHeader>

        <HsiCard padding="md">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                <select
                    v-model="fundAccountId"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"
                >
                    <option value="">Toutes les poches</option>
                    <option
                        v-for="a in accounts"
                        :key="a.id"
                        :value="a.id"
                    >
                        {{ a.name }}
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
                <select
                    v-model="direction"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"
                >
                    <option value="">Direction</option>
                    <option value="credit">Crédit</option>
                    <option value="debit">Débit</option>
                </select>
                <select
                    v-model="status"
                    class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"
                >
                    <option value="">Statut</option>
                    <option
                        v-for="s in statuses"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </option>
                </select>
                <input
                    v-model="dateFrom"
                    type="date"
                    class="rounded-xl border border-hsi-border px-3 py-2.5 text-sm"
                />
                <input
                    v-model="dateTo"
                    type="date"
                    class="rounded-xl border border-hsi-border px-3 py-2.5 text-sm"
                />
            </div>

            <HsiDataTable
                :columns="[
                    { key: 'movement_date', label: 'Date' },
                    { key: 'account', label: 'Poche' },
                    { key: 'type_label', label: 'Type' },
                    { key: 'direction_label', label: 'Direction' },
                    { key: 'amount_formatted', label: 'Montant', align: 'right' },
                    { key: 'reference', label: 'Référence' },
                    { key: 'created_by', label: 'Créé par' },
                    { key: 'status_label', label: 'Statut', align: 'center' },
                ]"
                compact
            >
                <template #body>
                    <tr
                        v-for="row in movements.data"
                        :key="row.id"
                        class="border-b border-hsi-border/60"
                    >
                        <td class="px-3 py-2.5 text-sm">{{ row.movement_date }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.account }}</td>
                        <td class="px-3 py-2.5 text-sm">
                            <HsiBadge :variant="row.type_color">{{ row.type_label }}</HsiBadge>
                        </td>
                        <td class="px-3 py-2.5 text-sm">{{ row.direction_label }}</td>
                        <td class="px-3 py-2.5 text-sm text-right font-semibold">{{ row.amount_formatted }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.reference }}</td>
                        <td class="px-3 py-2.5 text-sm">{{ row.created_by }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <HsiBadge :variant="row.status_color">{{ row.status_label }}</HsiBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <button
                                v-if="can.cancel && row.status === 'validated'"
                                type="button"
                                @click="cancelMovement(row.id)"
                            >
                                <Ban class="h-4 w-4 text-hsi-danger" />
                            </button>
                        </td>
                    </tr>
                </template>
            </HsiDataTable>

            <div
                v-if="movements.data.length"
                class="flex justify-between mt-4 pt-4 border-t"
            >
                <p class="text-xs text-hsi-slate-light">
                    {{ movements.from }}–{{ movements.to }} sur {{ movements.total }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in movements.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-2 py-1 text-xs rounded', link.active ? 'bg-hsi-navy text-white' : '']"
                        v-html="link.label"
                    />
                </div>
            </div>
        </HsiCard>
    </AuthenticatedLayout>
</template>
