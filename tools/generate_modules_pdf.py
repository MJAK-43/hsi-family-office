from pathlib import Path

import fitz

from generate_modules_report import completed, partial, not_started


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "deliverables"
OUTPUT_PATH = OUTPUT_DIR / "HSI-Etat-des-modules-2026-09-20.pdf"

PAGE_W = 612
PAGE_H = 792
MARGIN_X = 54
TOP_Y = 66
BOTTOM_Y = 748
CONTENT_W = PAGE_W - (2 * MARGIN_X)

NAVY = (16 / 255, 42 / 255, 67 / 255)
GOLD = (198 / 255, 161 / 255, 91 / 255)
CREAM = (247 / 255, 243 / 255, 234 / 255)
GREEN = (230 / 255, 244 / 255, 234 / 255)
GREEN_TEXT = (35 / 255, 122 / 255, 69 / 255)
AMBER = (1, 243 / 255, 214 / 255)
AMBER_TEXT = (154 / 255, 103 / 255, 0)
GRAY = (238 / 255, 241 / 255, 244 / 255)
GRAY_TEXT = (82 / 255, 96 / 255, 109 / 255)
TEXT = (36 / 255, 59 / 255, 83 / 255)
BORDER = (216 / 255, 222 / 255, 229 / 255)
WHITE = (1, 1, 1)


def text_width(text, fontname, fontsize):
    return fitz.get_text_length(text, fontname=fontname, fontsize=fontsize)


def wrap_text(text, width, fontname="helv", fontsize=9.2):
    words = text.split()
    lines = []
    current = ""
    for word in words:
        candidate = word if not current else f"{current} {word}"
        if text_width(candidate, fontname, fontsize) <= width:
            current = candidate
        else:
            if current:
                lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines


def draw_lines(page, lines, x, y, fontsize, color, fontname="helv", leading=None):
    leading = leading or fontsize * 1.3
    for line in lines:
        page.insert_text(
            fitz.Point(x, y),
            line,
            fontname=fontname,
            fontsize=fontsize,
            color=color,
        )
        y += leading
    return y


def add_page(document, page_number, label="ÉTAT DES MODULES"):
    page = document.new_page(width=PAGE_W, height=PAGE_H)
    page.insert_text(
        fitz.Point(MARGIN_X, 28),
        f"HSI FAMILY OFFICE  |  {label}",
        fontname="hebo",
        fontsize=7.7,
        color=NAVY,
    )
    page.draw_line(
        fitz.Point(MARGIN_X, 38),
        fitz.Point(PAGE_W - MARGIN_X, 38),
        color=GOLD,
        width=1.2,
    )
    page.draw_line(
        fitz.Point(MARGIN_X, 758),
        fitz.Point(PAGE_W - MARGIN_X, 758),
        color=BORDER,
        width=0.6,
    )
    page.insert_text(
        fitz.Point(PAGE_W - MARGIN_X - 36, 774),
        f"Page {page_number}",
        fontname="helv",
        fontsize=8,
        color=GRAY_TEXT,
    )
    return page


def draw_cover(document):
    page = add_page(document, 1, "RAPPORT DE PILOTAGE")
    page.draw_rect(
        fitz.Rect(MARGIN_X, 84, MARGIN_X + 92, 105),
        color=GOLD,
        fill=GOLD,
        radius=0.1,
    )
    page.insert_text(
        fitz.Point(MARGIN_X + 9, 98),
        "ÉTAT FONCTIONNEL",
        fontname="hebo",
        fontsize=7.2,
        color=WHITE,
    )
    page.insert_text(
        fitz.Point(MARGIN_X, 151),
        "État des modules",
        fontname="hebo",
        fontsize=26,
        color=NAVY,
    )
    page.insert_text(
        fitz.Point(MARGIN_X, 177),
        "HSI Family Office",
        fontname="hebo",
        fontsize=15,
        color=GOLD,
    )
    subtitle = wrap_text(
        "Modules réalisés, partiellement réalisés et pas encore développés",
        CONTENT_W,
        "helv",
        11.5,
    )
    draw_lines(page, subtitle, MARGIN_X, 207, 11.5, GRAY_TEXT, leading=15)

    intro = wrap_text(
        "Ce rapport présente l’état fonctionnel de la plateforme au 20 septembre 2026. "
        "Il décrit le périmètre livré, les fonctions encore incomplètes et les domaines inscrits dans la feuille de route.",
        CONTENT_W,
        "helv",
        10.2,
    )
    draw_lines(page, intro, MARGIN_X, 259, 10.2, TEXT, leading=14.2)

    cards = [
        ("12", "Modules réalisés", GREEN, GREEN_TEXT),
        ("9", "Modules partiels", AMBER, AMBER_TEXT),
        ("10", "Modules à développer", GRAY, GRAY_TEXT),
    ]
    gap = 10
    card_w = (CONTENT_W - (2 * gap)) / 3
    y1, y2 = 324, 413
    for index, (count, label, fill, accent) in enumerate(cards):
        x1 = MARGIN_X + index * (card_w + gap)
        x2 = x1 + card_w
        page.draw_rect(fitz.Rect(x1, y1, x2, y2), color=fill, fill=fill, radius=0.08)
        page.insert_text(
            fitz.Point(x1 + 12, y1 + 35),
            count,
            fontname="hebo",
            fontsize=25,
            color=accent,
        )
        label_lines = wrap_text(label, card_w - 24, "hebo", 8.5)
        draw_lines(page, label_lines, x1 + 12, y1 + 57, 8.5, accent, "hebo", 11)

    page.insert_text(
        fitz.Point(MARGIN_X, 461),
        "COMMENT LIRE CE RAPPORT",
        fontname="hebo",
        fontsize=9,
        color=NAVY,
    )
    legends = [
        (GREEN_TEXT, "Réalisé", "fonctionnalité disponible et opérationnelle."),
        (AMBER_TEXT, "Partiel", "fondation présente, mais périmètre restant à compléter."),
        (GRAY_TEXT, "À développer", "aucun module métier complet n’est encore disponible."),
    ]
    y = 492
    for accent, label, detail in legends:
        page.draw_circle(fitz.Point(MARGIN_X + 5, y - 3), 3, color=accent, fill=accent)
        page.insert_text(fitz.Point(MARGIN_X + 18, y), label, fontname="hebo", fontsize=9.3, color=accent)
        offset = text_width(label, "hebo", 9.3) + 34
        page.insert_text(fitz.Point(MARGIN_X + offset, y), detail, fontname="helv", fontsize=9.3, color=TEXT)
        y += 31

    page.draw_rect(
        fitz.Rect(MARGIN_X, 611, PAGE_W - MARGIN_X, 693),
        color=CREAM,
        fill=CREAM,
        radius=0.08,
    )
    page.insert_text(
        fitz.Point(MARGIN_X + 16, 633),
        "SOURCE DE L’ÉTAT",
        fontname="hebo",
        fontsize=8.2,
        color=GOLD,
    )
    source_lines = wrap_text(
        "Analyse fondée sur le code applicatif, les routes Laravel, les tests automatisés, "
        "les documents de phase et la feuille de route HSI.",
        CONTENT_W - 32,
        "helv",
        9.4,
    )
    draw_lines(page, source_lines, MARGIN_X + 16, 654, 9.4, TEXT, leading=13)


def draw_section_header(page, title, count, intro, accent):
    page.insert_text(fitz.Point(MARGIN_X, 76), title, fontname="hebo", fontsize=18, color=NAVY)
    page.insert_text(
        fitz.Point(MARGIN_X, 99),
        f"{count} modules",
        fontname="hebo",
        fontsize=9.3,
        color=accent,
    )
    intro_lines = wrap_text(intro, CONTENT_W - 90, "helv", 9.3)
    draw_lines(page, intro_lines, MARGIN_X + 88, 99, 9.3, TEXT, leading=12)
    page.draw_line(
        fitz.Point(MARGIN_X, 119),
        fitz.Point(PAGE_W - MARGIN_X, 119),
        color=BORDER,
        width=0.8,
    )
    return 133


def draw_continuation_header(page, title):
    page.insert_text(fitz.Point(MARGIN_X, 76), f"{title} — suite", fontname="hebo", fontsize=16, color=NAVY)
    page.draw_line(
        fitz.Point(MARGIN_X, 94),
        fitz.Point(PAGE_W - MARGIN_X, 94),
        color=BORDER,
        width=0.8,
    )
    return 108


def module_card_height(module, description):
    left_lines = wrap_text(module, 132, "hebo", 9.2)
    right_lines = wrap_text(description, 326, "helv", 8.9)
    text_height = max(len(left_lines) * 12, len(right_lines) * 12)
    return max(62, text_height + 28)


def draw_module_card(page, y, module, description, status_label, fill, accent):
    left_w = 150
    height = module_card_height(module, description)
    x1 = MARGIN_X
    x2 = PAGE_W - MARGIN_X
    page.draw_rect(fitz.Rect(x1, y, x2, y + height), color=BORDER, fill=WHITE, width=0.7, radius=0.05)
    page.draw_rect(fitz.Rect(x1, y, x1 + left_w, y + height), color=fill, fill=fill, width=0, radius=0.05)
    page.draw_line(
        fitz.Point(x1 + left_w, y),
        fitz.Point(x1 + left_w, y + height),
        color=BORDER,
        width=0.7,
    )

    module_lines = wrap_text(module, left_w - 20, "hebo", 9.2)
    module_y = draw_lines(page, module_lines, x1 + 10, y + 19, 9.2, TEXT, "hebo", 12)
    page.insert_text(
        fitz.Point(x1 + 10, min(module_y + 7, y + height - 10)),
        status_label.upper(),
        fontname="hebo",
        fontsize=6.9,
        color=accent,
    )

    description_lines = wrap_text(description, CONTENT_W - left_w - 24, "helv", 8.9)
    draw_lines(page, description_lines, x1 + left_w + 12, y + 19, 8.9, TEXT, "helv", 12)
    return height


def draw_section(document, page_number, title, rows, intro, status_label, fill, accent):
    page = add_page(document, page_number)
    y = draw_section_header(page, title, len(rows), intro, accent)
    cards_on_page = 0
    for module, description in rows:
        height = module_card_height(module, description)
        if cards_on_page >= 6 or y + height > BOTTOM_Y - 8:
            page_number += 1
            page = add_page(document, page_number)
            y = draw_continuation_header(page, title)
            cards_on_page = 0
        draw_module_card(page, y, module, description, status_label, fill, accent)
        y += height + 9
        cards_on_page += 1
    return page_number, page, y


def build_pdf():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    document = fitz.open()
    draw_cover(document)
    page_number = 2

    page_number, _, _ = draw_section(
        document,
        page_number,
        "1. Modules réalisés",
        completed,
        "Fonctionnalités actuellement disponibles et opérationnelles.",
        "Réalisé",
        GREEN,
        GREEN_TEXT,
    )
    page_number += 1
    page_number, _, _ = draw_section(
        document,
        page_number,
        "2. Modules partiellement réalisés",
        partial,
        "Fondations disponibles, avec des compléments à planifier.",
        "Partiel",
        AMBER,
        AMBER_TEXT,
    )
    page_number += 1
    page_number, last_page, last_y = draw_section(
        document,
        page_number,
        "3. Modules pas encore développés",
        not_started,
        "Domaines inscrits dans la cible fonctionnelle mais non encore livrés.",
        "À développer",
        GRAY,
        GRAY_TEXT,
    )

    if last_y + 94 > BOTTOM_Y:
        page_number += 1
        last_page = add_page(document, page_number)
        last_y = 92
    last_page.draw_rect(
        fitz.Rect(MARGIN_X, last_y + 8, PAGE_W - MARGIN_X, last_y + 86),
        color=CREAM,
        fill=CREAM,
        radius=0.08,
    )
    last_page.insert_text(
        fitz.Point(MARGIN_X + 15, last_y + 30),
        "PRIORITÉ RECOMMANDÉE",
        fontname="hebo",
        fontsize=8.4,
        color=GOLD,
    )
    priority_lines = wrap_text(
        "Finaliser le ledger et l’intégrité financière, puis le suivi des projets, les votes v2 et les notifications. "
        "Ces fondations sécuriseront l’ajout des investissements, prêts, dispositifs de solidarité et tontines.",
        CONTENT_W - 30,
        "helv",
        9.2,
    )
    draw_lines(last_page, priority_lines, MARGIN_X + 15, last_y + 51, 9.2, TEXT, leading=12.5)

    metadata = {
        "title": "HSI Family Office — État des modules",
        "author": "HSI Family Office",
        "subject": "Modules réalisés, partiels et à développer",
        "keywords": "HSI, modules, roadmap, état fonctionnel",
        "creator": "HSI Family Office",
        "producer": "PyMuPDF",
    }
    document.set_metadata(metadata)
    document.save(OUTPUT_PATH, garbage=4, deflate=True)
    print(f"{OUTPUT_PATH}\nPAGES={len(document)}")


if __name__ == "__main__":
    build_pdf()
