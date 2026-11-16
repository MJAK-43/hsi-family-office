<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-hsi-cream pt-6 sm:justify-center sm:pt-0">
        <div class="text-center mb-6">
            <Link
                href="/"
                class="inline-flex flex-col items-center gap-2"
            >
                <div class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-hsi-gold/40 bg-hsi-navy">
                    <span class="font-heading text-xl font-bold text-hsi-gold">HSI</span>
                </div>
                <p class="text-[10px] font-medium uppercase tracking-[0.25em] text-hsi-slate-light">
                    Family Office
                </p>
            </Link>
        </div>

        <div class="mt-2 w-full overflow-hidden bg-hsi-white border border-hsi-border px-6 py-6 shadow-hsiSoft sm:max-w-md sm:rounded-hsi">
            <slot />
        </div>

        <p class="mt-8 text-xs text-hsi-slate-light italic">
            Foi · Sagesse · Travail · Héritage
        </p>
    </div>
</template>
