<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        default: '',
    },
    src: {
        type: String,
        default: null,
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },
});

const initials = computed(() => {
    if (!props.name) return '?';
    return props.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
});

const sizeClasses = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-sm',
    lg: 'h-12 w-12 text-base',
    xl: 'h-14 w-14 text-lg',
};
</script>

<template>
    <div
        :class="[
            'relative inline-flex shrink-0 items-center justify-center rounded-full overflow-hidden bg-hsi-navy-soft text-white font-medium ring-2 ring-hsi-border',
            sizeClasses[size],
        ]"
    >
        <img
            v-if="src"
            :src="src"
            :alt="name"
            class="h-full w-full object-cover"
        />
        <span v-else>{{ initials }}</span>
    </div>
</template>
