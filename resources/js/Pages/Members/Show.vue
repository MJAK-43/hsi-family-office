<script setup>
import { tr } from '@/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Pause, Play, Mail, Phone, MapPin, Calendar, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiAvatar from '@/Components/Hsi/HsiAvatar.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

defineProps({
    member: Object,
    activities: Array,
    can: Object,
});

const suspend = (id) => {
    if (confirm(tr('Suspendre ce membre ?'))) {
        router.patch(route('members.suspend', id));
    }
};

const activate = (id) => {
    router.patch(route('members.activate', id));
};

const destroy = (id, name) => {
    if (confirm(tr('Supprimer définitivement le membre {name} ?', { name }))) {
        router.delete(route('members.destroy', id));
    }
};
</script>

<template>
    <Head :title="`${$tr('Fiche membre')} — ${member.name}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="'Fiche membre '"
            :accent="member.name"
            subtitle="Profil détaillé du membre HSI Family Office"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.edit"
                        :href="route('members.edit', member.id)"
                    >
                        <HsiButton>
                            <Pencil class="h-4 w-4" />
                            Modifier
                        </HsiButton>
                    </Link>
                    <HsiButton
                        v-if="can.suspend && member.status === 'active'"
                        variant="outline"
                        @click="suspend(member.id)"
                    >
                        <Pause class="h-4 w-4" />
                        Suspendre
                    </HsiButton>
                    <HsiButton
                        v-if="can.suspend && member.status === 'suspended'"
                        variant="secondary"
                        @click="activate(member.id)"
                    >
                        <Play class="h-4 w-4" />
                        Activer
                    </HsiButton>
                    <HsiButton
                        v-if="can.delete"
                        variant="danger"
                        @click="destroy(member.id, member.name)"
                    >
                        <Trash2 class="h-4 w-4" />
                        Supprimer
                    </HsiButton>
                </div>
            </template>
        </HsiPageHeader>

        <!-- Carte profil principal -->
        <HsiCard
            padding="lg"
            class="mb-6"
        >
            <div class="flex flex-col lg:flex-row gap-6">
                <div class="flex flex-col items-center lg:items-start shrink-0">
                    <HsiAvatar
                        :name="member.name"
                        size="xl"
                    />
                    <HsiBadge
                        :variant="member.status_variant"
                        class="mt-3"
                    >
                        {{ member.status_label }}
                    </HsiBadge>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <h2 class="font-heading text-2xl font-semibold text-hsi-navy">
                            {{ member.name }}
                        </h2>
                        <HsiBadge variant="gold">{{ member.role_label }}</HsiBadge>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-hsi-slate mb-4">
                        <p v-if="member.country">
                            {{ member.country.flag_emoji }} {{ member.country.name }}
                        </p>
                        <p
                            v-if="member.city"
                            class="flex items-center gap-1"
                        >
                            <MapPin class="h-3.5 w-3.5" /> {{ member.city }}
                        </p>
                        <p v-if="member.zone">
                            Zone : {{ member.zone.name }}
                        </p>
                        <p v-if="member.total_contributions">
                            Cotisations : {{ member.total_contributions }} · part du fonds {{ member.share_percent }} %
                        </p>
                        <p v-if="member.family_branch">
                            Branche : {{ member.family_branch.name }}
                        </p>
                        <p v-if="member.profession">
                            {{ member.profession }}
                        </p>
                        <p
                            v-if="member.member_since"
                            class="flex items-center gap-1"
                        >
                            <Calendar class="h-3.5 w-3.5" /> Membre depuis {{ member.member_since }}
                        </p>
                    </div>
                    <p
                        v-if="member.bio"
                        class="text-sm text-hsi-slate leading-relaxed mb-4"
                    >
                        {{ member.bio }}
                    </p>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <a
                            v-if="member.email"
                            :href="`mailto:${member.email}`"
                            class="flex items-center gap-1.5 text-hsi-navy hover:text-hsi-gold transition-colors"
                        >
                            <Mail class="h-4 w-4" /> {{ member.email }}
                        </a>
                        <span
                            v-if="member.phone"
                            class="flex items-center gap-1.5 text-hsi-slate"
                        >
                            <Phone class="h-4 w-4" /> {{ member.phone }}
                        </span>
                    </div>
                </div>
            </div>
        </HsiCard>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Infos personnelles -->
            <HsiCard padding="md">
                <HsiSectionTitle title="Informations personnelles" />
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-hsi-slate-light text-xs uppercase tracking-wide">Date de naissance</dt>
                        <dd class="text-hsi-navy mt-0.5">{{ member.date_of_birth ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-hsi-slate-light text-xs uppercase tracking-wide">Genre</dt>
                        <dd class="text-hsi-navy mt-0.5">{{ member.gender ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-hsi-slate-light text-xs uppercase tracking-wide">Langues</dt>
                        <dd class="text-hsi-navy mt-0.5">{{ member.languages?.join(', ') || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-hsi-slate-light text-xs uppercase tracking-wide">Adresse</dt>
                        <dd class="text-hsi-navy mt-0.5">{{ member.address ?? '—' }}</dd>
                    </div>
                </dl>
            </HsiCard>

            <!-- Rôle & permissions -->
            <HsiCard padding="md">
                <HsiSectionTitle title="Rôle & permissions" />
                <p class="text-sm font-medium text-hsi-navy mb-2">{{ member.role_label }}</p>
                <p class="text-xs text-hsi-slate-light mb-3">{{ (member.roles ?? []).join(', ') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <HsiBadge
                        v-for="perm in member.permissions?.slice(0, 8)"
                        :key="perm"
                        variant="neutral"
                        size="xs"
                    >
                        {{ perm }}
                    </HsiBadge>
                </div>
            </HsiCard>

            <!-- Branche familiale -->
            <HsiCard padding="md">
                <HsiSectionTitle title="Branche familiale / Réseau" />
                <p class="text-sm font-medium text-hsi-navy">
                    {{ member.family_branch?.name ?? 'Non assignée' }}
                </p>
                <p class="text-xs text-hsi-slate-light mt-2">
                    Pays : {{ member.country?.name ?? '—' }}
                </p>
            </HsiCard>
        </div>

        <!-- Activité récente -->
        <HsiCard
            padding="md"
            class="mt-6"
        >
            <HsiSectionTitle
                title="Activité récente"
                subtitle="Journal d'audit du membre"
            />
            <div
                v-if="activities.length"
                class="space-y-3"
            >
                <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="flex items-start justify-between gap-4 py-2 border-b border-hsi-border/50 last:border-0"
                >
                    <div>
                        <p class="text-sm text-hsi-navy">{{ activity.description }}</p>
                        <p
                            v-if="activity.causer"
                            class="text-xs text-hsi-slate-light mt-0.5"
                        >
                            par {{ activity.causer }}
                        </p>
                    </div>
                    <span class="text-xs text-hsi-slate-light shrink-0">{{ activity.created_at }}</span>
                </div>
            </div>
            <p
                v-else
                class="text-sm text-hsi-slate-light italic"
            >
                Aucune activité enregistrée.
            </p>
        </HsiCard>

        <div class="mt-6">
            <Link
                :href="route('members.index')"
                class="text-sm text-hsi-gold hover:text-hsi-gold-light transition-colors"
            >
                ← Retour à l'annuaire
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
