<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { tr } from '@/i18n';

/**
 * Liste déroulante avec recherche (remplace <select> pour les listes longues : pays, membres, secteurs…).
 * options : [{ value, label, hint? }] — v-model sur la valeur.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Sélectionner' },
    searchPlaceholder: { type: String, default: 'Rechercher…' },
    emptyText: { type: String, default: 'Aucun résultat' },
    clearable: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    id: { type: String, default: null },
    size: { type: String, default: 'md' }, // md | sm
});
const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const searchInput = ref(null);
const list = ref(null);
const open = ref(false);
const query = ref('');
const active = ref(0);

const normalize = (v) => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const same = (a, b) => a !== null && a !== undefined && a !== '' && String(a) === String(b);
const selected = computed(() => props.options.find((o) => same(o.value, props.modelValue)));
const filtered = computed(() => {
    const q = normalize(query.value).trim();
    if (!q) return props.options;
    return props.options.filter((o) => normalize(`${tr(o.label)} ${o.label} ${o.hint ?? ''}`).includes(q));
});

const toggle = async () => {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        query.value = '';
        active.value = Math.max(0, filtered.value.findIndex((o) => same(o.value, props.modelValue)));
        await nextTick();
        searchInput.value?.focus();
        scrollToActive();
    }
};
const choose = (option) => {
    emit('update:modelValue', option.value);
    emit('change', option.value);
    open.value = false;
};
const clear = () => { emit('update:modelValue', null); emit('change', null); };
const scrollToActive = () => nextTick(() => list.value?.querySelector(`[data-index="${active.value}"]`)?.scrollIntoView({ block: 'nearest' }));
const move = (step) => {
    if (!filtered.value.length) return;
    active.value = (active.value + step + filtered.value.length) % filtered.value.length;
    scrollToActive();
};
const enter = () => { if (filtered.value[active.value]) choose(filtered.value[active.value]); };
watch(query, () => { active.value = 0; });

const onDocClick = (event) => { if (open.value && root.value && !root.value.contains(event.target)) open.value = false; };
onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            :id="id"
            type="button"
            class="flex w-full items-center gap-2 rounded-xl border border-hsi-border bg-white text-left text-hsi-navy transition focus:border-hsi-gold focus:outline-none focus:ring-2 focus:ring-hsi-gold/20 disabled:cursor-not-allowed disabled:bg-hsi-muted"
            :class="size === 'sm' ? 'px-3 py-2 text-sm' : 'px-4 py-2.5 text-sm'"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="toggle"
            @keydown.down.prevent="open ? move(1) : toggle()"
        >
            <span class="min-w-0 flex-1 truncate" :class="selected ? '' : 'text-hsi-slate-light'">{{ selected ? selected.label : placeholder }}</span>
            <span v-if="clearable && selected && !disabled" role="button" tabindex="-1" class="rounded p-0.5 text-hsi-slate-light hover:bg-hsi-muted hover:text-hsi-navy" :aria-label="$tr('Effacer')" @click.stop="clear"><X class="h-3.5 w-3.5" /></span>
            <ChevronDown class="h-4 w-4 shrink-0 text-hsi-slate-light transition" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
        </button>
        <!-- Champ caché pour la validation native « required » -->
        <input v-if="required" :value="modelValue ?? ''" required tabindex="-1" aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-px opacity-0">

        <div v-if="open" class="absolute left-0 right-0 z-40 mt-1 min-w-[14rem] overflow-hidden rounded-xl border border-hsi-border bg-white shadow-hsiSoft">
            <div class="relative border-b border-hsi-border p-2">
                <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-hsi-slate-light" aria-hidden="true" />
                <input
                    ref="searchInput"
                    v-model="query"
                    type="text"
                    class="w-full rounded-lg border border-hsi-border bg-hsi-muted/40 py-2 pl-8 pr-3 text-sm focus:border-hsi-gold focus:bg-white focus:ring-2 focus:ring-hsi-gold/20"
                    :placeholder="searchPlaceholder"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="enter"
                    @keydown.esc.prevent="open = false"
                    @keydown.tab="open = false"
                >
            </div>
            <ul ref="list" class="max-h-64 overflow-y-auto py-1" role="listbox">
                <li
                    v-for="(option, index) in filtered"
                    :key="`${option.value}`"
                    :data-index="index"
                    role="option"
                    :aria-selected="same(option.value, modelValue)"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm"
                    :class="[index === active ? 'bg-hsi-gold/10' : '', same(option.value, modelValue) ? 'font-semibold text-hsi-navy' : 'text-hsi-navy']"
                    @mouseenter="active = index"
                    @mousedown.prevent="choose(option)"
                >
                    <span class="min-w-0 flex-1 truncate">{{ option.label }}<span v-if="option.hint" class="ml-1 text-xs font-normal text-hsi-slate-light">{{ option.hint }}</span></span>
                    <Check v-if="same(option.value, modelValue)" class="h-4 w-4 shrink-0 text-hsi-gold" aria-hidden="true" />
                </li>
                <li v-if="!filtered.length" class="px-3 py-3 text-center text-sm text-hsi-slate-light">{{ emptyText }}</li>
            </ul>
        </div>
    </div>
</template>
