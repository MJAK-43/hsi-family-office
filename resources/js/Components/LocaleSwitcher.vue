<script setup>
import { useI18n } from '@/i18n';

defineProps({
    // « dark » pour un fond sombre (panneau de marque), « light » pour un fond clair.
    tone: { type: String, default: 'light' },
});

const { locale, setLocale, t } = useI18n();
const options = [
    { value: 'fr', label: 'FR', name: 'Français' },
    { value: 'en', label: 'EN', name: 'English' },
];
</script>

<template>
    <div
        class="inline-flex items-center rounded-full p-1 text-xs font-semibold"
        :class="tone === 'dark' ? 'bg-white/10 ring-1 ring-white/15' : 'bg-hsi-muted ring-1 ring-hsi-border'"
        role="group"
        :aria-label="t('common.language')"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :lang="option.value"
            :aria-pressed="locale === option.value"
            :title="option.name"
            class="rounded-full px-3 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
            :class="locale === option.value
                ? (tone === 'dark' ? 'bg-white text-hsi-navy shadow-sm' : 'bg-hsi-navy text-white shadow-sm')
                : (tone === 'dark' ? 'text-white/70 hover:text-white' : 'text-hsi-slate hover:text-hsi-navy')"
            @click="locale !== option.value && setLocale(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
