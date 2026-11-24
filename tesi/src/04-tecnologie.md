# Tecnologie e strumenti

Lo sviluppo ha richiesto strumenti molto diversi tra loro, scelti di volta in volta in base a ciò che serviva in quel momento. In questo capitolo li presento uno per uno, spiegando che cosa fanno, perché li ho preferiti alle alternative e quali difficoltà mi hanno dato. La tabella 4.1 ne dà una visione d'insieme.

: Tabella 4.1 – Stack tecnologico del progetto.

| Livello | Tecnologia | Ruolo nel progetto |
|:------------------|:---------------------|:--------------------------------------|
| Linguaggio e framework | PHP 8.4, Symfony 8.0 | Logica applicativa, routing, sicurezza, form |
| Accesso ai dati | Doctrine ORM 3 | Mappatura tra classi PHP e tabelle |
| Database | MariaDB 11.8.3 | Persistenza relazionale |
| Archivio in memoria | Redis 7 | Cache, cronologia del feed, coda di messaggi |
| Tempo reale | Mercure | Invio di aggiornamenti dal server al browser |
| Presentazione | Twig, Turbo, Stimulus | Template HTML e navigazione senza ricariche |
| Mappe | Leaflet, OpenStreetMap | Scelta della posizione e mappa dei prestatori |
| Fonti bibliografiche | Google Books, Open Library | Metadati e copertine a partire dall'ISBN |
| Infrastruttura | Docker, Docker Compose, nginx | Ambiente di esecuzione a container |
| Sviluppo | Visual Studio Code, Git, GitHub | Scrittura del codice e versionamento |

## Il modello client-server e il protocollo HTTP

BookForEveryone è un'applicazione web tradizionale nel senso migliore del termine: il browser invia richieste HTTP, il server risponde con pagine HTML. Il protocollo HTTP è senza stato: ogni richiesta è indipendente dalle precedenti e porta con sé tutto ciò che serve per essere elaborata [@rfc9110; @kurose2021]. La continuità tra una richiesta e l'altra, per esempio il fatto che l'utente resti autenticato, è ottenuta con un cookie di sessione.

Accanto alle pagine complete, molte interazioni usano chiamate asincrone: il browser invia una richiesta con la Fetch API e riceve in risposta un piccolo documento JSON [@rfc8259], con cui aggiorna soltanto la parte di pagina interessata. È il caso del like, del follow, della ricerca per ISBN e del caricamento dei blocchi successivi del feed. Le rotte dell'applicazione seguono le convenzioni dello stile REST per quanto riguarda l'uso dei verbi: le letture avvengono con GET, tutte le operazioni che modificano lo stato con POST [@fielding2000].

Un'eccezione al modello richiesta-risposta è la chat, dove serve che sia il server a prendere l'iniziativa. Ne parlo nel paragrafo dedicato a Mercure.

## Symfony

Il framework principale è Symfony, nella versione 8.0, abbinato a PHP 8.4 [@symfonydoc]. Si tratta di un framework PHP di livello enterprise, molto diffuso in ambito professionale, che mette a disposizione un insieme ben organizzato di componenti per gestire richieste HTTP, routing, sicurezza, form, cache e molto altro. L'ho scelto per la sua solidità architetturale, per la qualità della documentazione ufficiale e per il peso che ha nel mercato del lavoro.

Non avendolo studiato all'università, ho dovuto dedicare parte della prima fase a ripassarlo per conto mio sulla documentazione, anche se avevo già avuto modo di usarlo nelle mie esperienze lavorative. La curva di apprendimento iniziale è stata l'ostacolo più grande: Symfony introduce concetti come la *dependency injection*, il *service container* e la configurazione basata su file YAML che, per quanto ben documentati, richiedono tempo per essere assimilati. In particolare la dependency injection, il meccanismo con cui Symfony fornisce in automatico alle classi i servizi di cui hanno bisogno senza doverli istanziare a mano, ha richiesto un po' di pratica prima di diventarmi naturale.

L'esempio più semplice è la firma di un metodo di un controller. Nel frammento che segue non c'è una sola istruzione `new`: è il framework a leggere i tipi dei parametri e a passare al metodo il repository dei libri, la sessione e il servizio che gestisce la cronologia del feed.

```php
#[Route('/', name: 'app_feed')]
public function index(
    Request $request,
    BookRepository $bookRepo,
    SessionInterface $session,
    FeedHistoryService $feedHistory,
): Response {
    // ...
}
```

La stessa firma mostra un'altra caratteristica di Symfony che ho usato ovunque: le rotte dichiarate con gli attributi di PHP direttamente sopra il metodo che le gestisce. L'applicazione espone in tutto una quarantina di rotte, distribuite su undici controller; l'elenco completo è nell'appendice B.

Symfony organizza il codice secondo il pattern *Model-View-Controller*: i controller ricevono la richiesta e decidono cosa fare, il modello (entità e repository) custodisce i dati e le regole per interrogarli, le viste (i template Twig) producono l'HTML [@fowler2002]. Twig è il motore di template nativo del framework: ha una sintassi propria, più compatta di quella del PHP, e applica in automatico l'escape dell'output, il che chiude la porta alla maggior parte degli attacchi di tipo *cross-site scripting* [@twigdoc].

## Doctrine ORM

Il dialogo con il database passa da Doctrine ORM, che Symfony si porta dietro nativamente [@doctrinedoc]. Con Doctrine non serve quasi mai scrivere SQL a mano, perché il database si manovra attraverso classi PHP chiamate entità. Una classe vale una tabella, una proprietà vale una colonna e i legami tra tabelle diventano riferimenti da un oggetto all'altro. È l'applicazione del pattern che Fowler chiama *Data Mapper*: uno strato che trasferisce i dati tra oggetti e database tenendo gli uni indipendenti dall'altro [@fowler2002].

Per interrogare il database ci sono due strade: il DQL, che somiglia a SQL ma ragiona in termini di oggetti, e il QueryBuilder, con cui la query si costruisce da codice pezzo per pezzo. Quando nessuna delle due basta, per esempio per usare funzioni specifiche di MariaDB, resta sempre possibile scendere al livello sottostante ed eseguire SQL nativo attraverso la connessione.

L'ostacolo principale è stato il classico problema delle query N+1. Se non gli si dice esplicitamente di caricare le relazioni insieme all'oggetto principale, Doctrine ne lancia una separata per ogni collegamento, e con l'aumentare dei risultati il conto sale in fretta. Ne sono uscito con query native e caricamento in blocco, ma per arrivarci ho dovuto studiarmi come funziona davvero il caricamento pigro (*lazy loading*) sotto il cofano. Il problema e la sua soluzione sono descritti nel capitolo 6.

Le modifiche allo schema sono gestite con le migrazioni: ogni cambiamento alle entità genera un file con le istruzioni SQL per portare il database dalla versione precedente a quella nuova. Nel repository ce ne sono undici, e scorrerle in ordine equivale a ripercorrere l'evoluzione dello schema durante lo sviluppo.

## MariaDB

Come database relazionale ho usato MariaDB nella versione 11.8.3, un fork open source di MySQL tuttora mantenuto attivamente e pienamente compatibile con esso [@mariadbdoc]. L'ho scelto per la stabilità, per la disponibilità di un'immagine Docker ufficiale e per la piena compatibilità con Doctrine; nel contesto del progetto non mi ha dato particolari grattacapi, né in configurazione né in uso.

Il modello relazionale, introdotto da Codd [@codd1970], resta la scelta naturale per un dominio come questo, fatto di entità ben definite e di molte relazioni tra loro: utenti che possiedono libri, libri che appartengono a generi, richieste che collegano due utenti e un libro. I vincoli di integrità referenziale e di unicità mi hanno permesso di affidare al database, e non al solo codice applicativo, alcune garanzie importanti, come il fatto che la stessa persona non possa recensire due volte lo stesso titolo.

## Redis

Per la cache e per la deduplicazione del feed ho usato Redis, un archivio di strutture dati in memoria capace di leggere e scrivere con latenze molto inferiori a quelle di un database su disco [@redisdoc; @carlson2013]. Le ragioni della scelta sono due. La prima è banalmente la velocità. Il feed deve escludere al volo i libri già visti, e qualsiasi ritardo, anche minimo, l'utente lo percepisce. La seconda è che Symfony lo supporta nativamente, sia con il componente Cache sia con il componente Messenger, per cui non ho dovuto scrivere codice di raccordo per farli parlare.

Nel progetto Redis svolge tre compiti distinti:

- **cronologia del feed**: per ogni utente un insieme (*Set*) con gli identificativi dei libri che gli sono già stati mostrati;
- **cache applicativa**: risultati di interrogazioni costose o ripetute, come le statistiche dei libri, i contatori del profilo e le risposte delle API bibliografiche;
- **coda di messaggi**: trasporto del componente Messenger per le operazioni da eseguire in modo asincrono, come l'invio delle email.

Per la deduplicazione mi sono servito del Set, la struttura di Redis che tiene valori unici senza ordinarli. Ci si aggiungono elementi e si chiede se un certo elemento è già dentro, e la risposta arriva in tempo costante anche quando il Set è enorme. La difficoltà qui è stata la scadenza dei dati: i Set non permettono di impostare una scadenza sui singoli elementi, ma solo sull'intero insieme. Ho quindi progettato la deduplicazione tenendo conto di questo vincolo, applicando la scadenza di trenta giorni all'intero insieme associato a ciascun utente.

## Mercure e i Server-Sent Events

La chat in tempo reale poggia su Mercure, un protocollo aperto costruito sui Server-Sent Events con cui il server spinge gli aggiornamenti verso il browser lungo una connessione HTTP persistente e a senso unico [@mercurespec; @whatwgsse]. Il browser apre la connessione una volta sola, con l'oggetto `EventSource`, e da quel momento riceve gli eventi man mano che il server li pubblica, senza dover chiedere ogni due secondi se è arrivato qualcosa.

L'alternativa naturale sarebbe stata WebSocket, che però apre un canale bidirezionale e porta con sé una complessità di cui qui non avevo bisogno: per inviare un messaggio basta una normale richiesta POST, quello che serve in più è soltanto la consegna dal server al client. I Server-Sent Events viaggiano su una normalissima connessione HTTP, si configurano con poco, si riconnettono da soli se la connessione cade e non danno fastidio né ai proxy né ai load balancer.

Mercure aggiunge due cose sue. La prima è l'organizzazione per *topic*: chi pubblica indica un argomento, chi ascolta si iscrive agli argomenti che gli interessano, e in mezzo c'è un *hub* che smista. La seconda è l'autorizzazione tramite JSON Web Token [@rfc7519]: per pubblicare un aggiornamento l'applicazione deve presentare all'hub un token firmato con una chiave che Symfony e il container Mercure hanno in comune. Far tornare i conti tra la generazione dei token da un lato e la loro validazione dall'altro è stata la parte più ostica, e mi ha richiesto parecchia attenzione nella configurazione di entrambi.

In Symfony tutta la configurazione dell'hub sta in poche righe; la chiave non è scritta nel file ma letta da una variabile d'ambiente [@symfonymercure].

```yaml
mercure:
    hubs:
        default:
            url: '%env(default::MERCURE_URL)%'
            public_url: '%env(default::MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: '*'
```

## Hotwire: Turbo e Stimulus

Per la navigazione fluida e gli aggiornamenti parziali della pagina ho usato Hotwire, composto da due moduli distinti, Turbo e Stimulus [@turbodoc; @stimulusdoc]. Quello che si vede a schermo è che la pagina non si ricarica mai per intero, e rispetto a un'applicazione tradizionale la differenza si nota subito. Dietro c'è Turbo, che intercetta il passaggio da una pagina all'altra e lo converte in una richiesta asincrona all'insaputa dell'utente. Quando invece basta toccare un singolo pezzo di pagina entrano in gioco i Turbo Frames e i Turbo Streams, che aggiornano quel pezzo e nient'altro.

Un Turbo Stream è un frammento di HTML racchiuso in un elemento `<turbo-stream>` che dichiara un'azione (aggiungere in coda, sostituire, rimuovere) e l'identificativo dell'elemento su cui eseguirla. È il formato che ho usato per la chat: perché un messaggio possa essere applicato direttamente al DOM, Mercure deve spedirlo già impacchettato come frammento HTML nel formato che Turbo Streams si aspetta, e per ottenerlo ho scritto template Twig che non fanno altro che generare questi aggiornamenti parziali.

Il lavoro di Stimulus è un altro. Copre il lato JavaScript e lo fa in modo volutamente scarno, senza costruire niente di elaborato: si scrivono controller piccoli e li si aggancia agli elementi del DOM con dei semplici attributi HTML.

Con Turbo la difficoltà principale è stata il comportamento dei form: Turbo intercetta in automatico il loro invio trasformandolo in una richiesta asincrona, e questo mi ha costretto ad adattare i controller di Symfony perché rispondessero correttamente sia alle richieste tradizionali sia a quelle intercettate, gestendo la protezione CSRF in modo compatibile con questo meccanismo. Una seconda conseguenza riguarda il JavaScript: dato che la pagina non viene mai ricaricata davvero, il codice di inizializzazione non può essere legato al solo evento di caricamento del documento, ma va rieseguito a ogni evento `turbo:load`, avendo cura di rimuovere prima i gestori registrati nella navigazione precedente.

## Leaflet e OpenStreetMap

Le mappe sono realizzate con Leaflet, una libreria JavaScript open source leggera e pensata per i dispositivi mobili [@leafletdoc], con la cartografia di OpenStreetMap come sfondo [@osm]. Le uso in due punti: nel modulo di registrazione, dove l'utente indica la propria posizione cercando un indirizzo o toccando un punto sulla mappa, e nella sezione del prestito, dove i proprietari di un libro compaiono come segnaposto attorno alla posizione di chi sta cercando. La ricerca dell'indirizzo si appoggia a Nominatim, il servizio di geocodifica di OpenStreetMap, che trasforma un testo in una coppia di coordinate.

Ho preferito questa combinazione ai servizi cartografici commerciali perché non richiede chiavi di accesso né un account di fatturazione, e chi installa il progetto in locale non deve configurare nulla.

## Le API bibliografiche

Il recupero automatico dei dati bibliografici a partire dall'ISBN si appoggia a due API esterne. La prima a essere interrogata è Google Books API [@googlebooks], che ha il catalogo più ampio e nella stragrande maggioranza dei casi risponde con titolo, autore, descrizione, genere e copertina. Se però quello che torna è parziale, o non torna proprio niente, subentra Open Library API [@openlibrary], mantenuta dall'Internet Archive.

L'ISBN è il punto di partenza ideale per questa ricerca: è un identificativo normato a livello internazionale, assegnato a ogni edizione di un libro pubblicato [@iso2108], e questo lo rende lo strumento giusto sia per evitare i duplicati in catalogo sia per interrogare le fonti esterne senza ambiguità.

Il problema vero non è stato collegare le due API, ma la qualità altalenante di quello che restituiscono. Capitava di ricevere libri senza copertina, senza descrizione, oppure con i campi riempiti in maniera del tutto disomogenea. Ho dovuto perciò validare la risposta prima di riversarla nel form, ed è proprio da qui che è nata la decisione di lasciare all'utente la possibilità di completare a mano quello che manca.

## Docker e Docker Compose

Per containerizzare l'ambiente di sviluppo ho usato Docker, con Docker Compose per orchestrare i servizi [@dockerdoc; @composedoc]. Docker permette di descrivere l'intero ambiente di esecuzione come un insieme di container isolati, ciascuno responsabile di un singolo servizio, garantendo che il progetto giri allo stesso modo su qualsiasi macchina, indipendentemente dal sistema operativo o da ciò che è installato localmente [@merkel2014].

In questo progetto i container necessari erano sei: PHP-FPM per il codice applicativo, nginx come web server, MariaDB per il database, Redis per la cache e la deduplicazione del feed, Mercure per la comunicazione in tempo reale e un container dedicato esclusivamente a far girare il consumer della coda di messaggi di Symfony. Ho scelto Docker proprio perché mi permetteva di gestire tanti servizi interdipendenti senza installarli a mano e di replicare l'ambiente altrove senza configurazioni aggiuntive. La composizione dei servizi e le difficoltà che ho incontrato, che sono state tra le più impegnative di tutto il progetto, sono descritte nel capitolo 7.

## Ambiente di sviluppo e versionamento

Come ambiente di sviluppo ho usato Visual Studio Code, l'editor gratuito e open source di Microsoft [@vscode]. L'ho preferito ad altri per tre motivi: pesa molto meno di alternative come PhpStorm, ha un catalogo di estensioni per lo sviluppo web praticamente sterminato e soprattutto lo uso già tutti i giorni nel mio lavoro da sviluppatore fullstack. Le estensioni che ho aggiunto sono poche ma mirate. PHP Intelephense si occupa di autocompletamento, analisi statica e navigazione tra le classi PHP. Twig Language mi serviva per la sintassi dei template di Symfony. L'estensione Docker, infine, mi permetteva di controllare lo stato dei container senza uscire dall'editor. Avere un terminale integrato è stato molto comodo, perché mi ha permesso di lanciare i comandi di Symfony, le migrazioni di Doctrine e i comandi Docker senza saltare di continuo da una finestra all'altra.

La difficoltà più fastidiosa con l'editor è stata far funzionare l'autocompletamento PHP in un ambiente containerizzato: PHP non era installato sulla mia macchina ma solo dentro il container Docker, e così Intelephense non riusciva ad analizzare la cartella `vendor`, quella con tutte le dipendenze installate da Composer, lasciandomi senza completamento per le classi di Symfony e Doctrine. Alla fine ho puntato Intelephense su una copia locale di `vendor`, in modo che l'editor potesse leggere i file anche se poi vengono eseguiti dentro il container.

Il versionamento l'ho affidato a Git, con GitHub come hosting remoto [@chacon2014]. Git conserva tutta la storia delle modifiche. Ogni commit fotografa il progetto in un dato momento e si porta dietro un messaggio che spiega cosa è cambiato. Nel corso dello sviluppo mi è servito su due fronti. Il primo era poter tornare indietro senza pensieri, perché quando una modifica andava male recuperare la versione buona era questione di un comando. Il secondo l'ho scoperto strada facendo ed è la tracciabilità, che nella caccia ai bug vale molto più di quanto immaginassi. Confrontavo il codice prima e dopo un commit e nella maggior parte dei casi il punto in cui avevo sbagliato saltava fuori da solo; rileggendo i vecchi messaggi, invece, mi tornavano in mente scelte fatte settimane prima e ormai dimenticate. GitHub ci ha messo sopra il backup remoto, che mi ha reso il codice raggiungibile da qualunque macchina indipendentemente da cosa succedesse al computer di casa.

Una difficoltà iniziale è stata la gestione dei file sensibili: i file di configurazione di Symfony contengono variabili d'ambiente come le chiavi JWT di Mercure e le credenziali del database, che non devono mai finire su un repository pubblico. Ho quindi configurato il `.gitignore` per escludere dal tracciamento i file `.env.local`, destinati ai valori specifici di ciascun ambiente, lasciando nel repository soltanto i file `.env` con i valori predefiniti per lo sviluppo in locale.

## Documentazione e fonti di riferimento

Per tutta la durata dello sviluppo la mia fonte di riferimento è stata la documentazione ufficiale, quella di ogni singolo framework e di ogni libreria, che aprivo prima di mettere mano a qualsiasi funzionalità. Trattandosi di materiale tecnico interamente in inglese, saper leggere e comprendere l'inglese scritto si è rivelato una competenza trasversale indispensabile. Per i problemi più specifici, non coperti dalla documentazione, mi sono affidato a Stack Overflow e alle discussioni aperte nei repository GitHub ufficiali, dove nove volte su dieci qualcuno si era già scontrato con lo stesso identico problema e l'aveva risolto.
