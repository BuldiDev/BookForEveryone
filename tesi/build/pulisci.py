"""Toglie dal .docx la riga «<?php» aggiunta ai blocchi PHP per attivare la colorazione."""
import re
import shutil
import sys
import zipfile

percorso = sys.argv[1]
tmp = percorso + ".tmp"
modello = re.compile(r'<w:r>(?:<w:rPr><w:rStyle w:val="\w+" ?/></w:rPr>)?<w:t[^>]*>&lt;\?php</w:t></w:r><w:r><w:br ?/></w:r>')
with zipfile.ZipFile(percorso) as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
    for voce in zin.infolist():
        dati = zin.read(voce.filename)
        if voce.filename == "word/document.xml":
            testo, n = modello.subn("", dati.decode("utf-8"))
            dati = testo.encode("utf-8")
            print("righe <?php rimosse:", n)
        zout.writestr(voce, dati)
shutil.move(tmp, percorso)
