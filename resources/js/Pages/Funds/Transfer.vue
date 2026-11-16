<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

const props = defineProps({ accounts: Array });

const form = useForm({
    from_fund_account_id: '',
    to_fund_account_id: '',
    amount: '',
    description: '',
    idempotency_key: crypto.randomUUID(),
});

const fromAccount = computed(() => props.accounts.find((a) => a.id === Number(form.from_fund_account_id)));
const insufficientBalance = computed(() => {
    if (!fromAccount.value || !form.amount) return false;
    return Number(form.amount) > Number(fromAccount.value.current_balance);
});

const submit = () => form.post(route('funds.transfer'), {
    onSuccess: () => { form.idempotency_key = crypto.randomUUID(); },
});

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm';
</script>

<template>
    <Head title="Transfert entre poches" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Transfert "
            accent="entre poches"
            subtitle="Déplacer des fonds d'une poche à une autre"
        />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <HsiCard
                padding="lg"
                class="lg:col-span-2"
            >
                <form
                    class="space-y-4"
                    @submit.prevent="submit"
                >
                    <div>
                        <label class="block text-sm font-medium mb-1">Compte source *</label>
                        <select
                            v-model="form.from_fund_account_id"
                            :class="inputClass"
                            required
                        >
                            <option value="">Sélectionner</option>
                            <option
                                v-for="a in accounts"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.name }} — {{ a.current_balance }} {{ a.currency }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Compte destination *</label>
                        <select
                            v-model="form.to_fund_account_id"
                            :class="inputClass"
                            required
                        >
                            <option value="">Sélectionner</option>
                            <option
                                v-for="a in accounts"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.name }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.to_fund_account_id"
                            class="text-xs text-hsi-danger mt-1"
                        >
                            {{ form.errors.to_fund_account_id }}
                        </p>
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
                        <p
                            v-if="form.errors.amount"
                            class="text-xs text-hsi-danger mt-1"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Description *</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            :class="inputClass"
                            required
                        />
                    </div>
                    <div
                        v-if="insufficientBalance"
                        class="p-3 rounded-xl bg-red-50 border border-red-200 text-sm text-hsi-danger"
                    >
                        Solde insuffisant sur le compte source ({{ fromAccount?.current_balance }} {{ fromAccount?.currency }} disponibles).
                    </div>
                    <div class="flex justify-end gap-2">
                        <Link :href="route('funds.index')">
                            <HsiButton variant="ghost">Annuler</HsiButton>
                        </Link>
                        <HsiButton
                            type="submit"
                            :disabled="form.processing || insufficientBalance"
                        >
                            Confirmer le transfert
                        </HsiButton>
                    </div>
                </form>
            </HsiCard>

            <HsiCard
                v-if="fromAccount && form.amount && form.to_fund_account_id"
                padding="md"
            >
                <h4 class="font-heading font-semibold text-hsi-navy mb-3">Aperçu</h4>
                <p class="text-sm mb-2">
                    <span class="text-hsi-slate-light">De :</span> {{ fromAccount.name }}
                </p>
                <p class="text-sm mb-2">
                    <span class="text-hsi-slate-light">Vers :</span>
                    {{ accounts.find(a => a.id === Number(form.to_fund_account_id))?.name }}
                </p>
                <p class="text-lg font-bold text-hsi-navy">
                    {{ form.amount }} USD
                </p>
            </HsiCard>
        </div>
    </AuthenticatedLayout>
</template>
