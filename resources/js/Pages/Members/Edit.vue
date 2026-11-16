<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import MemberForm from '@/Components/Members/MemberForm.vue';

const props = defineProps({
    member: Object,
    countries: Array,
    branches: Array,
    zones: Array,
    roles: Array,
    statuses: Array,
});

const form = useForm({
    name: props.member.name,
    email: props.member.email,
    password: '',
    password_confirmation: '',
    roles: [...(props.member.roles ?? [])],
    country_id: props.member.country?.id ?? null,
    zone_id: props.member.zone?.id ?? null,
    family_branch_id: props.member.family_branch?.id ?? null,
    phone: props.member.phone ?? '',
    city: props.member.city ?? '',
    address: props.member.address ?? '',
    profession: props.member.profession ?? '',
    bio: props.member.bio ?? '',
    date_of_birth: props.member.date_of_birth_raw ?? '',
    gender: props.member.gender ?? '',
    languages: props.member.languages ?? [],
    member_since: props.member.member_since_raw ?? '',
    status: props.member.status,
    notes: props.member.notes ?? '',
});

const submit = () => {
    form.put(route('members.update', props.member.id));
};
</script>

<template>
    <Head :title="`${$tr('Modifier')} — ${member.name}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="'Modifier '"
            :accent="member.name"
            subtitle="Mettre à jour le profil membre"
        />

        <HsiCard padding="lg">
            <MemberForm
                :form="form"
                :countries="countries"
                :branches="branches"
                :zones="zones"
                :roles="roles"
                :statuses="statuses"
                is-edit
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('members.show', member.id)">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </MemberForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
