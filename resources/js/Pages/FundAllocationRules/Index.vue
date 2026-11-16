<script setup>
import { tr } from '@/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Star, Power, PowerOff, Pencil } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

defineProps({
    rules: Array,
});

const setDefault = (id) => {
    if (confirm(tr('Définir cette règle par défaut ?'))) {
        router.patch(route('fund-allocation-rules.set-default', id));
    }
};

const toggleActive = (rule) => {
    const routeName = rule.is_active ? 'fund-allocation-rules.deactivate' : 'fund-allocation-rules.activate';
    router.patch(route(routeName, rule.id));
};
</script>

<template>
    <Head title="Règles d'allocation" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Règles d'"
            accent="allocation"
            subtitle="Répartition automatique des cotisations validées"
        >
            <template #actions>
                <Link :href="route('fund-allocation-rules.create')">
                    <HsiButton>
                        <Plus class="h-4 w-4" />
                        Nouvelle règle
                    </HsiButton>
                </Link>
            </template>
        </HsiPageHeader>

        <div class="space-y-4">
            <HsiCard
                v-for="rule in rules"
                :key="rule.id"
                padding="md"
            >
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <h3 class="font-heading font-semibold text-hsi-navy">{{ rule.name }}</h3>
                            <HsiBadge
                                v-if="rule.is_default"
                                variant="gold"
                            >
                                Par défaut
                            </HsiBadge>
                            <HsiBadge :variant="rule.is_active ? 'success' : 'neutral'">
                                {{ rule.is_active ? 'Active' : 'Inactive' }}
                            </HsiBadge>
                        </div>
                        <p
                            v-if="rule.description"
                            class="text-sm text-hsi-slate mb-3"
                        >
                            {{ rule.description }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="item in rule.items"
                                :key="item.fund_account_id"
                                class="text-xs px-2 py-1 rounded-full bg-hsi-muted"
                            >
                                {{ item.account_name }} : {{ item.percentage }}%
                            </span>
                        </div>
                        <p class="text-xs text-hsi-slate-light mt-2">
                            Total : {{ rule.total_percentage }}%
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Link
                            :href="route('fund-allocation-rules.edit', rule.id)"
                            class="p-2 rounded-lg hover:bg-hsi-muted"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>
                        <button
                            v-if="!rule.is_default"
                            type="button"
                            class="p-2 rounded-lg hover:bg-hsi-muted"
                            title="Définir par défaut"
                            @click="setDefault(rule.id)"
                        >
                            <Star class="h-4 w-4 text-hsi-gold" />
                        </button>
                        <button
                            type="button"
                            class="p-2 rounded-lg hover:bg-hsi-muted"
                            @click="toggleActive(rule)"
                        >
                            <PowerOff
                                v-if="rule.is_active"
                                class="h-4 w-4"
                            />
                            <Power
                                v-else
                                class="h-4 w-4"
                            />
                        </button>
                    </div>
                </div>
            </HsiCard>
        </div>

        <Link
            :href="route('funds.index')"
            class="inline-block mt-4 text-sm text-hsi-gold"
        >
            ← Retour au fonds familial
        </Link>
    </AuthenticatedLayout>
</template>
