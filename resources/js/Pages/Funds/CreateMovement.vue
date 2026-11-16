<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

defineProps({ accounts: Array, types: Array });

const form = useForm({
    fund_account_id: '',
    type: 'manual_income',
    amount: '',
    currency: usePage().props.currency?.code ?? 'USD',
    direction: 'credit',
    label: '',
    description: '',
    reference: '',
    movement_date: new Date().toISOString().slice(0, 10),
});

const submit = () => form.post(route('funds.movements.store'));

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm';
</script>

<template>
    <Head title="Ajouter un mouvement" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Ajouter un "
            accent="mouvement"
            subtitle="Entrée, sortie ou ajustement manuel"
        />

        <HsiCard padding="lg">
            <form
                class="grid grid-cols-1 md:grid-cols-2 gap-4"
                @submit.prevent="submit"
            >
                <div>
                    <label class="block text-sm font-medium mb-1">Poche *</label>
                    <select
                        v-model="form.fund_account_id"
                        :class="inputClass"
                        required
                    >
                        <option value="">Sélectionner</option>
                        <option
                            v-for="a in accounts"
                            :key="a.id"
                            :value="a.id"
                        >
                            {{ a.name }} ({{ a.current_balance }} {{ a.currency }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Type *</label>
                    <select
                        v-model="form.type"
                        :class="inputClass"
                        required
                    >
                        <option
                            v-for="t in types"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Montant *</label>
                    <input
                        v-model="form.amount"
                        type="number"
                        min="1"
                        step="0.01"
                        :class="inputClass"
                        required
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Direction *</label>
                    <select
                        v-model="form.direction"
                        :class="inputClass"
                        required
                    >
                        <option value="credit">Crédit (entrée)</option>
                        <option value="debit">Débit (sortie)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Libellé *</label>
                    <input
                        v-model="form.label"
                        type="text"
                        :class="inputClass"
                        required
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        :class="inputClass"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Référence</label>
                    <input
                        v-model="form.reference"
                        type="text"
                        :class="inputClass"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Date</label>
                    <input
                        v-model="form.movement_date"
                        type="date"
                        :class="inputClass"
                    />
                </div>
                <div class="md:col-span-2 flex justify-end gap-2">
                    <Link :href="route('funds.movements')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                    <HsiButton
                        type="submit"
                        :disabled="form.processing"
                    >
                        Enregistrer
                    </HsiButton>
                </div>
            </form>
        </HsiCard>
    </AuthenticatedLayout>
</template>
