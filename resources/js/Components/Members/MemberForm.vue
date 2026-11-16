<script setup>
import HsiButton from '@/Components/Hsi/HsiButton.vue';

const props = defineProps({
    form: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm text-hsi-navy focus:border-hsi-gold focus:outline-none focus:ring-2 focus:ring-hsi-gold/20';
const labelClass = 'block text-sm font-medium text-hsi-slate mb-1.5';
</script>

<template>
    <form
        class="space-y-8"
        @submit.prevent="emit('submit')"
    >
        <!-- Compte -->
        <div>
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                Compte utilisateur
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Nom complet *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        :class="inputClass"
                        required
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-xs text-hsi-danger"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>
                <div>
                    <label :class="labelClass">Email *</label>
                    <input
                        v-model="form.email"
                        type="email"
                        :class="inputClass"
                        required
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-xs text-hsi-danger"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>
                <div>
                    <label :class="labelClass">{{ isEdit ? 'Nouveau mot de passe' : 'Mot de passe' }}</label>
                    <input
                        v-model="form.password"
                        type="password"
                        :class="inputClass"
                        :required="!isEdit"
                    />
                    <p
                        v-if="form.errors.password"
                        class="mt-1 text-xs text-hsi-danger"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>
                <div>
                    <label :class="labelClass">Confirmer le mot de passe</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        :class="inputClass"
                    />
                </div>
                <fieldset class="md:col-span-2">
                    <legend :class="labelClass">Rôles * <span class="font-normal text-hsi-slate-light">(une personne peut en cumuler plusieurs)</span></legend>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <label
                            v-for="role in roles"
                            :key="role.name ?? role"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm transition"
                            :class="form.roles.includes(role.name ?? role) ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border text-hsi-slate hover:border-hsi-gold/50'"
                        >
                            <input
                                v-model="form.roles"
                                type="checkbox"
                                :value="role.name ?? role"
                                class="h-4 w-4 rounded border-hsi-border text-hsi-navy focus:ring-hsi-gold/40"
                            >
                            {{ role.label ?? role.name ?? role }}
                        </label>
                    </div>
                    <p
                        v-if="form.errors.roles"
                        class="mt-1 text-xs text-hsi-danger"
                    >
                        {{ form.errors.roles }}
                    </p>
                </fieldset>
                <div>
                    <label :class="labelClass">Statut *</label>
                    <select
                        v-model="form.status"
                        :class="inputClass"
                        required
                    >
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Profil -->
        <div>
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                Profil membre
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Pays</label>
                    <select
                        v-model="form.country_id"
                        :class="inputClass"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="country in countries"
                            :key="country.id"
                            :value="country.id"
                        >
                            {{ country.flag_emoji }} {{ country.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass">Zone du fonds</label>
                    <select
                        v-model="form.zone_id"
                        :class="inputClass"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="zone in zones"
                            :key="zone.id"
                            :value="zone.id"
                        >
                            {{ zone.name }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-hsi-slate-light">Les cotisations et remboursements sont reçus par le ou les trésoriers de cette zone.</p>
                </div>
                <div>
                    <label :class="labelClass">Branche familiale</label>
                    <select
                        v-model="form.family_branch_id"
                        :class="inputClass"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass">Profession</label>
                    <input
                        v-model="form.profession"
                        type="text"
                        :class="inputClass"
                    />
                </div>
                <div>
                    <label :class="labelClass">Ville</label>
                    <input
                        v-model="form.city"
                        type="text"
                        :class="inputClass"
                    />
                </div>
                <div>
                    <label :class="labelClass">Téléphone</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        :class="inputClass"
                    />
                </div>
                <div>
                    <label :class="labelClass">Date de naissance</label>
                    <input
                        v-model="form.date_of_birth"
                        type="date"
                        :class="inputClass"
                    />
                </div>
                <div>
                    <label :class="labelClass">Genre</label>
                    <input
                        v-model="form.gender"
                        type="text"
                        :class="inputClass"
                        placeholder="Masculin, Féminin..."
                    />
                </div>
                <div>
                    <label :class="labelClass">Membre depuis</label>
                    <input
                        v-model="form.member_since"
                        type="date"
                        :class="inputClass"
                    />
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">Adresse</label>
                    <input
                        v-model="form.address"
                        type="text"
                        :class="inputClass"
                    />
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">Biographie</label>
                    <textarea
                        v-model="form.bio"
                        rows="4"
                        :class="inputClass"
                    />
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">Notes internes</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        :class="inputClass"
                    />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-hsi-border">
            <HsiButton type="submit" :disabled="form.processing">
                {{ isEdit ? 'Enregistrer les modifications' : 'Créer le membre' }}
            </HsiButton>
            <slot name="cancel" />
        </div>
    </form>
</template>
