<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="mb-4">
        <h3 class="font-heading text-lg font-semibold text-hsi-navy">
            {{ title }}
        </h3>
        <p
            v-if="subtitle"
            class="mt-1 text-sm text-hsi-slate-light"
        >
            {{ subtitle }}
        </p>
    </div>
</template>
