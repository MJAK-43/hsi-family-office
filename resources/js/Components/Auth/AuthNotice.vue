<script setup>
import { AlertTriangle, CheckCircle2, Info } from 'lucide-vue-next';

defineProps({
    tone: { type: String, default: 'success' },
});
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm leading-relaxed"
        :class="{
            'border-hsi-success/25 bg-hsi-success/[0.07] text-[#166534]': tone === 'success',
            'border-hsi-warning/30 bg-hsi-warning/[0.08] text-[#92400E]': tone === 'warning',
            'border-hsi-info/25 bg-hsi-info/[0.06] text-[#1E40AF]': tone === 'info',
        }"
        :role="tone === 'warning' ? 'alert' : 'status'"
    >
        <CheckCircle2 v-if="tone === 'success'" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <AlertTriangle v-else-if="tone === 'warning'" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <Info v-else class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <div><slot /></div>
    </div>
</template>
