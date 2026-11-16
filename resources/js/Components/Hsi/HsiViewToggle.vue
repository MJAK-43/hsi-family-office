<script setup>
import { LayoutGrid, List } from 'lucide-vue-next';

/** Bascule cartes / liste. */
defineProps({ modelValue: { type: String, default: 'cards' } });
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="inline-flex rounded-xl border border-hsi-border bg-white p-0.5" role="group" :aria-label="$tr('Affichage')">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
            :class="modelValue === 'cards' ? 'bg-hsi-navy text-white' : 'text-hsi-slate hover:bg-hsi-muted'"
            :aria-pressed="modelValue === 'cards'"
            @click="$emit('update:modelValue', 'cards')"
        ><LayoutGrid class="h-3.5 w-3.5" aria-hidden="true" /> Cartes</button>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
            :class="modelValue === 'list' ? 'bg-hsi-navy text-white' : 'text-hsi-slate hover:bg-hsi-muted'"
            :aria-pressed="modelValue === 'list'"
            @click="$emit('update:modelValue', 'list')"
        ><List class="h-3.5 w-3.5" aria-hidden="true" /> Liste</button>
    </div>
</template>
