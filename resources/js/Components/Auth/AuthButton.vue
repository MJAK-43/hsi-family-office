<script setup>
import { ArrowRight, Loader2 } from 'lucide-vue-next';

defineProps({
    loading: { type: Boolean, default: false },
    loadingText: { type: String, default: 'Un instant…' },
    arrow: { type: Boolean, default: true },
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'submit' },
});
</script>

<template>
    <button
        :type="type"
        :disabled="loading"
        :aria-busy="loading"
        class="group inline-flex h-[54px] w-full items-center justify-center gap-2 rounded-2xl px-6 text-[15px] font-semibold transition duration-150 focus:outline-none focus-visible:ring-4 disabled:cursor-wait"
        :class="variant === 'primary'
            ? 'bg-gradient-to-b from-[#0E2F57] to-hsi-navy text-white shadow-[0_12px_28px_-10px_rgba(7,27,52,0.55)] hover:from-[#123766] hover:to-[#0A2140] focus-visible:ring-hsi-gold/40 disabled:opacity-80'
            : 'border border-hsi-border bg-white text-hsi-navy hover:border-hsi-navy/30 hover:bg-hsi-cream focus-visible:ring-hsi-gold/30'"
    >
        <Loader2 v-if="loading" class="h-5 w-5 animate-spin" aria-hidden="true" />
        <span>{{ loading ? loadingText : '' }}<slot v-if="!loading" /></span>
        <ArrowRight
            v-if="arrow && !loading"
            class="h-5 w-5 transition-transform duration-150 group-hover:translate-x-0.5 motion-reduce:transition-none"
            aria-hidden="true"
        />
    </button>
</template>
