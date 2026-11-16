<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, Globe2, Pencil, Plus, Save, Scale, Trash2, X } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiViewToggle from '@/Components/Hsi/HsiViewToggle.vue';
import { useListTools, useViewMode } from '@/Composables/useListTools';

const props = defineProps({
    rules: Array,
    zones: Array,
    transactionTypes: Array,
    treasurerCandidates: Array,
    roles: Array,
    directions: Array,
    can: Object,
});

const tabs = computed(() => [
    { key: 'rules', label: 'Règles du fonds', icon: Scale },
    { key: 'zones', label: 'Zones et trésoriers', icon: Globe2 },
    { key: 'types', label: 'Types de transactions', icon: ArrowLeftRight },
]);
const initialTab = (() => {
    try { return localStorage.getItem('hsi-fund-settings-tab') || 'rules'; } catch { return 'rules'; }
})();
const activeTab = ref(tabs.value.some((tab) => tab.key === initialTab) ? initialTab : 'rules');
const selectTab = (key) => {
    activeTab.value = key;
    try { localStorage.setItem('hsi-fund-settings-tab', key); } catch { /* stockage indisponible */ }
};

/* ---------- Règles ---------- */
const ruleGroups = [
    { title: 'Cotisations', test: (key) => key.startsWith('contribution_') },
    { title: 'Prêts et remboursements', test: (key) => key.startsWith('loan') },
    { title: 'Demandes de projet', test: (key) => key.startsWith('project_') || key.startsWith('refusal_') },
    { title: 'Votes', test: (key) => key.startsWith('vote_') },
    { title: 'Adhésion et retrait', test: (key) => key.startsWith('admission_') || key.startsWith('withdrawal_') },
    { title: 'Règlement intérieur', test: (key) => key === 'policy_version' },
    { title: 'Informations du fonds (facultatives)', test: (key) => ['association_name', 'governing_state', 'bank_name', 'bank_account_numbers', 'bank_location', 'first_payment_date'].includes(key) },
];
const groupedRules = computed(() => ruleGroups
    .map((group) => ({ title: group.title, items: props.rules.filter((rule) => group.test(rule.key)) }))
    .filter((group) => group.items.length));
const rulesForm = useForm({ rules: Object.fromEntries(props.rules.map((rule) => [rule.key, rule.value])) });
const saveRules = () => rulesForm.put(route('fund-settings.rules.update'), { preserveScroll: true });
const typeView = useViewMode('transaction-types');
const typeList = useListTools(() => props.transactionTypes, {
    searchKeys: ['name', 'description', 'code'],
    filters: { direction: (t, value) => t.direction === value, state: (t, value) => (value === 'active' ? t.is_active : !t.is_active) },
    perPage: 12,
});
const inputType = (rule) => ({ integer: 'number', decimal: 'number', date: 'date' }[rule.type] ?? 'text');
const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm text-hsi-navy focus:border-hsi-gold focus:ring-2 focus:ring-hsi-gold/20 disabled:bg-hsi-muted';

/* ---------- Zones ---------- */
const emptyZone = () => ({ name: '', description: '', is_active: true, treasurer_ids: [] });
const zoneForm = useForm(emptyZone());
const editingZoneId = ref(null);
const newZone = () => { editingZoneId.value = 'new'; zoneForm.defaults(emptyZone()); zoneForm.reset(); zoneForm.clearErrors(); };
const editZone = (zone) => {
    editingZoneId.value = zone.id;
    zoneForm.defaults({ name: zone.name, description: zone.description ?? '', is_active: zone.is_active, treasurer_ids: [...zone.treasurer_ids] });
    zoneForm.reset();
    zoneForm.clearErrors();
};
const saveZone = () => {
    const options = { preserveScroll: true, onSuccess: () => { editingZoneId.value = null; } };
    editingZoneId.value === 'new'
        ? zoneForm.post(route('fund-settings.zones.store'), options)
        : zoneForm.put(route('fund-settings.zones.update', editingZoneId.value), options);
};
const deleteZone = (zone) => {
    if (confirm(`Supprimer la zone « ${zone.name} » ?`)) router.delete(route('fund-settings.zones.destroy', zone.id), { preserveScroll: true });
};

/* ---------- Types de transactions ---------- */
const emptyType = () => ({ name: '', description: '', direction: 'out', required_approvals: 1, approver_roles: ['treasurer', 'president', 'super_admin'], is_active: true });
const typeForm = useForm(emptyType());
const editingTypeId = ref(null);
const editingType = computed(() => props.transactionTypes.find((type) => type.id === editingTypeId.value));
const newType = () => { editingTypeId.value = 'new'; typeForm.defaults(emptyType()); typeForm.reset(); typeForm.clearErrors(); };
const editType = (type) => {
    editingTypeId.value = type.id;
    typeForm.defaults({ name: type.name, description: type.description ?? '', direction: type.direction, required_approvals: type.required_approvals, approver_roles: [...type.approver_roles], is_active: type.is_active });
    typeForm.reset();
    typeForm.clearErrors();
};
const saveType = () => {
    const options = { preserveScroll: true, onSuccess: () => { editingTypeId.value = null; } };
    editingTypeId.value === 'new'
        ? typeForm.post(route('fund-settings.transaction-types.store'), options)
        : typeForm.put(route('fund-settings.transaction-types.update', editingTypeId.value), options);
};
const deleteType = (type) => {
    if (confirm(`Supprimer le type « ${type.name} » ?`)) router.delete(route('fund-settings.transaction-types.destroy', type.id), { preserveScroll: true });
};
const directionLabel = (value) => props.directions.find((d) => d.value === value)?.label ?? value;
const directionVariant = (value) => ({ in: 'success', out: 'danger', internal: 'info' }[value] ?? 'neutral');
const roleLabel = (name) => props.roles.find((role) => role.name === name)?.label ?? name;
</script>

<template>
    <Head title="Paramètres du fonds" />

    <AuthenticatedLayout>
        <HsiPageHeader title="Paramètres " accent="du fonds" subtitle="Règles chiffrées du règlement intérieur, zones, trésoriers et validation des transactions" />

        <div class="mb-6 flex flex-wrap gap-2" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.key"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium"
                :class="activeTab === tab.key ? 'bg-hsi-navy text-white' : 'border border-hsi-border bg-white text-hsi-slate'"
                @click="selectTab(tab.key)"
            >
                <component :is="tab.icon" class="h-4 w-4" aria-hidden="true" />{{ tab.label }}
            </button>
        </div>

        <!-- Règles du fonds -->
        <form v-if="activeTab === 'rules'" class="space-y-5" @submit.prevent="saveRules">
            <p class="rounded-2xl border border-hsi-border bg-white p-4 text-sm text-hsi-slate">
                Ces valeurs appliquent le règlement intérieur. Selon la section 9, une modification doit d’abord être approuvée par un vote du groupe à 80 %.
                Chaque changement est enregistré dans le journal (ancienne et nouvelle valeur).
            </p>
            <HsiCard v-for="group in groupedRules" :key="group.title" padding="lg">
                <HsiSectionTitle :title="group.title" />
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                    <div v-for="rule in group.items" :key="rule.key">
                        <label :for="`rule-${rule.key}`" class="mb-1.5 block text-sm font-medium text-hsi-navy">{{ rule.label }}</label>
                        <input
                            :id="`rule-${rule.key}`"
                            v-model="rulesForm.rules[rule.key]"
                            :type="inputType(rule)"
                            :step="rule.type === 'decimal' ? '0.01' : (rule.type === 'integer' ? '1' : undefined)"
                            :min="rule.min ?? undefined"
                            :max="rule.max ?? undefined"
                            :disabled="!can.rules"
                            :class="inputClass"
                        >
                        <p v-if="rulesForm.errors[`rules.${rule.key}`]" class="mt-1 text-xs font-medium text-hsi-danger" role="alert">{{ rulesForm.errors[`rules.${rule.key}`] }}</p>
                        <p v-else-if="rule.description" class="mt-1 text-xs text-hsi-slate-light">{{ rule.description }}</p>
                    </div>
                </div>
            </HsiCard>
            <div v-if="can.rules" class="sticky bottom-4 flex justify-end">
                <HsiButton type="submit" :disabled="rulesForm.processing || !rulesForm.isDirty"><Save class="h-4 w-4" aria-hidden="true" /> Enregistrer les règles</HsiButton>
            </div>
        </form>

        <!-- Zones -->
        <div v-else-if="activeTab === 'zones'" class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-hsi-slate">Chaque membre est rattaché à une zone. Une zone peut avoir un, plusieurs ou aucun trésorier.</p>
                <HsiButton v-if="can.zones && editingZoneId !== 'new'" @click="newZone"><Plus class="h-4 w-4" aria-hidden="true" /> Nouvelle zone</HsiButton>
            </div>

            <HsiCard v-if="editingZoneId === 'new'" padding="lg">
                <HsiSectionTitle title="Nouvelle zone" />
                <form class="space-y-4" @submit.prevent="saveZone">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="zone-name" class="mb-1.5 block text-sm font-medium text-hsi-navy">Nom *</label>
                            <input id="zone-name" v-model="zoneForm.name" required maxlength="80" :class="inputClass">
                            <p v-if="zoneForm.errors.name" class="mt-1 text-xs text-hsi-danger">{{ zoneForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="zone-description" class="mb-1.5 block text-sm font-medium text-hsi-navy">Description</label>
                            <input id="zone-description" v-model="zoneForm.description" maxlength="255" :class="inputClass">
                        </div>
                    </div>
                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium text-hsi-navy">Trésoriers de la zone <span class="font-normal text-hsi-slate-light">(facultatif)</span></legend>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="person in treasurerCandidates" :key="person.id" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm" :class="zoneForm.treasurer_ids.includes(person.id) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate'">
                                <input v-model="zoneForm.treasurer_ids" type="checkbox" :value="person.id" class="h-4 w-4 rounded border-hsi-border text-hsi-navy">{{ person.name }}
                            </label>
                            <p v-if="!treasurerCandidates.length" class="text-sm text-hsi-slate-light">Aucune personne n’a encore le rôle Trésorier.</p>
                        </div>
                        <p v-if="zoneForm.errors['treasurer_ids.0']" class="mt-1 text-xs text-hsi-danger">{{ zoneForm.errors['treasurer_ids.0'] }}</p>
                    </fieldset>
                    <div class="flex gap-2">
                        <HsiButton type="submit" :disabled="zoneForm.processing"><Save class="h-4 w-4" aria-hidden="true" /> Créer la zone</HsiButton>
                        <HsiButton type="button" variant="ghost" @click="editingZoneId = null">Annuler</HsiButton>
                    </div>
                </form>
            </HsiCard>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <HsiCard v-for="zone in zones" :key="zone.id" padding="lg">
                    <form v-if="editingZoneId === zone.id" class="space-y-4" @submit.prevent="saveZone">
                        <div>
                            <label :for="`zone-name-${zone.id}`" class="mb-1.5 block text-sm font-medium text-hsi-navy">Nom *</label>
                            <input :id="`zone-name-${zone.id}`" v-model="zoneForm.name" required maxlength="80" :class="inputClass">
                            <p v-if="zoneForm.errors.name" class="mt-1 text-xs text-hsi-danger">{{ zoneForm.errors.name }}</p>
                        </div>
                        <div>
                            <label :for="`zone-description-${zone.id}`" class="mb-1.5 block text-sm font-medium text-hsi-navy">Description</label>
                            <input :id="`zone-description-${zone.id}`" v-model="zoneForm.description" maxlength="255" :class="inputClass">
                        </div>
                        <fieldset>
                            <legend class="mb-1.5 text-sm font-medium text-hsi-navy">Trésoriers de la zone <span class="font-normal text-hsi-slate-light">(facultatif)</span></legend>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="person in treasurerCandidates" :key="person.id" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm" :class="zoneForm.treasurer_ids.includes(person.id) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate'">
                                    <input v-model="zoneForm.treasurer_ids" type="checkbox" :value="person.id" class="h-4 w-4 rounded border-hsi-border text-hsi-navy">{{ person.name }}
                                </label>
                                <p v-if="!treasurerCandidates.length" class="text-sm text-hsi-slate-light">Aucune personne n’a encore le rôle Trésorier.</p>
                            </div>
                            <p v-if="zoneForm.errors['treasurer_ids.0']" class="mt-1 text-xs text-hsi-danger">{{ zoneForm.errors['treasurer_ids.0'] }}</p>
                        </fieldset>
                        <label class="inline-flex items-center gap-2 text-sm text-hsi-navy"><input v-model="zoneForm.is_active" type="checkbox" class="h-4 w-4 rounded border-hsi-border text-hsi-navy"> Zone active</label>
                        <div class="flex gap-2">
                            <HsiButton type="submit" :disabled="zoneForm.processing"><Save class="h-4 w-4" aria-hidden="true" /> Enregistrer</HsiButton>
                            <HsiButton type="button" variant="ghost" @click="editingZoneId = null">Annuler</HsiButton>
                        </div>
                    </form>
                    <div v-else>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-heading text-lg font-semibold text-hsi-navy">{{ zone.name }}</h3>
                                <p v-if="zone.description" class="text-sm text-hsi-slate">{{ zone.description }}</p>
                            </div>
                            <HsiBadge :variant="zone.is_active ? 'success' : 'neutral'">{{ zone.is_active ? 'Active' : 'Inactive' }}</HsiBadge>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-hsi-slate-light">Membres</dt><dd class="font-semibold text-hsi-navy">{{ zone.members_count }}</dd></div>
                            <div>
                                <dt class="text-hsi-slate-light">Trésoriers</dt>
                                <dd class="font-semibold text-hsi-navy">{{ zone.treasurers.length ? zone.treasurers.join(', ') : 'Aucun' }}</dd>
                            </div>
                        </dl>
                        <div v-if="can.zones" class="mt-4 flex gap-2">
                            <HsiButton size="sm" variant="outline" @click="editZone(zone)"><Pencil class="h-3.5 w-3.5" aria-hidden="true" /> Modifier</HsiButton>
                            <HsiButton v-if="!zone.members_count" size="sm" variant="ghost" @click="deleteZone(zone)"><Trash2 class="h-3.5 w-3.5" aria-hidden="true" /> Supprimer</HsiButton>
                        </div>
                    </div>
                </HsiCard>
            </div>
        </div>

        <!-- Types de transactions -->
        <div v-else class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-3xl text-sm text-hsi-slate">Pour chaque type de transaction, choisissez combien de personnes doivent la valider, et quels rôles peuvent le faire. Une même personne ne compte qu’une fois.</p>
                <HsiButton v-if="can.transactionTypes && editingTypeId !== 'new'" @click="newType"><Plus class="h-4 w-4" aria-hidden="true" /> Nouveau type</HsiButton>
            </div>

            <HsiCard v-if="editingTypeId !== null" padding="lg">
                <div class="mb-4 flex items-center justify-between">
                    <HsiSectionTitle :title="editingTypeId === 'new' ? 'Nouveau type de transaction' : 'Modifier le type de transaction'" />
                    <button type="button" class="rounded-lg p-1 text-hsi-slate hover:bg-hsi-muted" aria-label="Fermer" @click="editingTypeId = null"><X class="h-5 w-5" /></button>
                </div>
                <form class="space-y-4" @submit.prevent="saveType">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="type-name" class="mb-1.5 block text-sm font-medium text-hsi-navy">Nom *</label>
                            <input id="type-name" v-model="typeForm.name" required maxlength="100" :class="inputClass">
                            <p v-if="typeForm.errors.name" class="mt-1 text-xs text-hsi-danger">{{ typeForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="type-direction" class="mb-1.5 block text-sm font-medium text-hsi-navy">Sens *</label>
                            <select id="type-direction" v-model="typeForm.direction" :disabled="editingType?.is_system" :class="inputClass">
                                <option v-for="direction in directions" :key="direction.value" :value="direction.value">{{ direction.label }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="type-description" class="mb-1.5 block text-sm font-medium text-hsi-navy">Description</label>
                            <input id="type-description" v-model="typeForm.description" maxlength="255" :class="inputClass">
                        </div>
                        <div>
                            <label for="type-approvals" class="mb-1.5 block text-sm font-medium text-hsi-navy">Nombre de validations requises *</label>
                            <select id="type-approvals" v-model.number="typeForm.required_approvals" :class="inputClass">
                                <option v-for="n in 5" :key="n" :value="n">{{ n === 1 ? '1 validation' : `${n} validations` }}</option>
                            </select>
                            <p v-if="typeForm.errors.required_approvals" class="mt-1 text-xs text-hsi-danger">{{ typeForm.errors.required_approvals }}</p>
                        </div>
                        <label class="inline-flex items-center gap-2 self-end pb-2 text-sm text-hsi-navy"><input v-model="typeForm.is_active" type="checkbox" class="h-4 w-4 rounded border-hsi-border text-hsi-navy"> Type actif</label>
                    </div>
                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium text-hsi-navy">Rôles autorisés à valider *</legend>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="role in roles" :key="role.name" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm" :class="typeForm.approver_roles.includes(role.name) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate'">
                                <input v-model="typeForm.approver_roles" type="checkbox" :value="role.name" class="h-4 w-4 rounded border-hsi-border text-hsi-navy">{{ role.label }}
                            </label>
                        </div>
                        <p v-if="typeForm.errors.approver_roles" class="mt-1 text-xs text-hsi-danger">{{ typeForm.errors.approver_roles }}</p>
                    </fieldset>
                    <div class="flex gap-2">
                        <HsiButton type="submit" :disabled="typeForm.processing"><Save class="h-4 w-4" aria-hidden="true" /> Enregistrer</HsiButton>
                        <HsiButton type="button" variant="ghost" @click="editingTypeId = null">Annuler</HsiButton>
                    </div>
                </form>
            </HsiCard>

            <HsiCard padding="md">
                <div class="mb-1 flex justify-end"><HsiViewToggle v-model="typeView" /></div>
                <HsiListToolbar v-model:search="typeList.search.value" placeholder="Rechercher un type…" :active="typeList.active.value" :count="typeList.total.value" label="type(s)" @reset="typeList.reset()">
                    <select v-model="typeList.filters.direction" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Tous les sens</option><option v-for="d in directions" :key="d.value" :value="d.value">{{ d.label }}</option></select>
                    <select v-model="typeList.filters.state" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Actifs et inactifs</option><option value="active">Actifs</option><option value="inactive">Inactifs</option></select>
                </HsiListToolbar>

                <div v-if="typeView === 'cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="type in typeList.paged.value" :key="type.id" class="flex flex-col rounded-2xl border border-hsi-border p-4" :class="type.is_active ? 'bg-white' : 'bg-hsi-muted/40'">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <HsiBadge :variant="directionVariant(type.direction)" size="xs">{{ directionLabel(type.direction) }}</HsiBadge>
                            <HsiBadge :variant="type.is_active ? 'success' : 'neutral'" size="xs">{{ type.is_active ? 'Actif' : 'Inactif' }}</HsiBadge>
                        </div>
                        <h3 class="font-semibold text-hsi-navy">{{ type.name }}</h3>
                        <p v-if="type.description" class="mt-1 text-xs text-hsi-slate-light">{{ type.description }}</p>
                        <p v-if="type.is_system" class="mt-1 text-[11px] font-semibold uppercase tracking-wider text-[#A8841F]">Prévu par le règlement</p>
                        <div class="mt-4 flex items-center gap-3 rounded-xl bg-hsi-muted/60 p-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-hsi-navy font-heading text-lg font-semibold text-white">{{ type.required_approvals }}</span>
                            <div class="min-w-0 text-xs"><p class="font-semibold text-hsi-navy">validation(s) requise(s)</p><p class="text-hsi-slate">{{ type.approver_roles.map(roleLabel).join(', ') || '—' }}</p></div>
                        </div>
                        <div v-if="can.transactionTypes" class="mt-auto flex justify-end gap-1 pt-3">
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-hsi-navy hover:bg-hsi-muted" @click="editType(type)"><Pencil class="h-3.5 w-3.5" /> Modifier</button>
                            <button v-if="!type.is_system" type="button" class="rounded-lg p-1.5 text-hsi-danger hover:bg-red-50" :aria-label="`Supprimer ${type.name}`" @click="deleteType(type)"><Trash2 class="h-4 w-4" /></button>
                        </div>
                    </article>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b border-hsi-border text-left text-hsi-slate-light">
                                <th class="py-3 pr-3 font-medium">Type</th>
                                <th class="py-3 pr-3 font-medium">Sens</th>
                                <th class="py-3 pr-3 font-medium">Validations</th>
                                <th class="py-3 pr-3 font-medium">Peuvent valider</th>
                                <th class="py-3 pr-3 font-medium">État</th>
                                <th class="py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="type in typeList.paged.value" :key="type.id" class="border-b border-hsi-border/50 align-top">
                                <td class="py-3 pr-3">
                                    <p class="font-medium text-hsi-navy">{{ type.name }}</p>
                                    <p v-if="type.description" class="text-xs text-hsi-slate-light">{{ type.description }}</p>
                                    <p v-if="type.is_system" class="mt-0.5 text-[11px] font-semibold uppercase tracking-wider text-[#A8841F]">Prévu par le règlement</p>
                                </td>
                                <td class="py-3 pr-3"><HsiBadge :variant="directionVariant(type.direction)">{{ directionLabel(type.direction) }}</HsiBadge></td>
                                <td class="py-3 pr-3 font-semibold text-hsi-navy">{{ type.required_approvals }}</td>
                                <td class="py-3 pr-3 text-hsi-slate">{{ type.approver_roles.map(roleLabel).join(', ') }}</td>
                                <td class="py-3 pr-3"><HsiBadge :variant="type.is_active ? 'success' : 'neutral'">{{ type.is_active ? 'Actif' : 'Inactif' }}</HsiBadge></td>
                                <td class="py-3 text-right">
                                    <div v-if="can.transactionTypes" class="flex justify-end gap-1">
                                        <button type="button" class="rounded-lg p-1.5 text-hsi-slate hover:bg-hsi-muted hover:text-hsi-navy" :aria-label="`Modifier ${type.name}`" @click="editType(type)"><Pencil class="h-4 w-4" /></button>
                                        <button v-if="!type.is_system" type="button" class="rounded-lg p-1.5 text-hsi-danger hover:bg-red-50" :aria-label="`Supprimer ${type.name}`" @click="deleteType(type)"><Trash2 class="h-4 w-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!typeList.total.value" class="py-4 text-center text-sm text-hsi-slate-light">Aucun type ne correspond.</p>
                <HsiPagination v-model:page="typeList.page.value" v-model:per-page="typeList.perPage.value" :total="typeList.total.value" :per-page-options="[12, 24, 48]" label="types" />
            </HsiCard>
        </div>
    </AuthenticatedLayout>
</template>
