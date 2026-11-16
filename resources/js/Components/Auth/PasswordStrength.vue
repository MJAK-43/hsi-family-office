<script setup>
import { computed } from 'vue';
import { Check, Circle } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const { t, tm } = useI18n();

const props = defineProps({
    password: { type: String, default: '' },
});

const rules = computed(() => [
    { label: t('strength.min'), ok: props.password.length >= 12 },
    { label: t('strength.letter'), ok: /\p{L}/u.test(props.password) },
    { label: t('strength.digit'), ok: /\d/.test(props.password) },
]);

const score = computed(() => {
    const p = props.password;
    if (!p) return 0;
    let s = 0;
    if (p.length >= 12) s++;
    if (p.length >= 16) s++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/\d/.test(p) && /[^\p{L}\d]/u.test(p)) s++;
    return Math.max(1, s);
});

const levels = [
    { bar: 'bg-hsi-danger', text: 'text-hsi-danger' },
    { bar: 'bg-hsi-warning', text: 'text-hsi-warning' },
    { bar: 'bg-[#CA8A04]', text: 'text-[#A16207]' },
    { bar: 'bg-hsi-success', text: 'text-hsi-success' },
];
const level = computed(() => ({ ...levels[Math.min(score.value, 4) - 1], label: tm('strength.levels')[Math.min(score.value, 4) - 1] }));
</script>

<template>
    <div v-if="password" class="space-y-3 pt-1" aria-live="polite">
        <div class="flex items-center gap-3">
            <div class="grid flex-1 grid-cols-4 gap-1.5" aria-hidden="true">
                <span
                    v-for="i in 4"
                    :key="i"
                    class="h-1.5 rounded-full transition-colors duration-200"
                    :class="i <= score ? level.bar : 'bg-hsi-border'"
                />
            </div>
            <span class="w-24 text-right text-[13px] font-semibold" :class="level.text">{{ level.label }}</span>
        </div>
        <ul class="grid gap-1.5 sm:grid-cols-3 sm:gap-2">
            <li
                v-for="rule in rules"
                :key="rule.label"
                class="flex items-center gap-1.5 text-[12.5px]"
                :class="rule.ok ? 'text-hsi-success' : 'text-hsi-slate'"
            >
                <Check v-if="rule.ok" class="h-3.5 w-3.5" aria-hidden="true" />
                <Circle v-else class="h-3.5 w-3.5" aria-hidden="true" />
                {{ rule.label }}
            </li>
        </ul>
    </div>
</template>
