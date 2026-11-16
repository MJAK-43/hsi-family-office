<script setup>
import ContributionPlanForm from '@/Pages/ContributionPlans/ContributionPlanForm.vue';

const props = defineProps({
    form: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    frequencies: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm text-hsi-navy focus:border-hsi-gold focus:outline-none focus:ring-2 focus:ring-hsi-gold/20';
const labelClass = 'block text-sm font-medium text-hsi-slate mb-1.5';
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
                <p
                    v-if="form.errors.name"
                    class="mt-1 text-xs text-hsi-danger"
                >
                    {{ form.errors.name }}
                </p>
            </div>
            <div class="md:col-span-2">
                <label :class="labelClass">Description</label>
                <textarea
                    v-model="form.description"
                    rows="3"
                    :class="inputClass"
                />
            </div>
            <div>
                <label :class="labelClass">Montant *</label>
                <input
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    :class="inputClass"
                    required
                />
            </div>
            <div>
                <label :class="labelClass">Devise *</label>
                <select
                    v-model="form.currency"
                    :class="inputClass"
                    required
                >
                    <option
                        v-for="c in currencies"
                        :key="c"
                        :value="c"
                    >
                        {{ c }}
                    </option>
                </select>
            </div>
            <div>
                <label :class="labelClass">Fréquence *</label>
                <select
                    v-model="form.frequency"
                    :class="inputClass"
                    required
                >
                    <option
                        v-for="f in frequencies"
                        :key="f.value"
                        :value="f.value"
                    >
                        {{ f.label }}
                    </option>
                </select>
            </div>
            <div>
                <label :class="labelClass">Pays associé</label>
                <select
                    v-model="form.country_id"
                    :class="inputClass"
                >
                    <option :value="null">Aucun</option>
                    <option
                        v-for="c in countries"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.flag_emoji }} {{ c.name }}
                    </option>
                </select>
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_default"
                        type="checkbox"
                        class="rounded border-hsi-border text-hsi-gold focus:ring-hsi-gold/20"
                    />
                    Plan par défaut
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-hsi-border text-hsi-gold focus:ring-hsi-gold/20"
                    />
                    Actif
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.auto_generate" type="checkbox" class="rounded border-hsi-border text-hsi-gold focus:ring-hsi-gold/20" />
                    Générer les échéances
                </label>
            </div>
            <template v-if="form.auto_generate">
                <div>
                    <label :class="labelClass">Date de début</label>
                    <input v-model="form.starts_on" type="date" :class="inputClass" />
                </div>
                <div>
                    <label :class="labelClass">Jour d’échéance (1 à 28)</label>
                    <input v-model="form.due_day" type="number" min="1" max="28" :class="inputClass" required />
                </div>
                <div>
                    <label :class="labelClass">Délai de grâce (jours)</label>
                    <input v-model="form.grace_days" type="number" min="0" max="90" :class="inputClass" required />
                </div>
            </template>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-hsi-border">
            <slot name="cancel" />
            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-hsi-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-hsi-navy-soft transition-colors disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ isEdit ? 'Enregistrer' : 'Créer le plan' }}
            </button>
        </div>
    </form>
</template>
