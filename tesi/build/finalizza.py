"""Apre il .docx prodotto da pandoc con LibreOffice, aggiorna l'indice (numeri di pagina)
e salva la versione finale in .docx e in .pdf.

Uso:  /usr/bin/python3 finalizza.py <ingresso.docx> <uscita.docx> <uscita.pdf>
(serve il Python di sistema, che include il modulo «uno» di LibreOffice)
"""
import subprocess
import sys
import time
from pathlib import Path

import uno
from com.sun.star.beans import PropertyValue


def prop(nome, valore):
    p = PropertyValue()
    p.Name, p.Value = nome, valore
    return p


ingresso, uscita_docx, uscita_pdf = (Path(a).resolve() for a in sys.argv[1:4])
porta = 2002
proc = subprocess.Popen([
    "soffice", "--headless", "--invisible", "--norestore", "--nologo",
    f"--accept=socket,host=localhost,port={porta};urp;",
], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
try:
    locale = uno.getComponentContext()
    resolver = locale.ServiceManager.createInstanceWithContext("com.sun.star.bridge.UnoUrlResolver", locale)
    ctx = None
    for _ in range(60):
        try:
            ctx = resolver.resolve(f"uno:socket,host=localhost,port={porta};urp;StarOffice.ComponentContext")
            break
        except Exception:
            time.sleep(1)
    if ctx is None:
        sys.exit("LibreOffice non raggiungibile")
    desktop = ctx.ServiceManager.createInstanceWithContext("com.sun.star.frame.Desktop", ctx)
    doc = desktop.loadComponentFromURL(ingresso.as_uri(), "_blank", 0, (prop("Hidden", True),))

    indici = doc.getDocumentIndexes()
    for giro in range(2):  # due passate: i numeri di pagina si assestano dopo il primo aggiornamento
        for i in range(indici.getCount()):
            indici.getByIndex(i).update()
    doc.refresh()
    print("indici aggiornati:", indici.getCount(), "- pagine:", doc.CurrentController.PageCount)

    doc.storeToURL(uscita_docx.as_uri(), (prop("FilterName", "MS Word 2007 XML"),))
    doc.storeToURL(uscita_pdf.as_uri(), (prop("FilterName", "writer_pdf_Export"),))
    doc.close(True)
    try:
        desktop.terminate()
    except Exception:
        pass
finally:
    time.sleep(1)
    proc.terminate()
print("creati", uscita_docx.name, "e", uscita_pdf.name)
