#!/usr/bin/env bash
# Ricostruisce la tesi (Word + PDF) a partire dai sorgenti Markdown in tesi/src.
# Requisiti: pandoc, LibreOffice, python3 con matplotlib e python-docx, node con Playwright.
set -euo pipefail
cd "$(dirname "$0")/.."            # cartella tesi/

if [[ "${1:-}" == "--figure" ]]; then
  python3 build/grafici.py         # grafici (matplotlib)
  node build/diagrammi.js          # diagrammi (HTML/SVG -> PNG)
fi

python3 build/riferimento.py       # stili di pagina

pandoc src/0*.md src/1*.md \
  --from markdown+smart \
  --to docx \
  --reference-doc build/riferimento.docx \
  --resource-path . \
  --number-sections \
  --lua-filter build/tabelle.lua \
  --citeproc --bibliography src/bibliografia.bib \
  --metadata lang=it-IT \
  --metadata link-citations=true \
  --metadata reference-section-title="" \
  --highlight-style monochrome \
  --output build/bozza.docx

/usr/bin/python3 build/finalizza.py build/bozza.docx \
  Tesi_BookForEveryone_AntoninoBaldanza.docx \
  Tesi_BookForEveryone_AntoninoBaldanza.pdf
rm -f build/bozza.docx
