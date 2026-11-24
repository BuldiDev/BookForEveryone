"""Genera i grafici della tesi (cartella tesi/figure) a partire da dati reali del repository.

Uso:  python3 tesi/build/grafici.py   (dalla radice del repository)
"""
import glob
import os
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt

OUT = Path(__file__).resolve().parent.parent / "figure"
os.chdir(Path(__file__).resolve().parents[2])  # radice del repository
BLU, ARANCIO, VERDE, GIALLO = "#2a78d6", "#eb6834", "#1baf7a", "#eda100"
INK, MUTED, GRID = "#0b0b0b", "#52514e", "#e4e3df"

plt.rcParams.update({
    "font.family": "DejaVu Sans", "font.size": 10, "axes.edgecolor": MUTED,
    "axes.labelcolor": MUTED, "xtick.color": MUTED, "ytick.color": MUTED,
    "axes.spines.top": False, "axes.spines.right": False, "axes.linewidth": 0.8,
    "figure.dpi": 100, "savefig.dpi": 300, "savefig.bbox": "tight",
})


def base(figsize):
    fig, ax = plt.subplots(figsize=figsize)
    ax.set_axisbelow(True)
    return fig, ax


def righe(pattern):
    return sum(sum(1 for _ in open(f, encoding="utf-8", errors="ignore")) for f in glob.glob(pattern))


# 1. Composizione del codice sorgente -------------------------------------------------
voci = [
    ("Fogli di stile (CSS)", righe("public/css/*.css")),
    ("Template Twig", righe("templates/*/*.twig")),
    ("Controller", righe("src/Controller/*.php")),
    ("Entità", righe("src/Entity/*.php")),
    ("Repository", righe("src/Repository/*.php")),
    ("Comandi di popolamento", righe("src/Command/*.php")),
    ("JavaScript", righe("assets/*.js") + righe("assets/controllers/*.js")),
    ("Migrazioni", righe("migrations/*.php")),
    ("Fixture", righe("src/DataFixtures/*.php")),
    ("Form", righe("src/Form/*.php")),
    ("Servizi", righe("src/Service/*.php")),
]
voci.sort(key=lambda v: v[1])
fig, ax = base((6.8, 3.9))
bars = ax.barh([v[0] for v in voci], [v[1] for v in voci], color=BLU, height=0.62)
for b, (_, n) in zip(bars, voci):
    ax.text(n + 40, b.get_y() + b.get_height() / 2, f"{n:,}".replace(",", "."), va="center", color=INK, fontsize=9)
ax.set_xlabel("Righe di codice (commenti inclusi)")
ax.xaxis.grid(True, color=GRID, linewidth=0.8)
ax.spines["left"].set_visible(False)
ax.tick_params(axis="y", length=0, labelcolor=INK)
ax.set_xlim(0, max(v[1] for v in voci) * 1.12)
fig.savefig(OUT / "g-codice.png")
plt.close(fig)

# 3. Query per pagina di feed: modello N+1 contro caricamento in blocco ---------------
n = list(range(0, 31))
per_libro = 4   # interazione utente, voto medio, numero di like, recensione dell'utente
fisse = 2       # selezione degli ID + caricamento delle entità
n1 = [fisse + per_libro * x for x in n]
batch = [fisse + 5 for _ in n]  # 5 query aggregate indipendenti dal numero di libri
fig, ax = base((6.8, 3.4))
ax.plot(n, n1, color=ARANCIO, linewidth=2)
ax.plot(n, batch, color=BLU, linewidth=2)
ax.scatter([30, 30], [n1[-1], batch[-1]], color=[ARANCIO, BLU], s=28, zorder=3)
ax.text(29.4, n1[-1] - 3, f"una query per libro: {n1[-1]}", color=INK, ha="right", va="top", fontsize=9)
ax.text(29.4, batch[-1] + 5, f"caricamento in blocco: {batch[-1]}", color=INK, ha="right", fontsize=9)
ax.set_xlabel("Libri nel blocco restituito dal feed")
ax.set_ylabel("Query SQL eseguite")
ax.yaxis.grid(True, color=GRID, linewidth=0.8)
ax.set_xlim(0, 31)
ax.set_ylim(0, 135)
fig.savefig(OUT / "g-query.png")
plt.close(fig)

# 4. Richieste HTTP necessarie per scorrere N libri -----------------------------------
libri = list(range(1, 121))
uno = libri
blocchi = [-(-x // 30) for x in libri]
fig, ax = base((6.8, 3.4))
ax.plot(libri, uno, color=ARANCIO, linewidth=2)
ax.step(libri, blocchi, color=BLU, linewidth=2, where="post")
ax.text(118, 108, "un libro per richiesta: 120", color=INK, ha="right", fontsize=9)
ax.text(118, 10, "blocchi da 30 libri: 4", color=INK, ha="right", fontsize=9)
ax.set_xlabel("Libri scorsi dall'utente")
ax.set_ylabel("Richieste HTTP al server")
ax.yaxis.grid(True, color=GRID, linewidth=0.8)
ax.set_xlim(0, 122)
ax.set_ylim(0, 125)
fig.savefig(OUT / "g-richieste.png")
plt.close(fig)

# 5. Fasi di lavoro (diagramma di Gantt) ----------------------------------------------
fasi = [
    ("Fase 1 – Ideazione e progettazione", 0, 2),
    ("Fase 2 – Struttura di base", 2, 1),
    ("Fase 3 – Funzionalità principali", 3, 3),
    ("Fase 4 – Ottimizzazione e rifinitura", 6, 1),
]
fig, ax = base((6.8, 2.5))
for i, (nome, inizio, durata) in enumerate(fasi):
    ax.barh(i, durata, left=inizio, color=BLU, height=0.5)
    ax.text(inizio + durata + 0.1, i, f"{durata} sett.", va="center", color=INK, fontsize=9)
ax.set_yticks(range(len(fasi)))
ax.set_yticklabels([f[0] for f in fasi], color=INK)
ax.invert_yaxis()
ax.set_xticks(range(0, 8))
ax.set_xlim(0, 7.8)
ax.set_xlabel("Settimane dall'avvio del progetto")
ax.xaxis.grid(True, color=GRID, linewidth=0.8)
ax.spines["left"].set_visible(False)
ax.tick_params(axis="y", length=0)
fig.savefig(OUT / "g-fasi.png")
plt.close(fig)

# 6. Culture Points: saldo dei due utenti lungo una trattativa -------------------------
passi = ["Saldo\niniziale", "Offerta\n(deposito 1 CP)", "Contro-\nproposta", "Accetta-\nzione", "Doppia\nconferma"]
richiedente = [20, 19, 19, 19, 9]
proprietario = [20, 20, 20, 20, 30]
fig, ax = base((6.8, 3.2))
ax.step(range(5), richiedente, where="mid", color=ARANCIO, linewidth=2)
ax.step(range(5), proprietario, where="mid", color=BLU, linewidth=2)
ax.scatter(range(5), richiedente, color=ARANCIO, s=30, zorder=3)
ax.scatter(range(5), proprietario, color=BLU, s=30, zorder=3)
ax.text(4.15, 9, "richiedente: 9", color=INK, va="center", fontsize=9)
ax.text(4.15, 30, "proprietario: 30", color=INK, va="center", fontsize=9)
ax.set_xticks(range(5))
ax.set_xticklabels(passi, color=INK, fontsize=8.5)
ax.set_ylabel("Culture Points")
ax.set_ylim(0, 35)
ax.set_xlim(-0.4, 5.3)
ax.yaxis.grid(True, color=GRID, linewidth=0.8)
ax.tick_params(axis="x", length=0)
fig.savefig(OUT / "g-punti.png")
plt.close(fig)

print("Grafici generati in", OUT)
