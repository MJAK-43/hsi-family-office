<script setup>
import { Search } from 'lucide-vue-next';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Rechercher...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative w-full">
        <Search
            class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-hsi-slate-light pointer-events-none"
        />
        <input
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-full rounded-xl border border-hsi-border bg-hsi-muted/50 py-3 pl-11 pr-4 text-sm text-hsi-navy placeholder:text-hsi-slate-light focus:border-hsi-gold focus:bg-white focus:outline-none focus:ring-2 focus:ring-hsi-gold/20 transition-all"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
