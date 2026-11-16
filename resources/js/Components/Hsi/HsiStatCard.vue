<script setup>
import { computed } from 'vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';

const props = defineProps({
    icon: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    change: {
        type: String,
        default: null,
    },
    changeType: {
        type: String,
        default: 'neutral',
        validator: (v) => ['success', 'warning', 'danger', 'neutral'].includes(v),
    },
});

const changeClasses = computed(() => {
    const map = {
        success: 'text-hsi-success',
        warning: 'text-hsi-warning',
        danger: 'text-hsi-danger',
        neutral: 'text-hsi-slate-light',
    };
    return map[props.changeType];
});
</script>

<template>
    <HsiCard
        padding="md"
        hover
        class="relative overflow-hidden"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-hsi-slate-light mb-2">
                    {{ title }}
                </p>
                <p class="font-heading text-2xl md:text-3xl font-semibold text-hsi-navy truncate">
                    {{ value }}
                </p>
                <p
                    v-if="change"
                    :class="['mt-2 text-xs font-medium', changeClasses]"
                >
                    {{ change }}
                </p>
            </div>
            <div
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-hsi-muted"
            >
                <component
                    :is="icon"
                    class="h-5 w-5 text-hsi-gold"
                />
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-hsi-gold/60 via-hsi-gold to-hsi-gold/60" />
    </HsiCard>
</template>
