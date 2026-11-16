<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle, CheckCircle2, ChevronDown, Circle, Download, FileText, History, ListChecks,
    Paperclip, PencilLine, Send, ShieldCheck, UserRound, Users, X, MessageCircle, Wrench,
} from 'lucide-vue-next';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { intlLocale, tr } from '@/i18n';
import logoUrl from '../../../images/hsi-logo.png';

const props = defineProps({
    days: { type: Array, required: true },
    entries: { type: Array, required: true },
    token: { type: String, required: true },
    limits: { type: Object, required: true },
    today: { type: String, required: true },
    roles: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

// ---------------------------------------------------------------------------
// Onglets : plan de test / rôles et droits (mémorisé sur l'appareil)
// ---------------------------------------------------------------------------
const VIEW_KEY = 'hsi-recette-view';
const view = ref('plan');
const openRoles = reactive({});
const allRolesOpen = computed(() => props.roles.length > 0 && props.roles.every((r) => openRoles[r.name]));

function setView(value) {
    view.value = value;
    history.replaceState(history.state, '', value === 'roles' ? '#roles' : location.pathname + location.search);
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        /* navigation privée */
    }
}

function toggleAllRoles() {
    const open = !allRolesOpen.value;
    props.roles.forEach((r) => { openRoles[r.name] = open; });
}

const usersLabel = (n) => tr(n > 1 ? '{n} comptes' : '{n} compte', { n });

// ---------------------------------------------------------------------------
// Prénom du testeur (mémorisé sur l'appareil)
// ---------------------------------------------------------------------------
const STORAGE_KEY = 'hsi-recette-tester';
const testerName = ref('');
const nameDraft = ref('');
const askName = ref(false);
// Sur l'onglet des rôles, le prénom n'est demandé que si on clique dessus.
const nameRequested = ref(false);
const normalize = (s) => s.trim().replace(/\s+/g, ' ').toLowerCase();
const myKey = computed(() => normalize(testerName.value));

onMounted(() => {
    try {
        if (location.hash === '#roles' || (!location.hash && localStorage.getItem(VIEW_KEY) === 'roles')) view.value = 'roles';
    } catch {
        /* navigation privée */
    }
    try {
        testerName.value = localStorage.getItem(STORAGE_KEY) ?? '';
    } catch {
        testerName.value = '';
    }
    nameDraft.value = testerName.value;
    askName.value = !testerName.value;
});

function saveName() {
    const value = nameDraft.value.trim().replace(/\s+/g, ' ');
    if (value.length < 2) return;
    testerName.value = value;
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        /* navigation privée : le prénom reste valable pour cette visite */
    }
    askName.value = false;
}

// ---------------------------------------------------------------------------
// Résultats : le dernier envoi de chaque testeur fait foi
// ---------------------------------------------------------------------------
const STATUS = {
    untested: { label: tr('Pas testé'), icon: Circle, chip: 'bg-slate-100 text-slate-600 ring-slate-200', dot: 'bg-slate-300' },
    validated: { label: tr('Validé'), icon: CheckCircle2, chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    to_review: { label: tr('À revoir'), icon: AlertTriangle, chip: 'bg-amber-50 text-amber-800 ring-amber-200', dot: 'bg-amber-500' },
};
const OPTIONS = ['untested', 'validated', 'to_review'];

/** { task_key: { tester_key: [envois du plus ancien au plus récent] } } */
const byTask = computed(() => {
    const map = {};
    for (const e of props.entries) {
        ((map[e.task_key] ??= {})[e.tester_key] ??= []).push(e);
    }
    return map;
});

/** Dernier envoi de chaque testeur sur une tâche, le plus récent en premier. */
const latestFor = (key) =>
    Object.values(byTask.value[key] ?? {})
        .map((list) => ({ ...list[list.length - 1], history: list.slice(0, -1).reverse() }))
        .sort((a, b) => (a.created_at < b.created_at ? 1 : -1));

const myLatest = (key) => {
    const list = byTask.value[key]?.[myKey.value];
    return list ? list[list.length - 1] : null;
};

/** État d'une tâche : à revoir si un testeur l'a signalée, validée si au moins un validé, sinon pas testée. */
const taskState = (key) => {
    const latest = latestFor(key);
    if (latest.some((e) => e.status === 'to_review')) return 'to_review';
    if (latest.some((e) => e.status === 'validated')) return 'validated';
    return 'untested';
};

const countsFor = (key) => {
    const c = { validated: 0, to_review: 0, untested: 0 };
    latestFor(key).forEach((e) => c[e.status]++);
    return c;
};

const allTasks = computed(() => props.days.flatMap((d) => d.tasks));
const global = computed(() => {
    const c = { validated: 0, to_review: 0, untested: 0 };
    allTasks.value.forEach((t) => c[taskState(t.key)]++);
    return { ...c, total: allTasks.value.length, tested: c.validated + c.to_review };
});

const dayStats = (day) => {
    const c = { validated: 0, to_review: 0, untested: 0 };
    day.tasks.forEach((t) => c[taskState(t.key)]++);
    return c;
};

// ---------------------------------------------------------------------------
// Jours, filtres
// ---------------------------------------------------------------------------
const todayIndex = props.days.findIndex((d) => d.date === props.today);
const activeDay = ref(todayIndex >= 0 ? todayIndex : 0);
const filter = ref('all');
const FILTERS = [
    { value: 'all', label: tr('Toutes les tâches') },
    { value: 'to_review', label: tr('À revoir') },
    { value: 'unanswered', label: tr('À revoir sans réponse') },
    { value: 'mine', label: tr('Pas encore testées par moi') },
];
// Filtres qui montrent les tâches de tous les jours.
const crossDay = computed(() => ['to_review', 'unanswered'].includes(filter.value));
// Retours « À revoir » encore sans réponse (dernier envoi de chaque testeur).
const unansweredFor = (key) => latestFor(key).filter((e) => e.status === 'to_review' && !(e.replies ?? []).length);
const unansweredCount = computed(() => allTasks.value.reduce((n, t) => n + unansweredFor(t.key).length, 0));

function selectDay(i) {
    activeDay.value = i;
    if (crossDay.value) filter.value = 'all';
}

const fmtDay = (iso, opts) => new Intl.DateTimeFormat(intlLocale(), opts).format(new Date(`${iso}T12:00:00`));
const fmtDate = (iso) => new Intl.DateTimeFormat(intlLocale(), { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
const fmtSize = (bytes) => (bytes < 1048576 ? tr('{n} Ko', { n: Math.max(1, Math.round(bytes / 1024)) }) : tr('{n} Mo', { n: (bytes / 1048576).toFixed(1) }));

const visibleTasks = computed(() => {
    const day = props.days[activeDay.value];
    if (filter.value === 'to_review') {
        return props.days.flatMap((d) => d.tasks.filter((t) => taskState(t.key) === 'to_review').map((t) => ({ ...t, day: d })));
    }
    if (filter.value === 'unanswered') {
        return props.days.flatMap((d) => d.tasks.filter((t) => unansweredFor(t.key).length).map((t) => ({ ...t, day: d })));
    }
    return day.tasks
        .filter((t) => filter.value !== 'mine' || !myLatest(t.key) || myLatest(t.key).status === 'untested')
        .map((t) => ({ ...t, day }));
});

// ---------------------------------------------------------------------------
// Envoi d'un résultat
// ---------------------------------------------------------------------------
const open = reactive({}); // tâches dont le détail (étapes) est déplié
const reviewOpen = reactive({}); // formulaire « À revoir » ouvert
const historyOpen = reactive({});
const form = useForm({ tester_name: '', task_key: '', status: '', comment: '', attachments: [] });
const files = reactive({}); // fichiers choisis, par tâche
const comments = reactive({});
const sendingKey = ref(null);
const fileError = ref({});

function choose(task, status) {
    if (!testerName.value) {
        askName.value = true;
        return;
    }
    if (status === 'to_review') {
        reviewOpen[task.key] = true;
        comments[task.key] ??= myLatest(task.key)?.status === 'to_review' ? myLatest(task.key).comment ?? '' : '';
        return;
    }
    reviewOpen[task.key] = false;
    if (myLatest(task.key)?.status === status || (!myLatest(task.key) && status === 'untested')) return;
    submit(task, status);
}

function onFiles(task, event) {
    const chosen = [...(files[task.key] ?? []), ...Array.from(event.target.files ?? [])];
    event.target.value = '';
    const tooBig = chosen.filter((f) => f.size > props.limits.max_file_mb * 1048576);
    fileError.value[task.key] = tooBig.length
        ? tr('{files} : plus de {mb} Mo.', { files: tooBig.map((f) => f.name).join(', '), mb: props.limits.max_file_mb })
        : chosen.length > props.limits.max_files
            ? tr('Au maximum {n} pièces jointes.', { n: props.limits.max_files })
            : '';
    files[task.key] = chosen.filter((f) => f.size <= props.limits.max_file_mb * 1048576).slice(0, props.limits.max_files);
}

const removeFile = (task, index) => files[task.key].splice(index, 1);

function submit(task, status) {
    sendingKey.value = task.key;
    form.tester_name = testerName.value;
    form.task_key = task.key;
    form.status = status;
    form.comment = status === 'to_review' ? (comments[task.key] ?? '') : '';
    form.attachments = status === 'to_review' ? (files[task.key] ?? []) : [];
    form.post(route('recette.store', props.token), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            reviewOpen[task.key] = false;
            files[task.key] = [];
            comments[task.key] = '';
        },
        onFinish: () => {
            sendingKey.value = null;
        },
    });
}

// ---------------------------------------------------------------------------
// Réponses aux retours « À revoir »
// ---------------------------------------------------------------------------
const replyOpen = reactive({});
const replyText = reactive({});
const replyFixed = reactive({});
const replyForm = useForm({ author_name: '', body: '', marks_fixed: false });
const sendingReply = ref(null);
const lastReply = (e) => (e.replies ?? [])[(e.replies ?? []).length - 1];
function openReply(e) {
    if (!testerName.value) { askName.value = true; return; }
    replyOpen[e.id] = !replyOpen[e.id];
}
function sendReply(e) {
    replyForm.clearErrors();
    replyForm.author_name = testerName.value;
    replyForm.body = replyText[e.id] ?? '';
    replyForm.marks_fixed = !!replyFixed[e.id];
    sendingReply.value = e.id;
    replyForm.post(route('recette.reply', [props.token, e.id]), {
        preserveScroll: true,
        onSuccess: () => { replyOpen[e.id] = false; replyText[e.id] = ''; replyFixed[e.id] = false; },
        onFinish: () => { sendingReply.value = null; },
    });
}

const toast = ref('');
watch(flash, (value) => {
    if (!value) return;
    toast.value = value;
    setTimeout(() => (toast.value = ''), 4000);
}, { immediate: true });

const initials = (name) => name.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase();
const PALETTE = ['bg-[#0B2748]', 'bg-[#A8841F]', 'bg-emerald-700', 'bg-sky-700', 'bg-rose-700', 'bg-violet-700', 'bg-teal-700', 'bg-orange-700'];
const colorFor = (key) => PALETTE[[...key].reduce((a, ch) => a + ch.charCodeAt(0), 0) % PALETTE.length];
const isImage = (a) => (a.mime ?? '').startsWith('image/') && !/hei[cf]/.test(a.mime);
</script>

<template>
    <Head title="Recette de l'application" />

    <div class="min-h-screen bg-hsi-cream pb-16">
        <!-- En-tête -->
        <header class="sticky top-0 z-30 border-b border-white/10 bg-hsi-navy text-white shadow-lg">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
                <img :src="logoUrl" alt="HSI" class="h-10 w-auto" width="60" height="40">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[15px] font-semibold leading-tight">Recette de l'application</p>
                    <p class="truncate text-xs text-white/60">HSI · Fonds familial JOKUNG</p>
                </div>
                <LocaleSwitcher tone="dark" class="shrink-0" />
                <button
                    type="button"
                    class="inline-flex max-w-[40%] items-center gap-2 rounded-full bg-white/10 py-1.5 pl-1.5 pr-3 text-sm transition hover:bg-white/20"
                    @click="nameDraft = testerName; askName = true; nameRequested = true"
                >
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-hsi-gold text-xs font-bold text-hsi-navy">
                        <UserRound v-if="!testerName" class="h-4 w-4" />
                        <template v-else>{{ initials(testerName) }}</template>
                    </span>
                    <span class="truncate">{{ testerName || 'Votre prénom' }}</span>
                    <PencilLine class="h-3.5 w-3.5 shrink-0 text-white/60" />
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 sm:px-6">
            <!-- Onglets -->
            <div class="mt-6 grid grid-cols-2 gap-1 rounded-2xl border border-hsi-border bg-white p-1 shadow-sm sm:inline-grid sm:w-auto" role="tablist">
                <button
                    v-for="tab in [{ value: 'plan', label: tr('Plan de test'), icon: ListChecks }, { value: 'roles', label: tr('Rôles et droits'), icon: ShieldCheck }]"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="view === tab.value"
                    class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="view === tab.value ? 'bg-hsi-navy text-white shadow' : 'text-hsi-slate hover:text-hsi-navy'"
                    @click="setView(tab.value)"
                >
                    <component :is="tab.icon" class="h-4 w-4" /> {{ tab.label }}
                </button>
            </div>

            <!-- Rôles et droits -->
            <template v-if="view === 'roles'">
                <section class="mt-6 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 class="font-heading text-[26px] font-semibold leading-tight text-hsi-navy">Rôles et droits</h1>
                        <p class="mt-2 max-w-3xl text-[15px] leading-relaxed text-hsi-slate">Ce que chaque rôle peut faire dans l'application. Chaque compte a un seul rôle.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm font-medium text-hsi-navy transition hover:bg-hsi-muted" @click="toggleAllRoles">
                        {{ allRolesOpen ? 'Tout replier' : 'Tout déplier' }}
                    </button>
                </section>

                <div class="mt-5 space-y-3">
                    <article v-for="role in roles" :key="role.name" class="overflow-hidden rounded-3xl border border-hsi-border bg-white shadow-sm">
                        <button
                            type="button"
                            class="flex w-full items-start gap-4 p-5 text-left transition hover:bg-hsi-muted/40 sm:p-6"
                            :aria-expanded="!!openRoles[role.name]"
                            @click="openRoles[role.name] = !openRoles[role.name]"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-hsi-navy/[0.06] text-hsi-navy">
                                <ShieldCheck class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-heading text-lg font-semibold text-hsi-navy">{{ role.label }}</span>
                                <span class="mt-1 block text-sm leading-relaxed text-hsi-slate">{{ role.mission }}</span>
                                <span class="mt-2.5 flex flex-wrap gap-2 text-xs font-medium">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-hsi-gold/15 px-2.5 py-1 text-[#7a5f12]">{{ tr('{n} actions', { n: role.actions_count }) }}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-slate-600"><Users class="h-3.5 w-3.5" /> {{ usersLabel(role.users_count) }}</span>
                                </span>
                            </span>
                            <ChevronDown class="mt-1 h-5 w-5 shrink-0 text-hsi-slate-light transition" :class="openRoles[role.name] && 'rotate-180'" />
                        </button>

                        <div v-if="openRoles[role.name]" class="border-t border-hsi-border px-5 pb-6 pt-5 sm:px-6">
                            <div class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                                <div v-for="group in role.groups" :key="group.module">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-[#A8841F]">{{ group.module }}</p>
                                    <ul class="mt-2 space-y-1.5">
                                        <li v-for="action in group.actions" :key="action" class="flex items-start gap-2 text-sm text-hsi-navy">
                                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> {{ action }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div v-if="role.extras.length" class="mt-5 rounded-2xl bg-hsi-cream px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">À savoir</p>
                                <ul class="mt-1.5 space-y-1 text-sm text-hsi-slate">
                                    <li v-for="extra in role.extras" :key="extra">· {{ extra }}</li>
                                </ul>
                            </div>
                        </div>
                    </article>
                </div>
            </template>

            <template v-else>
            <!-- Avancement global -->
            <section class="mt-6 rounded-3xl border border-hsi-border bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">Avancement global</p>
                        <p class="mt-1 font-heading text-2xl font-semibold text-hsi-navy">
                            {{ global.tested }} / {{ global.total }} <span class="text-base font-normal text-hsi-slate">tâches testées</span>
                        </p>
                    </div>
                    <a
                        :href="route('recette.export', token)"
                        class="inline-flex items-center gap-2 rounded-xl border border-hsi-border px-3 py-2 text-sm font-medium text-hsi-navy transition hover:bg-hsi-muted"
                    >
                        <Download class="h-4 w-4" /> Export Excel
                    </a>
                </div>
                <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="bg-emerald-500 transition-all" :style="{ width: `${(global.validated / global.total) * 100}%` }" />
                    <div class="bg-amber-500 transition-all" :style="{ width: `${(global.to_review / global.total) * 100}%` }" />
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-hsi-slate">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" /> {{ global.validated }} validées</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500" /> {{ global.to_review }} à revoir</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300" /> {{ global.untested }} pas testées</span>
                </div>
            </section>

            <!-- Jours -->
            <nav class="-mx-4 mt-4 overflow-x-auto px-4 pb-1 pt-3 sm:mx-0 sm:px-0" aria-label="Jours de test">
                <div class="flex min-w-max gap-2">
                    <button
                        v-for="(day, i) in days"
                        :key="day.number"
                        type="button"
                        class="relative flex w-[104px] flex-col items-start rounded-2xl border px-3 py-2.5 text-left transition"
                        :class="i === activeDay && !crossDay
                            ? 'border-hsi-navy bg-hsi-navy text-white shadow-md'
                            : 'border-hsi-border bg-white text-hsi-navy hover:border-hsi-gold/60'"
                        @click="selectDay(i)"
                    >
                        <span class="text-[11px] font-semibold uppercase tracking-wider opacity-70">{{ fmtDay(day.date, { weekday: 'short' }) }} {{ fmtDay(day.date, { day: 'numeric' }) }}</span>
                        <span class="mt-0.5 line-clamp-1 text-[13px] font-semibold">{{ day.module.split(',')[0] }}</span>
                        <span class="mt-1.5 flex gap-1">
                            <span v-for="n in dayStats(day).validated" :key="`v${n}`" class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            <span v-for="n in dayStats(day).to_review" :key="`r${n}`" class="h-1.5 w-1.5 rounded-full bg-amber-400" />
                            <span v-for="n in dayStats(day).untested" :key="`u${n}`" class="h-1.5 w-1.5 rounded-full" :class="i === activeDay && !crossDay ? 'bg-white/25' : 'bg-slate-200'" />
                        </span>
                        <span v-if="day.date === today" class="absolute -top-2 right-2 rounded-full bg-hsi-gold px-1.5 text-[10px] font-bold text-hsi-navy">Aujourd'hui</span>
                    </button>
                </div>
            </nav>

            <!-- Filtres -->
            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="f in FILTERS"
                    :key="f.value"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 transition"
                    :class="filter === f.value ? 'bg-hsi-gold/15 text-[#7a5f12] ring-hsi-gold/50' : 'bg-white text-hsi-slate ring-hsi-border hover:text-hsi-navy'"
                    @click="filter = f.value"
                >
                    {{ f.label }}
                    <span v-if="f.value === 'to_review'" class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ global.to_review }}</span>
                    <span v-if="f.value === 'unanswered'" class="ml-1 rounded-full bg-rose-500 px-1.5 text-xs text-white">{{ unansweredCount }}</span>
                </button>
            </div>

            <!-- En-tête du jour -->
            <section v-if="!crossDay" class="mt-6">
                <p class="text-sm font-semibold text-[#A8841F]">
                    Jour {{ days[activeDay].number }} · {{ fmtDay(days[activeDay].date, { weekday: 'long', day: 'numeric', month: 'long' }) }}
                </p>
                <h1 class="mt-1 font-heading text-[26px] font-semibold leading-tight text-hsi-navy">{{ days[activeDay].module }}</h1>
                <p class="mt-2 max-w-3xl text-[15px] leading-relaxed text-hsi-slate">{{ days[activeDay].intro }}</p>
            </section>
            <section v-else class="mt-6">
                <h1 class="font-heading text-[26px] font-semibold text-hsi-navy">Tout ce qui est à revoir</h1>
                <p class="mt-2 text-[15px] text-hsi-slate">Les tâches signalées par au moins un testeur, tous jours confondus.</p>
            </section>

            <!-- Tâches -->
            <div class="mt-5 space-y-4">
                <p v-if="!visibleTasks.length" class="rounded-2xl border border-dashed border-hsi-border bg-white p-8 text-center text-hsi-slate">
                    <ListChecks class="mx-auto mb-2 h-6 w-6 text-hsi-slate-light" />
                    {{ filter === 'unanswered' ? 'Tous les retours à revoir ont reçu une réponse.' : filter === 'to_review' ? 'Aucune tâche à revoir pour le moment.' : 'Vous avez testé toutes les tâches de cette journée. Merci !' }}
                </p>

                <article
                    v-for="task in visibleTasks"
                    :key="task.key"
                    class="overflow-hidden rounded-3xl border bg-white shadow-sm"
                    :class="taskState(task.key) === 'to_review' ? 'border-amber-300' : taskState(task.key) === 'validated' ? 'border-emerald-200' : 'border-hsi-border'"
                >
                    <div class="p-5 sm:p-6">
                        <!-- Titre -->
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 shrink-0 rounded-lg bg-hsi-navy px-2 py-1 text-xs font-bold text-white">{{ task.key }}</span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-[17px] font-semibold leading-snug text-hsi-navy">{{ task.title }}</h2>
                                <p class="mt-1 text-sm text-hsi-slate">
                                    <span v-if="crossDay">Jour {{ task.day.number }} · {{ task.day.module }} · </span>Rôle : {{ task.role }}
                                </p>
                            </div>
                            <span class="hidden shrink-0 items-center gap-1 text-xs font-medium sm:inline-flex">
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">✓ {{ countsFor(task.key).validated }}</span>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-800">! {{ countsFor(task.key).to_review }}</span>
                            </span>
                        </div>

                        <!-- Étapes -->
                        <button
                            type="button"
                            class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-[#A8841F] hover:text-hsi-navy"
                            @click="open[task.key] = !open[task.key]"
                        >
                            <ChevronDown class="h-4 w-4 transition" :class="open[task.key] ? 'rotate-180' : ''" />
                            {{ open[task.key] ? 'Masquer les étapes' : 'Voir les étapes et le résultat attendu' }}
                        </button>
                        <div v-if="open[task.key]" class="mt-3 grid gap-4 rounded-2xl bg-hsi-cream/70 p-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">Étapes</p>
                                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-[15px] text-hsi-navy">
                                    <li v-for="(step, i) in task.steps" :key="i">{{ step }}</li>
                                </ol>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">Résultat attendu</p>
                                <p class="mt-2 text-[15px] text-hsi-navy">{{ task.expected }}</p>
                            </div>
                        </div>

                        <!-- Mon résultat -->
                        <div class="mt-5">
                            <p class="text-sm font-medium text-hsi-slate">Mon résultat</p>
                            <div class="mt-2 grid grid-cols-3 gap-2" role="radiogroup" :aria-label="$tr('Résultat pour la tâche {key}', { key: task.key })">
                                <button
                                    v-for="opt in OPTIONS"
                                    :key="opt"
                                    type="button"
                                    role="radio"
                                    :aria-checked="(reviewOpen[task.key] ? 'to_review' : (myLatest(task.key)?.status ?? 'untested')) === opt"
                                    :disabled="sendingKey === task.key"
                                    class="flex flex-col items-center justify-center gap-1 rounded-xl px-1.5 py-2 text-[13px] font-semibold leading-tight ring-1 transition disabled:opacity-60 sm:flex-row sm:gap-1.5 sm:px-2 sm:py-2.5 sm:text-sm"
                                    :class="(reviewOpen[task.key] ? 'to_review' : (myLatest(task.key)?.status ?? 'untested')) === opt
                                        ? (opt === 'validated' ? 'bg-emerald-600 text-white ring-emerald-600' : opt === 'to_review' ? 'bg-amber-500 text-white ring-amber-500' : 'bg-slate-600 text-white ring-slate-600')
                                        : 'bg-white text-hsi-slate ring-hsi-border hover:ring-hsi-gold/60'"
                                    @click="choose(task, opt)"
                                >
                                    <component :is="STATUS[opt].icon" class="h-4 w-4 shrink-0" />
                                    <span class="whitespace-nowrap">{{ STATUS[opt].label }}</span>
                                </button>
                            </div>

                            <!-- Formulaire « À revoir » -->
                            <div v-if="reviewOpen[task.key]" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                                <label :for="`c-${task.key}`" class="text-sm font-semibold text-hsi-navy">Qu'est-ce qui ne va pas ? <span class="text-amber-700">*</span></label>
                                <textarea
                                    :id="`c-${task.key}`"
                                    v-model="comments[task.key]"
                                    rows="4"
                                    class="mt-2 w-full rounded-xl border-hsi-border text-[15px] focus:border-hsi-gold focus:ring-hsi-gold/30"
                                    placeholder="Décrivez ce que vous avez fait, ce qui s'est passé et ce que vous attendiez…"
                                />
                                <p v-if="form.errors.comment && form.task_key === task.key" class="mt-1 text-sm text-red-600">{{ form.errors.comment }}</p>

                                <div class="mt-3">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-amber-300 bg-white px-3 py-2 text-sm font-medium text-hsi-navy hover:border-amber-500">
                                        <Paperclip class="h-4 w-4" />
                                        Ajouter des pièces jointes
                                        <input
                                            type="file"
                                            multiple
                                            class="sr-only"
                                            accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                            @change="onFiles(task, $event)"
                                        >
                                    </label>
                                    <span class="ml-2 text-xs text-hsi-slate">Captures, PDF ou vidéos · {{ limits.max_files }} fichiers, {{ limits.max_file_mb }} Mo max. chacun</span>
                                    <p v-if="fileError[task.key]" class="mt-1 text-sm text-red-600">{{ fileError[task.key] }}</p>
                                    <p v-if="form.task_key === task.key && Object.keys(form.errors).some((k) => k.startsWith('attachments'))" class="mt-1 text-sm text-red-600">
                                        {{ Object.entries(form.errors).find(([k]) => k.startsWith('attachments'))[1] }}
                                    </p>
                                    <ul v-if="files[task.key]?.length" class="mt-2 flex flex-wrap gap-2">
                                        <li v-for="(f, i) in files[task.key]" :key="`${f.name}-${i}`" class="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-white px-2 py-1 text-xs ring-1 ring-amber-200">
                                            <FileText class="h-3.5 w-3.5 shrink-0 text-amber-700" />
                                            <span class="max-w-[160px] truncate">{{ f.name }}</span>
                                            <span class="text-hsi-slate">{{ fmtSize(f.size) }}</span>
                                            <button type="button" class="rounded p-0.5 hover:bg-amber-100" :aria-label="$tr('Retirer {name}', { name: f.name })" @click="removeFile(task, i)"><X class="h-3.5 w-3.5" /></button>
                                        </li>
                                    </ul>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-xl bg-hsi-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-hsi-navy-soft disabled:opacity-60"
                                        :disabled="sendingKey === task.key || !(comments[task.key] ?? '').trim()"
                                        @click="submit(task, 'to_review')"
                                    >
                                        <Send class="h-4 w-4" />
                                        {{ sendingKey === task.key ? (form.progress ? `${$tr('Envoi…')} ${form.progress.percentage}%` : 'Envoi…') : 'Envoyer mon retour' }}
                                    </button>
                                    <button type="button" class="rounded-xl px-3 py-2.5 text-sm text-hsi-slate hover:text-hsi-navy" @click="reviewOpen[task.key] = false">Annuler</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Résultats de l'équipe -->
                    <div v-if="latestFor(task.key).length" class="border-t border-hsi-border/70 bg-slate-50/60 px-5 py-4 sm:px-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-hsi-slate-light">Résultats de l'équipe ({{ latestFor(task.key).length }})</p>
                        <ul class="mt-3 space-y-3">
                            <li v-for="e in latestFor(task.key)" :key="e.id" class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" :class="colorFor(e.tester_key)">{{ initials(e.tester_name) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-semibold text-hsi-navy">{{ e.tester_name }}</span>
                                        <span v-if="e.tester_key === myKey" class="text-xs text-hsi-slate">(vous)</span>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="STATUS[e.status].chip">
                                            <component :is="STATUS[e.status].icon" class="h-3.5 w-3.5" /> {{ STATUS[e.status].label }}
                                        </span>
                                        <span class="text-xs text-hsi-slate-light">{{ fmtDate(e.created_at) }}</span>
                                        <span v-if="e.status === 'to_review' && lastReply(e)?.marks_fixed" class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-200">
                                            <Wrench class="h-3.5 w-3.5" /> Corrigé · à retester
                                        </span>
                                    </div>
                                    <p v-if="e.comment" class="mt-1 whitespace-pre-line break-words text-[15px] text-hsi-navy">« {{ e.comment }} »</p>
                                    <div v-if="e.attachments.length" class="mt-2 flex flex-wrap gap-2">
                                        <a
                                            v-for="a in e.attachments"
                                            :key="a.id"
                                            :href="a.url"
                                            target="_blank"
                                            rel="noopener"
                                            class="group inline-flex items-center gap-2 overflow-hidden rounded-xl bg-white text-xs ring-1 ring-hsi-border transition hover:ring-hsi-gold"
                                        >
                                            <img v-if="isImage(a)" :src="a.url" :alt="a.name" class="h-14 w-14 object-cover" loading="lazy">
                                            <span v-else class="flex h-14 w-10 items-center justify-center bg-hsi-muted"><FileText class="h-4 w-4 text-hsi-slate" /></span>
                                            <span class="max-w-[140px] truncate pr-3 text-hsi-navy">{{ a.name }}</span>
                                        </a>
                                    </div>
                                    <!-- Réponses -->
                                    <ul v-if="e.replies?.length" class="mt-2 space-y-2 border-l-2 border-sky-200 pl-3">
                                        <li v-for="r in e.replies" :key="r.id" class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-hsi-border/70">
                                            <div class="flex flex-wrap items-center gap-x-2 text-xs">
                                                <span class="font-semibold text-hsi-navy">{{ r.author_name }}</span>
                                                <span v-if="r.author_key === myKey" class="text-hsi-slate">(vous)</span>
                                                <span v-if="r.marks_fixed" class="inline-flex items-center gap-1 font-semibold text-sky-800"><Wrench class="h-3 w-3" /> Corrigé</span>
                                                <span class="text-hsi-slate-light">{{ fmtDate(r.created_at) }}</span>
                                            </div>
                                            <p class="mt-0.5 whitespace-pre-line break-words text-hsi-navy">{{ r.body }}</p>
                                        </li>
                                    </ul>
                                    <button
                                        v-if="e.comment || e.status === 'to_review'"
                                        type="button"
                                        class="mt-1.5 mr-3 inline-flex items-center gap-1 text-xs font-semibold text-[#7a5f12] hover:text-hsi-navy"
                                        @click="openReply(e)"
                                    >
                                        <MessageCircle class="h-3.5 w-3.5" /> Répondre{{ e.replies?.length ? ` (${e.replies.length})` : '' }}
                                    </button>
                                    <form v-if="replyOpen[e.id]" class="mt-2 space-y-2" @submit.prevent="sendReply(e)">
                                        <textarea
                                            v-model="replyText[e.id]"
                                            rows="3"
                                            maxlength="3000"
                                            required
                                            class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm focus:border-hsi-gold focus:ring-hsi-gold/30"
                                            :placeholder="$tr('Votre réponse à {name}…', { name: e.tester_name })"
                                        />
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <label v-if="e.status === 'to_review'" class="inline-flex items-center gap-2 text-sm text-hsi-slate">
                                                <input v-model="replyFixed[e.id]" type="checkbox" class="rounded border-hsi-border text-hsi-gold focus:ring-hsi-gold">
                                                Corrigé, à retester
                                            </label>
                                            <span v-else />
                                            <div class="flex gap-2">
                                                <button type="button" class="rounded-xl px-3 py-1.5 text-sm text-hsi-slate hover:bg-hsi-muted" @click="replyOpen[e.id] = false">Annuler</button>
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-hsi-navy px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="sendingReply === e.id || !(replyText[e.id] ?? '').trim()">
                                                    <Send class="h-3.5 w-3.5" /> {{ sendingReply === e.id ? $tr('Envoi…') : $tr('Envoyer') }}
                                                </button>
                                            </div>
                                        </div>
                                        <p v-if="Object.keys(replyForm.errors).length && sendingReply === null" class="text-xs text-hsi-danger">{{ Object.values(replyForm.errors)[0] }}</p>
                                    </form>
                                    <button
                                        v-if="e.history.length"
                                        type="button"
                                        class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-hsi-slate hover:text-hsi-navy"
                                        @click="historyOpen[`${task.key}-${e.tester_key}`] = !historyOpen[`${task.key}-${e.tester_key}`]"
                                    >
                                        <History class="h-3.5 w-3.5" /> Historique ({{ e.history.length }})
                                    </button>
                                    <ul v-if="historyOpen[`${task.key}-${e.tester_key}`]" class="mt-2 space-y-1.5 border-l-2 border-hsi-border pl-3">
                                        <li v-for="h in e.history" :key="h.id" class="text-xs text-hsi-slate">
                                            <span class="font-semibold">{{ STATUS[h.status].label }}</span> · {{ fmtDate(h.created_at) }}
                                            <span v-if="h.comment"> — « {{ h.comment }} »</span>
                                            <span v-if="h.attachments.length"> · {{ h.attachments.length }} pièce(s) jointe(s)</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
            </template>
        </main>

        <!-- Prénom -->
        <div v-if="askName && (view === 'plan' || nameRequested)" class="fixed inset-0 z-50 flex items-end justify-center bg-hsi-navy/60 p-4 backdrop-blur-sm sm:items-center">
            <form class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl" @submit.prevent="saveName">
                <img :src="logoUrl" alt="" class="h-12 w-auto">
                <h2 class="mt-4 font-heading text-2xl font-semibold text-hsi-navy">Bienvenue dans la recette</h2>
                <p class="mt-1 text-[15px] text-hsi-slate">Indiquez votre prénom : il accompagnera chacun de vos résultats. Ajoutez l'initiale de votre nom si un autre testeur a le même prénom.</p>
                <label for="tester-name" class="mt-5 block text-sm font-medium text-hsi-navy">Prénom</label>
                <input
                    id="tester-name"
                    v-model="nameDraft"
                    type="text"
                    maxlength="60"
                    autocomplete="given-name"
                    autofocus
                    placeholder="Ex. : Marie K."
                    class="mt-1.5 w-full rounded-xl border-hsi-border text-base focus:border-hsi-gold focus:ring-hsi-gold/30"
                >
                <div class="mt-5 flex gap-2">
                    <button
                        type="submit"
                        class="flex-1 rounded-xl bg-hsi-gold px-4 py-3 font-semibold text-hsi-navy transition hover:brightness-105 disabled:opacity-50"
                        :disabled="nameDraft.trim().length < 2"
                    >
                        Commencer
                    </button>
                    <button v-if="testerName" type="button" class="rounded-xl px-4 py-3 text-hsi-slate hover:text-hsi-navy" @click="askName = false">Annuler</button>
                </div>
            </form>
        </div>

        <!-- Confirmation -->
        <transition enter-from-class="translate-y-4 opacity-0" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-200">
            <div v-if="toast" class="fixed inset-x-4 bottom-4 z-40 mx-auto flex max-w-md items-center gap-2 rounded-2xl bg-hsi-navy px-4 py-3 text-sm text-white shadow-xl">
                <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-400" /> {{ toast }}
            </div>
        </transition>
    </div>
</template>
