<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

const props = defineProps({
    types: Array,
    majorityRules: Array,
    projects: Array,
});

const form = useForm({
    title: '',
    description: '',
    type: 'governance',
    project_id: null,
    status: 'open',
    opens_at: '',
    closes_at: '',
    quorum_percent: 50,
    majority_rule: 'simple',
    allow_abstention: true,
    ballot_visibility: 'secret',
});

const customOptions = ref('');

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm text-hsi-navy focus:border-hsi-gold focus:outline-none focus:ring-2 focus:ring-hsi-gold/20';
const labelClass = 'block text-sm font-medium text-hsi-slate mb-1.5';

const submit = () => {
    form.transform((data) => ({
        ...data,
        options: customOptions.value.split('\n').map((value) => value.trim()).filter(Boolean),
    })).post(route('votes.store'));
};
</script>

<template>
    <Head title="Créer un vote" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Créer un "
            accent="vote"
            subtitle="Ouvrir une consultation familiale"
        />

        <HsiCard padding="lg">
            <form
                class="space-y-6"
                @submit.prevent="submit"
            >
                <div>
                    <label :class="labelClass">Titre *</label>
                    <input
                        v-model="form.title"
                        type="text"
                        :class="inputClass"
                        required
                        maxlength="180"
                    />
                    <p
                        v-if="form.errors.title"
                        class="mt-1 text-xs text-hsi-danger"
                    >
                        {{ form.errors.title }}
                    </p>
                </div>
                <div>
                    <label :class="labelClass">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        :class="inputClass"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelClass">Ouverture</label>
                        <select v-model="form.status" :class="inputClass">
                            <option value="open">Immédiate</option>
                            <option value="scheduled">Planifiée</option>
                            <option value="draft">Brouillon</option>
                        </select>
                    </div>
                    <div v-if="form.status === 'scheduled'">
                        <label :class="labelClass">Date d’ouverture *</label>
                        <input v-model="form.opens_at" type="datetime-local" :class="inputClass" required />
                    </div>
                    <div>
                        <label :class="labelClass">Type *</label>
                        <select
                            v-model="form.type"
                            :class="inputClass"
                            required
                        >
                            <option
                                v-for="t in types"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass">Projet lié</label>
                        <select
                            v-model="form.project_id"
                            :class="inputClass"
                        >
                            <option :value="null">Aucun</option>
                            <option
                                v-for="p in projects"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.title }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass">Date de clôture</label>
                        <input
                            v-model="form.closes_at"
                            type="datetime-local"
                            :class="inputClass"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Quorum (%)</label>
                        <input
                            v-model="form.quorum_percent"
                            type="number"
                            min="1"
                            max="100"
                            :class="inputClass"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Règle de majorité</label>
                        <select
                            v-model="form.majority_rule"
                            :class="inputClass"
                        >
                            <option
                                v-for="r in majorityRules"
                                :key="r.value"
                                :value="r.value"
                            >
                                {{ r.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.allow_abstention"
                                type="checkbox"
                            />
                            Autoriser l'abstention
                        </label>
                    </div>
                    <div>
                        <label :class="labelClass">Confidentialité</label>
                        <select v-model="form.ballot_visibility" :class="inputClass">
                            <option value="secret">Bulletin secret</option>
                            <option value="named">Bulletin nominatif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label :class="labelClass">Options personnalisées</label>
                    <textarea v-model="customOptions" rows="4" :class="inputClass" placeholder="Une option par ligne. Laissez vide pour Pour / Contre." />
                    <p class="mt-1 text-xs text-hsi-slate-light">Deux options minimum. L’abstention est ajoutée automatiquement si elle est autorisée.</p>
                </div>

                <div class="flex gap-3 pt-4 border-t border-hsi-border">
                    <Link :href="route('votes.index')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                    <HsiButton
                        type="submit"
                        :disabled="form.processing"
                    >
                        Créer le vote
                    </HsiButton>
                </div>
            </form>
        </HsiCard>
    </AuthenticatedLayout>
</template>
