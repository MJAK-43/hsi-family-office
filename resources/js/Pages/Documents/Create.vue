<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

const props = defineProps({ categories: Array, approvalVotes: Array, folders: { type: Array, default: () => [] }, folderId: { type: Number, default: null } });
// Arborescence indentée des dossiers.
const folderOptions = (() => {
    const byParent = {};
    props.folders.forEach((f) => { (byParent[f.parent_id ?? 'root'] ??= []).push(f); });
    const out = [];
    const walk = (parent, depth) => (byParent[parent] ?? []).forEach((f) => { out.push({ id: f.id, label: `${'— '.repeat(depth)}${f.name}` }); walk(f.id, depth + 1); });
    walk('root', 0);
    return out;
})();
const form = useForm({ folder_id: props.folderId, document_category_id: '', title: '', description: '', visibility: 'members', is_constitution: false, approval_vote_id: null, change_note: 'Version initiale', file: null });
const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm text-hsi-navy focus:border-hsi-gold focus:ring-hsi-gold/20';
const submit = () => form.post(route('documents.store'), { forceFormData: true });
</script>

<template>
    <Head title="Nouveau document" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Nouveau " accent="document" subtitle="Créer un document versionné dans le stockage privé" />
        <HsiCard padding="lg"><form class="space-y-5" @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="text-sm font-medium">Titre *</label><input v-model="form.title" :class="inputClass" required maxlength="180" /></div>
                <div><label class="text-sm font-medium">Catégorie *</label><select v-model="form.document_category_id" :class="inputClass"><option value="">Autres documents (par défaut)</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
                <div><label class="text-sm font-medium">Dossier</label><select v-model="form.folder_id" :class="inputClass"><option :value="null">Bibliothèque (racine)</option><option v-for="f in folderOptions" :key="f.id" :value="f.id">{{ f.label }}</option></select></div>
                <div><label class="text-sm font-medium">Visibilité *</label><select v-model="form.visibility" :class="inputClass"><option value="members">Tous les membres autorisés</option><option value="restricted">Accès restreint</option><option value="public">Public interne</option></select></div>
                <div><label class="text-sm font-medium">Fichier *</label><input type="file" :class="inputClass" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" @change="form.file = $event.target.files[0]" /></div>
                <div class="md:col-span-2"><label class="text-sm font-medium">Description</label><textarea v-model="form.description" rows="3" :class="inputClass" /></div>
                <div class="md:col-span-2"><label class="text-sm font-medium">Note de version</label><input v-model="form.change_note" :class="inputClass" /></div>
                <div><label class="flex items-center gap-2 text-sm"><input v-model="form.is_constitution" type="checkbox" /> Constitution ou texte fondamental</label><p v-if="form.is_constitution" class="mt-1 text-xs text-hsi-warning">Il ne pourra être publié qu’une fois lié à un vote clôturé et approuvé.</p></div>
                <div v-if="form.is_constitution"><label class="text-sm font-medium">Vote d’approbation</label><select v-model="form.approval_vote_id" :class="inputClass"><option :value="null">À associer ultérieurement</option><option v-for="vote in approvalVotes" :key="vote.id" :value="vote.id">{{ vote.title }}</option></select></div>
            </div>
            <p v-if="Object.keys(form.errors).length" class="text-sm text-hsi-danger">{{ Object.values(form.errors)[0] }}</p>
            <div class="flex justify-end gap-3 border-t border-hsi-border pt-4"><Link :href="route('documents.index')"><HsiButton variant="ghost">Annuler</HsiButton></Link><HsiButton type="submit" :disabled="form.processing">Créer le document</HsiButton></div>
        </form></HsiCard>
    </AuthenticatedLayout>
</template>
