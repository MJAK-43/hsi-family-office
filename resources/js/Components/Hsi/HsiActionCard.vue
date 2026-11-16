<script setup>
import { ChevronRight } from 'lucide-vue-next';

defineProps({
    icon: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    showArrow: {
        type: Boolean,
        default: true,
    },
    href: {
        type: String,
        default: null,
    },
});

defineEmits(['click']);
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        type="button"
        class="group flex w-full flex-col items-center gap-3 rounded-hsi border border-hsi-border bg-hsi-white p-5 text-center shadow-hsiSoft transition-all duration-200 hover:border-hsi-gold/30 hover:shadow-hsi focus:outline-none focus:ring-2 focus:ring-hsi-gold/30"
        @click="$emit('click')"
    >
        <div
            v-if="icon"
            class="flex h-12 w-12 items-center justify-center rounded-xl bg-hsi-muted transition-colors group-hover:bg-amber-50"
        >
            <component
                :is="icon"
                class="h-5 w-5 text-hsi-gold"
            />
        </div>
        <div>
            <p class="text-sm font-semibold text-hsi-navy">
                {{ title }}
            </p>
            <p
                v-if="description"
                class="mt-1 text-xs text-hsi-slate-light"
            >
                {{ description }}
            </p>
        </div>
        <ChevronRight
            v-if="showArrow"
            class="h-4 w-4 text-hsi-slate-light opacity-0 transition-all group-hover:opacity-100 group-hover:translate-x-0.5"
        />
    </component>
</template>
