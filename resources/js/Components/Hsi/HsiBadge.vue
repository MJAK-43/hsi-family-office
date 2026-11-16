<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['success', 'warning', 'danger', 'info', 'neutral', 'gold'].includes(v),
    },
    size: {
        type: String,
        default: 'sm',
        validator: (v) => ['xs', 'sm'].includes(v),
    },
});

const classes = computed(() => {
    const base = 'inline-flex items-center font-medium rounded-full whitespace-nowrap';

    const sizes = {
        xs: 'px-2 py-0.5 text-[10px]',
        sm: 'px-2.5 py-0.5 text-xs',
    };

    const variants = {
        success: 'bg-green-50 text-hsi-success border border-green-200',
        warning: 'bg-amber-50 text-hsi-warning border border-amber-200',
        danger: 'bg-red-50 text-hsi-danger border border-red-200',
        info: 'bg-blue-50 text-hsi-info border border-blue-200',
        neutral: 'bg-hsi-muted text-hsi-slate border border-hsi-border',
        gold: 'bg-amber-50 text-amber-700 border border-amber-200',
    };

    return [base, sizes[props.size], variants[props.variant]].join(' ');
});
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
