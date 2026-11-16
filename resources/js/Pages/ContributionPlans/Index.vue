<script setup>
import { tr } from '@/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Power, PowerOff, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiDataTable from '@/Components/Hsi/HsiDataTable.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';

defineProps({
    plans: Array,
});

const toggleActive = (plan) => {
    const routeName = plan.is_active ? 'contribution-plans.deactivate' : 'contribution-plans.activate';
    router.patch(route(routeName, plan.id));
};

const destroyPlan = (id, name) => {
    if (confirm(tr('Supprimer le plan « {name} » ?', { name }))) {
        router.delete(route('contribution-plans.destroy', id));
    }
};
</script>

<template>
    <Head title="Plans de cotisation" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Plans de "
            accent="cotisation"
            subtitle="Configuration des montants et fréquences par zone"
        >
            <template #actions>
                <Link :href="route('contribution-plans.create')">
                    <HsiButton>
                        <Plus class="h-4 w-4" />
                        Nouveau plan
                    </HsiButton>
                </Link>
            </template>
        </HsiPageHeader>

        <HsiCard padding="md">
            <HsiEmptyState
                v-if="!plans.length"
                title="Aucun plan configuré"
                description="Créez un premier plan de cotisation."
            />

            <HsiDataTable
                v-else
                :columns="[
                    { key: 'name', label: 'Nom' },
                    { key: 'amount', label: 'Montant', align: 'right' },
                    { key: 'frequency', label: 'Fréquence' },
                    { key: 'country', label: 'Pays' },
                    { key: 'active', label: 'Actif', align: 'center' },
                    { key: 'default', label: 'Défaut', align: 'center' },
                ]"
                compact
            >
                <template #body>
                    <tr
                        v-for="plan in plans"
                        :key="plan.id"
                        class="border-b border-hsi-border/60 last:border-0 hover:bg-hsi-muted/40 transition-colors"
                    >
                        <td class="px-3 py-2.5 text-sm font-medium">{{ plan.name }}<span v-if="plan.is_regulatory" class="ml-2 rounded-full bg-hsi-gold/15 px-2 py-0.5 text-[11px] font-semibold text-[#A8841F]">Règlement</span></td>
                        <td class="px-3 py-2.5 text-sm text-right font-semibold">
                            {{ plan.amount }} {{ plan.currency }}
                        </td>
                        <td class="px-3 py-2.5 text-sm">{{ plan.frequency_label }}</td>
                        <td class="px-3 py-2.5 text-sm">
                            <span v-if="plan.country">{{ plan.country.flag_emoji }} {{ plan.country.name }}</span>
                            <span
                                v-else
                                class="text-hsi-slate-light"
                            >—</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <HsiBadge :variant="plan.is_active ? 'success' : 'neutral'">
                                {{ plan.is_active ? 'Oui' : 'Non' }}
                            </HsiBadge>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <HsiBadge
                                v-if="plan.is_default"
                                variant="gold"
                            >
                                Oui
                            </HsiBadge>
                            <span
                                v-else
                                class="text-hsi-slate-light text-sm"
                            >Non</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <div v-if="plan.is_regulatory" class="text-right">
                                <Link :href="route('fund-settings.index')" class="text-xs font-semibold text-[#1D4ED8] hover:underline">Paramètres du fonds</Link>
                            </div>
                            <div v-else class="flex items-center justify-end gap-1">
                                <Link
                                    :href="route('contribution-plans.edit', plan.id)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted transition-colors"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted transition-colors"
                                    :title="plan.is_active ? 'Désactiver' : 'Activer'"
                                    @click="toggleActive(plan)"
                                >
                                    <PowerOff
                                        v-if="plan.is_active"
                                        class="h-4 w-4"
                                    />
                                    <Power
                                        v-else
                                        class="h-4 w-4"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-danger hover:bg-red-50 transition-colors"
                                    @click="destroyPlan(plan.id, plan.name)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </HsiDataTable>
        </HsiCard>

        <div class="mt-4">
            <Link
                :href="route('contributions.index')"
                class="text-sm text-hsi-gold hover:text-hsi-gold-light"
            >
                ← Retour aux cotisations
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
