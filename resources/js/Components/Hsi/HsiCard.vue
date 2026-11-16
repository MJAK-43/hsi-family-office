<script setup>
defineProps({
    padding: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
    },
    hover: {
        type: Boolean,
        default: false,
    },
});

const paddingClasses = {
    none: '',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
};
</script>

<template>
    <div
        :class="[
            'bg-hsi-white border border-hsi-border rounded-hsi shadow-hsiSoft',
            paddingClasses[padding],
            hover ? 'transition-shadow duration-200 hover:shadow-hsi' : '',
        ]"
    >
        <slot />
    </div>
</template>
