import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import fr from './fr';
import en from './en';
import enStrings from '../../../lang/en.json';
import { withCurrency } from '../currency';

/**
 * Traductions de l'interface (français / anglais).
 *
 * Deux mécanismes complémentaires :
 *  1. des clés (t('auth.login.title')) pour les écrans d'accueil, dans ./fr.js et ./en.js ;
 *  2. le texte français lui-même comme clé (tr('Cotisations')), traduit grâce à lang/en.json —
 *     le même fichier que Laravel utilise côté serveur (__('…')). Les gabarits Vue sont
 *     enveloppés automatiquement à la compilation (voir ./autoTranslateTransform.js).
 *
 * La langue active vient du serveur (props partagées « locale ») ; le français sert de secours.
 */
const messages = { fr, en };

/** Langue active, tenue à jour à chaque navigation Inertia (voir app.js). */
export const activeLocale = ref('fr');

export function setActiveLocale(value) {
    activeLocale.value = value === 'en' ? 'en' : 'fr';
    if (typeof document !== 'undefined') document.documentElement.lang = activeLocale.value;
}

const lookup = (dict, key) => key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), dict);

export function translate(locale, key, params = {}) {
    let text = lookup(messages[locale], key) ?? lookup(messages.fr, key) ?? key;
    if (typeof text !== 'string') return key;
    return interpolate(text, params);
}

function interpolate(text, params = {}) {
    for (const [name, value] of Object.entries(params ?? {})) {
        text = text.replaceAll(`{${name}}`, value);
    }
    return text;
}

// ---------------------------------------------------------------------------
// Traduction par texte source (français → anglais)
// ---------------------------------------------------------------------------

/** Normalise apostrophes et espaces pour que « l’échéance » et « l'échéance » se retrouvent. */
const normalize = (s) => s.replace(/[’‘]/g, "'").replace(/[  ]/g, ' ').replace(/\s+/g, ' ').trim();

const dictionary = new Map(Object.entries(enStrings).map(([fr, en]) => [normalize(fr), en]));

const MONTHS = {
    janvier: 'January', 'février': 'February', mars: 'March', avril: 'April', mai: 'May', juin: 'June',
    juillet: 'July', 'août': 'August', septembre: 'September', octobre: 'October', novembre: 'November', 'décembre': 'December',
};
const MONTH_RE = new RegExp(`\\b(${Object.keys(MONTHS).join('|')})\\b`, 'giu');

/** Débuts de libellés générés par le serveur (« Transfert depuis Trésorerie »…) : la suite est traduite à part. */
const PREFIXES = [
    ['Transfert depuis ', 'Transfer from '],
    ['Transfert vers ', 'Transfer to '],
    ['Financement projet — ', 'Project funding — '],
    ['Allocation cotisation — ', 'Contribution allocation — '],
    ['Financement — ', 'Funding — '],
    ['Débit de la poche Projets familiaux pour le projet #', 'Debit from the Family projects pocket for project #'],
];

/** Valeurs calculées côté serveur qui ne sont pas des phrases fixes (périodes, dates). */
function translatePattern(text) {
    for (const [fr, en] of PREFIXES) {
        if (text.startsWith(fr) && text.length > fr.length) {
            const rest = text.slice(fr.length);
            return en + (dictionary.get(normalize(rest)) ?? rest);
        }
    }
    let m = text.match(/^Vote familial sur le financement HSI du projet « (.+) »\. Montant demandé : (.+)\.$/u);
    if (m) return `Family vote on HSI funding for the project “${m[1]}”. Requested amount: ${m[2]}.`;
    m = text.match(/^Année (\d{4})$/u);
    if (m) return `Year ${m[1]}`;
    m = text.match(/^T([1-4]) (\d{4})$/u);
    if (m) return `Q${m[1]} ${m[2]}`;
    if (MONTH_RE.test(text) && /\d/.test(text)) {
        MONTH_RE.lastIndex = 0;
        // « 12 mars 2026 » → « 12 March 2026 », « Novembre 2026 » → « November 2026 »
        return text.replace(MONTH_RE, (w) => MONTHS[w.toLowerCase()]);
    }
    MONTH_RE.lastIndex = 0;
    return null;
}

function lookupSource(text) {
    const key = normalize(text);
    if (!key) return text;
    const found = dictionary.get(key);
    if (found !== undefined) return found;
    return translatePattern(key) ?? text;
}

/**
 * Traduit un texte français dans la langue active. Les espaces autour du texte sont conservés.
 * Paramètres : tr('{n} membres', { n: 3 }).
 */
export function tr(text, params) {
    if (typeof text !== 'string') return text;
    if (activeLocale.value !== 'en' || !/\p{L}/u.test(text)) return withCurrency(interpolate(text, params));
    const lead = text.match(/^\s*/)[0];
    const trail = text.match(/\s*$/)[0];
    const core = text.trim();
    return withCurrency(lead + interpolate(lookupSource(core), params) + (core ? trail : ''));
}

/** Variante tolérante pour les valeurs affichées ({{ … }}) : seuls les textes sont traduits. */
export function trValue(value) {
    return typeof value === 'string' ? tr(value) : value;
}

/** Titres en deux parties (titre + accent doré) : traduits ensemble, séparés par « | » dans en.json. */
export function trPair(title, accent, index) {
    if (activeLocale.value !== 'en') return withCurrency(index === 0 ? title : accent);
    const found = dictionary.get(normalize(`${title}|${accent}`));
    if (found !== undefined) {
        const [a, b = ''] = found.split('|');
        return index === 0 ? a : b;
    }
    return tr(index === 0 ? title : accent);
}

/** Plugin Vue : expose $tr, $trv et $trp aux gabarits. */
export const autoTranslatePlugin = {
    install(app) {
        app.config.globalProperties.$tr = tr;
        app.config.globalProperties.$trv = trValue;
        app.config.globalProperties.$trp = trPair;
    },
};

/** Locale d'affichage pour Intl (dates, nombres). */
export const intlLocale = () => (activeLocale.value === 'en' ? 'en-GB' : 'fr-FR');

export function useI18n() {
    const page = usePage();
    const locale = computed(() => (page.props.locale === 'en' ? 'en' : 'fr'));
    const t = (key, params) => translate(locale.value, key, params);
    // Pour les listes (étapes, points clés…) : renvoie la valeur brute (tableau ou objet).
    const tm = (key) => lookup(messages[locale.value], key) ?? lookup(messages.fr, key) ?? [];
    const setLocale = (value) => router.post(route('locale.update'), { locale: value }, { preserveScroll: true, preserveState: false });

    return { t, tm, tr, locale, setLocale };
}
