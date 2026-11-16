from pathlib import Path
import sys

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "deliverables"
OUTPUT_PATH = OUTPUT_DIR / "HSI-Etat-des-modules-2026-09-20.docx"
SKILL_SCRIPTS = Path(
    r"C:\Users\mboni\.codex\plugins\cache\openai-primary-runtime\documents\26.430.10722\skills\documents\scripts"
)
sys.path.insert(0, str(SKILL_SCRIPTS))

from table_geometry import apply_table_geometry  # noqa: E402


NAVY = "102A43"
GOLD = "C6A15B"
CREAM = "F7F3EA"
GREEN = "E6F4EA"
GREEN_TEXT = "237A45"
AMBER = "FFF3D6"
AMBER_TEXT = "9A6700"
GRAY = "EEF1F4"
GRAY_TEXT = "52606D"
WHITE = "FFFFFF"
TEXT = "243B53"
BORDER = "D8DEE5"


completed = [
    (
        "Authentification",
        "Gestion complète de l’accès utilisateur : connexion, déconnexion, mot de passe oublié, réinitialisation et vérification de l’adresse email. L’inscription publique est désactivée afin de réserver l’accès aux comptes créés ou autorisés par l’administration.",
    ),
    (
        "Sécurité et contrôle d’accès",
        "Gestion des rôles et permissions, contrôle du statut actif du membre, révocation d’accès pour les comptes suspendus et protection du dernier super-administrateur. Les pages et actions métier sont protégées côté serveur.",
    ),
    (
        "Tableau de bord",
        "Vue synthétique alimentée par les données réelles de l’application : membres, cotisations, fonds, projets et votes. Le contenu affiché est filtré selon les permissions de l’utilisateur connecté.",
    ),
    (
        "Membres",
        "Annuaire et cycle de vie des membres : création, consultation, modification, suspension, réactivation et archivage. Les profils sont liés aux pays, branches familiales, rôles et permissions, avec journalisation des opérations sensibles.",
    ),
    (
        "Cotisations",
        "Gestion des plans de cotisation et des paiements soumis par les membres. Le module prend en charge les preuves de paiement privées, la consultation selon le rôle, ainsi que la validation ou le refus par le trésorier.",
    ),
    (
        "Fonds familial",
        "Gestion des poches du fonds, règles d’allocation, mouvements financiers, transferts internes, annulations contrôlées et recalcul des soldes. Une cotisation validée peut être répartie automatiquement entre les différentes poches.",
    ),
    (
        "Intégrité financière v1",
        "Les montants sont traités en unités monétaires exactes, sans calcul flottant sur les écritures. Les validations, allocations, transferts et financements bénéficient de transactions atomiques, contrôles de devise, verrous et clés d’idempotence.",
    ),
    (
        "Projets familiaux",
        "Dépôt et instruction des projets proposés par les membres, avec règle de financement 50/50. Le module inclut les documents privés, l’évaluation multicritère, la décision du comité d’investissement et la soumission au vote familial.",
    ),
    (
        "Votes familiaux v1",
        "Consultations avec options Pour, Contre et Abstention, contrôle du quorum, calcul de majorité, vote unique par membre, clôture et annulation. La décision finale met automatiquement à jour le statut du projet concerné.",
    ),
    (
        "Financement des projets",
        "Financement manuel d’un projet approuvé par la famille depuis la poche « Projets familiaux ». Le module contrôle le solde, empêche le double financement et enregistre le mouvement financier ainsi que l’auteur et la date du financement.",
    ),
    (
        "Audit et traçabilité",
        "Journalisation des principales opérations métier sur les membres, cotisations, fonds, projets et votes. Cette traçabilité permet d’identifier l’action, son auteur et le contexte fonctionnel.",
    ),
    (
        "Déploiement et sauvegarde",
        "Processus de mise en production avec archive contrôlée, sauvegarde préalable, migrations additives, audit d’intégrité et possibilité de rollback. Les données de production sont conservées pendant les mises à jour.",
    ),
]

partial = [
    (
        "Ledger et fonds v2",
        "La fondation financière est sécurisée, mais le grand livre comptable en partie double reste à construire. Il manque notamment les contre-écritures, la réconciliation automatisée, les réservations, le journal quotidien et les tests de concurrence MySQL à deux connexions.",
    ),
    (
        "Suivi d’exécution des projets",
        "Le workflow fonctionne jusqu’au statut financé. Les étapes postérieures restent à ajouter : démarrage, jalons, suivi d’utilisation des fonds, rapports, clôture, échec, annulation et procédure de remboursement ou correction.",
    ),
    (
        "Votes familiaux v2",
        "La version actuelle couvre principalement les votes liés aux projets. La version avancée doit ajouter la planification, l’ouverture et la clôture automatiques, les conflits d’intérêts, les options personnalisées, l’anonymat et d’autres sujets de gouvernance.",
    ),
    (
        "Automatisation des cotisations",
        "La saisie, la validation et l’allocation existent. La génération automatique des échéances, le marquage des retards, les relances et les notifications de validation ou de refus restent à développer.",
    ),
    (
        "Notifications",
        "Les notifications techniques d’authentification existent, notamment pour le mot de passe et la vérification email. Il manque un centre de notifications métier, une cloche dynamique, les préférences utilisateur et l’envoi asynchrone lié aux cotisations, projets et votes.",
    ),
    (
        "Documents",
        "Les preuves de paiement et les documents de projet sont stockés de manière privée avec contrôle d’accès. Il manque encore une bibliothèque globale, le versionnage, les catégories, la publication, l’archivage, la rétention et la gestion de la Constitution.",
    ),
    (
        "Administration",
        "Les rôles, permissions et règles d’autorisation sont opérationnels côté serveur. Une interface d’administration complète pour gérer les rôles, permissions, paramètres et référentiels reste à réaliser.",
    ),
    (
        "Reporting et exports",
        "Le tableau de bord fournit déjà plusieurs statistiques locales. Il manque des rapports consolidés, des filtres avancés, des exports CSV et PDF, ainsi qu’un historique des exports sensibles.",
    ),
    (
        "Profil membre",
        "Les informations personnelles et le mot de passe peuvent être modifiés. Le téléversement de photo, la gestion d’un avatar persistant et certains enrichissements du profil ne sont pas encore disponibles.",
    ),
]

not_started = [
    (
        "Investissements HSI",
        "Module destiné à gérer les catégories d’actifs, achats, cessions, revenus, frais, valorisations et documents de due diligence. Il devra fournir un portefeuille par devise et relier chaque sortie de fonds au futur ledger.",
    ),
    (
        "Prêts familiaux",
        "Gestion future des demandes de prêt, décisions, contrats, échéanciers, décaissements, remboursements, retards et défauts. Les paiements devront être idempotents et rapprochés au centime avec le ledger.",
    ),
    (
        "Solidarité",
        "Gestion future des demandes d’aide, bénéficiaires, urgences, justificatifs, décisions du comité et décaissements depuis la poche Solidarité. Le module devra renforcer la confidentialité des données personnelles et médicales.",
    ),
    (
        "Tontines digitales",
        "Gestion future des tontines tournantes, votées ou d’épargne collective. Le périmètre comprend les membres, cycles, calendriers, contributions, retards, bénéficiaires et paiements via des sous-comptes dédiés.",
    ),
    (
        "Documents globaux et Constitution",
        "Espace documentaire transversal avec catégories, versions, droits d’accès, empreintes de fichiers, validation, publication et archivage. La Constitution devra être versionnée et éventuellement approuvée par un vote avancé.",
    ),
    (
        "Gouvernance avancée",
        "Gestion future des comités, postes, membres, mandats, réunions, ordres du jour, décisions et procès-verbaux. Les décisions devront pouvoir être reliées aux votes et aux documents officiels.",
    ),
    (
        "HSI Academy",
        "Module prévu pour le mentorat, les bourses, les stages, les formations et les inscriptions. Il devra réutiliser les membres, documents et notifications, avec un financement sécurisé des bourses via la poche Éducation.",
    ),
    (
        "Recherche globale",
        "Moteur de recherche transversal sur les ressources autorisées : membres, cotisations, projets, votes et documents. Les résultats devront respecter strictement les permissions et la confidentialité de chaque domaine.",
    ),
    (
        "Notifications multicanales",
        "Extensions futures vers SMS, WhatsApp et notifications push. Elles dépendront d’abord du centre de notifications interne, des préférences utilisateur et d’une gestion fiable des files d’attente.",
    ),
    (
        "Reporting global et exports complets",
        "Couche transversale de reporting couvrant tous les domaines, avec exports CSV en priorité puis PDF. Elle comprendra des tableaux de bord par rôle, des indicateurs consolidés et des contrôles de traçabilité.",
    ),
]


def set_cell_shading(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_margins(cell, top=110, start=130, bottom=110, end=130):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for margin, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{margin}"))
        if node is None:
            node = OxmlElement(f"w:{margin}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_cell_border(cell, color=BORDER, size="5"):
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = tc_pr.find(qn("w:tcBorders"))
    if borders is None:
        borders = OxmlElement("w:tcBorders")
        tc_pr.append(borders)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        node = borders.find(qn(f"w:{edge}"))
        if node is None:
            node = OxmlElement(f"w:{edge}")
            borders.append(node)
        node.set(qn("w:val"), "single")
        node.set(qn("w:sz"), size)
        node.set(qn("w:color"), color)


def repeat_table_header(row):
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def keep_row_together(row):
    tr_pr = row._tr.get_or_add_trPr()
    cant_split = OxmlElement("w:cantSplit")
    tr_pr.append(cant_split)


def set_repeatable_font(run, name="Arial"):
    run.font.name = name
    if run._element.get_or_add_rPr().rFonts is None:
        run._element.get_or_add_rPr().append(OxmlElement("w:rFonts"))
    fonts = run._element.get_or_add_rPr().rFonts
    fonts.set(qn("w:ascii"), name)
    fonts.set(qn("w:hAnsi"), name)


def add_page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = paragraph.add_run("Page ")
    set_repeatable_font(run)
    run.font.size = Pt(9)
    run.font.color.rgb = RGBColor.from_string(GRAY_TEXT)
    fld_char1 = OxmlElement("w:fldChar")
    fld_char1.set(qn("w:fldCharType"), "begin")
    instr_text = OxmlElement("w:instrText")
    instr_text.set(qn("xml:space"), "preserve")
    instr_text.text = " PAGE "
    fld_char2 = OxmlElement("w:fldChar")
    fld_char2.set(qn("w:fldCharType"), "end")
    run._r.append(fld_char1)
    run._r.append(instr_text)
    run._r.append(fld_char2)


def add_header_footer(section):
    header = section.header
    header.distance = Inches(0.35)
    paragraph = header.paragraphs[0]
    paragraph.text = "HSI FAMILY OFFICE  |  ÉTAT DES MODULES"
    paragraph.alignment = WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.space_after = Pt(4)
    for run in paragraph.runs:
        set_repeatable_font(run)
        run.font.size = Pt(8)
        run.font.bold = True
        run.font.color.rgb = RGBColor.from_string(NAVY)
    p_pr = paragraph._p.get_or_add_pPr()
    p_bdr = OxmlElement("w:pBdr")
    bottom = OxmlElement("w:bottom")
    bottom.set(qn("w:val"), "single")
    bottom.set(qn("w:sz"), "8")
    bottom.set(qn("w:space"), "4")
    bottom.set(qn("w:color"), GOLD)
    p_bdr.append(bottom)
    p_pr.append(p_bdr)

    footer = section.footer
    footer.distance = Inches(0.35)
    add_page_number(footer.paragraphs[0])


def add_status_table(document):
    table = document.add_table(rows=1, cols=3)
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    table.autofit = False
    labels = [
        ("12", "Modules réalisés", GREEN, GREEN_TEXT),
        ("9", "Modules partiels", AMBER, AMBER_TEXT),
        ("10", "Modules à développer", GRAY, GRAY_TEXT),
    ]
    for idx, (number, label, fill, color) in enumerate(labels):
        cell = table.rows[0].cells[idx]
        set_cell_shading(cell, fill)
        set_cell_border(cell, WHITE, "0")
        set_cell_margins(cell, top=180, start=160, bottom=180, end=160)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(3)
        r = p.add_run(number)
        set_repeatable_font(r)
        r.font.size = Pt(22)
        r.font.bold = True
        r.font.color.rgb = RGBColor.from_string(color)
        p2 = cell.add_paragraph()
        p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p2.paragraph_format.space_after = Pt(0)
        r2 = p2.add_run(label)
        set_repeatable_font(r2)
        r2.font.size = Pt(9)
        r2.font.bold = True
        r2.font.color.rgb = RGBColor.from_string(color)
    apply_table_geometry(table, [3120, 3120, 3120], table_width_dxa=9360, indent_dxa=0)
    return table


def add_module_table(document, rows, status_label, status_fill, status_text):
    table = document.add_table(rows=1, cols=2)
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    table.autofit = False
    header = table.rows[0]
    repeat_table_header(header)
    for idx, title in enumerate(("Module", "Description et périmètre")):
        cell = header.cells[idx]
        set_cell_shading(cell, NAVY)
        set_cell_border(cell, NAVY, "6")
        set_cell_margins(cell, top=120, start=135, bottom=120, end=135)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        r = p.add_run(title)
        set_repeatable_font(r)
        r.font.size = Pt(10)
        r.font.bold = True
        r.font.color.rgb = RGBColor.from_string(WHITE)

    for module, description in rows:
        row = table.add_row()
        keep_row_together(row)
        module_cell, description_cell = row.cells
        for cell in row.cells:
            set_cell_border(cell)
            set_cell_margins(cell)
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER

        set_cell_shading(module_cell, status_fill)
        p = module_cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(5)
        r = p.add_run(module)
        set_repeatable_font(r)
        r.font.size = Pt(10)
        r.font.bold = True
        r.font.color.rgb = RGBColor.from_string(TEXT)

        badge = module_cell.add_paragraph()
        badge.paragraph_format.space_after = Pt(0)
        rb = badge.add_run(status_label.upper())
        set_repeatable_font(rb)
        rb.font.size = Pt(7.5)
        rb.font.bold = True
        rb.font.color.rgb = RGBColor.from_string(status_text)

        p2 = description_cell.paragraphs[0]
        p2.paragraph_format.space_after = Pt(0)
        p2.paragraph_format.line_spacing = 1.05
        r2 = p2.add_run(description)
        set_repeatable_font(r2)
        r2.font.size = Pt(9.2)
        r2.font.color.rgb = RGBColor.from_string(TEXT)

    apply_table_geometry(table, [2350, 7010], table_width_dxa=9360, indent_dxa=0)
    document.add_paragraph().paragraph_format.space_after = Pt(0)
    return table


def style_document(document):
    styles = document.styles
    normal = styles["Normal"]
    normal.font.name = "Arial"
    normal.font.size = Pt(10.5)
    normal.font.color.rgb = RGBColor.from_string(TEXT)
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.08

    title = styles["Title"]
    title.font.name = "Arial"
    title.font.size = Pt(25)
    title.font.bold = True
    title.font.color.rgb = RGBColor.from_string(NAVY)
    title.paragraph_format.space_after = Pt(8)

    subtitle = styles["Subtitle"]
    subtitle.font.name = "Arial"
    subtitle.font.size = Pt(12)
    subtitle.font.color.rgb = RGBColor.from_string(GRAY_TEXT)
    subtitle.paragraph_format.space_after = Pt(18)

    h1 = styles["Heading 1"]
    h1.font.name = "Arial"
    h1.font.size = Pt(17)
    h1.font.bold = True
    h1.font.color.rgb = RGBColor.from_string(NAVY)
    h1.paragraph_format.space_before = Pt(0)
    h1.paragraph_format.space_after = Pt(8)

    h2 = styles["Heading 2"]
    h2.font.name = "Arial"
    h2.font.size = Pt(13)
    h2.font.bold = True
    h2.font.color.rgb = RGBColor.from_string(GOLD)
    h2.paragraph_format.space_before = Pt(10)
    h2.paragraph_format.space_after = Pt(5)

    for style_name in ("Normal", "Title", "Subtitle", "Heading 1", "Heading 2"):
        style = styles[style_name]
        style._element.rPr.rFonts.set(qn("w:ascii"), "Arial")
        style._element.rPr.rFonts.set(qn("w:hAnsi"), "Arial")


def add_section_intro(document, title, count, description, color):
    heading = document.add_heading(title, level=1)
    heading.paragraph_format.keep_with_next = True
    p = document.add_paragraph()
    p.paragraph_format.space_after = Pt(10)
    r = p.add_run(f"{count} modules — ")
    set_repeatable_font(r)
    r.font.bold = True
    r.font.color.rgb = RGBColor.from_string(color)
    r2 = p.add_run(description)
    set_repeatable_font(r2)
    r2.font.color.rgb = RGBColor.from_string(TEXT)


def build_document():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    document = Document()
    style_document(document)

    section = document.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.top_margin = Inches(0.8)
    section.bottom_margin = Inches(0.75)
    section.left_margin = Inches(1)
    section.right_margin = Inches(1)
    add_header_footer(section)

    spacer = document.add_paragraph()
    spacer.paragraph_format.space_after = Pt(44)

    eyebrow = document.add_paragraph()
    eyebrow.paragraph_format.space_after = Pt(8)
    r = eyebrow.add_run("RAPPORT DE PILOTAGE FONCTIONNEL")
    set_repeatable_font(r)
    r.font.size = Pt(9)
    r.font.bold = True
    r.font.color.rgb = RGBColor.from_string(GOLD)

    document.add_paragraph("État des modules", style="Title")
    document.add_paragraph(
        "Modules réalisés, partiellement réalisés et pas encore développés",
        style="Subtitle",
    )

    intro = document.add_paragraph()
    intro.paragraph_format.space_after = Pt(18)
    intro.paragraph_format.line_spacing = 1.15
    rr = intro.add_run(
        "Ce document présente l’état fonctionnel de HSI Family Office au 20 septembre 2026. "
        "Il décrit le périmètre livré, les fonctions encore incomplètes et les domaines prévus dans la feuille de route."
    )
    set_repeatable_font(rr)
    rr.font.size = Pt(11)
    rr.font.color.rgb = RGBColor.from_string(TEXT)

    add_status_table(document)

    note = document.add_paragraph()
    note.paragraph_format.space_before = Pt(16)
    note.paragraph_format.space_after = Pt(8)
    rn = note.add_run("Lecture du rapport")
    set_repeatable_font(rn)
    rn.font.bold = True
    rn.font.color.rgb = RGBColor.from_string(NAVY)

    for text in (
        "Réalisé : fonctionnalité disponible et couverte par les routes, écrans ou services actuels.",
        "Partiel : fondation présente, mais périmètre fonctionnel ou technique restant à compléter.",
        "À développer : aucun module métier complet n’est encore disponible pour ce domaine.",
    ):
        p = document.add_paragraph(text, style="List Bullet")
        p.paragraph_format.left_indent = Inches(0.25)
        p.paragraph_format.first_line_indent = Inches(-0.15)
        p.paragraph_format.space_after = Pt(6)

    document.add_paragraph("Document préparé à partir du code, des routes et de la feuille de route HSI.")

    document.add_page_break()
    add_section_intro(
        document,
        "1. Modules réalisés",
        len(completed),
        "Fonctionnalités actuellement disponibles et opérationnelles.",
        GREEN_TEXT,
    )
    add_module_table(document, completed, "Réalisé", GREEN, GREEN_TEXT)

    document.add_page_break()
    add_section_intro(
        document,
        "2. Modules partiellement réalisés",
        len(partial),
        "Fondations disponibles, avec des compléments fonctionnels ou techniques à planifier.",
        AMBER_TEXT,
    )
    add_module_table(document, partial, "Partiel", AMBER, AMBER_TEXT)

    document.add_page_break()
    add_section_intro(
        document,
        "3. Modules pas encore développés",
        len(not_started),
        "Domaines inscrits dans la cible fonctionnelle mais non encore livrés.",
        GRAY_TEXT,
    )
    add_module_table(document, not_started, "À développer", GRAY, GRAY_TEXT)

    document.add_heading("Priorité recommandée", level=2)
    priority = document.add_paragraph()
    priority.paragraph_format.space_after = Pt(0)
    rp = priority.add_run(
        "Finaliser d’abord le ledger et l’intégrité financière, puis le suivi d’exécution des projets, "
        "les votes v2 et les notifications. Ces fondations sécurisent ensuite l’ajout des investissements, "
        "prêts, dispositifs de solidarité et tontines."
    )
    set_repeatable_font(rp)

    document.core_properties.title = "HSI Family Office — État des modules"
    document.core_properties.subject = "État fonctionnel des modules réalisés, partiels et à développer"
    document.core_properties.author = "HSI Family Office"
    document.core_properties.keywords = "HSI, modules, roadmap, état fonctionnel"
    document.save(OUTPUT_PATH)
    print(OUTPUT_PATH)


if __name__ == "__main__":
    build_document()
