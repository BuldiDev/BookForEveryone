# Tesi – versione estesa per la stampa

- `Tesi_BookForEveryone_AntoninoBaldanza.pdf` – versione pronta per la stampa (A4, margine di rilegatura a sinistra).
- `Tesi_BookForEveryone_AntoninoBaldanza.docx` – stessa versione modificabile in Word/LibreOffice.
- `src/` – testo in Markdown, un file per capitolo, e bibliografia (`bibliografia.bib`).
- `figure/` – schermate, diagrammi e grafici.
- `build/` – script che rigenerano figure e documento.

Per ricostruire il documento dopo una modifica ai sorgenti:

```bash
./tesi/build/build.sh            # solo il documento
./tesi/build/build.sh --figure   # rigenera anche grafici e diagrammi
```

Servono pandoc, LibreOffice, Python 3 (matplotlib, python-docx) e Node con Playwright.
