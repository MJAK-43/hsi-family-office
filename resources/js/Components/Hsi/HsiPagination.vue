<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

/**
 * Pagination commune.
 *  - Liste chargée dans la page : v-model:page, v-model:per-page, :total.
 *  - Pagination Laravel (serveur) : :paginator="projects" (liens + per_page via @per-page).
 */
const props = defineProps({
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 10 },
    total: { type: Number, default: 0 },
    paginator: { type: Object, default: null },
    perPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
    label: { type: String, default: 'éléments' },
    compact: { type: Boolean, default: false },
});
const emit = defineEmits(['update:page', 'update:perPage']);

const server = computed(() => !!props.paginator);
const current = computed(() => (server.value ? props.paginator.current_page : props.page));
const size = computed(() => Number(server.value ? props.paginator.per_page : props.perPage));
const count = computed(() => (server.value ? props.paginator.total : props.total));
const lastPage = computed(() => (server.value ? props.paginator.last_page : Math.max(1, Math.ceil(count.value / size.value))));
const from = computed(() => (count.value ? (current.value - 1) * size.value + 1 : 0));
const to = computed(() => Math.min(count.value, current.value * size.value));

// 1 … 4 5 6 … 12
const items = computed(() => {
    const last = lastPage.value;
    const pages = new Set([1, last, current.value - 1, current.value, current.value + 1]);
    const list = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
    return list.flatMap((p, i) => (i && p - list[i - 1] > 1 ? ['…', p] : [p]));
});

const urlFor = (target) => {
    if (!server.value) return null;
    const base = props.paginator.path ?? props.paginator.first_page_url?.split('?')[0];
    const params = new URLSearchParams(window.location.search);
    const pageName = (props.paginator.first_page_url ?? '').match(/[?&]([a-z_]*page)=1/)?.[1] ?? 'page';
    params.set(pageName, target);
    return `${base}?${params.toString()}`;
};

const go = (target) => {
    if (target < 1 || target > lastPage.value || target === current.value) return;
    if (server.value) router.get(urlFor(target), {}, { preserveState: true, preserveScroll: true });
    else emit('update:page', target);
};
const changeSize = (event) => emit('update:perPage', Number(event.target.value));
const sizeOptions = computed(() => [...new Set([...props.perPageOptions, size.value])].sort((a, b) => a - b));
</script>

<template>
    <div v-if="count > 0" class="flex flex-col items-center justify-between gap-3 border-t border-hsi-border pt-4 sm:flex-row" :class="compact ? 'mt-3' : 'mt-5'">
        <p class="text-xs text-hsi-slate-light">{{ from }}–{{ to }} sur {{ count }} {{ label }}</p>
        <nav v-if="lastPage > 1" class="flex items-center gap-1" :aria-label="$tr('Pagination')">
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted disabled:opacity-30" :disabled="current === 1" :aria-label="$tr('Page précédente')" @click="go(current - 1)"><ChevronLeft class="h-4 w-4" /></button>
            <template v-for="(item, i) in items" :key="i">
                <span v-if="item === '…'" class="px-1 text-xs text-hsi-slate-light">…</span>
                <component
                    :is="server ? Link : 'button'"
                    v-else
                    :href="server ? urlFor(item) : undefined"
                    :type="server ? undefined : 'button'"
                    preserve-scroll
                    preserve-state
                    class="flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-medium transition"
                    :class="item === current ? 'bg-hsi-navy text-white' : 'text-hsi-slate hover:bg-hsi-muted'"
                    :aria-current="item === current ? 'page' : undefined"
                    @click="server ? null : go(item)"
                >{{ item }}</component>
            </template>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-hsi-slate hover:bg-hsi-muted disabled:opacity-30" :disabled="current === lastPage" :aria-label="$tr('Page suivante')" @click="go(current + 1)"><ChevronRight class="h-4 w-4" /></button>
        </nav>
        <select :value="size" class="rounded-lg border border-hsi-border py-1 pl-2 pr-7 text-xs" :aria-label="$tr('Éléments par page')" @change="changeSize">
            <option v-for="n in sizeOptions" :key="n" :value="n">{{ n }} par page</option>
        </select>
    </div>
</template>
