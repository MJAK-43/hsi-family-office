<script setup>
import { tr } from '@/i18n';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download, Upload, Send, CheckCircle, Archive, RotateCcw, ShieldCheck, FileText } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

const props = defineProps({ document: Object, activities: Array, roles: Array, users: Array, can: Object, approvalVotes: { type: Array, default: () => [] } });
// Constitution sans vote approuvé : publication bloquée, on propose de lier le vote ou de retirer la mention.
const constitutionForm = useForm({ is_constitution: true, approval_vote_id: props.document.approval_vote_id ?? '' });
const linkVote = () => { constitutionForm.is_constitution = true; constitutionForm.patch(route('documents.constitution', props.document.id), { preserveScroll: true }); };
const removeConstitution = () => {
    if (!confirm(tr('Retirer la mention « Constitution » de ce document ?'))) return;
    constitutionForm.is_constitution = false;
    constitutionForm.approval_vote_id = '';
    constitutionForm.patch(route('documents.constitution', props.document.id), { preserveScroll: true });
};
const versionForm = useForm({ file: null, change_note: '' });
const accessForm = useForm({ role_ids: [...(props.document.role_ids ?? [])], user_ids: [...(props.document.user_ids ?? [])] });
const addVersion = () => versionForm.post(route('documents.versions.store', props.document.id), { forceFormData: true, onSuccess: () => versionForm.reset() });
const workflow = (action) => {
    const labels = { 'submit-review': 'soumettre ce document à validation', publish: 'publier cette version', archive: 'archiver ce document', restore: 'restaurer ce document' };
    if (confirm(tr('Voulez-vous {action} ?', { action: tr(labels[action]) }))) router.patch(route(`documents.${action}`, props.document.id));
};
const saveAccess = () => accessForm.put(route('documents.access.update', props.document.id));
const toggle = (values, id) => { const index = values.indexOf(id); index >= 0 ? values.splice(index, 1) : values.push(id); };
</script>

<template>
    <Head :title="document.title" />
    <AuthenticatedLayout>
        <HsiPageHeader :title="document.title" :subtitle="`${$tr(document.category)} · ${$tr(document.source_label)}`">
            <template #actions><div class="flex flex-wrap gap-2">
                <a v-if="can.download && document.download_url" :href="document.download_url"><HsiButton variant="outline"><Download class="h-4 w-4" /> Télécharger</HsiButton></a>
                <HsiButton v-if="can.manage && document.status === 'draft'" variant="outline" @click="workflow('submit-review')"><Send class="h-4 w-4" /> Soumettre</HsiButton>
                <HsiButton v-if="can.publish && ['draft', 'in_review'].includes(document.status)" :disabled="document.constitution_blocked" :title="document.constitution_blocked ? $tr('Liez d’abord le vote d’approbation (voir l’encadré)') : ''" @click="workflow('publish')"><CheckCircle class="h-4 w-4" /> Publier</HsiButton>
                <HsiButton v-if="can.manage && document.status === 'published'" variant="outline" @click="workflow('archive')"><Archive class="h-4 w-4" /> Archiver</HsiButton>
                <HsiButton v-if="can.manage && document.status === 'archived'" @click="workflow('restore')"><RotateCcw class="h-4 w-4" /> Restaurer</HsiButton>
            </div></template>
        </HsiPageHeader>
        <div class="flex flex-wrap items-center gap-3 mb-6"><HsiBadge :variant="document.status_variant" size="lg">{{ document.status_label }}</HsiBadge><HsiBadge v-if="document.is_constitution" variant="gold">Constitution</HsiBadge><span class="text-sm text-hsi-slate">Version courante {{ document.version }}</span><span v-if="document.retention_until" class="text-sm text-hsi-slate">Rétention jusqu’au {{ document.retention_until }}</span></div>
        <div v-if="document.constitution_blocked && ['draft', 'in_review'].includes(document.status)" class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5">
            <p class="font-semibold text-hsi-navy">Publication impossible pour l’instant</p>
            <p class="mt-1 text-sm text-hsi-slate">Ce document est marqué « Constitution ou texte fondamental ». Il ne peut être publié qu’une fois lié à un vote clôturé et approuvé par la famille.</p>
            <div v-if="can.manage" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <label v-if="approvalVotes.length" class="flex-1 text-sm font-medium text-hsi-slate">Vote d’approbation
                    <select v-model="constitutionForm.approval_vote_id" class="mt-1 w-full rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="" disabled>Choisir un vote approuvé</option><option v-for="vote in approvalVotes" :key="vote.id" :value="vote.id">{{ vote.title }}</option></select>
                </label>
                <p v-else class="flex-1 text-sm text-hsi-slate">Aucun vote clôturé et approuvé pour l’instant : organisez d’abord le vote de la famille, ou retirez la mention si ce n’est pas la Constitution.</p>
                <HsiButton v-if="approvalVotes.length" :disabled="!constitutionForm.approval_vote_id || constitutionForm.processing" @click="linkVote">Lier le vote</HsiButton>
                <HsiButton variant="outline" :disabled="constitutionForm.processing" @click="removeConstitution">Ce n’est pas la Constitution</HsiButton>
            </div>
            <p v-if="Object.keys(constitutionForm.errors).length" class="mt-2 text-sm text-hsi-danger">{{ Object.values(constitutionForm.errors)[0] }}</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <HsiCard padding="lg"><HsiSectionTitle title="Informations" /><p v-if="document.description" class="text-sm text-hsi-slate whitespace-pre-wrap">{{ document.description }}</p><p v-else class="text-sm text-hsi-slate-light">Aucune description.</p><p v-if="document.approval_vote" class="mt-3 text-sm"><strong>Vote d’approbation :</strong> {{ document.approval_vote }}</p></HsiCard>
                <HsiCard padding="lg"><HsiSectionTitle title="Historique des versions" />
                    <div class="space-y-3"><article v-for="version in document.versions" :key="version.id" class="rounded-xl border border-hsi-border p-4">
                        <div class="flex justify-between gap-3"><div><p class="font-semibold">Version {{ version.number }} — {{ version.name }}</p><p class="text-xs text-hsi-slate-light">{{ version.uploader }} · {{ version.date }} · {{ version.size }}</p></div><a :href="version.download_url" class="text-hsi-gold"><Download class="h-4 w-4" /></a></div>
                        <p v-if="version.note" class="mt-2 text-sm">{{ version.note }}</p><p class="mt-2 font-mono text-[10px] break-all text-hsi-slate-light">SHA-256 : {{ version.sha256 }}</p>
                    </article></div>
                    <form v-if="can.manage && document.status !== 'archived'" class="mt-5 border-t border-hsi-border pt-4 space-y-3" @submit.prevent="addVersion"><h3 class="font-medium">Ajouter une version immuable</h3><input type="file" required class="w-full rounded-xl border-hsi-border text-sm" @change="versionForm.file = $event.target.files[0]" /><input v-model="versionForm.change_note" class="w-full rounded-xl border-hsi-border text-sm" placeholder="Résumé des changements" /><HsiButton type="submit" :disabled="versionForm.processing"><Upload class="h-4 w-4" /> Ajouter la version</HsiButton></form>
                </HsiCard>
                <HsiCard v-if="can.manageAccess && document.visibility === 'restricted'" padding="lg"><HsiSectionTitle title="Accès restreints" />
                    <form class="space-y-5" @submit.prevent="saveAccess"><div><h3 class="text-sm font-medium mb-2">Rôles autorisés</h3><div class="grid grid-cols-2 md:grid-cols-3 gap-2"><label v-for="role in roles" :key="role.id" class="flex items-center gap-2 text-sm"><input type="checkbox" :checked="accessForm.role_ids.includes(role.id)" @change="toggle(accessForm.role_ids, role.id)" /> {{ role.name }}</label></div></div>
                    <div><h3 class="text-sm font-medium mb-2">Utilisateurs explicitement autorisés</h3><div class="max-h-52 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-2"><label v-for="user in users" :key="user.id" class="flex items-center gap-2 text-sm"><input type="checkbox" :checked="accessForm.user_ids.includes(user.id)" @change="toggle(accessForm.user_ids, user.id)" /> {{ user.name }} <span class="text-xs text-hsi-slate-light">{{ user.email }}</span></label></div></div>
                    <HsiButton type="submit" :disabled="accessForm.processing"><ShieldCheck class="h-4 w-4" /> Enregistrer les accès</HsiButton></form>
                </HsiCard>
            </div>
            <div class="space-y-6"><HsiCard padding="lg"><HsiSectionTitle title="Traçabilité" /><ul class="space-y-3"><li v-for="activity in activities" :key="`${activity.date}-${activity.description}`" class="border-b border-hsi-border/50 pb-2 text-sm"><p>{{ activity.description }}</p><p class="text-xs text-hsi-slate-light">{{ activity.causer }} · {{ activity.date }}</p></li><li v-if="!activities.length" class="text-sm text-hsi-slate-light"><FileText class="h-4 w-4 inline" /> Historique non disponible.</li></ul></HsiCard>
                <HsiCard padding="lg"><HsiSectionTitle title="Confidentialité" /><dl class="text-sm space-y-2"><div><dt class="text-hsi-slate-light">Visibilité</dt><dd>{{ document.visibility }}</dd></div><div><dt class="text-hsi-slate-light">Empreinte</dt><dd>SHA-256 par version</dd></div><div><dt class="text-hsi-slate-light">Stockage</dt><dd>Privé, téléchargement contrôlé</dd></div></dl></HsiCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
