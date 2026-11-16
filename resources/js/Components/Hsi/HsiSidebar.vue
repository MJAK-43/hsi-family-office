<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Users,
    Wallet,
    Landmark,
    FolderKanban,
    Vote,
    X,
    Quote,
    Library,
    ShieldCheck,
    UserPlus,
    BookOpenCheck,
    SlidersHorizontal,
    BadgeCheck,
    BarChart3,
    DoorOpen,
} from 'lucide-vue-next';
import { useI18n } from '@/i18n';
import logoUrl from '../../../images/hsi-logo.png';

const { t } = useI18n();

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close']);

const menuItems = [
    { key: 'dashboard', route: 'dashboard', icon: LayoutDashboard, permissions: ['view dashboard'] },
    { key: 'members', route: 'members.index', routePattern: 'members.*', icon: Users, permissions: ['view members'] },
    { key: 'contributions', route: 'contributions.index', routePattern: ['contributions.*', 'contribution-plans.*'], icon: Wallet, permissions: ['view contributions', 'view all contribution payments', 'view own contribution payments'] },
    { key: 'funds', route: 'funds.index', routePattern: ['funds.*', 'fund-allocation-rules.*'], icon: Landmark, permissions: ['view funds'] },
    { key: 'projects', route: 'projects.index', routePattern: 'projects.*', icon: FolderKanban, permissions: ['view projects', 'view all projects'] },
    { key: 'votes', route: 'votes.index', routePattern: 'votes.*', icon: Vote, permissions: ['view votes', 'view all votes'] },
    { key: 'documents', route: 'documents.index', routePattern: ['documents.*', 'document-categories.*'], icon: Library, permissions: ['view document library'] },
    { key: 'applications', route: 'membership-applications.index', routePattern: 'membership-applications.*', icon: UserPlus, permissions: ['manage users'] },
    { key: 'approvals', route: 'approvals.index', routePattern: 'approvals.*', icon: BadgeCheck, permissions: ['approve transactions'], badge: 'pending_approvals' },
    { key: 'reports', route: 'reports.index', routePattern: 'reports.*', icon: BarChart3, permissions: ['view dashboard'] },
    { key: 'withdrawals', route: 'withdrawals.index', routePattern: 'withdrawals.*', icon: DoorOpen, permissions: ['view dashboard'] },
    { key: 'fundSettings', route: 'fund-settings.index', routePattern: 'fund-settings.*', icon: SlidersHorizontal, permissions: ['manage settings', 'manage zones', 'manage transaction types'] },
    { key: 'policies', route: 'policies.show', routePattern: 'policies.*', icon: BookOpenCheck, permissions: ['view dashboard'] },
    { key: 'administration', route: 'admin.index', routePattern: 'admin.*', icon: ShieldCheck, permissions: ['access administration'] },
];

const page = usePage();
const visibleMenuItems = computed(() => {
    const permissions = page.props.auth?.permissions ?? [];
    return menuItems.filter((item) => item.permissions.some((permission) => permissions.includes(permission)));
});

const isActive = (item) => {
    if (item.routePattern) {
        const patterns = Array.isArray(item.routePattern) ? item.routePattern : [item.routePattern];
        if (patterns.some((pattern) => route().current(pattern))) {
            return true;
        }
    }
    if (!item.route) return false;
    return route().current(item.route);
};

const itemHref = (item) => {
    if (item.route) {
        return route(item.route);
    }
    return '#';
};
</script>

<template>
    <!-- Overlay mobile -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-hsi-navy/60 backdrop-blur-sm lg:hidden"
            @click="$emit('close')"
        />
    </Transition>

    <!-- Sidebar -->
    <aside
        :class="[
            'fixed inset-y-0 left-0 z-50 flex w-[280px] flex-col hsi-sidebar-gradient text-white transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:z-auto',
            open ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Logo -->
        <div class="flex items-center justify-between px-6 pt-8 pb-6">
            <Link
                :href="route('dashboard')"
                class="flex items-center gap-3"
            >
                <img
                    :src="logoUrl"
                    alt=""
                    class="h-12 w-auto drop-shadow-[0_4px_18px_rgba(212,175,55,0.3)]"
                    width="72"
                    height="48"
                >
                <div class="leading-tight">
                    <p class="text-lg font-semibold tracking-tight">
                        HSI
                    </p>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-hsi-gold-light/80">
                        {{ t('common.fund') }}
                    </p>
                </div>
            </Link>
            <button
                class="lg:hidden p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                @click="$emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
            <Link
                v-for="item in visibleMenuItems"
                :key="item.key"
                :href="itemHref(item)"
                :class="[
                    'group relative flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-200',
                    isActive(item)
                        ? 'bg-white/10 text-white'
                        : 'text-white/70 hover:bg-white/5 hover:text-white',
                ]"
                @click="$emit('close')"
            >
                <!-- Barre or active -->
                <span
                    v-if="isActive(item)"
                    class="absolute right-0 top-1/2 -translate-y-1/2 h-6 w-0.5 rounded-full bg-hsi-gold"
                />
                <component
                    :is="item.icon"
                    :class="[
                        'h-[18px] w-[18px] shrink-0 transition-colors',
                        isActive(item) ? 'text-hsi-gold' : 'text-white/60 group-hover:text-white/80',
                    ]"
                />
                {{ t(`nav.${item.key}`) }}
                <span
                    v-if="item.badge && page.props.auth?.[item.badge]"
                    class="ml-auto rounded-full bg-hsi-gold px-2 py-0.5 text-[11px] font-bold text-hsi-navy"
                >{{ page.props.auth[item.badge] }}</span>
            </Link>
        </nav>

        <!-- Citation -->
        <div class="mx-4 mb-6 mt-4 rounded-xl border border-white/10 bg-white/5 p-5">
            <Quote class="h-5 w-5 text-hsi-gold mb-3 opacity-80" />
            <p class="text-xs font-medium text-hsi-gold leading-relaxed">
                {{ t('nav.values') }}
            </p>
            <p class="mt-2 text-[11px] leading-relaxed text-white/60 italic">
                {{ t('nav.valuesLine') }}
            </p>
        </div>
    </aside>
</template>
