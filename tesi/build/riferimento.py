"""Crea tesi/build/riferimento.docx: il documento di stile usato da pandoc.

Parte dal modello predefinito di pandoc e imposta formato A4, margini per la
rilegatura, carattere, interlinea, stili dei titoli, del codice, delle tabelle,
del frontespizio e la numerazione delle pagine a piè di pagina.
"""
import subprocess
import sys
from pathlib import Path

from docx import Document
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

QUI = Path(__file__).resolve().parent
OUT = QUI / "riferimento.docx"
SERIF, MONO = "Times New Roman", "Courier New"
NERO = RGBColor(0, 0, 0)

OUT.write_bytes(subprocess.run(
    ["pandoc", "--print-default-data-file", "reference.docx"], capture_output=True, check=True).stdout)
doc = Document(str(OUT))


def carattere(stile, nome=SERIF, pt=None, grassetto=None, corsivo=None, colore=NERO):
    f = stile.font
    f.name = nome
    rpr = stile.element.get_or_add_rPr()
    rfonts = rpr.find(qn("w:rFonts"))
    if rfonts is None:
        rfonts = OxmlElement("w:rFonts")
        rpr.append(rfonts)
    for attr in ("w:ascii", "w:hAnsi", "w:cs", "w:eastAsia"):
        rfonts.set(qn(attr), nome)
    for attr in ("w:asciiTheme", "w:hAnsiTheme", "w:cstheme", "w:eastAsiaTheme"):
        rfonts.attrib.pop(qn(attr), None)
    if pt is not None:
        f.size = Pt(pt)
    if grassetto is not None:
        f.bold = grassetto
    if corsivo is not None:
        f.italic = corsivo
    if colore is not None:
        f.color.rgb = colore


def paragrafo(stile, allinea=None, interlinea=None, prima=None, dopo=None, rientro_sx=None,
              sporgente=None, con_successivo=None, salto_pagina=None):
    p = stile.paragraph_format
    if allinea is not None:
        p.alignment = allinea
    if interlinea is not None:
        p.line_spacing_rule = WD_LINE_SPACING.MULTIPLE
        p.line_spacing = interlinea
    if prima is not None:
        p.space_before = Pt(prima)
    if dopo is not None:
        p.space_after = Pt(dopo)
    if rientro_sx is not None:
        p.left_indent = Cm(rientro_sx)
    if sporgente is not None:
        p.first_line_indent = Cm(-sporgente)
    if con_successivo is not None:
        p.keep_with_next = con_successivo
    if salto_pagina is not None:
        p.page_break_before = salto_pagina


class Stili:
    """Accesso agli stili per nome esatto (python-docx traduce alcuni nomi predefiniti)."""

    def __getitem__(self, nome):
        for s in doc.styles:
            if s.name == nome:
                return s
        raise KeyError(nome)


stili = Stili()


def stile(nome, base="Normal"):
    try:
        return stili[nome]
    except KeyError:
        s = doc.styles.add_style(nome, WD_STYLE_TYPE.PARAGRAPH)
        s.base_style = stili[base]
        return s


G, C, S = WD_ALIGN_PARAGRAPH.JUSTIFY, WD_ALIGN_PARAGRAPH.CENTER, WD_ALIGN_PARAGRAPH.LEFT

# --- Testo -------------------------------------------------------------------------
carattere(stili["Normal"], pt=12)
for nome in ("Body Text", "First Paragraph"):
    carattere(stili[nome], pt=12)
    paragrafo(stili[nome], allinea=G, interlinea=1.5, prima=0, dopo=8)
carattere(stili["Compact"], pt=11)
paragrafo(stili["Compact"], allinea=S, interlinea=1.2, prima=2, dopo=2)
carattere(stili["Block Text"], pt=11, corsivo=True)
paragrafo(stili["Block Text"], allinea=G, interlinea=1.3, rientro_sx=1, dopo=8)
carattere(stili["Footnote Text"], pt=10)
carattere(stili["Bibliography"], pt=11)
paragrafo(stili["Bibliography"], allinea=S, interlinea=1.2, rientro_sx=0.8, sporgente=0.8, dopo=7)

carattere(stili["Hyperlink"], nome=SERIF, colore=RGBColor(0x1F, 0x3A, 0x5F))

# --- Titoli ------------------------------------------------------------------------
carattere(stili["Heading 1"], pt=20, grassetto=True)
paragrafo(stili["Heading 1"], allinea=S, interlinea=1.1, prima=0, dopo=22, con_successivo=True, salto_pagina=True)
carattere(stili["Heading 2"], pt=14, grassetto=True)
paragrafo(stili["Heading 2"], allinea=S, interlinea=1.1, prima=18, dopo=8, con_successivo=True)
carattere(stili["Heading 3"], pt=12, grassetto=True, corsivo=True)
paragrafo(stili["Heading 3"], allinea=S, interlinea=1.1, prima=12, dopo=6, con_successivo=True)

# --- Codice ------------------------------------------------------------------------
carattere(stili["Verbatim Char"], nome=MONO, pt=None, colore=None)
vrpr = stili["Verbatim Char"].element.get_or_add_rPr()
for tag in ("w:sz", "w:szCs"):          # la dimensione la decide il paragrafo
    for e in vrpr.findall(qn(tag)):
        vrpr.remove(e)
codice = stile("Source Code")
carattere(codice, nome=MONO, pt=8.5)
paragrafo(codice, allinea=S, interlinea=1.0, prima=8, dopo=12)
ppr = codice.element.get_or_add_pPr()
for vecchio in ppr.findall(qn("w:shd")) + ppr.findall(qn("w:pBdr")):
    ppr.remove(vecchio)
bordo = OxmlElement("w:pBdr")
for lato in ("top", "left", "bottom", "right"):
    e = OxmlElement(f"w:{lato}")
    for k, v in (("val", "single"), ("sz", "4"), ("space", "4"), ("color", "C9C8C2")):
        e.set(qn(f"w:{k}"), v)
    bordo.append(e)
ppr.append(bordo)
sfondo = OxmlElement("w:shd")
for k, v in (("val", "clear"), ("color", "auto"), ("fill", "F6F5F2")):
    sfondo.set(qn(f"w:{k}"), v)
ppr.append(sfondo)

inlinea = doc.styles.add_style("CodiceInLinea", WD_STYLE_TYPE.CHARACTER)
carattere(inlinea, nome=MONO, pt=10.5, colore=None)

# --- Figure e tabelle --------------------------------------------------------------
for nome in ("Image Caption", "Table Caption", "Caption"):
    carattere(stili[nome], pt=10, corsivo=True)
    paragrafo(stili[nome], allinea=C, interlinea=1.1, prima=4, dopo=12)
paragrafo(stili["Table Caption"], con_successivo=True, prima=10, dopo=4)
paragrafo(stili["Captioned Figure"], allinea=C, prima=10, dopo=0, con_successivo=True)
paragrafo(stili["Figure"], allinea=C)

tab = stili["Table"].element
tblpr = tab.find(qn("w:tblPr"))
if tblpr is None:
    tblpr = OxmlElement("w:tblPr")
    tab.append(tblpr)
for vecchio in tblpr.findall(qn("w:tblBorders")) + tblpr.findall(qn("w:tblCellMar")):
    tblpr.remove(vecchio)
bordi = OxmlElement("w:tblBorders")
for lato, sz in (("top", "8"), ("bottom", "8"), ("insideH", "4")):
    e = OxmlElement(f"w:{lato}")
    for k, v in (("val", "single"), ("sz", sz), ("space", "0"), ("color", "52514E")):
        e.set(qn(f"w:{k}"), v)
    bordi.append(e)
tblpr.append(bordi)
margini = OxmlElement("w:tblCellMar")
for lato, w in (("top", "50"), ("left", "90"), ("bottom", "50"), ("right", "90")):
    e = OxmlElement(f"w:{lato}")
    e.set(qn("w:w"), w)
    e.set(qn("w:type"), "dxa")
    margini.append(e)
tblpr.append(margini)

# --- Frontespizio e indice ---------------------------------------------------------
front = {
    "FrontLogo": dict(pt=12, dopo=8),
    "FrontAteneo": dict(pt=18, grassetto=True, dopo=18),
    "FrontCorso": dict(pt=13, dopo=2),
    "FrontTipo": dict(pt=13, grassetto=True, prima=34, dopo=24),
    "FrontTitolo": dict(pt=21, grassetto=True, dopo=14),
    "FrontSotto": dict(pt=12, corsivo=True, dopo=44),
    "FrontNomi": dict(pt=13, dopo=12),
    "FrontAnno": dict(pt=13, grassetto=True, prima=36, dopo=0),
}
for nome, o in front.items():
    s = stile(nome)
    carattere(s, pt=o["pt"], grassetto=o.get("grassetto", False), corsivo=o.get("corsivo", False))
    paragrafo(s, allinea=C, interlinea=1.25, prima=o.get("prima", 0), dopo=o["dopo"])
s = stile("Formula")
carattere(s, pt=12)
paragrafo(s, allinea=C, interlinea=1.5, prima=6, dopo=12)
s = stile("TitoloIndice")
carattere(s, pt=20, grassetto=True)
paragrafo(s, allinea=S, prima=0, dopo=22, salto_pagina=True)
for nome, rientro in (("TOC 1", 0), ("TOC 2", 0.6), ("toc 1", 0), ("toc 2", 0.6)):
    try:
        carattere(stili[nome], pt=11.5, grassetto=nome.endswith("1"))
        paragrafo(stili[nome], interlinea=1.15, prima=5 if nome.endswith("1") else 0, dopo=2, rientro_sx=rientro)
    except KeyError:
        pass

# --- Pagina: A4, margini con lato di rilegatura, numero di pagina -------------------
sez = doc.sections[0]
sez.page_width, sez.page_height = Cm(21), Cm(29.7)
sez.top_margin, sez.bottom_margin = Cm(3), Cm(3)
sez.left_margin, sez.right_margin = Cm(3), Cm(2.5)
sez.footer_distance = Cm(1.4)
sez.different_first_page_header_footer = True  # nessun numero sul frontespizio
pie = sez.footer.paragraphs[0]
pie.alignment = C
for tipo, testo in (("begin", None), (None, " PAGE "), ("end", None)):
    r = pie.add_run()
    r.font.name, r.font.size = SERIF, Pt(11)
    if tipo:
        fc = OxmlElement("w:fldChar")
        fc.set(qn("w:fldCharType"), tipo)
        r._r.append(fc)
    else:
        it = OxmlElement("w:instrText")
        it.set(qn("xml:space"), "preserve")
        it.text = testo
        r._r.append(it)

doc.save(str(OUT))
print("creato", OUT)
