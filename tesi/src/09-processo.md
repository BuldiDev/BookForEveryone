# Il processo di sviluppo

Questo capitolo ricostruisce come si è svolto il lavoro: le fasi, i tempi, le difficoltà incontrate lungo la strada e le competenze del percorso di studi che mi sono servite per superarle.

## Fasi e tempi

Lo sviluppo è durato poco meno di due mesi e si è articolato in quattro fasi principali, riassunte nella figura 9.1.

![Figura 9.1 – Le quattro fasi di lavoro e la loro durata.](figure/g-fasi.png){width=14cm}

### Fase 1 – Ideazione e progettazione (circa due settimane)

La prima fase è stata dedicata all'ideazione e alla progettazione. L'idea di partenza era una piattaforma che permettesse di condividere il proprio patrimonio di libri e di prestarli ad altri utenti, con un sistema di punti, una chat in tempo reale e una ricerca basata sulla vicinanza geografica. Prima di scrivere una riga di codice ho dedicato del tempo a ripassare Symfony, il framework scelto per lo sviluppo. In parallelo ho analizzato le piattaforme già esistenti descritte nel capitolo 2, in particolare Vinted per il meccanismo di offerta e negoziazione e Instagram e YouTube per la struttura del feed. Sempre in questa fase ho disegnato lo schema del database, con le relative entità e relazioni, e definito l'architettura generale del sistema.

Prima di partire ho anche creato un repository Git su GitHub per gestire il versionamento.

### Fase 2 – Implementazione della struttura di base (circa una settimana)

Nella seconda fase ho messo in piedi l'ossatura dell'applicazione. Sono partito dalle entità principali (`User`, `Book`, `Genre`, `UserBook`) e dai controller, poi ho aggiunto la registrazione. Alla fine è arrivata la ricerca tramite ISBN, e in questa stessa fase ho aggiunto le interazioni sociali di base: mettere like a un libro, seguire altri utenti, segnare un libro come letto o come posseduto.

È stato lavorando su questa parte che ho preso la decisione che ha orientato l'intera piattaforma, quella di far costruire il catalogo agli utenti: nasce, come ho raccontato, dalla qualità insufficiente dei dati restituiti dalle API esterne.

### Fase 3 – Sviluppo delle funzionalità principali (circa tre settimane)

La terza fase è quella che mi ha portato via più tempo, ed è anche quella in cui ho scritto il grosso delle funzionalità: il feed personalizzato con le sue tre ottimizzazioni successive, la chat in tempo reale, i Culture Points e il sistema di prestito, riscritto una seconda volta dopo aver provato la prima versione. In questa fase è entrata nel progetto la maggior parte dell'infrastruttura: Redis, Mercure e la configurazione completa dei container.

### Fase 4 – Ottimizzazione, correzione dei problemi e rifinitura (circa una settimana)

La quarta e ultima fase è stata dedicata a sistemare i problemi rimasti, ottimizzare l'infrastruttura e rifinire l'interfaccia. Qui si collocano la configurazione di OPcache e la conversione dei volumi Docker descritte nel capitolo 7. La fase si è chiusa con la rifinitura grafica dell'interfaccia, la rimozione dei fogli di stile inutilizzati, l'aggiunta della dashboard amministrativa, la stesura dei commenti nel codice e una serie di prove sull'intero flusso della piattaforma, dalla registrazione fino alla conclusione di uno scambio.

## Il versionamento come metodo di lavoro

Un sistema di controllo di versione mi serviva per due motivi molto pratici. Il primo è la sicurezza. Ogni commit è uno stato stabile del progetto, e sapere di poterci tornare quando voglio leva un bel po' di ansia: qualunque modifica diventa reversibile. Il secondo motivo riguarda la comodità di spostarmi tra macchine diverse. Con il codice su un repository remoto non ho mai dovuto copiare cartelle a mano da un computer all'altro, mi bastava un *pull*.

Ho lavorato fin da subito con commit frequenti e descrittivi, uno per ogni funzionalità completata o problema risolto, così che la storia del repository rispecchiasse l'andamento del lavoro. Nelle fasi finali è servito più di quanto immaginassi: scorrendo la lista dei commit riuscivo a ricostruire senza sforzo cosa avessi fatto in una certa settimana.

I messaggi dei commit raccontano bene le svolte del progetto. Si leggono in sequenza l'introduzione dello scorrimento infinito, il rifacimento del feed con l'ottimizzazione delle query, l'integrazione di Redis, i ripensamenti sulla dimensione del blocco, l'arrivo della chat con Mercure e il rifacimento del sistema di prestiti sul modello di Vinted.

## Le difficoltà incontrate

La tabella 9.1 raccoglie in un unico posto le difficoltà principali e il modo in cui le ho superate; ciascuna è descritta in dettaglio nel capitolo indicato.

: Tabella 9.1 – Principali difficoltà incontrate e soluzioni adottate.

| Difficoltà | Soluzione | Cap. |
|:------------------------------|:------------------------------------------|:--:|
| Curva di apprendimento di Symfony (dependency injection, configurazione) | Studio della documentazione ufficiale e pratica nella prima fase | 4 |
| Dati incompleti dalle API bibliografiche | Doppia fonte con ripiego e completamento manuale da parte dell'utente | 6 |
| Feed lento per le query N+1 | Caricamento in blocco e query native aggregate | 6 |
| Feed che riproponeva gli stessi titoli | Cronologia per utente in un insieme Redis con scadenza | 6 |
| Scorrimento a scatti | Blocchi da trenta libri e precaricamento a metà blocco | 6 |
| Scadenza degli elementi nei Set di Redis | Scadenza applicata all'intero insieme dell'utente | 4 |
| Autenticazione JWT tra Symfony e Mercure | Chiave condivisa tramite variabili d'ambiente | 4, 7 |
| Stesso frammento HTML per due destinatari diversi | Decisione lato browser e segnale di ricarica per la barra delle azioni | 6 |
| Form intercettati da Turbo e protezione CSRF | Controller che rispondono a entrambe le modalità di invio | 4 |
| Prima versione del prestito troppo rigida | Riprogettazione come macchina a stati con controproposta | 5 |
| OPcache che serviva codice vecchio | Validazione dei timestamp a ogni richiesta | 7 |
| Permessi e lentezza delle cartelle condivise | Volumi nativi Docker per `vendor` e `var` | 7 |
| Autocompletamento PHP assente nell'editor | Analisi su una copia locale delle dipendenze | 4 |
| File sensibili nel repository | Esclusione dei file `.env.local` tramite `.gitignore` | 4 |

Guardandole tutte insieme, noto che poche riguardano la scrittura del codice in senso stretto. La maggior parte nasce nei punti di contatto tra componenti diversi: tra l'applicazione e il database, tra il server e il browser, tra il container e il computer che lo ospita. È stata probabilmente la lezione più utile dell'intero progetto.

## Le competenze del percorso di studi

Per realizzare questo progetto ho dovuto rimettere insieme nozioni provenienti da insegnamenti molto diversi tra loro. Data la natura dell'applicazione, le competenze più utili sono arrivate soprattutto da Tecnologie Web, Ingegneria del Software, Basi di Dati, Reti di Calcolatori e Programmazione Distribuita e Cloud Computing, mentre le altre materie del percorso hanno fatto da cornice concettuale più generale.

**Tecnologie Web** è stato probabilmente il corso più direttamente coinvolto: da lì provengono le basi sul protocollo HTTP, sulla costruzione delle pagine in HTML e CSS e sull'uso di JavaScript per rendere dinamica l'interfaccia e manipolare il DOM. Sempre da quel corso ho imparato a usare il formato JSON per lo scambio di dati tra client e server, che nel progetto ricorre praticamente ovunque, in particolare nelle chiamate asincrone gestite con la Fetch API.

**Basi di Dati** è stata fondamentale nel momento della progettazione: lo schema relazionale, la definizione delle entità e delle relazioni tra di esse nascono direttamente da quelle nozioni. Nella pratica le ho applicate soprattutto nel lavoro con Doctrine ORM, ma è stata la conoscenza dell'SQL sottostante a permettermi di riconoscere il problema delle query N+1 e di riscrivere a mano le interrogazioni quando l'ORM non bastava.

Da **Ingegneria del Software** arrivano invece i principi della programmazione orientata agli oggetti, che ho applicato in particolare nella struttura delle entità: ognuna è una classe con i propri attributi e i propri metodi. Dallo stesso insegnamento vengono l'abitudine a raccogliere i requisiti prima di scrivere il codice, la separazione delle responsabilità tra gli strati dell'applicazione e la modellazione del prestito come macchina a stati.

**Reti di Calcolatori** mi ha dato il quadro del modello client-server e del funzionamento di HTTP e HTTPS, indispensabile per capire che cosa succede davvero tra il browser, il web server e l'applicazione, e per ragionare sulle connessioni persistenti della chat.

**Programmazione Distribuita e Cloud Computing** ha fornito le basi teoriche per capire come funziona una coda di messaggi asincrona, concetto che nel progetto ho poi ritrovato implementato tramite Redis, e più in generale per ragionare su un sistema composto da servizi separati che comunicano in rete.

La tabella 9.2 mette in relazione gli insegnamenti con le parti del progetto in cui le loro nozioni sono state applicate.

: Tabella 9.2 – Insegnamenti del percorso di studi e loro applicazione nel progetto.

| Insegnamento | Nozioni utilizzate | Dove sono state applicate |
|:--------------------|:---------------------------|:-------------------------------|
| Tecnologie Web | HTTP, HTML, CSS, JavaScript, DOM, JSON | Interfaccia, chiamate asincrone, scorrimento infinito |
| Basi di Dati | Modello relazionale, schema E-R, SQL, vincoli | Schema del database, query native, vincoli di unicità |
| Ingegneria del Software | Programmazione a oggetti, requisiti, architetture | Entità, struttura MVC, macchina a stati |
| Reti di Calcolatori | Modello client-server, HTTP e HTTPS | Web server, connessioni persistenti, rete dei container |
| Programmazione Distribuita e Cloud Computing | Code di messaggi, sistemi a servizi | Coda su Redis, architettura a container |

A queste si aggiunge una competenza che non viene da un insegnamento in particolare: la capacità di imparare da soli uno strumento nuovo partendo dalla sua documentazione. Symfony, Mercure, Turbo e Redis non facevano parte del programma di nessun esame, e il metodo per affrontarli, leggere, provare, sbagliare e tornare a leggere, è forse la cosa più trasferibile che questo lavoro mi lascia.
