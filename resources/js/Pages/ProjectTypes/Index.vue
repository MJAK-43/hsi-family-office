<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, Bot, Copy, Pencil, Plus, Save, Trash2 } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import { tr } from '@/i18n';

const props = defineProps({ types: Array, autoChecks: Array, canManage: Boolean, criteriaLibrary: { type: Array, default: () => [] } });

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm text-hsi-navy focus:border-hsi-gold focus:ring-2 focus:ring-hsi-gold/20';
const emptyType = () => ({ name: '', description: '', is_loan: false, requires_personal_contribution: false, is_active: true, copy_criteria_ids: [] });
const typeForm = useForm(emptyType());
const editingType = ref(null);
const newType = () => { editingType.value = 'new'; typeForm.defaults(emptyType()); typeForm.reset(); };
const editType = (type) => {
    editingType.value = type.id;
    typeForm.defaults({ name: type.name, description: type.description ?? '', is_loan: type.is_loan, requires_personal_contribution: type.requires_personal_contribution, is_active: type.is_active, copy_criteria_ids: [] });
    typeForm.reset();
};
const saveType = () => {
    const options = { preserveScroll: true, onSuccess: () => { editingType.value = null; } };
    editingType.value === 'new' ? typeForm.post(route('project-types.store'), options) : typeForm.put(route('project-types.update', editingType.value), options);
};
const deleteType = (type) => { if (confirm(tr('Supprimer le type « {name} » ?', { name: type.name }))) router.delete(route('project-types.destroy', type.id), { preserveScroll: true }); };

const emptyCriterion = () => ({ label: '', description: '', is_blocking: true, auto_check: null, is_active: true });
const criterionForm = useForm(emptyCriterion());
const editingCriterion = ref(null); // { typeId, id|'new' }
const newCriterion = (type) => { editingCriterion.value = { typeId: type.id, id: 'new' }; criterionForm.defaults(emptyCriterion()); criterionForm.reset(); };
const editCriterion = (type, criterion) => {
    editingCriterion.value = { typeId: type.id, id: criterion.id };
    criterionForm.defaults({ label: criterion.label, description: criterion.description ?? '', is_blocking: criterion.is_blocking, auto_check: criterion.auto_check, is_active: criterion.is_active });
    criterionForm.reset();
};
const saveCriterion = () => {
    const { typeId, id } = editingCriterion.value;
    const options = { preserveScroll: true, onSuccess: () => { editingCriterion.value = null; } };
    id === 'new' ? criterionForm.post(route('project-types.criteria.store', typeId), options) : criterionForm.put(route('project-types.criteria.update', id), options);
};
const moveCriterion = (criterion, direction) => router.patch(route('project-types.criteria.move', criterion.id), { direction }, { preserveScroll: true });
const deleteCriterion = (criterion) => { if (confirm(tr('Supprimer le critère « {name} » ?', { name: criterion.label }))) router.delete(route('project-types.criteria.destroy', criterion.id), { preserveScroll: true }); };
const autoLabel = (value) => props.autoChecks.find((c) => c.value === value)?.label;
// Bibliothèque des critères déjà définis (pour un nouveau type ou un type existant).
const libraryQuery = ref('');
const libraryType = ref('');
const libraryExclude = ref(null);
const normalize = (v) => String(v ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const filteredLibrary = computed(() => props.criteriaLibrary.filter((c) => (!libraryType.value || c.type_id === Number(libraryType.value))
    && (!libraryExclude.value || c.type_id !== libraryExclude.value.id)
    && (!libraryQuery.value || normalize(`${c.label} ${c.description ?? ''}`).includes(normalize(libraryQuery.value)))));
const selectAllVisible = () => {
    const target = importTarget.value ? importForm.criteria_ids : typeForm.copy_criteria_ids;
    filteredLibrary.value.forEach((c) => { if (!target.includes(c.id)) target.push(c.id); });
};
const importTarget = ref(null);
const importForm = useForm({ criteria_ids: [] });
const openImport = (type) => { importTarget.value = type; libraryExclude.value = type; libraryQuery.value = ''; libraryType.value = ''; importForm.reset(); };
const closeImport = () => { importTarget.value = null; libraryExclude.value = null; };
const alreadyIn = (type, criterion) => type.criteria.some((c) => normalize(c.label).trim() === normalize(criterion.label).trim());
const submitImport = () => importForm.post(route('project-types.criteria.import', importTarget.value.id), { preserveScroll: true, onSuccess: closeImport });
const isEditingCriterion = (type, id) => editingCriterion.value?.typeId === type.id && editingCriterion.value?.id === id;
</script>

<template>
    <Head title="Types de projets" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Types de " accent="projets" subtitle="Chaque type a sa liste de critères, vérifiés par le comité d’investissement">
            <template #actions>
                <Link :href="route('projects.index')"><HsiButton variant="ghost"><ArrowLeft class="h-4 w-4" aria-hidden="true" /> Projets</HsiButton></Link>
                <HsiButton v-if="canManage && editingType !== 'new'" @click="newType"><Plus class="h-4 w-4" aria-hidden="true" /> Nouveau type</HsiButton>
            </template>
        </HsiPageHeader>

        <p class="mb-6 rounded-2xl border border-hsi-border bg-white p-4 text-sm text-hsi-slate">
            La liste de critères est copiée dans chaque dossier au moment du dépôt : la modifier ne change pas les dossiers déjà déposés.
            Un critère « bloquant » doit être validé (ou non applicable) pour que le comité puisse accepter la demande.
            Les critères avec vérification automatique sont cochés par l’application à partir de la situation du demandeur ; le comité peut toujours corriger.
        </p>

        <HsiCard v-if="editingType === 'new'" padding="lg" class="mb-6">
            <form class="space-y-4" @submit.prevent="saveType">
                <h2 class="font-semibold text-hsi-navy">Nouveau type de projet</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="type-name">Nom *</label><input id="type-name" v-model="typeForm.name" required maxlength="100" :class="inputClass"><p v-if="typeForm.errors.name" class="mt-1 text-xs text-hsi-danger">{{ typeForm.errors.name }}</p></div>
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="type-desc">Description</label><input id="type-desc" v-model="typeForm.description" maxlength="2000" :class="inputClass"></div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-hsi-navy">
                    <label class="inline-flex items-center gap-2"><input v-model="typeForm.is_loan" type="checkbox" class="h-4 w-4 rounded"> Prêt remboursable (échéancier, intérêts, pénalités)</label>
                    <label class="inline-flex items-center gap-2"><input v-model="typeForm.requires_personal_contribution" type="checkbox" class="h-4 w-4 rounded"> Apport personnel de 50 % exigé</label>
                </div>
                <div v-if="criteriaLibrary.length" class="rounded-2xl border border-hsi-border bg-hsi-muted/30 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="flex items-center gap-2 text-sm font-semibold text-hsi-navy"><Copy class="h-4 w-4 text-hsi-gold" aria-hidden="true" /> Reprendre des critères existants</p>
                            <p class="text-xs text-hsi-slate-light">Cochez les critères déjà utilisés par d’autres types : ils seront copiés dans le nouveau type (modifiables ensuite).</p>
                        </div>
                        <span class="text-xs font-medium text-hsi-slate">{{ typeForm.copy_criteria_ids.length }} sélectionné(s)</span>
                    </div>
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row">
                        <input v-model="libraryQuery" type="search" :class="inputClass" :placeholder="$tr('Filtrer les critères…')">
                        <select v-model="libraryType" :class="[inputClass, 'sm:w-56']"><option value="">Tous les types</option><option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option></select>
                    </div>
                    <div class="max-h-72 space-y-1.5 overflow-y-auto pr-1">
                        <label v-for="c in filteredLibrary" :key="c.id" class="flex cursor-pointer items-start gap-3 rounded-xl border bg-white p-2.5 text-sm" :class="typeForm.copy_criteria_ids.includes(c.id) ? 'border-hsi-gold bg-hsi-gold/5' : 'border-hsi-border'">
                            <input v-model="typeForm.copy_criteria_ids" type="checkbox" :value="c.id" class="mt-0.5 h-4 w-4 rounded">
                            <span class="min-w-0 flex-1">
                                <span class="font-medium text-hsi-navy">{{ c.label }}</span>
                                <span v-if="c.is_blocking" class="ml-1 rounded-full bg-hsi-navy/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider">Bloquant</span>
                                <span class="block text-xs text-hsi-slate-light">{{ c.type_name }}<template v-if="c.auto_check"> · {{ autoLabel(c.auto_check) }}</template></span>
                            </span>
                        </label>
                        <p v-if="!filteredLibrary.length" class="py-3 text-center text-xs text-hsi-slate-light">Aucun critère ne correspond.</p>
                    </div>
                    <button v-if="filteredLibrary.length" type="button" class="mt-2 text-xs font-semibold text-hsi-navy hover:underline" @click="selectAllVisible">Tout cocher (liste filtrée)</button>
                </div>
                <div class="flex gap-2"><HsiButton type="submit" :disabled="typeForm.processing"><Save class="h-4 w-4" aria-hidden="true" /> Créer<template v-if="typeForm.copy_criteria_ids.length"> avec {{ typeForm.copy_criteria_ids.length }} critère(s)</template></HsiButton><HsiButton type="button" variant="ghost" @click="editingType = null">Annuler</HsiButton></div>
            </form>
        </HsiCard>

        <div class="space-y-6">
            <HsiCard v-for="type in types" :key="type.id" padding="lg">
                <form v-if="editingType === type.id" class="mb-4 space-y-4" @submit.prevent="saveType">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="mb-1 block text-sm font-medium text-hsi-slate" :for="`tn-${type.id}`">Nom *</label><input :id="`tn-${type.id}`" v-model="typeForm.name" required maxlength="100" :class="inputClass"></div>
                        <div><label class="mb-1 block text-sm font-medium text-hsi-slate" :for="`td-${type.id}`">Description</label><input :id="`td-${type.id}`" v-model="typeForm.description" maxlength="2000" :class="inputClass"></div>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-hsi-navy">
                        <label class="inline-flex items-center gap-2"><input v-model="typeForm.is_loan" type="checkbox" class="h-4 w-4 rounded"> Prêt remboursable</label>
                        <label class="inline-flex items-center gap-2"><input v-model="typeForm.requires_personal_contribution" type="checkbox" class="h-4 w-4 rounded"> Apport personnel de 50 % exigé</label>
                        <label class="inline-flex items-center gap-2"><input v-model="typeForm.is_active" type="checkbox" class="h-4 w-4 rounded"> Proposé aux membres</label>
                    </div>
                    <div class="flex gap-2"><HsiButton type="submit" :disabled="typeForm.processing"><Save class="h-4 w-4" aria-hidden="true" /> Enregistrer</HsiButton><HsiButton type="button" variant="ghost" @click="editingType = null">Annuler</HsiButton></div>
                </form>
                <div v-else class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-heading text-xl font-semibold text-hsi-navy">{{ type.name }}</h2>
                            <HsiBadge v-if="type.is_loan" variant="gold">Prêt</HsiBadge>
                            <HsiBadge v-if="type.requires_personal_contribution" variant="info">Apport 50 %</HsiBadge>
                            <HsiBadge :variant="type.is_active ? 'success' : 'neutral'">{{ type.is_active ? 'Proposé' : 'Désactivé' }}</HsiBadge>
                        </div>
                        <p v-if="type.description" class="mt-1 text-sm text-hsi-slate">{{ type.description }}</p>
                        <p class="mt-1 text-xs text-hsi-slate-light">{{ type.projects_count }} dossier(s)</p>
                    </div>
                    <div v-if="canManage" class="flex gap-2">
                        <HsiButton size="sm" variant="outline" @click="editType(type)"><Pencil class="h-3.5 w-3.5" aria-hidden="true" /> Modifier</HsiButton>
                        <HsiButton v-if="!type.projects_count" size="sm" variant="ghost" @click="deleteType(type)"><Trash2 class="h-3.5 w-3.5" aria-hidden="true" /></HsiButton>
                    </div>
                </div>

                <ol class="divide-y divide-hsi-border/60 rounded-2xl border border-hsi-border">
                    <li v-for="(criterion, index) in type.criteria" :key="criterion.id" class="p-3">
                        <form v-if="isEditingCriterion(type, criterion.id)" class="space-y-3" @submit.prevent="saveCriterion">
                            <input v-model="criterionForm.label" required maxlength="255" :class="inputClass" placeholder="Libellé du critère">
                            <input v-model="criterionForm.description" maxlength="2000" :class="inputClass" placeholder="Explication (facultatif)">
                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <label class="inline-flex items-center gap-2"><input v-model="criterionForm.is_blocking" type="checkbox" class="h-4 w-4 rounded"> Bloquant</label>
                                <label class="inline-flex items-center gap-2"><input v-model="criterionForm.is_active" type="checkbox" class="h-4 w-4 rounded"> Actif</label>
                                <select v-model="criterionForm.auto_check" class="rounded-xl border border-hsi-border px-3 py-1.5 text-sm">
                                    <option :value="null">Vérification manuelle par le comité</option>
                                    <option v-for="check in autoChecks" :key="check.value" :value="check.value">Automatique : {{ check.label }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2"><HsiButton size="sm" type="submit" :disabled="criterionForm.processing">Enregistrer</HsiButton><HsiButton size="sm" type="button" variant="ghost" @click="editingCriterion = null">Annuler</HsiButton></div>
                        </form>
                        <div v-else class="flex items-start gap-3">
                            <span class="mt-0.5 w-6 shrink-0 text-sm font-semibold text-hsi-slate-light">{{ index + 1 }}.</span>
                            <div class="min-w-0 flex-1" :class="!criterion.is_active ? 'opacity-50' : ''">
                                <p class="text-sm font-medium text-hsi-navy">{{ criterion.label }} <span v-if="criterion.is_blocking" class="ml-1 rounded-full bg-hsi-navy/5 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider">Bloquant</span></p>
                                <p v-if="criterion.description" class="text-xs text-hsi-slate-light">{{ criterion.description }}</p>
                                <p v-if="criterion.auto_check" class="mt-0.5 flex items-center gap-1 text-xs text-hsi-slate"><Bot class="h-3.5 w-3.5" aria-hidden="true" /> {{ autoLabel(criterion.auto_check) }}</p>
                            </div>
                            <div v-if="canManage" class="flex shrink-0 gap-1">
                                <button type="button" class="rounded p-1 text-hsi-slate hover:bg-hsi-muted disabled:opacity-30" :disabled="index === 0" :aria-label="$tr('Monter')" @click="moveCriterion(criterion, 'up')"><ArrowUp class="h-4 w-4" /></button>
                                <button type="button" class="rounded p-1 text-hsi-slate hover:bg-hsi-muted disabled:opacity-30" :disabled="index === type.criteria.length - 1" :aria-label="$tr('Descendre')" @click="moveCriterion(criterion, 'down')"><ArrowDown class="h-4 w-4" /></button>
                                <button type="button" class="rounded p-1 text-hsi-slate hover:bg-hsi-muted" :aria-label="$tr('Modifier')" @click="editCriterion(type, criterion)"><Pencil class="h-4 w-4" /></button>
                                <button type="button" class="rounded p-1 text-hsi-danger hover:bg-red-50" :aria-label="$tr('Supprimer')" @click="deleteCriterion(criterion)"><Trash2 class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </li>
                    <li v-if="isEditingCriterion(type, 'new')" class="p-3">
                        <form class="space-y-3" @submit.prevent="saveCriterion">
                            <input v-model="criterionForm.label" required maxlength="255" :class="inputClass" placeholder="Libellé du critère">
                            <input v-model="criterionForm.description" maxlength="2000" :class="inputClass" placeholder="Explication (facultatif)">
                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <label class="inline-flex items-center gap-2"><input v-model="criterionForm.is_blocking" type="checkbox" class="h-4 w-4 rounded"> Bloquant</label>
                                <select v-model="criterionForm.auto_check" class="rounded-xl border border-hsi-border px-3 py-1.5 text-sm">
                                    <option :value="null">Vérification manuelle par le comité</option>
                                    <option v-for="check in autoChecks" :key="check.value" :value="check.value">Automatique : {{ check.label }}</option>
                                </select>
                            </div>
                            <p v-if="criterionForm.errors.label" class="text-xs text-hsi-danger">{{ criterionForm.errors.label }}</p>
                            <div class="flex gap-2"><HsiButton size="sm" type="submit" :disabled="criterionForm.processing">Ajouter</HsiButton><HsiButton size="sm" type="button" variant="ghost" @click="editingCriterion = null">Annuler</HsiButton></div>
                        </form>
                    </li>
                    <li v-if="!type.criteria.length && !isEditingCriterion(type, 'new')" class="p-3 text-sm text-hsi-slate-light">Aucun critère pour l’instant.</li>
                </ol>
                <div v-if="canManage && !isEditingCriterion(type, 'new')" class="mt-3 flex flex-wrap gap-2">
                    <HsiButton size="sm" variant="outline" @click="newCriterion(type)"><Plus class="h-3.5 w-3.5" aria-hidden="true" /> Ajouter un critère</HsiButton>
                    <HsiButton v-if="criteriaLibrary.some((c) => c.type_id !== type.id)" size="sm" variant="ghost" @click="openImport(type)"><Copy class="h-3.5 w-3.5" aria-hidden="true" /> Reprendre des critères existants</HsiButton>
                </div>
            </HsiCard>
        </div>
        <Modal :show="!!importTarget" max-width="2xl" @close="closeImport">
            <form v-if="importTarget" class="space-y-4 p-6" @submit.prevent="submitImport">
                <h2 class="flex items-center gap-2 font-heading text-lg font-semibold text-hsi-navy"><Copy class="h-5 w-5 text-hsi-gold" /> Reprendre des critères pour « {{ importTarget.name }} »</h2>
                <p class="text-sm text-hsi-slate">Les critères cochés sont copiés à la fin de la liste. Ceux qui existent déjà dans ce type sont ignorés.</p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input v-model="libraryQuery" type="search" :class="inputClass" :placeholder="$tr('Filtrer les critères…')">
                    <select v-model="libraryType" :class="[inputClass, 'sm:w-56']"><option value="">Tous les types</option><option v-for="t in types.filter((t) => t.id !== importTarget.id)" :key="t.id" :value="t.id">{{ t.name }}</option></select>
                </div>
                <div class="max-h-80 space-y-1.5 overflow-y-auto pr-1">
                    <label v-for="c in filteredLibrary" :key="c.id" class="flex items-start gap-3 rounded-xl border p-2.5 text-sm" :class="[alreadyIn(importTarget, c) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer', importForm.criteria_ids.includes(c.id) ? 'border-hsi-gold bg-hsi-gold/5' : 'border-hsi-border']">
                        <input v-model="importForm.criteria_ids" type="checkbox" :value="c.id" :disabled="alreadyIn(importTarget, c)" class="mt-0.5 h-4 w-4 rounded">
                        <span class="min-w-0 flex-1"><span class="font-medium text-hsi-navy">{{ c.label }}</span><span v-if="alreadyIn(importTarget, c)" class="ml-1 text-xs text-hsi-slate-light">(déjà présent)</span><span class="block text-xs text-hsi-slate-light">{{ c.type_name }}<template v-if="c.auto_check"> · {{ autoLabel(c.auto_check) }}</template></span></span>
                    </label>
                    <p v-if="!filteredLibrary.length" class="py-3 text-center text-xs text-hsi-slate-light">Aucun critère ne correspond.</p>
                </div>
                <p v-if="importForm.errors.criteria_ids" class="text-sm text-hsi-danger">{{ importForm.errors.criteria_ids }}</p>
                <div class="flex justify-end gap-2"><HsiButton type="button" variant="ghost" @click="closeImport">Annuler</HsiButton><HsiButton type="submit" :disabled="importForm.processing || !importForm.criteria_ids.length">Ajouter {{ importForm.criteria_ids.length }} critère(s)</HsiButton></div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
