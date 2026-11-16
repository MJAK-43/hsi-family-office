import { createSimpleExpression } from '@vue/compiler-core';

/**
 * Traduction automatique des gabarits Vue (exécutée à la compilation par Vite).
 *
 * Les pages sont écrites en français. Ce transform enveloppe, sans toucher aux fichiers source :
 *  - chaque texte statique            « Cotisations »        → {{ $tr('Cotisations') }}
 *  - chaque interpolation              {{ item.status_label }} → {{ $trv(item.status_label) }}
 *  - les attributs de libellé          title="…", placeholder="…", :subtitle="…", …
 *
 * À l'exécution, $tr / $trv cherchent le texte dans lang/en.json quand la langue active est l'anglais,
 * et renvoient le texte d'origine sinon (voir resources/js/i18n/index.js).
 * Pour exclure un élément : ajouter l'attribut translate="no".
 */

const TEXT = 2;
const ELEMENT = 1;
const INTERPOLATION = 5;
const ATTRIBUTE = 6;
const DIRECTIVE = 7;

export const TRANSLATABLE_ATTRS = new Set([
    'placeholder', 'title', 'subtitle', 'accent', 'aria-label', 'description', 'alt', 'label',
    'hint', 'help', 'eyebrow', 'caption', 'heading', 'text', 'empty-text', 'empty-title',
    'confirm-text', 'cancel-text', 'action-label', 'tooltip', 'change',
]);

const SKIP_TAGS = new Set(['script', 'style', 'code', 'pre', 'svg', 'path']);
const HAS_LETTER = /\p{L}/u;

// Les nœuds v-if / v-for sont revisités par le compilateur : on ne traite chaque nœud qu'une fois.
const processed = new WeakSet();

const call = (fn, args, loc) => createSimpleExpression(`${fn}(${args})`, false, loc);

const isSkipped = (node) =>
    SKIP_TAGS.has(node.tag) ||
    node.props.some((p) => (p.type === ATTRIBUTE && p.name === 'translate' && p.value?.content === 'no') || (p.type === DIRECTIVE && p.name === 'pre'));

function translateProps(element) {
    if (processed.has(element)) return;
    processed.add(element);
    const staticAttr = (name) => element.props.find((p) => p.type === ATTRIBUTE && p.name === name && p.value && HAS_LETTER.test(p.value.content));
    const title = staticAttr('title');
    const accent = staticAttr('accent');
    const pair = title && accent ? `${JSON.stringify(title.value.content)}, ${JSON.stringify(accent.value.content)}` : null;

    element.props.forEach((p, index) => {
        element.props[index] = translateProp(p, title, accent, pair);
    });
}

function translateProp(p, title, accent, pair) {
    if (p.type === ATTRIBUTE && TRANSLATABLE_ATTRS.has(p.name) && p.value && HAS_LETTER.test(p.value.content)) {
        // Titre en deux parties (« Plans de » + « cotisation ») : traduit ensemble, l'ordre des mots change en anglais.
        const exp = pair && (p === title || p === accent)
            ? call('$trp', `${pair}, ${p === title ? 0 : 1}`, p.loc)
            : call('$tr', JSON.stringify(p.value.content), p.loc);
        return {
            type: DIRECTIVE,
            name: 'bind',
            rawName: `:${p.name}`,
            exp,
            arg: createSimpleExpression(p.name, true, p.nameLoc ?? p.loc),
            modifiers: [],
            loc: p.loc,
        };
    }
    if (p.type === DIRECTIVE && p.name === 'bind' && p.arg?.isStatic && TRANSLATABLE_ATTRS.has(p.arg.content) && p.exp?.content?.trim()) {
        p.exp = call('$trv', p.exp.content, p.exp.loc);
    }
    return p;
}

export function autoTranslateTransform(node) {
    if (!node.children || (node.type === ELEMENT && isSkipped(node))) return;

    // Modification sur place : certains transforms du compilateur (ex. <slot>) gardent une
    // référence au tableau d'enfants d'origine ; le remplacer ferait perdre v-for / v-if.
    node.children.forEach((child, index) => {
        if (processed.has(child)) return;
        if (child.type === TEXT && HAS_LETTER.test(child.content)) {
            const replacement = { type: INTERPOLATION, loc: child.loc, content: call('$tr', JSON.stringify(child.content), child.loc) };
            processed.add(replacement);
            node.children[index] = replacement;
        } else if (child.type === INTERPOLATION && child.content?.content?.trim()) {
            processed.add(child);
            child.content = call('$trv', child.content.content, child.content.loc);
        } else if (child.type === ELEMENT && !isSkipped(child)) {
            translateProps(child);
        }
    });
}
