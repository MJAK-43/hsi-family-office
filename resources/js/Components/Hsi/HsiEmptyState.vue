<script setup>
defineProps({
    title: {
        type: String,
        default: 'Aucun élément',
    },
    description: {
        type: String,
        default: null,
    },
    icon: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 px-6 text-center">
        <div
            v-if="icon"
            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-hsi-muted"
        >
            <component
                :is="icon"
                class="h-6 w-6 text-hsi-slate-light"
            />
        </div>
        <h4 class="font-heading text-lg font-medium text-hsi-navy">
            {{ title }}
        </h4>
        <p
            v-if="description"
            class="mt-2 max-w-sm text-sm text-hsi-slate-light"
        >
            {{ description }}
        </p>
        <div
            v-if="$slots.action"
            class="mt-6"
        >
            <slot name="action" />
        </div>
    </div>
</template>
