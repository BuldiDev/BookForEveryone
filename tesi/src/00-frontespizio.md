::: {custom-style="FrontLogo"}
![](figure/logo-pegaso.png){width=3.6cm}
:::

::: {custom-style="FrontAteneo"}
UNIVERSITÀ TELEMATICA PEGASO
:::

::: {custom-style="FrontCorso"}
Corso di Laurea in

INFORMATICA PER LE AZIENDE DIGITALI (L-31)
:::

::: {custom-style="FrontTipo"}
ELABORATO FINALE – PROJECT WORK
:::

::: {custom-style="FrontTitolo"}
BookForEveryone: sviluppo di una piattaforma social per il prestito geolocalizzato di libri fisici
:::

::: {custom-style="FrontSotto"}
Tema n. 4 «Sharing technologies» – Traccia n. 14: sviluppo di un software di geolocalizzazione culturale per condividere il patrimonio librario degli utenti privati
:::

::: {custom-style="FrontNomi"}
**Relatore**\
[Nome e Cognome del relatore]

**Candidato**\
Antonino Baldanza\
Matricola 0312300739
:::

::: {custom-style="FrontAnno"}
Anno Accademico [2025/2026]
:::

# Sommario {.unnumbered}

Questo elaborato descrive la progettazione e lo sviluppo di *BookForEveryone*, un'applicazione web che permette a utenti privati di pubblicare la propria collezione di libri, di scoprire quelle degli altri attraverso un feed personalizzato e di prendere in prestito le copie fisiche disponibili nelle vicinanze. Il progetto risponde alla traccia «Sviluppo di un software di geolocalizzazione culturale per condividere il patrimonio librario degli utenti privati» e si colloca all'incrocio di due fenomeni: le comunità di lettori nate sui social network e l'economia della condivisione.

L'applicazione è realizzata con il framework Symfony 8.0 su PHP 8.4 e adotta un'architettura a container orchestrata con Docker Compose: un web server nginx, un interprete PHP-FPM, un database MariaDB, un archivio in memoria Redis, un hub Mercure per la comunicazione in tempo reale e un processo dedicato al consumo della coda di messaggi. Il lavoro affronta quattro problemi tecnici principali. Il primo è il feed a scorrimento infinito, reso efficiente dal caricamento in blocco dei dati e dalla deduplicazione dei contenuti tramite insiemi Redis. Il secondo è la ricerca geografica dei prestatori, basata sul calcolo della distanza ortodromica direttamente in SQL. Il terzo è la negoziazione del prestito, modellata come una macchina a stati con offerta, controproposta e doppia conferma dello scambio. Il quarto è la chat in tempo reale, costruita su Server-Sent Events e Turbo Streams.

L'elaborato ripercorre l'analisi dei requisiti, le scelte tecnologiche, la progettazione della base di dati e l'implementazione delle funzionalità, documentandole con estratti del codice sorgente, diagrammi e schermate dell'applicazione. Si chiude con una valutazione dei risultati, dei limiti attuali e dei possibili sviluppi futuri.

**Parole chiave:** sharing economy, geolocalizzazione, Symfony, Doctrine ORM, Redis, Mercure, Server-Sent Events, Docker.

::: {custom-style="TitoloIndice"}
Indice
:::

```{=openxml}
<w:p><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r><w:r><w:instrText xml:space="preserve"> TOC \o "1-2" \h \z \u </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>Aggiornare il campo per generare l'indice.</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>
```
