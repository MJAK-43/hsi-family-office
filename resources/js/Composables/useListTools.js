import { computed, reactive, ref, toValue, watch } from 'vue';

/**
 * Recherche, filtres et pagination côté navigateur pour une liste déjà chargée.
 *
 *   const list = useListTools(() => props.users, {
 *       searchKeys: ['name', 'email'],
 *       filters: { status: (row, value) => row.status === value },
 *       perPage: 12,
 *   });
 *   list.search, list.filters.status, list.paged, list.page, list.pages…
 */
const normalize = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim();

const read = (row, key) => (typeof key === 'function' ? key(row) : key.split('.').reduce((v, part) => v?.[part], row));

export function useListTools(source, { searchKeys = [], filters = {}, perPage = 10 } = {}) {
    const search = ref('');
    const filterValues = reactive(Object.fromEntries(Object.keys(filters).map((key) => [key, ''])));
    const page = ref(1);
    const size = ref(perPage);

    const filtered = computed(() => {
        let rows = toValue(source) ?? [];
        const term = normalize(search.value);
        if (term) {
            rows = rows.filter((row) => searchKeys.some((key) => {
                const value = read(row, key);
                return normalize(Array.isArray(value) ? value.join(' ') : value).includes(term);
            }));
        }
        for (const [key, test] of Object.entries(filters)) {
            const value = filterValues[key];
            if (value !== '' && value !== null && value !== undefined) rows = rows.filter((row) => test(row, value));
        }
        return rows;
    });

    const total = computed(() => filtered.value.length);
    const pages = computed(() => Math.max(1, Math.ceil(total.value / size.value)));
    const paged = computed(() => filtered.value.slice((page.value - 1) * size.value, page.value * size.value));
    const active = computed(() => search.value.trim() !== '' || Object.values(filterValues).some((v) => v !== '' && v !== null));

    watch([search, size, () => ({ ...filterValues })], () => { page.value = 1; });
    watch(pages, (count) => { if (page.value > count) page.value = count; });

    const reset = () => {
        search.value = '';
        Object.keys(filterValues).forEach((key) => { filterValues[key] = ''; });
        page.value = 1;
    };

    return { search, filters: filterValues, page, perPage: size, filtered, paged, total, pages, active, reset };
}

/** Mode d'affichage (cartes ou liste) mémorisé dans le navigateur, page par page. */
export function useViewMode(key, initial = 'cards') {
    let saved = null;
    try { saved = window.localStorage.getItem(`hsi.view.${key}`); } catch { /* stockage indisponible */ }
    const mode = ref(saved === 'cards' || saved === 'list' ? saved : initial);
    watch(mode, (value) => {
        try { window.localStorage.setItem(`hsi.view.${key}`, value); } catch { /* stockage indisponible */ }
    });
    return mode;
}
