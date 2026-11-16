<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'ghost', 'danger', 'outline'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    href: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['click']);

const classes = computed(() => {
    const base = 'inline-flex items-center justify-center gap-2 font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-hsi-gold/40 disabled:opacity-50 disabled:cursor-not-allowed';

    const sizes = {
        sm: 'px-3 py-1.5 text-xs rounded-lg',
        md: 'px-5 py-2.5 text-sm rounded-xl',
        lg: 'px-6 py-3 text-base rounded-xl',
    };

    const variants = {
        primary: 'hsi-gold-gradient text-hsi-navy hover:shadow-hsiSoft font-semibold',
        secondary: 'bg-hsi-navy text-white hover:bg-hsi-navy-soft rounded-xl',
        ghost: 'text-hsi-slate hover:bg-hsi-muted rounded-xl',
        danger: 'bg-hsi-danger text-white hover:bg-red-700 rounded-xl',
        outline: 'border border-hsi-border text-hsi-navy hover:bg-hsi-muted rounded-xl',
    };

    return [base, sizes[props.size], variants[props.variant]].join(' ');
});
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        :type="href ? undefined : type"
        :disabled="disabled"
        :class="classes"
        @click="emit('click', $event)"
    >
        <slot />
    </component>
</template>
