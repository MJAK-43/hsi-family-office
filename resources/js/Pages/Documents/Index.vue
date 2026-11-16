<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Library, FileCheck, Clock3, Folder, FolderOpen, FolderPlus, FolderInput, Upload, Plus, House, ChevronRight, LayoutGrid, List,
    FileText, FileImage, FileSpreadsheet, Presentation, File as FileIcon, Download, ExternalLink, Pencil, Trash2, EllipsisVertical, Eye, X, Lock, Search,
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import Modal from '@/Components/Modal.vue';
import { useViewMode } from '@/Composables/useListTools';
import { tr } from '@/i18n';

const props = defineProps({
    folders: Array, files: Array, location: Object, breadcrumbs: Array, tree: Array,
    filters: Object, categories: Array, statuses: Array, stats: Object, can: Object,
});

const view = useViewMode('documents', 'cards');
const isLibrary = computed(() => ['root', 'folder'].includes(props.location.type));
const canEditHere = computed(() => props.can.manage && isLibrary.value);
const currentFolderId = computed(() => (props.location.type === 'folder' ? props.location.id : null));

// --- Navigation, recherche et filtres ---------------------------------------
const search = ref(props.filters.search ?? '');
const categoryId = ref(props.filters.category_id ?? '');
const status = ref(props.filters.status ?? '');
const visit = (params) => router.get(route('documents.index'), Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)), { preserveState: false, preserveScroll: false });
const open = (key) => visit({ folder: key, category_id: categoryId.value, status: status.value });
let timer;
watch(search, (value) => { clearTimeout(timer); timer = setTimeout(() => visit({ search: value.trim(), folder: value.trim() ? undefined : props.filters.folder, category_id: categoryId.value, status: status.value }), 400); });
watch([categoryId, status], () => visit({ folder: props.filters.folder, search: search.value.trim(), category_id: categoryId.value, status: status.value }));

// Pagination des fichiers (un dossier peut contenir beaucoup de pièces).
const page = ref(1);
const perPage = ref(48);
const pagedFiles = computed(() => props.files.slice((page.value - 1) * perPage.value, page.value * perPage.value));

// --- Icônes ------------------------------------------------------------------
const fileIcon = (file) => {
    if (file.preview === 'image') return FileImage;
    if (file.ext === 'pdf') return FileText;
    if (['xls', 'xlsx', 'csv'].includes(file.ext)) return FileSpreadsheet;
    if (['ppt', 'pptx'].includes(file.ext)) return Presentation;
    if (['doc', 'docx', 'txt'].includes(file.ext)) return FileText;
    return FileIcon;
};
const fileTint = (file) => ({
    pdf: 'bg-red-50 text-red-600', xls: 'bg-green-50 text-green-700', xlsx: 'bg-green-50 text-green-700', csv: 'bg-green-50 text-green-700',
    doc: 'bg-blue-50 text-blue-700', docx: 'bg-blue-50 text-blue-700', ppt: 'bg-orange-50 text-orange-600', pptx: 'bg-orange-50 text-orange-600',
}[file.ext] ?? (file.preview === 'image' ? 'bg-purple-50 text-purple-600' : 'bg-hsi-muted text-hsi-slate'));
const folderTint = (folder) => ({ gold: 'text-hsi-gold', blue: 'text-[#3B82F6]', green: 'text-hsi-success', red: 'text-hsi-danger', purple: 'text-purple-500', slate: 'text-hsi-slate' }[folder.color] ?? 'text-hsi-gold');

// --- Menus contextuels --------------------------------------------------------
const menu = ref(null); // clé de l'élément dont le menu est ouvert
const toggleMenu = (key) => { menu.value = menu.value === key ? null : key; };
const closeMenu = (event) => { if (!event.target.closest?.('[data-menu]')) menu.value = null; };
onMounted(() => document.addEventListener('click', closeMenu));
onBeforeUnmount(() => document.removeEventListener('click', closeMenu));

// --- Dossiers ----------------------------------------------------------------
const folderModal = ref(false);
const folderForm = useForm({ name: '', parent_id: null });
const newFolder = () => { folderForm.reset(); folderForm.clearErrors(); folderForm.parent_id = currentFolderId.value; folderModal.value = true; };
const createFolder = () => folderForm.post(route('document-folders.store'), { preserveScroll: true, onSuccess: () => { folderModal.value = false; } });

const renaming = ref(''); // clé du dossier en cours de renommage ('' = aucun)
const renameValue = ref('');
const startRename = (folder) => { if (!folder.editable) return; menu.value = null; renaming.value = folder.key; renameValue.value = folder.name; };
const saveRename = (folder) => {
    const name = renameValue.value.trim();
    if (renaming.value !== folder.key) return;
    renaming.value = '';
    if (name && name !== folder.name) router.patch(route('document-folders.update', folder.id), { name }, { preserveScroll: true });
};
const setColor = (folder, color) => { menu.value = null; router.patch(route('document-folders.update', folder.id), { color }, { preserveScroll: true }); };
const deleteFolder = (folder) => {
    menu.value = null;
    if (confirm(tr('Supprimer le dossier « {name} » ? Il doit être vide.', { name: folder.name }))) router.delete(route('document-folders.destroy', folder.id), { preserveScroll: true });
};

// Arborescence indentée pour « Déplacer vers… ».
const treeOptions = computed(() => {
    const byParent = {};
    (props.tree ?? []).forEach((f) => { (byParent[f.parent_id ?? 'root'] ??= []).push(f); });
    const out = [];
    const walk = (parent, depth) => (byParent[parent] ?? []).forEach((f) => { out.push({ id: f.id, label: `${'— '.repeat(depth)}${f.name}` }); walk(f.id, depth + 1); });
    walk('root', 0);
    return out;
});
const moveModal = ref(null); // { kind: 'file'|'folder', item }
const moveTarget = ref('');
const startMove = (kind, item) => { menu.value = null; moveModal.value = { kind, item }; moveTarget.value = ''; };
const confirmMove = () => {
    const { kind, item } = moveModal.value;
    const target = moveTarget.value === '' ? null : Number(moveTarget.value);
    moveModal.value = null;
    moveTo(kind, item, target);
};
const moveTo = (kind, item, folderId) => {
    if (kind === 'folder') {
        if (item.id === folderId) return;
        router.patch(route('document-folders.update', item.id), { parent_id: folderId }, { preserveScroll: true });
    } else {
        if ((item.folder_id ?? null) === folderId) return;
        router.patch(route('documents.move', item.id), { folder_id: folderId }, { preserveScroll: true });
    }
};

// --- Glisser-déposer -----------------------------------------------------------
const dragged = ref(null); // { kind, item }
const dropTarget = ref(null);
const fileDragOver = ref(false);
const onItemDragStart = (event, kind, item) => {
    dragged.value = { kind, item };
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', `${kind}:${item.id}`);
};
const onItemDragEnd = () => { dragged.value = null; dropTarget.value = null; };
const acceptsDrop = (event) => dragged.value || [...(event.dataTransfer?.types ?? [])].includes('Files');
const onFolderDragOver = (event, key) => {
    if (!props.can.manage || !acceptsDrop(event)) return;
    event.preventDefault();
    dropTarget.value = key;
};
const onFolderDrop = (event, folderId, key) => {
    event.preventDefault();
    event.stopPropagation();
    dropTarget.value = null;
    fileDragOver.value = false;
    if (dragged.value) {
        const { kind, item } = dragged.value;
        dragged.value = null;
        if (kind === 'folder' && String(item.id) === key) return;
        moveTo(kind, item, folderId);
    } else if (event.dataTransfer?.files?.length && props.can.manage) {
        collectDropped(event.dataTransfer).then((items) => openUpload(items, folderId));
    }
};
// Fichiers de l'ordinateur déposés dans la zone : import dans le dossier ouvert.
let dragDepth = 0;
const onZoneDragEnter = (event) => { if (canEditHere.value && !dragged.value && [...(event.dataTransfer?.types ?? [])].includes('Files')) { dragDepth++; fileDragOver.value = true; } };
const onZoneDragLeave = () => { dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) fileDragOver.value = false; };
const onZoneDragOver = (event) => { if (fileDragOver.value) event.preventDefault(); };
const onZoneDrop = (event) => {
    dragDepth = 0;
    if (!fileDragOver.value) return;
    event.preventDefault();
    fileDragOver.value = false;
    if (event.dataTransfer?.files?.length) {
        const folderId = currentFolderId.value;
        collectDropped(event.dataTransfer).then((items) => openUpload(items, folderId));
    }
};

// --- Import de fichiers --------------------------------------------------------
const uploadModal = ref(false);
const fileInput = ref(null);
const folderInput = ref(null);
const uploadForm = useForm({ folder_id: null, document_category_id: '', visibility: 'members' });
const uploadItems = ref([]); // [{ file, path }] : path = chemin relatif quand un dossier entier est importé
const skippedFiles = ref(0);
const uploading = ref(null); // { done, total }
const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
const isAllowed = (name) => ALLOWED_EXTENSIONS.includes((name.split('.').pop() ?? '').toLowerCase());
// Lecture récursive d'un dossier glissé depuis l'ordinateur (API FileSystemEntry).
const readEntry = (entry, out) => new Promise((resolve) => {
    if (entry.isFile) {
        entry.file((file) => { out.push({ file, path: entry.fullPath.replace(/^\/+/, '') }); resolve(); }, () => resolve());
    } else if (entry.isDirectory) {
        const reader = entry.createReader();
        const children = [];
        const next = () => reader.readEntries(async (batch) => {
            if (batch.length) { children.push(...batch); next(); return; }
            for (const child of children) await readEntry(child, out);
            resolve();
        }, () => resolve());
        next();
    } else resolve();
});
const collectDropped = async (dataTransfer) => {
    // Les entrées doivent être lues immédiatement, avant toute attente.
    const entries = [...(dataTransfer.items ?? [])].map((item) => item.webkitGetAsEntry?.()).filter(Boolean);
    if (!entries.length) return [...dataTransfer.files].map((file) => ({ file, path: file.name }));
    const out = [];
    for (const entry of entries) await readEntry(entry, out);
    return out;
};
const openUpload = (items, folderId) => {
    uploadForm.clearErrors();
    const accepted = items.filter((item) => isAllowed(item.file.name));
    skippedFiles.value = items.length - accepted.length;
    uploadItems.value = accepted.slice(0, 500);
    uploadForm.folder_id = folderId;
    uploadForm.document_category_id ||= props.categories[0]?.id ?? '';
    uploadModal.value = true;
};
const uploadFolderCount = computed(() => new Set(uploadItems.value.map((i) => i.path.split('/').slice(0, -1).join('/')).filter(Boolean)).size);
const pickFiles = () => fileInput.value?.click();
const pickFolder = () => folderInput.value?.click();
const onPicked = (event) => {
    if (event.target.files?.length) openUpload([...event.target.files].map((file) => ({ file, path: file.webkitRelativePath || file.name })), currentFolderId.value);
    event.target.value = '';
};
const folderName = (id) => (id ? (props.tree.find((f) => f.id === id)?.name ?? '') : tr('Bibliothèque'));
// Envoi par lots de 20 fichiers (limite PHP), les sous-dossiers sont recréés côté serveur.
const submitUpload = async () => {
    const items = uploadItems.value;
    if (!items.length || uploading.value) return;
    uploadForm.clearErrors();
    uploading.value = { done: 0, total: items.length };
    try {
        for (let start = 0; start < items.length; start += 20) {
            const chunk = items.slice(start, start + 20);
            const data = new FormData();
            chunk.forEach((item, i) => { data.append(`files[${i}]`, item.file); data.append(`paths[${i}]`, item.path); });
            if (uploadForm.folder_id) data.append('folder_id', uploadForm.folder_id);
            if (uploadForm.document_category_id) data.append('document_category_id', uploadForm.document_category_id);
            data.append('visibility', uploadForm.visibility);
            await window.axios.post(route('documents.upload'), data, { headers: { Accept: 'application/json' } });
            uploading.value.done += chunk.length;
        }
        uploadModal.value = false;
        uploadItems.value = [];
        router.reload({ preserveScroll: true });
    } catch (error) {
        const errors = error.response?.data?.errors;
        uploadForm.setError('files', errors ? Object.values(errors)[0][0] : (error.response?.data?.message || tr('L’import a échoué. Réessayez.')));
        router.reload({ preserveScroll: true });
    } finally {
        uploading.value = null;
    }
};
const sizeOf = (file) => (file.size < 1048576 ? `${Math.max(1, Math.round(file.size / 1024))} Ko` : `${(file.size / 1048576).toFixed(1)} Mo`);
const uploadError = computed(() => Object.values(uploadForm.errors)[0]);

// --- Aperçu ------------------------------------------------------------------
const preview = ref(null);
const previewText = ref('');
const showPreview = async (file) => {
    menu.value = null;
    preview.value = file;
    previewText.value = '';
    if (file.preview === 'text' && file.preview_url) {
        try { previewText.value = await (await fetch(file.preview_url, { credentials: 'same-origin' })).text(); } catch { previewText.value = tr('Aperçu indisponible.'); }
    }
};
const onKey = (event) => { if (event.key === 'Escape') preview.value = null; };
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
const openFile = (file) => (file.preview && file.preview_url ? showPreview(file) : router.visit(file.show_url));
const canDownload = (file) => file.download_url && (file.source !== 'library' || file.status === 'published' || props.can.manage);

const cards = [
    { title: 'Documents visibles', value: props.stats.total, icon: Library },
    { title: 'Publiés', value: props.stats.published, icon: FileCheck },
    { title: 'À valider', value: props.stats.review, icon: Clock3 },
    { title: 'Dossiers', value: props.stats.folders, icon: Folder },
];
const selectClass = 'rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm text-hsi-navy';
</script>

<template>
    <Head title="Bibliothèque documentaire" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Bibliothèque " accent="documentaire" subtitle="Classez vos documents dans des dossiers, glissez-les pour les ranger et prévisualisez-les avant de les télécharger">
            <template #actions>
                <template v-if="can.manage">
                    <HsiButton variant="outline" class="whitespace-nowrap" @click="newFolder"><FolderPlus class="h-4 w-4" /> Nouveau dossier</HsiButton>
                    <HsiButton variant="outline" class="whitespace-nowrap" @click="pickFiles"><Upload class="h-4 w-4" /> Importer</HsiButton>
                    <HsiButton variant="outline" class="whitespace-nowrap" @click="pickFolder"><FolderInput class="h-4 w-4" /> Importer un dossier</HsiButton>
                    <Link :href="route('documents.create', currentFolderId ? { folder: currentFolderId } : {})"><HsiButton class="whitespace-nowrap"><Plus class="h-4 w-4" /> Nouveau document</HsiButton></Link>
                </template>
            </template>
        </HsiPageHeader>
        <input ref="fileInput" type="file" multiple class="hidden" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" @change="onPicked">
        <input ref="folderInput" type="file" multiple webkitdirectory class="hidden" @change="onPicked">

        <div class="mb-7 grid grid-cols-2 gap-4 xl:grid-cols-4"><HsiStatCard v-for="card in cards" :key="card.title" :title="card.title" :value="String(card.value)" :icon="card.icon" /></div>

        <HsiCard padding="md" class="relative" @dragenter="onZoneDragEnter" @dragleave="onZoneDragLeave" @dragover="onZoneDragOver" @drop="onZoneDrop">
            <!-- Barre d'adresse -->
            <div class="mb-4 flex flex-col gap-3 border-b border-hsi-border pb-4 xl:flex-row xl:items-center">
                <nav class="flex min-w-0 flex-1 flex-wrap items-center gap-1 text-sm" :aria-label="$tr('Emplacement')">
                    <template v-for="(crumb, i) in breadcrumbs" :key="crumb.key">
                        <ChevronRight v-if="i" class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" />
                        <button
                            type="button"
                            class="inline-flex max-w-[14rem] items-center gap-1.5 truncate rounded-lg px-2 py-1 transition"
                            :class="[i === breadcrumbs.length - 1 && location.type !== 'search' ? 'font-semibold text-hsi-navy' : 'text-hsi-slate hover:bg-hsi-muted', dropTarget === `crumb-${crumb.key}` ? 'bg-hsi-gold/15 ring-2 ring-hsi-gold' : '']"
                            @click="open(crumb.key)"
                            @dragover="(crumb.key === '' || /^\d+$/.test(crumb.key)) && onFolderDragOver($event, `crumb-${crumb.key}`)"
                            @dragleave="dropTarget = null"
                            @drop="onFolderDrop($event, crumb.key === '' ? null : Number(crumb.key), `crumb-${crumb.key}`)"
                        ><House v-if="!i" class="h-4 w-4 shrink-0" aria-hidden="true" /><span class="truncate">{{ crumb.label }}</span></button>
                    </template>
                    <template v-if="location.type === 'search'"><ChevronRight class="h-4 w-4 text-hsi-slate-light" aria-hidden="true" /><span class="px-2 py-1 font-semibold text-hsi-navy">Résultats pour « {{ filters.search }} »</span></template>
                    <Lock v-if="location.readonly && location.type !== 'search' && location.type !== 'root'" class="ml-1 h-3.5 w-3.5 text-hsi-slate-light" :aria-label="$tr('Lecture seule')" />
                </nav>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative sm:w-64">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-hsi-slate-light" aria-hidden="true" />
                        <input v-model="search" type="search" class="w-full rounded-xl border border-hsi-border bg-hsi-muted/50 py-2 pl-9 pr-3 text-sm focus:border-hsi-gold focus:bg-white focus:ring-2 focus:ring-hsi-gold/20" :placeholder="$tr('Rechercher partout…')">
                    </div>
                    <select v-model="categoryId" :class="selectClass" :aria-label="$tr('Catégorie')"><option value="">Toutes les catégories</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                    <select v-model="status" :class="selectClass" :aria-label="$tr('Statut')"><option value="">Tous les statuts</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select>
                    <div class="inline-flex shrink-0 self-start rounded-xl border border-hsi-border bg-white p-0.5 sm:self-auto" role="group" :aria-label="$tr('Affichage')">
                        <button type="button" class="rounded-lg p-1.5" :class="view === 'cards' ? 'bg-hsi-navy text-white' : 'text-hsi-slate hover:bg-hsi-muted'" :aria-pressed="view === 'cards'" :title="$tr('Icônes')" @click="view = 'cards'"><LayoutGrid class="h-4 w-4" /></button>
                        <button type="button" class="rounded-lg p-1.5" :class="view === 'list' ? 'bg-hsi-navy text-white' : 'text-hsi-slate hover:bg-hsi-muted'" :aria-pressed="view === 'list'" :title="$tr('Détails')" @click="view = 'list'"><List class="h-4 w-4" /></button>
                    </div>
                </div>
            </div>

            <div v-if="location.type === 'project' && location.show_url" class="mb-4 flex items-center justify-between rounded-xl bg-hsi-muted/60 px-4 py-2.5 text-sm text-hsi-slate">
                <span>Pièces jointes du projet. Pour en ajouter ou en retirer, passez par la fiche du projet.</span>
                <Link :href="location.show_url" class="inline-flex items-center gap-1 font-semibold text-hsi-navy hover:underline">Ouvrir le projet <ExternalLink class="h-3.5 w-3.5" /></Link>
            </div>

            <HsiEmptyState
                v-if="!folders.length && !files.length"
                :icon="FolderOpen"
                :title="location.type === 'search' ? 'Aucun résultat' : 'Dossier vide'"
                :description="canEditHere ? 'Glissez des fichiers ici depuis votre ordinateur, ou créez un sous-dossier.' : 'Aucun document à afficher ici.'"
            />

            <!-- Vue icônes -->
            <div v-else-if="view === 'cards'" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                <div
                    v-for="folder in folders"
                    :key="`f-${folder.key}`"
                    class="group relative flex cursor-pointer flex-col items-center rounded-2xl border p-3 text-center transition"
                    :class="dropTarget === `folder-${folder.key}` ? 'border-hsi-gold bg-hsi-gold/10 ring-2 ring-hsi-gold' : 'border-transparent hover:border-hsi-border hover:bg-hsi-muted/50'"
                    :draggable="folder.editable"
                    role="button"
                    tabindex="0"
                    @click="renaming !== folder.key && open(folder.key)"
                    @keydown.enter="open(folder.key)"
                    @dragstart="onItemDragStart($event, 'folder', folder)"
                    @dragend="onItemDragEnd"
                    @dragover="folder.droppable && onFolderDragOver($event, `folder-${folder.key}`)"
                    @dragleave="dropTarget = null"
                    @drop="folder.droppable && onFolderDrop($event, folder.id, folder.key)"
                >
                    <component :is="dropTarget === `folder-${folder.key}` ? FolderOpen : Folder" class="h-16 w-16 fill-current opacity-90" :class="folderTint(folder)" aria-hidden="true" />
                    <input v-if="renaming && renaming === folder.key" v-model="renameValue" class="mt-1 w-full rounded-lg border border-hsi-gold px-2 py-1 text-center text-sm" maxlength="120" autofocus @click.stop @keydown.enter.prevent="saveRename(folder)" @keydown.esc="renaming = ''" @blur="saveRename(folder)">
                    <p v-else class="mt-1 line-clamp-2 w-full break-words text-sm font-medium text-hsi-navy">{{ folder.name }}</p>
                    <p class="text-[11px] text-hsi-slate-light">{{ folder.subtitle || `${folder.count} élément(s)` }}</p>
                    <div v-if="folder.editable" class="absolute right-1.5 top-1.5" data-menu>
                        <button type="button" class="rounded-lg p-1 text-hsi-slate opacity-0 transition hover:bg-white group-hover:opacity-100 focus:opacity-100" :class="menu === `f-${folder.key}` ? 'opacity-100' : ''" :aria-label="$tr('Actions')" @click.stop="toggleMenu(`f-${folder.key}`)"><EllipsisVertical class="h-4 w-4" /></button>
                        <div v-if="menu === `f-${folder.key}`" class="absolute right-0 z-20 mt-1 w-48 rounded-xl border border-hsi-border bg-white py-1 text-left text-sm shadow-hsiSoft" @click.stop>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-hsi-muted" @click="startRename(folder)"><Pencil class="h-4 w-4" /> Renommer</button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-hsi-muted" @click="startMove('folder', folder)"><FolderInput class="h-4 w-4" /> Déplacer vers…</button>
                            <div class="flex gap-1.5 px-3 py-2"><button v-for="c in ['gold', 'blue', 'green', 'red', 'purple', 'slate']" :key="c" type="button" class="h-5 w-5 rounded-full" :class="{ gold: 'bg-hsi-gold', blue: 'bg-[#3B82F6]', green: 'bg-hsi-success', red: 'bg-hsi-danger', purple: 'bg-purple-500', slate: 'bg-hsi-slate' }[c]" :aria-label="c" @click="setColor(folder, c)" /></div>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-hsi-danger hover:bg-red-50" @click="deleteFolder(folder)"><Trash2 class="h-4 w-4" /> Supprimer</button>
                        </div>
                    </div>
                </div>

                <div
                    v-for="file in pagedFiles"
                    :key="file.key"
                    class="group relative flex cursor-pointer flex-col items-center rounded-2xl border border-transparent p-3 text-center transition hover:border-hsi-border hover:bg-hsi-muted/50"
                    :class="dragged?.item?.key === file.key ? 'opacity-50' : ''"
                    :draggable="can.manage && file.movable"
                    role="button"
                    tabindex="0"
                    @click="openFile(file)"
                    @keydown.enter="openFile(file)"
                    @dragstart="onItemDragStart($event, 'file', file)"
                    @dragend="onItemDragEnd"
                >
                    <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl" :class="file.preview === 'image' && file.preview_url ? 'bg-hsi-muted' : fileTint(file)">
                        <img v-if="file.preview === 'image' && file.preview_url" :src="file.preview_url" alt="" loading="lazy" class="h-full w-full object-cover">
                        <component :is="fileIcon(file)" v-else class="h-8 w-8" aria-hidden="true" />
                    </div>
                    <p class="mt-2 line-clamp-2 w-full break-words text-sm font-medium text-hsi-navy" :title="file.title">{{ file.title }}</p>
                    <p class="text-[11px] uppercase text-hsi-slate-light">{{ file.ext || '—' }} · {{ file.size }}</p>
                    <HsiBadge v-if="file.source === 'library' && file.status !== 'published'" :variant="file.status_variant" size="xs" class="mt-1">{{ file.status_label }}</HsiBadge>
                    <div class="absolute right-1.5 top-1.5" data-menu>
                        <button type="button" class="rounded-lg p-1 text-hsi-slate opacity-0 transition hover:bg-white group-hover:opacity-100 focus:opacity-100" :class="menu === file.key ? 'opacity-100' : ''" :aria-label="$tr('Actions')" @click.stop="toggleMenu(file.key)"><EllipsisVertical class="h-4 w-4" /></button>
                        <div v-if="menu === file.key" class="absolute right-0 z-20 mt-1 w-48 rounded-xl border border-hsi-border bg-white py-1 text-left text-sm shadow-hsiSoft" @click.stop>
                            <button v-if="file.preview && file.preview_url" type="button" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-hsi-muted" @click="showPreview(file)"><Eye class="h-4 w-4" /> Aperçu</button>
                            <a v-if="canDownload(file)" :href="file.download_url" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-hsi-muted"><Download class="h-4 w-4" /> Télécharger</a>
                            <Link :href="file.show_url" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-hsi-muted"><ExternalLink class="h-4 w-4" /> Détails</Link>
                            <button v-if="can.manage && file.movable" type="button" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-hsi-muted" @click="startMove('file', file)"><FolderInput class="h-4 w-4" /> Déplacer vers…</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vue détails -->
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead><tr class="border-b border-hsi-border text-left text-[11px] uppercase tracking-wider text-hsi-slate-light"><th class="px-3 py-2.5">Nom</th><th>Catégorie</th><th>Statut</th><th>Taille</th><th>Modifié le</th><th class="text-right" /></tr></thead>
                    <tbody>
                        <tr
                            v-for="folder in folders"
                            :key="`fl-${folder.key}`"
                            class="cursor-pointer border-b border-hsi-border/50 hover:bg-hsi-muted/40"
                            :class="dropTarget === `folder-${folder.key}` ? 'bg-hsi-gold/10' : ''"
                            :draggable="folder.editable"
                            @click="open(folder.key)"
                            @dragstart="onItemDragStart($event, 'folder', folder)"
                            @dragend="onItemDragEnd"
                            @dragover="folder.droppable && onFolderDragOver($event, `folder-${folder.key}`)"
                            @dragleave="dropTarget = null"
                            @drop="folder.droppable && onFolderDrop($event, folder.id, folder.key)"
                        >
                            <td class="px-3 py-2.5"><span class="inline-flex items-center gap-2 font-medium text-hsi-navy"><Folder class="h-5 w-5 fill-current" :class="folderTint(folder)" aria-hidden="true" /> {{ folder.name }}</span></td>
                            <td class="text-hsi-slate-light">Dossier</td><td /><td class="text-hsi-slate-light">{{ folder.count }} élément(s)</td><td /><td class="pr-3 text-right"><button v-if="folder.editable" type="button" class="p-1 text-hsi-slate hover:text-hsi-navy" :aria-label="$tr('Renommer')" @click.stop="startRename(folder)"><Pencil class="h-4 w-4" /></button></td>
                        </tr>
                        <tr v-for="file in pagedFiles" :key="`l-${file.key}`" class="border-b border-hsi-border/50 hover:bg-hsi-muted/40" :draggable="can.manage && file.movable" @dragstart="onItemDragStart($event, 'file', file)" @dragend="onItemDragEnd">
                            <td class="px-3 py-2.5"><button type="button" class="inline-flex items-center gap-2 text-left font-medium text-hsi-navy hover:underline" @click="openFile(file)"><span class="flex h-7 w-7 items-center justify-center rounded-lg" :class="fileTint(file)"><component :is="fileIcon(file)" class="h-4 w-4" aria-hidden="true" /></span>{{ file.title }}</button><p class="pl-9 text-xs text-hsi-slate-light">{{ file.owner }}</p></td>
                            <td>{{ file.category || '—' }}</td><td><HsiBadge :variant="file.status_variant" size="xs">{{ file.status_label }}</HsiBadge></td><td>{{ file.size }}</td><td class="whitespace-nowrap">{{ file.updated_at }}</td>
                            <td class="pr-3"><div class="flex justify-end gap-1"><button v-if="file.preview && file.preview_url" type="button" class="p-1.5 text-hsi-slate hover:text-hsi-gold" :title="$tr('Aperçu')" @click="showPreview(file)"><Eye class="h-4 w-4" /></button><a v-if="canDownload(file)" :href="file.download_url" class="p-1.5 text-hsi-slate hover:text-hsi-gold" :title="$tr('Télécharger')"><Download class="h-4 w-4" /></a><button v-if="can.manage && file.movable" type="button" class="p-1.5 text-hsi-slate hover:text-hsi-gold" :title="$tr('Déplacer vers…')" @click="startMove('file', file)"><FolderInput class="h-4 w-4" /></button><Link :href="file.show_url" class="p-1.5 text-hsi-slate hover:text-hsi-gold" :title="$tr('Détails')"><ExternalLink class="h-4 w-4" /></Link></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <HsiPagination v-if="files.length > 48" v-model:page="page" v-model:per-page="perPage" :total="files.length" :per-page-options="[48, 96, 192]" label="fichiers" />

            <p v-if="canEditHere && (folders.length || files.length)" class="mt-4 text-center text-xs text-hsi-slate-light">Astuce : glissez un document sur un dossier (ou sur le chemin en haut) pour le ranger ; déposez des fichiers de votre ordinateur pour les importer ici.</p>

            <!-- Calque de dépôt -->
            <div v-if="fileDragOver" class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-hsi-gold bg-hsi-gold/10 backdrop-blur-[1px]">
                <Upload class="h-10 w-10 text-hsi-gold" aria-hidden="true" />
                <p class="mt-2 font-semibold text-hsi-navy">Déposez les fichiers pour les importer dans « {{ location.name }} »</p>
            </div>
        </HsiCard>

        <!-- Nouveau dossier -->
        <Modal :show="folderModal" max-width="md" @close="folderModal = false">
            <form class="space-y-4 p-6" @submit.prevent="createFolder">
                <h2 class="flex items-center gap-2 font-heading text-lg font-semibold text-hsi-navy"><FolderPlus class="h-5 w-5 text-hsi-gold" /> Nouveau dossier</h2>
                <p class="text-sm text-hsi-slate">Dans : <strong>{{ location.name }}</strong></p>
                <input v-model="folderForm.name" required maxlength="120" class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm" :placeholder="$tr('Nom du dossier')" autofocus>
                <p v-if="folderForm.errors.name" class="text-sm text-hsi-danger">{{ folderForm.errors.name }}</p>
                <div class="flex justify-end gap-2"><HsiButton type="button" variant="ghost" @click="folderModal = false">Annuler</HsiButton><HsiButton type="submit" :disabled="folderForm.processing">Créer</HsiButton></div>
            </form>
        </Modal>

        <!-- Déplacer -->
        <Modal :show="!!moveModal" max-width="md" @close="moveModal = null">
            <form v-if="moveModal" class="space-y-4 p-6" @submit.prevent="confirmMove">
                <h2 class="flex items-center gap-2 font-heading text-lg font-semibold text-hsi-navy"><FolderInput class="h-5 w-5 text-hsi-gold" /> Déplacer « {{ moveModal.item.title || moveModal.item.name }} »</h2>
                <select v-model="moveTarget" class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm" size="8">
                    <option value="">Bibliothèque (racine)</option>
                    <option v-for="f in treeOptions" :key="f.id" :value="f.id" :disabled="moveModal.kind === 'folder' && f.id === moveModal.item.id">{{ f.label }}</option>
                </select>
                <div class="flex justify-end gap-2"><HsiButton type="button" variant="ghost" @click="moveModal = null">Annuler</HsiButton><HsiButton type="submit">Déplacer</HsiButton></div>
            </form>
        </Modal>

        <!-- Import -->
        <Modal :show="uploadModal" max-width="lg" @close="uploadModal = false">
            <form class="space-y-4 p-6" @submit.prevent="submitUpload">
                <h2 class="flex items-center gap-2 font-heading text-lg font-semibold text-hsi-navy"><Upload class="h-5 w-5 text-hsi-gold" /> Importer {{ uploadItems.length }} fichier(s)</h2>
                <p class="text-sm text-hsi-slate">Dossier : <strong>{{ folderName(uploadForm.folder_id) }}</strong>. Chaque fichier devient un document en brouillon, à publier ensuite.</p>
                <p v-if="uploadFolderCount" class="text-sm text-hsi-slate">Les sous-dossiers ({{ uploadFolderCount }}) sont recréés à l’identique.</p>
                <p v-if="skippedFiles" class="text-xs text-hsi-warning">{{ skippedFiles }} fichier(s) ignoré(s) : format non autorisé (PDF, images, Office, texte, CSV).</p>
                <ul class="max-h-48 space-y-1 overflow-y-auto rounded-xl bg-hsi-muted/50 p-2 text-sm">
                    <li v-for="(item, i) in uploadItems" :key="i" class="flex justify-between gap-3 px-2 py-1"><span class="truncate" :title="item.path">{{ item.path || item.file.name }}</span><span class="shrink-0 text-xs text-hsi-slate-light">{{ sizeOf(item.file) }}</span></li>
                </ul>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium text-hsi-slate">Catégorie<select v-model="uploadForm.document_category_id" class="mt-1 w-full rounded-xl border border-hsi-border px-3 py-2 text-sm"><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></label>
                    <label class="text-sm font-medium text-hsi-slate">Visibilité<select v-model="uploadForm.visibility" class="mt-1 w-full rounded-xl border border-hsi-border px-3 py-2 text-sm"><option value="members">Tous les membres autorisés</option><option value="restricted">Accès restreint</option><option value="public">Public interne</option></select></label>
                </div>
                <p v-if="uploadError" class="text-sm text-hsi-danger">{{ uploadError }}</p>
                <div class="flex justify-end gap-2"><HsiButton type="button" variant="ghost" @click="uploadModal = false">Annuler</HsiButton><HsiButton type="submit" :disabled="!!uploading || !uploadItems.length">{{ uploading ? `${$tr('Envoi…')} ${uploading.done}/${uploading.total}` : $tr('Importer') }}</HsiButton></div>
            </form>
        </Modal>

        <!-- Aperçu -->
        <Teleport to="body">
            <div v-if="preview" class="fixed inset-0 z-50 flex flex-col bg-hsi-navy/80 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true" @click.self="preview = null">
                <div class="mx-auto flex h-full w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center gap-3 border-b border-hsi-border px-4 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="fileTint(preview)"><component :is="fileIcon(preview)" class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1"><p class="truncate font-semibold text-hsi-navy">{{ preview.title }}</p><p class="truncate text-xs text-hsi-slate-light">{{ preview.file_name }} · {{ preview.size }}</p></div>
                        <a v-if="canDownload(preview)" :href="preview.download_url"><HsiButton size="sm"><Download class="h-4 w-4" /> <span class="hidden sm:inline">Télécharger</span></HsiButton></a>
                        <a v-if="preview.preview === 'pdf'" :href="preview.preview_url" target="_blank" rel="noopener" class="hidden md:block"><HsiButton size="sm" variant="ghost">Plein écran</HsiButton></a>
                        <Link :href="preview.show_url"><HsiButton size="sm" variant="outline"><ExternalLink class="h-4 w-4" /> <span class="hidden sm:inline">Détails</span></HsiButton></Link>
                        <button type="button" class="rounded-lg p-2 text-hsi-slate hover:bg-hsi-muted" :aria-label="$tr('Fermer')" @click="preview = null"><X class="h-5 w-5" /></button>
                    </div>
                    <div class="min-h-0 flex-1 bg-hsi-muted/40">
                        <object v-if="preview.preview === 'pdf'" :data="preview.preview_url" type="application/pdf" class="h-full w-full" :aria-label="preview.title">
                            <!-- Navigateurs sans lecteur PDF intégré (mobiles notamment) -->
                            <div class="flex h-full flex-col items-center justify-center gap-4 p-6 text-center">
                                <FileText class="h-12 w-12 text-red-500" aria-hidden="true" />
                                <p class="max-w-sm text-sm text-hsi-slate">Votre navigateur ne peut pas afficher le PDF ici.</p>
                                <div class="flex flex-wrap justify-center gap-2">
                                    <a :href="preview.preview_url" target="_blank" rel="noopener"><HsiButton variant="outline"><ExternalLink class="h-4 w-4" /> Ouvrir dans un nouvel onglet</HsiButton></a>
                                    <a v-if="canDownload(preview)" :href="preview.download_url"><HsiButton><Download class="h-4 w-4" /> Télécharger</HsiButton></a>
                                </div>
                            </div>
                        </object>
                        <div v-else-if="preview.preview === 'image'" class="flex h-full items-center justify-center overflow-auto p-4"><img :src="preview.preview_url" :alt="preview.title" class="max-h-full max-w-full rounded-lg shadow"></div>
                        <pre v-else-if="preview.preview === 'text'" class="h-full overflow-auto whitespace-pre-wrap p-5 font-mono text-xs text-hsi-navy">{{ previewText || '…' }}</pre>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
