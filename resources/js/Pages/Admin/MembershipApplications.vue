<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Briefcase, Cake, Check, Home, Inbox, Mail, MapPin, Phone, UserRound, X } from 'lucide-vue-next';
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiEmptyState from '@/Components/Hsi/HsiEmptyState.vue';
import HsiListToolbar from '@/Components/Hsi/HsiListToolbar.vue';
import HsiPagination from '@/Components/Hsi/HsiPagination.vue';
import { useListTools } from '@/Composables/useListTools';

const props = defineProps({
    applications: { type: Array, required: true },
    status: { type: String, required: true },
    tabs: { type: Array, required: true },
});

// Affiche le numéro international de façon lisible : +237 6 70 12 34 56.
const formatPhone = (phone) => parsePhoneNumberFromString(phone ?? '')?.formatInternational() ?? phone;

const badgeVariant = { pending: 'warning', accepted: 'success', refused: 'danger' };

const countries = [...new Set(props.applications.map((a) => a.country).filter(Boolean))].sort();
const list = useListTools(() => props.applications, {
    searchKeys: ['name', 'email', 'city', 'country', 'family_link', 'profession', 'sponsor_name'],
    filters: { country: (a, value) => a.country === value, sponsor: (a, value) => (value === 'yes' ? !!a.sponsor_name : !a.sponsor_name) },
    perPage: 10,
});

// Une seule demande à la fois en cours de décision : on affiche la zone de confirmation sous la carte.
const pending = ref(null);
const notes = reactive({});
const processing = ref(false);

const startDecision = (application, decision) => {
    pending.value = { id: application.id, decision };
};

const confirmDecision = () => {
    const { id, decision } = pending.value;
    processing.value = true;
    router.patch(route('membership-applications.decide', id), {
        decision,
        decision_note: notes[id] || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            pending.value = null;
        },
    });
};
</script>

<template>
    <Head title="Demandes d'adhésion" />
    <AuthenticatedLayout>
        <HsiPageHeader
            title="Demandes "
            accent="d'adhésion"
            subtitle="Les personnes qui souhaitent rejoindre le fonds. La décision est envoyée par email au candidat."
        />

        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Filtrer par statut">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="route('membership-applications.index', { status: tab.key })"
                preserve-scroll
                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition"
                :class="tab.key === status
                    ? 'border-hsi-navy bg-hsi-navy text-white'
                    : 'border-hsi-border bg-white text-hsi-slate hover:border-hsi-navy/40 hover:text-hsi-navy'"
                :aria-current="tab.key === status ? 'page' : undefined"
            >
                {{ tab.label }}
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="tab.key === status ? 'bg-white/15 text-white' : 'bg-hsi-muted text-hsi-slate'"
                >{{ tab.count }}</span>
            </Link>
        </nav>

        <HsiCard v-if="applications.length" padding="md" class="mb-5">
            <HsiListToolbar v-model:search="list.search.value" placeholder="Nom, email, ville, profession…" :active="list.active.value" :count="list.total.value" label="demande(s)" class="!mb-0" @reset="list.reset()">
                <select v-model="list.filters.country" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Tous les pays</option><option v-for="c in countries" :key="c" :value="c">{{ c }}</option></select>
                <select v-model="list.filters.sponsor" class="rounded-xl border border-hsi-border bg-white px-3 py-2.5 text-sm"><option value="">Avec ou sans parrain</option><option value="yes">Recommandées par un membre</option><option value="no">Sans recommandation</option></select>
            </HsiListToolbar>
        </HsiCard>

        <HsiCard v-if="!applications.length" padding="md">
            <HsiEmptyState
                :icon="Inbox"
                title="Aucune demande ici"
                :description="status === 'pending'
                    ? 'Les nouvelles demandes envoyées depuis la page de connexion apparaîtront ici.'
                    : 'Aucune demande n\'a encore ce statut.'"
            />
        </HsiCard>

        <HsiCard v-else-if="!list.total.value" padding="md">
            <HsiEmptyState :icon="Inbox" title="Aucune demande ne correspond" description="Modifiez la recherche ou les filtres." />
        </HsiCard>

        <div v-else class="grid gap-4 xl:grid-cols-2">
            <HsiCard v-for="a in list.paged.value" :key="a.id" padding="md" class="flex flex-col">
                <div class="flex flex-1 flex-col gap-4">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="font-heading text-xl font-semibold text-hsi-navy">{{ a.name }}</h2>
                            <HsiBadge :variant="badgeVariant[a.status] ?? 'neutral'">{{ a.status_label }}</HsiBadge>
                            <span class="text-xs text-hsi-slate-light">Reçue le {{ a.created_at }}</span>
                        </div>

                        <dl class="grid gap-x-6 gap-y-2 text-sm text-hsi-slate sm:grid-cols-2">
                            <div class="flex min-w-0 items-center gap-2"><Mail class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Email</dt><dd class="truncate">{{ a.email }}</dd></div>
                            <div v-if="a.phone" class="flex items-center gap-2"><Phone class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Téléphone</dt><dd class="tabular-nums">{{ formatPhone(a.phone) }}</dd></div>
                            <div class="flex items-center gap-2"><MapPin class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Lieu</dt><dd>{{ a.city ? `${a.city}, ` : '' }}{{ a.country }}</dd></div>
                            <div v-if="a.family_link" class="flex items-center gap-2"><UserRound class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Lien familial</dt><dd>{{ a.family_link }}</dd></div>
                            <div v-if="a.profession" class="flex items-center gap-2"><Briefcase class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Profession</dt><dd>{{ a.profession }}</dd></div>
                            <div v-if="a.date_of_birth" class="flex items-center gap-2"><Cake class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Date de naissance</dt><dd>Né(e) le {{ a.date_of_birth }}</dd></div>
                            <div v-if="a.address" class="flex min-w-0 items-center gap-2 sm:col-span-2"><Home class="h-4 w-4 shrink-0 text-hsi-slate-light" aria-hidden="true" /><dt class="sr-only">Adresse</dt><dd class="truncate">{{ a.address }}</dd></div>
                        </dl>

                        <p v-if="a.sponsor_name" class="text-sm text-hsi-slate">
                            <span class="font-medium text-hsi-navy">Recommandé par :</span> {{ a.sponsor_name }}
                        </p>
                        <blockquote v-if="a.message" class="whitespace-pre-line rounded-xl border-l-4 border-hsi-gold bg-hsi-cream px-4 py-3 text-sm leading-relaxed text-hsi-navy">{{ a.message }}</blockquote>

                        <p v-if="a.reviewer" class="text-xs text-hsi-slate-light">
                            {{ a.status_label }} par {{ a.reviewer }} le {{ a.reviewed_at }}<span v-if="a.decision_note"> · « {{ a.decision_note }} »</span>
                        </p>
                        <Link
                            v-if="a.member_profile_id"
                            :href="route('members.show', a.member_profile_id)"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-hsi-navy underline-offset-4 hover:underline"
                        >
                            Voir la fiche du membre créé →
                        </Link>
                    </div>

                    <div v-if="a.status === 'pending'" class="mt-auto flex flex-wrap gap-2 border-t border-hsi-border/70 pt-4">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-hsi-success px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-hsi-success/30"
                            @click="startDecision(a, 'accepted')"
                        >
                            <Check class="h-4 w-4" aria-hidden="true" /> Accepter
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm font-semibold text-hsi-danger transition hover:border-hsi-danger/40 focus:outline-none focus-visible:ring-4 focus-visible:ring-hsi-danger/20"
                            @click="startDecision(a, 'refused')"
                        >
                            <X class="h-4 w-4" aria-hidden="true" /> Refuser
                        </button>
                    </div>
                </div>

                <div
                    v-if="pending && pending.id === a.id"
                    class="mt-5 space-y-3 rounded-2xl border p-4"
                    :class="pending.decision === 'accepted' ? 'border-hsi-success/30 bg-green-50/60' : 'border-hsi-danger/25 bg-red-50/60'"
                >
                    <p class="text-sm font-semibold text-hsi-navy">
                        <template v-if="pending.decision === 'accepted'">
                            Accepter {{ a.name }} ? Son compte de membre sera créé immédiatement avec le mot de passe qu'il ou elle a choisi, et un email de bienvenue lui sera envoyé.
                        </template>
                        <template v-else>
                            Refuser la demande de {{ a.name }} ? Aucun compte ne sera créé ; un email l'en informera.
                        </template>
                    </p>
                    <label :for="`note-${a.id}`" class="block text-sm text-hsi-slate">Message joint à l'email (facultatif)</label>
                    <textarea
                        :id="`note-${a.id}`"
                        v-model="notes[a.id]"
                        rows="2"
                        maxlength="1000"
                        class="block w-full rounded-xl border-hsi-border text-sm focus:border-hsi-gold focus:ring-hsi-gold/30"
                    />
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :disabled="processing"
                            class="rounded-xl bg-hsi-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-hsi-navy-soft disabled:opacity-60"
                            @click="confirmDecision"
                        >
                            {{ processing ? 'Traitement…' : (pending.decision === 'accepted' ? 'Créer le compte et prévenir' : 'Refuser et prévenir') }}
                        </button>
                        <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold text-hsi-slate hover:text-hsi-navy" @click="pending = null">Annuler</button>
                    </div>
                </div>
            </HsiCard>
        </div>
        <HsiPagination v-if="list.total.value" v-model:page="list.page.value" v-model:per-page="list.perPage.value" :total="list.total.value" label="demandes" />
    </AuthenticatedLayout>
</template>
