<script setup>
const props = defineProps({
    form: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm';
const labelClass = 'block text-sm font-medium text-hsi-slate mb-1.5';

const totalPercentage = () => {
    return props.form.items.reduce((sum, item) => sum + Number(item.percentage || 0), 0).toFixed(2);
};

const addItem = () => {
    props.form.items.push({ fund_account_id: '', percentage: '' });
};

const removeItem = (index) => {
    if (props.form.items.length > 1) {
        props.form.items.splice(index, 1);
    }
};
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="emit('submit')"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label :class="labelClass">Nom *</label>
                <input
                    v-model="form.name"
                    type="text"
                    :class="inputClass"
                    required
                />
            </div>
            <div class="md:col-span-2">
                <label :class="labelClass">Description</label>
                <textarea
                    v-model="form.description"
                    rows="2"
                    :class="inputClass"
                />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.is_default"
                    type="checkbox"
                />
                Règle par défaut
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                />
                Active
            </label>
        </div>

        <div>
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-heading font-semibold text-hsi-navy">
                    Répartition (total : {{ totalPercentage() }}%)
                </h3>
                <button
                    type="button"
                    class="text-sm text-hsi-gold"
                    @click="addItem"
                >
                    + Ajouter une ligne
                </button>
            </div>
            <p
                v-if="form.errors.items"
                class="text-xs text-hsi-danger mb-2"
            >
                {{ form.errors.items }}
            </p>
            <div
                v-for="(item, index) in form.items"
                :key="index"
                class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3"
            >
                <select
                    v-model="item.fund_account_id"
                    :class="inputClass"
                    required
                >
                    <option value="">Compte</option>
                    <option
                        v-for="a in accounts"
                        :key="a.id"
                        :value="a.id"
                    >
                        {{ a.name }}
                    </option>
                </select>
                <input
                    v-model="item.percentage"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max="100"
                    placeholder="%"
                    :class="inputClass"
                    required
                />
                <button
                    type="button"
                    class="text-sm text-hsi-danger"
                    @click="removeItem(index)"
                >
                    Supprimer
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t">
            <slot name="cancel" />
            <button
                type="submit"
                class="rounded-xl bg-hsi-navy px-5 py-2.5 text-sm text-white"
                :disabled="form.processing"
            >
                {{ isEdit ? 'Enregistrer' : 'Créer la règle' }}
            </button>
        </div>
    </form>
</template>
