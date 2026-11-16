<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import { CheckCircle2, Earth, ShieldCheck, Sparkles } from 'lucide-vue-next';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { useI18n } from '@/i18n';
import logoUrl from '../../images/hsi-logo.png';
import worldMapUrl from '../../images/world-map.jpg';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    pageTitle: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});

const { t, tm, locale } = useI18n();
const year = new Date().getFullYear();
const page = usePage();
</script>

<template>
    <Head :title="pageTitle || title" />

    <div class="min-h-screen bg-hsi-cream lg:grid lg:grid-cols-[minmax(0,44fr)_minmax(0,56fr)]" :lang="locale">
        <!-- Panneau de marque -->
        <aside class="auth-brand relative isolate flex flex-col overflow-hidden px-6 py-6 text-white sm:px-10 lg:sticky lg:top-0 lg:h-screen lg:self-start lg:px-14 lg:py-12">
            <div class="flex items-center justify-between gap-4">
                <Link
                    :href="route('home')"
                    class="flex w-fit items-center gap-4 rounded-2xl outline-none transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-hsi-gold focus-visible:ring-offset-4 focus-visible:ring-offset-hsi-navy"
                    :aria-label="t('authLayout.homeLabel')"
                >
                    <img
                        :src="logoUrl"
                        alt=""
                        class="h-12 w-auto drop-shadow-[0_4px_18px_rgba(212,175,55,0.25)] lg:h-14"
                        width="83"
                        height="56"
                    >
                    <span class="leading-tight">
                        <span class="block text-lg font-semibold tracking-tight">HSI</span>
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.28em] text-hsi-gold-light/80">{{ t('common.fund') }}</span>
                    </span>
                </Link>
                <LocaleSwitcher tone="dark" />
            </div>

            <div class="hidden flex-1 flex-col justify-center py-10 lg:flex">
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-1.5 text-sm font-medium text-white/90 backdrop-blur">
                    <Sparkles class="h-4 w-4 text-hsi-gold-light" aria-hidden="true" />
                    {{ t('common.motto') }}
                </span>

                <h2 class="mt-6 max-w-xl font-heading text-[clamp(2.25rem,3.2vw,3.25rem)] font-semibold leading-[1.08] tracking-tight">
                    {{ t('authLayout.headline1') }}
                    <span class="auth-gold-text">{{ t('authLayout.headline2') }}</span>
                </h2>

                <figure class="relative mt-8 max-w-xl overflow-hidden rounded-3xl border border-white/10 shadow-[0_30px_60px_-30px_rgba(0,0,0,0.6)]">
                    <img
                        :src="worldMapUrl"
                        :alt="t('authLayout.mapAlt')"
                        class="aspect-[16/9] w-full object-cover object-[50%_40%]"
                        width="1066"
                        height="600"
                        loading="eager"
                    >
                    <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#071B34] via-[#071B34]/85 to-transparent px-5 pb-4 pt-12">
                        <p class="flex items-center gap-2 text-sm font-semibold text-white">
                            <Earth class="h-4 w-4 text-hsi-gold-light" aria-hidden="true" />
                            {{ t('authLayout.mapTitle') }}
                        </p>
                        <p class="mt-1 text-[13px] text-white/70">{{ locale === 'en' ? 'Cameroon · France · Canada · United States · United Kingdom' : 'Cameroun · France · Canada · États-Unis · Royaume-Uni' }}</p>
                    </figcaption>
                </figure>

                <ul class="mt-8 grid max-w-xl gap-x-8 gap-y-3 sm:grid-cols-2">
                    <li v-for="item in tm('authLayout.highlights')" :key="item" class="flex items-start gap-3 text-[15px] text-white/85">
                        <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-hsi-gold-light" aria-hidden="true" />
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </div>

            <div class="hidden items-center justify-between border-t border-white/15 pt-6 text-sm text-white/60 lg:flex">
                <span>© {{ year }} HSI · {{ t('authLayout.rights') }}</span>
                <span class="inline-flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4" aria-hidden="true" />
                    {{ t('authLayout.protected') }}
                </span>
            </div>
        </aside>

        <!-- Formulaire -->
        <main class="auth-surface relative flex min-h-[calc(100vh-96px)] flex-col px-4 py-10 sm:px-8 lg:min-h-screen lg:py-12">
            <div class="flex flex-1 items-center justify-center">
                <div class="w-full" :class="wide ? 'max-w-[600px]' : 'max-w-[480px]'">
                    <div class="rounded-[28px] border border-hsi-border/80 bg-white/90 p-7 shadow-[0_24px_60px_-24px_rgba(7,27,52,0.25)] backdrop-blur-sm sm:p-10">
                        <div
                            v-if="$slots.icon"
                            class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-hsi-gold/25 bg-hsi-gold/10 text-[#A8841F]"
                        >
                            <slot name="icon" />
                        </div>

                        <h1 class="font-heading text-[28px] font-semibold leading-tight tracking-tight text-hsi-navy sm:text-[32px]">
                            {{ title }}
                        </h1>
                        <p v-if="subtitle" class="mt-2 text-[15px] leading-relaxed text-hsi-slate">
                            {{ subtitle }}
                        </p>

                        <div class="mt-8">
                            <AuthNotice v-if="page.props.flash?.error" tone="warning" class="mb-6">{{ page.props.flash.error }}</AuthNotice>
                            <slot />
                        </div>
                    </div>

                    <slot name="below" />
                </div>
            </div>

            <p class="mt-10 text-center text-sm text-hsi-slate-light lg:text-right">
                {{ t('common.help') }}
                <a href="mailto:contact@inovixora.com" class="font-medium text-hsi-slate underline-offset-4 hover:text-hsi-navy hover:underline">contact@inovixora.com</a>
            </p>
        </main>
    </div>
</template>

<style scoped>
.auth-brand {
    background:
        radial-gradient(120% 70% at 0% 0%, rgba(212, 175, 55, 0.14) 0%, transparent 55%),
        radial-gradient(90% 60% at 100% 100%, rgba(37, 99, 235, 0.18) 0%, transparent 60%),
        linear-gradient(160deg, #071b34 0%, #0b2748 55%, #08203d 100%);
}

.auth-brand::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(70% 60% at 30% 40%, #000 0%, transparent 100%);
}

.auth-gold-text {
    background: linear-gradient(100deg, #e8c766 0%, #d4af37 45%, #f3dc94 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.auth-surface {
    background:
        radial-gradient(60% 50% at 100% 0%, rgba(212, 175, 55, 0.08) 0%, transparent 70%),
        radial-gradient(50% 40% at 0% 100%, rgba(11, 39, 72, 0.06) 0%, transparent 70%),
        #f8f6f0;
}
</style>
