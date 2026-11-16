<script setup>
import HsiButton from '@/Components/Hsi/HsiButton.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    accent: {
        type: String,
        default: null,
    },
    subtitle: {
        type: String,
        default: null,
    },
    actionLabel: {
        type: String,
        default: null,
    },
    actionVariant: {
        type: String,
        default: 'primary',
    },
});

defineEmits(['action']);
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-8">
        <div>
            <h1 class="font-heading text-2xl md:text-3xl font-semibold text-hsi-navy">
                {{ title }}<span
                    v-if="accent"
                    class="text-hsi-gold"
                >{{ accent }}</span>
            </h1>
            <p
                v-if="subtitle"
                class="mt-1.5 text-sm md:text-base text-hsi-slate-light"
            >
                {{ subtitle }}
            </p>
        </div>
        <div
            v-if="actionLabel"
            class="shrink-0"
        >
            <HsiButton
                :variant="actionVariant"
                @click="$emit('action')"
            >
                {{ actionLabel }}
            </HsiButton>
        </div>
        <slot name="actions" />
    </div>
</template>
