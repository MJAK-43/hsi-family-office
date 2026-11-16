<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import MemberForm from '@/Components/Members/MemberForm.vue';

const props = defineProps({
    countries: Array,
    branches: Array,
    zones: Array,
    roles: Array,
    statuses: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: ['member'],
    country_id: null,
    zone_id: null,
    family_branch_id: null,
    phone: '',
    city: '',
    address: '',
    profession: '',
    bio: '',
    date_of_birth: '',
    gender: '',
    languages: [],
    member_since: new Date().toISOString().slice(0, 10),
    status: 'pending',
    notes: '',
});

const submit = () => {
    form.post(route('members.store'));
};
</script>

<template>
    <Head title="Ajouter un membre" />

    <AuthenticatedLayout>
        <HsiPageHeader
            title="Ajouter un "
            accent="membre"
            subtitle="Créer un nouveau profil membre HSI Family Office"
        />

        <HsiCard padding="lg">
            <MemberForm
                :form="form"
                :countries="countries"
                :branches="branches"
                :zones="zones"
                :roles="roles"
                :statuses="statuses"
                @submit="submit"
            >
                <template #cancel>
                    <Link :href="route('members.index')">
                        <HsiButton variant="ghost">Annuler</HsiButton>
                    </Link>
                </template>
            </MemberForm>
        </HsiCard>
    </AuthenticatedLayout>
</template>
