<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';
import ApprovalCard from '@/Components/Approvals/ApprovalCard.vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import { useListTools } from '@/Composables/useListTools';

const props = defineProps({ pending: Array, history: Array, types: Array, accounts: Array, canCreate: Boolean });

const showForm = ref(false);
const form = useForm({ transaction_type_id: null, title: '', amount: '', fund_account_id: null, to_fund_account_id: null, movement_date: new Date().toISOString().slice(0, 10), description: '' });
const selectedType = computed(() => props.types.find((t) => t.id === form.transaction_type_id));
const submit = () => form.post(route('approvals.store'), { preserveScroll: true, onSuccess: () => { form.reset(); showForm.value = false; } });
const toApprove = computed(() => props.pending.filter((r) => r.can_approve));
const others = computed(() => props.pending.filter((r) => !r.can_approve));
const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm';
const selectClass = 'rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm';

// Filtres communs aux listes : recherche, type de transaction, statut.
const searchKeys = ['title', 'type', 'requested_by', 'beneficiary', 'amount'];
const filters = { type: (r, value) => r.type === value, status: (r, value) => r.status === value };
const pendingList = useListTools(() => toApprove.value, { searchKeys, filters, perPage: 10 });
const othersList = useListTools(() => others.value, { searchKeys, filters, perPage: 10 });
const historyList = useListTools(() => props.history, { searchKeys, filters, perPage: 10 });
const typeNames = computed(() => [...new Set([...props.pending, ...props.history].map((r) => r.type).filter(Boolean))].sort());
const historyStatuses = computed(() => [...new Map(props.history.map((r) => [r.status, r.status_label])).entries()]);
</script>

<template>
    <Head title="Validations" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Validation des " accent="transactions" subtitle="Décaissements, remboursements, sorties d’argent : chaque transaction attend le nombre de validations prévu pour son type">
            <template #actions>
                <HsiButton v-if="canCreate && types.length && !showForm" @click="showForm = true"><Plus class="h-4 w-4" aria-hidden="true" /> Nouvelle transaction</HsiButton>
            </template>
        </HsiPageHeader>

        <HsiCard v-if="showForm" padding="lg" class="mb-6">
            <form class="space-y-4" @submit.prevent="submit">
                <h2 class="font-semibold text-hsi-navy">Nouvelle transaction</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-type">Type *</label>
                        <select id="tx-type" v-model="form.transaction_type_id" required :class="inputClass">
                            <option :value="null" disabled>Choisir</option>
                            <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }} ({{ t.required_approvals }} validation(s))</option>
                        </select>
                    </div>
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-title">Libellé *</label><input id="tx-title" v-model="form.title" required maxlength="180" :class="inputClass"></div>
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-amount">Montant ($) *</label><input id="tx-amount" v-model="form.amount" type="number" step="0.01" min="0.01" required :class="inputClass"></div>
                    <div><label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-date">Date</label><input id="tx-date" v-model="form.movement_date" type="date" :class="inputClass"></div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-account">{{ selectedType?.direction === 'internal' ? 'Compte de départ *' : 'Compte du fonds *' }}</label>
                        <select id="tx-account" v-model="form.fund_account_id" required :class="inputClass">
                            <option :value="null" disabled>Choisir</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} ({{ a.current_balance }} $)</option>
                        </select>
                    </div>
                    <div v-if="selectedType?.direction === 'internal'">
                        <label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-to">Compte d’arrivée *</label>
                        <select id="tx-to" v-model="form.to_fund_account_id" required :class="inputClass">
                            <option :value="null" disabled>Choisir</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2"><label class="mb-1 block text-sm font-medium text-hsi-slate" for="tx-desc">Description</label><input id="tx-desc" v-model="form.description" maxlength="2000" :class="inputClass"></div>
                </div>
                <p v-if="form.errors.amount || form.errors.fund_account_id || form.errors.to_fund_account_id" class="text-sm text-hsi-danger">{{ form.errors.amount || form.errors.fund_account_id || form.errors.to_fund_account_id }}</p>
                <div class="flex gap-2"><HsiButton type="submit" :disabled="form.processing">Soumettre</HsiButton><HsiButton type="button" variant="ghost" @click="showForm = false">Annuler</HsiButton></div>
            </form>
        </HsiCard>

        <p v-if="$page.props.errors?.approval" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $page.props.errors.approval }}</p>

        <div class="space-y-8">
            <section>
                <HsiSectionTitle :title="`${$tr('À valider')} (${toApprove.length})`" />
                <HsiListToolbar v-if="toApprove.length > 3" v-model:search="pendingList.search.value" placeholder="Rechercher…" :active="pendingList.active.value" :count="pendingList.total.value" @reset="pendingList.reset()">
                    <select v-model="pendingList.filters.type" :class="selectClass"><option value="">Tous les types</option><option v-for="t in typeNames" :key="t" :value="t">{{ t }}</option></select>
                </HsiListToolbar>
                <div class="grid gap-3 xl:grid-cols-2">
                    <ApprovalCard v-for="request in pendingList.paged.value" :key="request.id" :request="request" />
                </div>
                <p v-if="!toApprove.length" class="rounded-2xl border border-dashed border-hsi-border p-6 text-center text-sm text-hsi-slate-light">Rien à valider pour le moment.</p>
                <HsiPagination v-model:page="pendingList.page.value" v-model:per-page="pendingList.perPage.value" :total="pendingList.total.value" label="transactions" compact />
            </section>
            <section v-if="others.length">
                <HsiSectionTitle title="En attente d’autres personnes" />
                <HsiListToolbar v-if="others.length > 3" v-model:search="othersList.search.value" placeholder="Rechercher…" :active="othersList.active.value" :count="othersList.total.value" @reset="othersList.reset()">
                    <select v-model="othersList.filters.type" :class="selectClass"><option value="">Tous les types</option><option v-for="t in typeNames" :key="t" :value="t">{{ t }}</option></select>
                </HsiListToolbar>
                <div class="grid gap-3 xl:grid-cols-2"><ApprovalCard v-for="request in othersList.paged.value" :key="request.id" :request="request" /></div>
                <HsiPagination v-model:page="othersList.page.value" v-model:per-page="othersList.perPage.value" :total="othersList.total.value" label="transactions" compact />
            </section>
            <section>
                <HsiSectionTitle title="Historique" />
                <HsiListToolbar v-if="history.length" v-model:search="historyList.search.value" placeholder="Titre, type, personne…" :active="historyList.active.value" :count="historyList.total.value" @reset="historyList.reset()">
                    <select v-model="historyList.filters.type" :class="selectClass"><option value="">Tous les types</option><option v-for="t in typeNames" :key="t" :value="t">{{ t }}</option></select>
                    <select v-model="historyList.filters.status" :class="selectClass"><option value="">Tous les statuts</option><option v-for="[value, label] in historyStatuses" :key="value" :value="value">{{ label }}</option></select>
                </HsiListToolbar>
                <div class="grid gap-3 xl:grid-cols-2">
                    <ApprovalCard v-for="request in historyList.paged.value" :key="request.id" :request="request" />
                </div>
                <p v-if="!history.length" class="text-sm text-hsi-slate-light">Aucune transaction validée pour l’instant.</p>
                <p v-else-if="!historyList.total.value" class="text-sm text-hsi-slate-light">Aucune transaction ne correspond.</p>
                <HsiPagination v-model:page="historyList.page.value" v-model:per-page="historyList.perPage.value" :total="historyList.total.value" label="transactions" compact />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
