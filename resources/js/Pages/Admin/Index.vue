<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ShieldCheck, Users, KeyRound, Settings, Plus, Trash2, Save, FolderCog, Activity, Mail, Pencil, Coins, AlertTriangle, ArrowRight, CalendarDays } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiStatCard from '@/Components/Hsi/HsiStatCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiAvatar from '@/Components/Hsi/HsiAvatar.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import HsiViewToggle from '@/Components/Hsi/HsiViewToggle.vue';
import { useListTools, useViewMode } from '@/Composables/useListTools';
import { tr } from '@/i18n';

const props = defineProps({
    roles: Array, permissions: Array, users: Array, settings: Object, categories: Array, activities: Object,
    auditFilters: Object, auditLogs: Array, tab: { type: String, default: null }, currency: Object, stats: Object, can: Object,
});

const tabs = [
    { key: 'roles', label: 'Rôles et permissions', icon: ShieldCheck }, { key: 'users', label: 'Utilisateurs', icon: Users },
    { key: 'permissions', label: 'Permissions', icon: KeyRound }, { key: 'settings', label: 'Paramètres', icon: Settings },
    { key: 'categories', label: 'Catégories documentaires', icon: FolderCog }, { key: 'audit', label: 'Audit', icon: Activity },
];
const activeTab = ref(tabs.some((t) => t.key === props.tab) ? props.tab : 'roles');
watch(activeTab, (value) => {
    // L'onglet reste ouvert après un rechargement.
    const url = new URL(window.location.href);
    url.searchParams.set('tab', value);
    window.history.replaceState(window.history.state, '', url);
});
const selectClass = 'rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm text-hsi-navy';

// --- Rôles -----------------------------------------------------------------
const selectedRoleId = ref(props.roles[0]?.id ?? null);
const selectedRole = computed(() => props.roles.find((role) => role.id === selectedRoleId.value));
const permissionGroups = computed(() => props.permissions.reduce((groups, permission) => {
    (groups[permission.group] ??= []).push(permission);
    return groups;
}, {}));
const roleForm = useForm({ permissions: [] });
const newRoleForm = useForm({ name: '', permissions: [] });
const chooseRole = (role) => { selectedRoleId.value = role.id; roleForm.permissions = [...role.permissions]; };
if (selectedRole.value) roleForm.permissions = [...selectedRole.value.permissions];
const togglePermission = (form, name) => { const index = form.permissions.indexOf(name); index >= 0 ? form.permissions.splice(index, 1) : form.permissions.push(name); };
const saveRole = () => roleForm.put(route('admin.roles.update', selectedRoleId.value), { preserveScroll: true });
const createRole = () => newRoleForm.post(route('admin.roles.store'), { preserveScroll: true, onSuccess: () => newRoleForm.reset() });
const deleteRole = (role) => { if (confirm(tr('Supprimer le rôle {name} ?', { name: role.name }))) router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true }); };

// --- Utilisateurs ------------------------------------------------------------
const statusMeta = {
    active: { label: 'Actif', variant: 'success' }, suspended: { label: 'Suspendu', variant: 'danger' },
    inactive: { label: 'Inactif', variant: 'neutral' }, pending: { label: 'En attente', variant: 'warning' }, system: { label: 'Compte système', variant: 'info' },
};
const statusOf = (user) => statusMeta[user.status] ?? { label: user.status, variant: 'neutral' };
const userStatuses = computed(() => [...new Set(props.users.map((u) => u.status))]);
const userList = useListTools(() => props.users, {
    searchKeys: ['name', 'email', 'role'],
    filters: { role: (u, value) => u.role_ids.includes(Number(value)), status: (u, value) => u.status === value },
    perPage: 12,
});
const userView = useViewMode('admin-users');
const editingUserId = ref(null);
const editingRoleIds = ref([]);
const editUserRoles = (user) => { editingUserId.value = user.id; editingRoleIds.value = [...user.role_ids]; };
const saveUserRoles = (user) => router.patch(route('admin.users.role', user.id), { role_ids: editingRoleIds.value }, { preserveScroll: true, onSuccess: () => { editingUserId.value = null; } });
const roleChips = (user) => (user.role ? user.role.split(' · ') : []);

// --- Permissions -------------------------------------------------------------
const permissionForm = useForm({ name: '' });
const permissionList = useListTools(() => props.permissions, {
    searchKeys: ['name', 'group'],
    filters: { group: (p, value) => p.group === value, usage: (p, value) => (value === 'used' ? p.roles_count > 0 : p.roles_count === 0) },
    perPage: 24,
});
const createPermission = () => permissionForm.post(route('admin.permissions.store'), { preserveScroll: true, onSuccess: () => permissionForm.reset() });
const deletePermission = (permission) => { if (confirm(tr('Supprimer la permission « {name} » ?', { name: permission.name }))) router.delete(route('admin.permissions.destroy', permission.id), { preserveScroll: true }); };

// --- Paramètres et devise ----------------------------------------------------
const settingValues = {};
Object.values(props.settings).flat().forEach((setting) => { settingValues[setting.key] = setting.value ?? ''; });
const settingsForm = useForm({ settings: settingValues });
const saveSettings = () => settingsForm.put(route('admin.settings.update'), { preserveScroll: true });
const hasOtherSettings = computed(() => Object.values(props.settings).some((items) => items.length));

const currencyOption = (code) => props.currency.options.find((o) => o.value === code);
const currentCurrency = computed(() => currencyOption(props.currency.current));
const currencyForm = useForm({ currency: props.currency.current, rate: '', confirm: false });
watch(() => currencyForm.currency, (code) => {
    const suggested = props.currency.rates[code];
    currencyForm.rate = suggested ? String(suggested) : '';
    currencyForm.confirm = false;
});
const targetCurrency = computed(() => currencyOption(currencyForm.currency));
const changingCurrency = computed(() => currencyForm.currency !== props.currency.current);
const fmt = (value, code) => {
    const option = currencyOption(code);
    const decimals = code === 'XAF' ? 0 : 2;
    return `${Number(value).toLocaleString('fr-FR', { maximumFractionDigits: decimals, minimumFractionDigits: 0 })} ${option?.symbol ?? code}`;
};
const ratePreview = computed(() => {
    const rate = parseFloat(String(currencyForm.rate).replace(',', '.'));
    if (!changingCurrency.value || !(rate > 0)) return null;
    return { rate, examples: [75, 1000].map((v) => [fmt(v, props.currency.current), fmt(v * rate, currencyForm.currency)]) };
});
const convertCurrency = () => currencyForm
    .transform((data) => ({ ...data, rate: String(data.rate).replace(',', '.') }))
    .post(route('admin.currency.convert'), { preserveScroll: true, onSuccess: () => currencyForm.reset('confirm') });

// --- Catégories --------------------------------------------------------------
const categoryForm = useForm({ name: '', description: '', retention_days: '' });
const categoryList = useListTools(() => props.categories, {
    searchKeys: ['name', 'description'],
    filters: { state: (c, value) => (value === 'active' ? c.is_active : !c.is_active) },
    perPage: 10,
});
const createCategory = () => categoryForm.post(route('document-categories.store'), { preserveScroll: true, onSuccess: () => categoryForm.reset() });
const toggleCategory = (category) => router.patch(route('document-categories.toggle', category.id), {}, { preserveScroll: true });

// --- Journal (filtré et paginé par le serveur) -------------------------------
const audit = ref({
    search: props.auditFilters.search ?? '', log: props.auditFilters.log ?? '', user: props.auditFilters.user ?? '',
    from: props.auditFilters.from ?? '', to: props.auditFilters.to ?? '', per_page: props.auditFilters.per_page ?? 25,
});
const logLabels = { administration: 'Administration', documents: 'Documents', projects: 'Projets', funds: 'Fonds', votes: 'Votes', contributions: 'Cotisations', members: 'Membres', default: 'Général', auth: 'Connexions', approvals: 'Validations', loans: 'Prêts', reports: 'Rapports' };
const logLabel = (name) => logLabels[name] ?? name;
const auditActive = computed(() => Object.entries(audit.value).some(([k, v]) => k !== 'per_page' && v !== '' && v !== null));
let auditTimer;
const loadAudit = (extra = {}) => router.get(route('admin.index'), {
    tab: 'audit',
    audit_search: audit.value.search || undefined, audit_log: audit.value.log || undefined, audit_user: audit.value.user || undefined,
    audit_from: audit.value.from || undefined, audit_to: audit.value.to || undefined, audit_per_page: audit.value.per_page, ...extra,
}, { preserveState: true, preserveScroll: true, replace: true, only: ['activities', 'auditFilters'] });
watch(() => [audit.value.log, audit.value.user, audit.value.from, audit.value.to, audit.value.per_page], () => loadAudit());
watch(() => audit.value.search, () => { clearTimeout(auditTimer); auditTimer = setTimeout(() => loadAudit(), 350); });
const resetAudit = () => { audit.value = { search: '', log: '', user: '', from: '', to: '', per_page: audit.value.per_page }; };
</script>

<template>
    <Head title="Administration" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Centre " accent="d’administration" subtitle="Rôles, permissions, utilisateurs, paramètres et référentiels" />
        <div class="mb-7 grid grid-cols-2 gap-4 xl:grid-cols-4"><HsiStatCard title="Utilisateurs" :value="String(stats.users)" :icon="Users" /><HsiStatCard title="Rôles" :value="String(stats.roles)" :icon="ShieldCheck" /><HsiStatCard title="Permissions" :value="String(stats.permissions)" :icon="KeyRound" /><HsiStatCard title="Paramètres" :value="String(stats.settings + 1)" :icon="Settings" /></div>
        <div class="mb-6 flex flex-wrap gap-2"><button v-for="tab in tabs" :key="tab.key" type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium" :class="activeTab === tab.key ? 'bg-hsi-navy text-white' : 'border border-hsi-border bg-white text-hsi-slate'" @click="activeTab = tab.key"><component :is="tab.icon" class="h-4 w-4" />{{ tab.label }}</button></div>

        <!-- Rôles et permissions -->
        <div v-if="activeTab === 'roles'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <HsiCard padding="md"><HsiSectionTitle title="Rôles" /><button v-for="role in roles" :key="role.id" type="button" class="mb-1 flex w-full justify-between rounded-xl px-3 py-2.5 text-left text-sm" :class="selectedRoleId === role.id ? 'bg-hsi-gold/10 text-hsi-navy' : 'hover:bg-hsi-muted'" @click="chooseRole(role)"><span>{{ role.name }}</span><HsiBadge variant="neutral">{{ role.users_count }}</HsiBadge></button>
                <form v-if="can.roles" class="mt-5 space-y-2 border-t border-hsi-border pt-4" @submit.prevent="createRole"><input v-model="newRoleForm.name" required pattern="[a-z][a-z0-9_]*" class="w-full rounded-xl border-hsi-border text-sm" placeholder="nouveau_role" /><HsiButton type="submit" size="sm"><Plus class="h-4 w-4" /> Créer</HsiButton></form>
            </HsiCard>
            <HsiCard padding="lg" class="lg:col-span-2"><div v-if="selectedRole"><div class="mb-5 flex justify-between"><div><HsiSectionTitle :title="`Permissions — ${selectedRole.name}`" /><p class="text-xs text-hsi-slate-light">{{ selectedRole.users_count }} utilisateur(s)</p></div><button v-if="can.roles && !selectedRole.system" type="button" class="text-hsi-danger" @click="deleteRole(selectedRole)"><Trash2 class="h-4 w-4" /></button></div>
                <form @submit.prevent="saveRole"><div v-for="(items, group) in permissionGroups" :key="group" class="mb-5"><h3 class="mb-2 text-xs uppercase tracking-wide text-hsi-slate-light">{{ group }}</h3><div class="grid grid-cols-1 gap-2 md:grid-cols-2"><label v-for="permission in items" :key="permission.id" class="flex items-center gap-2 rounded-lg border border-hsi-border p-2 text-sm"><input type="checkbox" :checked="roleForm.permissions.includes(permission.name)" :disabled="!can.roles || selectedRole.name === 'super_admin'" @change="togglePermission(roleForm, permission.name)" />{{ permission.name }}</label></div></div><HsiButton v-if="can.roles && selectedRole.name !== 'super_admin'" type="submit"><Save class="h-4 w-4" /> Enregistrer la matrice</HsiButton></form></div></HsiCard>
        </div>

        <!-- Utilisateurs -->
        <HsiCard v-else-if="activeTab === 'users'" padding="md">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><HsiSectionTitle title="Attribution des rôles" /><HsiViewToggle v-model="userView" /></div>
            <HsiListToolbar v-model:search="userList.search.value" placeholder="Nom, email ou rôle…" :active="userList.active.value" :count="userList.total.value" label="utilisateur(s)" @reset="userList.reset()">
                <select v-model="userList.filters.role" :class="selectClass"><option value="">Tous les rôles</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select>
                <select v-model="userList.filters.status" :class="selectClass"><option value="">Tous les statuts</option><option v-for="s in userStatuses" :key="s" :value="s">{{ statusMeta[s]?.label ?? s }}</option></select>
            </HsiListToolbar>

            <HsiEmptyState v-if="!userList.total.value" :icon="Users" title="Aucun utilisateur" description="Aucun utilisateur ne correspond à ces filtres." />

            <div v-else-if="userView === 'cards'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="user in userList.paged.value" :key="user.id" class="flex flex-col rounded-2xl border border-hsi-border bg-white p-4 transition hover:border-hsi-gold/50 hover:shadow-hsiSoft">
                    <div class="flex items-start gap-3">
                        <HsiAvatar :name="user.name" :src="user.avatar_url" size="lg" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-hsi-navy">{{ user.name }}</p>
                            <p class="flex items-center gap-1 truncate text-xs text-hsi-slate-light"><Mail class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ user.email }}</span></p>
                            <div class="mt-1.5"><HsiBadge :variant="statusOf(user).variant" size="xs">{{ statusOf(user).label }}</HsiBadge></div>
                        </div>
                    </div>
                    <div v-if="editingUserId === user.id" class="mt-4 space-y-2 border-t border-hsi-border pt-3">
                        <div class="flex flex-wrap gap-1.5"><label v-for="role in roles" :key="role.id" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-xs" :class="editingRoleIds.includes(role.id) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate'"><input v-model="editingRoleIds" type="checkbox" :value="role.id" class="h-3.5 w-3.5 rounded border-hsi-border text-hsi-navy" />{{ role.name }}</label></div>
                        <div class="flex gap-2"><HsiButton size="sm" :disabled="!editingRoleIds.length" @click="saveUserRoles(user)"><Save class="h-4 w-4" /> Enregistrer</HsiButton><HsiButton size="sm" variant="ghost" @click="editingUserId = null">Annuler</HsiButton></div>
                    </div>
                    <template v-else>
                        <div class="mt-4 flex flex-wrap gap-1.5"><span v-for="chip in roleChips(user)" :key="chip" class="rounded-full bg-hsi-navy/5 px-2.5 py-1 text-[11px] font-medium text-hsi-navy">{{ chip }}</span></div>
                        <div class="mt-auto flex items-center justify-between gap-2 border-t border-hsi-border/70 pt-3 text-xs" :class="roleChips(user).length ? 'mt-4' : ''">
                            <span class="text-hsi-slate-light"><template v-if="user.created_at">Depuis le {{ user.created_at }}</template></span>
                            <div class="flex gap-3">
                                <Link v-if="user.member_profile_id" :href="route('members.show', user.member_profile_id)" class="font-semibold text-hsi-slate hover:text-hsi-navy">Fiche</Link>
                                <button v-if="can.users" type="button" class="inline-flex items-center gap-1 font-semibold text-[#1D4ED8] hover:underline" @click="editUserRoles(user)"><Pencil class="h-3.5 w-3.5" aria-hidden="true" /> Rôles</button>
                            </div>
                        </div>
                    </template>
                </article>
            </div>

            <div v-else class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm"><thead><tr class="border-b border-hsi-border text-left"><th class="py-3">Utilisateur</th><th>Email</th><th>Statut</th><th>Rôles</th></tr></thead><tbody><tr v-for="user in userList.paged.value" :key="user.id" class="border-b border-hsi-border/50"><td class="py-3"><div class="flex items-center gap-2"><HsiAvatar :name="user.name" :src="user.avatar_url" size="sm" /><span class="font-medium">{{ user.name }}</span></div></td><td>{{ user.email }}</td><td><HsiBadge :variant="statusOf(user).variant">{{ statusOf(user).label }}</HsiBadge></td><td class="py-3"><div v-if="editingUserId === user.id" class="space-y-2"><div class="flex flex-wrap gap-1.5"><label v-for="role in roles" :key="role.id" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-xs" :class="editingRoleIds.includes(role.id) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate'"><input v-model="editingRoleIds" type="checkbox" :value="role.id" class="h-3.5 w-3.5 rounded border-hsi-border text-hsi-navy" />{{ role.name }}</label></div><div class="flex gap-2"><HsiButton size="sm" :disabled="!editingRoleIds.length" @click="saveUserRoles(user)"><Save class="h-4 w-4" /> Enregistrer</HsiButton><HsiButton size="sm" variant="ghost" @click="editingUserId = null">Annuler</HsiButton></div></div><div v-else class="flex flex-wrap items-center gap-2"><span>{{ user.role }}</span><button v-if="can.users" type="button" class="text-xs font-semibold text-[#1D4ED8] hover:underline" @click="editUserRoles(user)">Modifier</button></div></td></tr></tbody></table></div>

            <HsiPagination v-model:page="userList.page.value" v-model:per-page="userList.perPage.value" :total="userList.total.value" :per-page-options="[12, 24, 48, 96]" label="utilisateurs" />
        </HsiCard>

        <!-- Permissions -->
        <HsiCard v-else-if="activeTab === 'permissions'" padding="lg">
            <HsiSectionTitle title="Catalogue des permissions" />
            <form v-if="can.permissions" class="mb-5 flex gap-2" @submit.prevent="createPermission"><input v-model="permissionForm.name" required class="flex-1 rounded-xl border-hsi-border text-sm" placeholder="nouvelle permission" /><HsiButton type="submit"><Plus class="h-4 w-4" /> Ajouter</HsiButton></form>
            <HsiListToolbar v-model:search="permissionList.search.value" placeholder="Rechercher une permission…" :active="permissionList.active.value" :count="permissionList.total.value" label="permission(s)" @reset="permissionList.reset()">
                <select v-model="permissionList.filters.group" :class="selectClass"><option value="">Tous les domaines</option><option v-for="(items, group) in permissionGroups" :key="group" :value="group">{{ group }}</option></select>
                <select v-model="permissionList.filters.usage" :class="selectClass"><option value="">Toutes</option><option value="used">Attribuées à un rôle</option><option value="unused">Non attribuées</option></select>
            </HsiListToolbar>
            <HsiEmptyState v-if="!permissionList.total.value" :icon="KeyRound" title="Aucune permission" description="Aucune permission ne correspond à ces filtres." />
            <div v-else class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3"><div v-for="permission in permissionList.paged.value" :key="permission.id" class="flex items-center justify-between rounded-xl border border-hsi-border p-3 text-sm"><div><p>{{ permission.name }}</p><p class="text-xs text-hsi-slate-light">{{ permission.group }} · {{ permission.roles_count }} rôle(s)</p></div><button v-if="can.permissions && permission.roles_count === 0" type="button" class="text-hsi-danger" @click="deletePermission(permission)"><Trash2 class="h-4 w-4" /></button></div></div>
            <HsiPagination v-model:page="permissionList.page.value" v-model:per-page="permissionList.perPage.value" :total="permissionList.total.value" :per-page-options="[24, 48, 96]" label="permissions" />
        </HsiCard>

        <!-- Paramètres -->
        <div v-else-if="activeTab === 'settings'" class="space-y-6">
            <HsiCard padding="lg">
                <div class="mb-1 flex items-center gap-2"><Coins class="h-5 w-5 text-hsi-gold" aria-hidden="true" /><HsiSectionTitle title="Devise du fonds" /></div>
                <p class="mb-5 text-sm text-hsi-slate">
                    Devise actuelle : <strong class="text-hsi-navy">{{ currentCurrency?.label }} ({{ currentCurrency?.symbol }})</strong>.
                    Tous les montants de l’application sont exprimés dans cette devise. En changer convertit toutes les données enregistrées au taux indiqué.
                </p>
                <form class="space-y-4" @submit.prevent="convertCurrency">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-hsi-slate" for="fund-currency">Devise</label>
                            <select id="fund-currency" v-model="currencyForm.currency" :disabled="!can.settings" class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm">
                                <option v-for="option in currency.options" :key="option.value" :value="option.value">{{ option.label }} — {{ option.value }} ({{ option.symbol }})</option>
                            </select>
                        </div>
                        <div v-if="changingCurrency">
                            <label class="mb-1 block text-sm font-medium text-hsi-slate" for="fund-rate">Taux : 1 {{ currency.current }} = … {{ currencyForm.currency }}</label>
                            <input id="fund-rate" v-model="currencyForm.rate" type="text" inputmode="decimal" required class="w-full rounded-xl border border-hsi-border px-3 py-2.5 text-sm" placeholder="ex. 0,92">
                            <p class="mt-1 text-xs text-hsi-slate-light">
                                <template v-if="currency.rates[currencyForm.currency]">Taux proposé : cours du jour (ou parité fixe euro / franc CFA : 1 € = 655,957 FCFA). Vous pouvez le corriger.</template>
                                <template v-else>Cours du jour indisponible : saisissez le taux de votre banque.</template>
                            </p>
                        </div>
                    </div>

                    <div v-if="changingCurrency" class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-hsi-navy">
                        <p class="flex items-start gap-2 font-semibold"><AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-hsi-warning" aria-hidden="true" /> Conversion de toutes les données</p>
                        <p>Cotisations, échéances et pénalités, soldes et mouvements des comptes, projets, prêts et échéanciers, retraits, ainsi que les montants du règlement (cotisation trimestrielle, pénalité) seront multipliés par le taux. L’opération est enregistrée dans le journal d’audit.</p>
                        <ul v-if="ratePreview" class="flex flex-wrap gap-x-6 gap-y-1 text-xs">
                            <li v-for="([before, after], i) in ratePreview.examples" :key="i" class="inline-flex items-center gap-1.5">{{ before }} <ArrowRight class="h-3 w-3" aria-hidden="true" /> <strong>{{ after }}</strong></li>
                        </ul>
                        <p v-if="currency.pending_approvals" class="rounded-xl bg-red-50 px-3 py-2 text-hsi-danger">{{ currency.pending_approvals }} opération(s) attendent des validations : traitez-les avant de changer de devise.</p>
                        <label class="flex items-start gap-2"><input v-model="currencyForm.confirm" type="checkbox" class="mt-0.5 h-4 w-4 rounded"> Je confirme la conversion de tous les montants de {{ currency.current }} en {{ currencyForm.currency }}.</label>
                    </div>
                    <p v-if="currencyForm.errors.currency || currencyForm.errors.rate || currencyForm.errors.confirm" class="text-sm text-hsi-danger">{{ currencyForm.errors.currency || currencyForm.errors.rate || currencyForm.errors.confirm }}</p>
                    <HsiButton v-if="can.settings && changingCurrency" type="submit" :disabled="currencyForm.processing || !currencyForm.confirm || !!currency.pending_approvals"><Coins class="h-4 w-4" /> Convertir et passer en {{ targetCurrency?.label }}</HsiButton>
                </form>
            </HsiCard>

            <HsiCard v-if="hasOtherSettings" padding="lg"><HsiSectionTitle title="Paramètres applicatifs" /><form class="space-y-6" @submit.prevent="saveSettings"><section v-for="(items, group) in settings" :key="group"><h3 class="mb-3 font-semibold capitalize">{{ group }}</h3><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><div v-for="setting in items" :key="setting.key"><label class="text-sm font-medium">{{ setting.label }}</label><input v-model="settingsForm.settings[setting.key]" :type="setting.type === 'integer' ? 'number' : 'text'" :disabled="!can.settings" class="w-full rounded-xl border-hsi-border text-sm" /><p v-if="setting.description" class="text-xs text-hsi-slate-light">{{ setting.description }}</p></div></div></section><HsiButton v-if="can.settings" type="submit"><Save class="h-4 w-4" /> Enregistrer</HsiButton></form></HsiCard>
        </div>

        <!-- Catégories documentaires -->
        <HsiCard v-else-if="activeTab === 'categories'" padding="lg">
            <HsiSectionTitle title="Catégories documentaires" />
            <form v-if="can.categories" class="mb-5 grid grid-cols-1 gap-2 md:grid-cols-4" @submit.prevent="createCategory"><input v-model="categoryForm.name" required class="rounded-xl border-hsi-border text-sm" placeholder="Nom" /><input v-model="categoryForm.description" class="rounded-xl border-hsi-border text-sm md:col-span-2" placeholder="Description" /><input v-model="categoryForm.retention_days" type="number" min="1" class="rounded-xl border-hsi-border text-sm" placeholder="Rétention (jours)" /><HsiButton type="submit"><Plus class="h-4 w-4" /> Ajouter</HsiButton></form>
            <HsiListToolbar v-model:search="categoryList.search.value" placeholder="Rechercher une catégorie…" :active="categoryList.active.value" :count="categoryList.total.value" label="catégorie(s)" @reset="categoryList.reset()">
                <select v-model="categoryList.filters.state" :class="selectClass"><option value="">Toutes</option><option value="active">Actives</option><option value="inactive">Inactives</option></select>
            </HsiListToolbar>
            <div class="space-y-2"><div v-for="category in categoryList.paged.value" :key="category.id" class="flex justify-between rounded-xl border border-hsi-border p-3"><div><p class="font-medium">{{ category.name }}</p><p class="text-xs text-hsi-slate-light">{{ category.description || 'Sans description' }} · Rétention {{ category.retention_days || 'illimitée' }}</p></div><button v-if="can.categories" type="button" @click="toggleCategory(category)"><HsiBadge :variant="category.is_active ? 'success' : 'neutral'">{{ category.is_active ? 'Active' : 'Inactive' }}</HsiBadge></button></div></div>
            <p v-if="!categoryList.total.value" class="text-sm text-hsi-slate-light">Aucune catégorie ne correspond.</p>
            <HsiPagination v-model:page="categoryList.page.value" v-model:per-page="categoryList.perPage.value" :total="categoryList.total.value" label="catégories" />
        </HsiCard>

        <!-- Journal d'administration -->
        <HsiCard v-else padding="lg">
            <HsiSectionTitle title="Journal d’administration" />
            <HsiListToolbar v-model:search="audit.search" placeholder="Rechercher une action…" :active="auditActive" :count="activities.total" label="entrée(s)" @reset="resetAudit">
                <select v-model="audit.log" :class="selectClass"><option value="">Tous les domaines</option><option v-for="log in auditLogs" :key="log" :value="log">{{ logLabel(log) }}</option></select>
                <select v-model="audit.user" :class="selectClass"><option value="">Toutes les personnes</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select>
                <label class="inline-flex items-center gap-1.5 text-xs text-hsi-slate"><CalendarDays class="h-4 w-4" aria-hidden="true" /> Du <input v-model="audit.from" type="date" class="rounded-xl border border-hsi-border px-2 py-2 text-sm"></label>
                <label class="inline-flex items-center gap-1.5 text-xs text-hsi-slate">au <input v-model="audit.to" type="date" class="rounded-xl border border-hsi-border px-2 py-2 text-sm"></label>
            </HsiListToolbar>
            <ul class="divide-y divide-hsi-border/60">
                <li v-for="activity in activities.data" :key="activity.id" class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0"><p class="text-hsi-navy">{{ activity.description }}</p><p class="text-xs text-hsi-slate-light">{{ activity.causer }} · {{ activity.event }}</p></div>
                    <div class="flex shrink-0 items-center gap-2"><HsiBadge variant="neutral" size="xs">{{ logLabel(activity.log) }}</HsiBadge><span class="whitespace-nowrap text-xs text-hsi-slate-light">{{ activity.date }}</span></div>
                </li>
            </ul>
            <p v-if="!activities.data.length" class="py-6 text-center text-sm text-hsi-slate-light">Aucune activité ne correspond à ces filtres.</p>
            <HsiPagination :paginator="activities" label="entrées" @update:per-page="audit.per_page = $event" />
        </HsiCard>
    </AuthenticatedLayout>
</template>
