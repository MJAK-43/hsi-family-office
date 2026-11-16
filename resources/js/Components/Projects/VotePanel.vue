<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarClock, Vote } from 'lucide-vue-next';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

const props = defineProps({
    project: { type: Object, required: true },
    defaults: { type: Object, required: true },
    canLaunch: { type: Boolean, default: false },
    canReschedule: { type: Boolean, default: false },
});

const mode = ref('now');
const launchForm = useForm({ opens_at: props.defaults.opens_at, closes_at: props.defaults.closes_at });
const launch = () => launchForm.transform((data) => (mode.value === 'now' ? { closes_at: data.closes_at } : data))
    .patch(route('projects.submit-to-vote', props.project.id), { preserveScroll: true });

const datesForm = useForm({ opens_at: props.project.active_vote_opens_at, closes_at: props.project.active_vote_closes_at });
const reschedule = () => datesForm.patch(route('votes.reschedule', props.project.active_vote_id), { preserveScroll: true });
const inputClass = 'w-full rounded-xl border border-hsi-border px-3 py-2 text-sm';
</script>

<template>
    <HsiCard padding="lg">
        <HsiSectionTitle title="Vote des membres" />
        <p class="mb-4 text-sm text-hsi-slate">
            Approbation si au moins <strong>{{ defaults.threshold }} %</strong> des voix exprimées (pour et contre) sont favorables. Les abstentions ne comptent pas. Le vote est secret et le demandeur ne vote pas.
        </p>

        <form v-if="canLaunch" class="space-y-3" @submit.prevent="launch">
            <div class="flex flex-wrap gap-2">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm" :class="mode === 'now' ? 'border-hsi-gold bg-hsi-gold/10' : 'border-hsi-border'"><input v-model="mode" type="radio" value="now" class="h-4 w-4"> Lancer maintenant</label>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm" :class="mode === 'schedule' ? 'border-hsi-gold bg-hsi-gold/10' : 'border-hsi-border'"><input v-model="mode" type="radio" value="schedule" class="h-4 w-4"> Programmer</label>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div v-if="mode === 'schedule'">
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="vote-opens">Ouverture</label>
                    <input id="vote-opens" v-model="launchForm.opens_at" type="datetime-local" :class="inputClass" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="vote-closes">Clôture</label>
                    <input id="vote-closes" v-model="launchForm.closes_at" type="datetime-local" :class="inputClass" required>
                </div>
            </div>
            <p v-if="launchForm.errors.submit_to_vote || launchForm.errors.closes_at" class="text-sm text-hsi-danger">{{ launchForm.errors.submit_to_vote || launchForm.errors.closes_at }}</p>
            <HsiButton type="submit" :disabled="launchForm.processing"><Vote class="h-4 w-4" aria-hidden="true" /> {{ mode === 'now' ? 'Ouvrir le vote' : 'Programmer le vote' }}</HsiButton>
        </form>

        <div v-else-if="project.active_vote_id" class="space-y-3">
            <p class="text-sm text-hsi-navy">
                <CalendarClock class="mr-1 inline h-4 w-4" aria-hidden="true" />
                {{ project.active_vote_status === 'scheduled' ? 'Vote programmé' : (project.active_vote_status === 'draft' ? 'Décision collective à préparer' : 'Vote en cours') }} :
                <Link :href="route('votes.show', project.active_vote_id)" class="font-semibold text-[#1D4ED8] hover:underline">{{ project.active_vote_title }}</Link>
            </p>
            <form v-if="canReschedule" class="grid gap-3 md:grid-cols-3 md:items-end" @submit.prevent="reschedule">
                <div v-if="project.active_vote_status === 'scheduled'">
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="resched-open">Ouverture</label>
                    <input id="resched-open" v-model="datesForm.opens_at" type="datetime-local" :class="inputClass">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hsi-slate" for="resched-close">Clôture</label>
                    <input id="resched-close" v-model="datesForm.closes_at" type="datetime-local" :class="inputClass" required>
                </div>
                <HsiButton type="submit" variant="outline" :disabled="datesForm.processing">Modifier les dates</HsiButton>
                <p v-if="datesForm.errors.vote_dates" class="text-sm text-hsi-danger md:col-span-3">{{ datesForm.errors.vote_dates }}</p>
            </form>
        </div>
    </HsiCard>
</template>
