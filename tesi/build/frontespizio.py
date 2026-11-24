"""Crea il frontespizio/copertina su pagina singola (A4) in Word, pronto da inviare in copisteria.

Uso:  python3 tesi/build/frontespizio.py   poi   soffice --headless --convert-to pdf ...
Modificare i valori qui sotto (relatore, anno accademico) e rigenerare.
"""
from pathlib import Path

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

ATENEO = "UNIVERSITÀ TELEMATICA PEGASO"
CORSO = ["CORSO DI LAUREA IN", "INFORMATICA PER LE AZIENDE DIGITALI", "(CLASSE L-31)"]
TITOLO = "BookForEveryone: sviluppo di una piattaforma social per il prestito geolocalizzato di libri fisici"
RELATORE = ["Chiar.mo Prof.", "[Nome Cognome]"]
CANDIDATO = ["Antonino Baldanza", "matricola n. 0312300739"]
ANNO = "ANNO ACCADEMICO [2025/2026]"

TESI = Path(__file__).resolve().parent.parent
FONT = "Arial"
doc = Document()
sez = doc.sections[0]
sez.page_width, sez.page_height = Cm(21), Cm(29.7)
sez.top_margin = sez.bottom_margin = Cm(3)
sez.left_margin = sez.right_margin = Cm(3)


def riga(par, testo, pt, grassetto=False, a_capo=False):
    r = par.add_run(testo)
    r.font.name, r.font.size, r.font.bold = FONT, Pt(pt), grassetto
    r.font.color.rgb = RGBColor(0, 0, 0)
    r._r.get_or_add_rPr().get_or_add_rFonts().set(qn("w:cs"), FONT)
    if a_capo:
        r.add_break()


def formato(par, allinea, prima=0, dopo=0, interlinea=1.15):
    f = par.paragraph_format
    par.alignment, f.space_before, f.space_after, f.line_spacing = allinea, Pt(prima), Pt(dopo), interlinea


def tabella():
    t = doc.add_table(rows=1, cols=2)
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.autofit = False
    for c in t.rows[0].cells:
        c.width = Cm(7.5)
    return t.rows[0].cells


S, C, D = WD_ALIGN_PARAGRAPH.LEFT, WD_ALIGN_PARAGRAPH.CENTER, WD_ALIGN_PARAGRAPH.RIGHT

# In alto: logo a sinistra, ateneo e corso a destra
sx, dx = tabella()
formato(sx.paragraphs[0], S)
sx.paragraphs[0].add_run().add_picture(str(TESI / "figure" / "logo-pegaso.png"), width=Cm(3.4))
dx.vertical_alignment = 1  # centrato in verticale
formato(dx.paragraphs[0], D)
riga(dx.paragraphs[0], ATENEO, 10, True, a_capo=True)
for i, t in enumerate(CORSO):
    riga(dx.paragraphs[0], t, 9, True, a_capo=i < len(CORSO) - 1)

# Al centro: titolo
p = doc.add_paragraph()
formato(p, S, prima=150, dopo=0, interlinea=1.2)
riga(p, TITOLO, 24, True)

# In basso: relatore e candidato
p = doc.add_paragraph()
formato(p, S, prima=150)
sx, dx = tabella()
formato(sx.paragraphs[0], S)
riga(sx.paragraphs[0], "Relatore", 11, True, a_capo=True)
for i, t in enumerate(RELATORE):
    riga(sx.paragraphs[0], t, 11, a_capo=i < len(RELATORE) - 1)
formato(dx.paragraphs[0], D)
riga(dx.paragraphs[0], "Candidato", 11, True, a_capo=True)
for i, t in enumerate(CANDIDATO):
    riga(dx.paragraphs[0], t, 11, a_capo=i < len(CANDIDATO) - 1)

p = doc.add_paragraph()
formato(p, C, prima=70)
riga(p, ANNO, 10, True)

out = TESI / "Frontespizio_AntoninoBaldanza.docx"
doc.save(str(out))
print("creato", out)
