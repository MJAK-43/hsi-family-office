<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, LogOut, Menu, Search, UserRound } from 'lucide-vue-next';
import HsiAvatar from '@/Components/Hsi/HsiAvatar.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { useI18n } from '@/i18n';

defineEmits(['toggle-sidebar']);

const page = usePage();
const { t } = useI18n();

const user = computed(() => page.props.auth?.user ?? {});
const userRole = computed(() => {
    const role = page.props.auth?.roles?.[0];
    return role ? t(`roles.${role}`) : t('roles.user');
});

// Menu du compte (ouverture au clic, fermeture au clic extérieur ou avec Échap).
const open = ref(false);
const menuRef = ref(null);
const onDocumentClick = (event) => {
    if (open.value && menuRef.value && !menuRef.value.contains(event.target)) open.value = false;
};
const onKeydown = (event) => {
    if (event.key === 'Escape') open.value = false;
};
onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <header class="sticky top-0 z-30 flex h-20 items-center gap-4 border-b border-hsi-border/70 bg-white/80 px-4 backdrop-blur-xl md:px-6 lg:px-8">
        <!-- Menu mobile -->
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-xl text-hsi-slate transition-colors hover:bg-hsi-muted lg:hidden"
            :aria-label="t('nav.openMenu')"
            @click="$emit('toggle-sidebar')"
        >
            <Menu class="h-5 w-5" aria-hidden="true" />
        </button>

        <!-- Recherche -->
        <div class="hidden max-w-xl flex-1 md:block">
            <label class="relative block">
                <span class="sr-only">{{ t('nav.search') }}</span>
                <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-hsi-slate-light" aria-hidden="true" />
                <input
                    type="search"
                    :placeholder="t('nav.search')"
                    disabled
                    class="h-11 w-full cursor-not-allowed rounded-2xl border border-transparent bg-hsi-muted/70 pl-11 pr-4 text-sm text-hsi-navy placeholder:text-hsi-slate-light"
                >
            </label>
        </div>

        <!-- Actions, alignées à l'extrême droite -->
        <div class="ml-auto flex shrink-0 items-center gap-3 md:gap-4">
            <LocaleSwitcher />

            <Link
                :href="route('notifications.index')"
                class="relative flex h-10 w-10 items-center justify-center rounded-xl text-hsi-slate transition-colors hover:bg-hsi-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
                :aria-label="t('nav.notifications')"
            >
                <Bell class="h-5 w-5" aria-hidden="true" />
                <span
                    v-if="page.props.auth?.unread_notifications"
                    class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-hsi-danger px-1 text-[10px] font-bold text-white"
                >{{ page.props.auth.unread_notifications > 99 ? '99+' : page.props.auth.unread_notifications }}</span>
            </Link>

            <div ref="menuRef" class="relative">
                <button
                    type="button"
                    class="flex items-center gap-3 rounded-2xl py-1.5 pl-1.5 pr-2 transition-colors hover:bg-hsi-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                    @click.stop="open = !open"
                >
                    <HsiAvatar :name="user.name" :src="user.avatar_url" size="md" />
                    <span class="hidden text-left md:block">
                        <span class="block text-sm font-semibold leading-tight text-hsi-navy">{{ user.name }}</span>
                        <span class="block text-[11px] text-hsi-slate-light">{{ userRole }}</span>
                    </span>
                    <ChevronDown class="hidden h-4 w-4 text-hsi-slate-light transition-transform md:block" :class="open && 'rotate-180'" aria-hidden="true" />
                </button>

                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-to-class="translate-y-1 opacity-0"
                >
                    <div
                        v-if="open"
                        class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-2xl border border-hsi-border bg-white shadow-[0_24px_48px_-16px_rgba(7,27,52,0.25)]"
                        role="menu"
                    >
                        <div class="flex items-center gap-3 border-b border-hsi-border/70 bg-hsi-cream/60 px-4 py-4">
                            <HsiAvatar :name="user.name" :src="user.avatar_url" size="lg" />
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-wider text-hsi-slate-light">{{ t('nav.signedInAs') }}</p>
                                <p class="truncate text-sm font-semibold text-hsi-navy">{{ user.name }}</p>
                                <p class="truncate text-xs text-hsi-slate">{{ user.email }}</p>
                            </div>
                        </div>
                        <div class="p-2">
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-hsi-navy transition hover:bg-hsi-muted"
                                role="menuitem"
                                @click="open = false"
                            >
                                <UserRound class="h-4 w-4 text-hsi-slate" aria-hidden="true" />{{ t('nav.profile') }}
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-hsi-danger transition hover:bg-red-50"
                                role="menuitem"
                            >
                                <LogOut class="h-4 w-4" aria-hidden="true" />{{ t('nav.logout') }}
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </header>
</template>
