<script setup>
import { RotateCcw } from 'lucide-vue-next';
import HsiSearchInput from '@/Components/Hsi/HsiSearchInput.vue';

/** Barre de recherche + filtres (slot) + réinitialisation, au-dessus d'un tableau ou d'une grille de cartes. */
defineProps({
    search: { type: String, default: '' },
    placeholder: { type: String, default: 'Rechercher…' },
    active: { type: Boolean, default: false },
    count: { type: Number, default: null },
    label: { type: String, default: 'résultat(s)' },
});
defineEmits(['update:search', 'reset']);
</script>

<template>
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="min-w-0 lg:max-w-sm lg:flex-1">
            <HsiSearchInput :model-value="search" :placeholder="placeholder" @update:model-value="$emit('update:search', $event)" />
        </div>
        <div v-if="$slots.default" class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
            <slot />
        </div>
        <div class="flex items-center gap-3 lg:ml-auto">
            <span v-if="count !== null" class="whitespace-nowrap text-xs text-hsi-slate-light">{{ count }} {{ label }}</span>
            <button v-if="active" type="button" class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-hsi-slate hover:text-hsi-navy" @click="$emit('reset')">
                <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Réinitialiser
            </button>
            <slot name="end" />
        </div>
    </div>
</template>
