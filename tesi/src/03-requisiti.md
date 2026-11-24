# Analisi dei requisiti

La fase di ideazione si è chiusa con un elenco di requisiti che ho usato come riferimento per tutto lo sviluppo. Li riporto qui distinguendo, come si fa nell'ingegneria del software, tra requisiti funzionali, che dicono che cosa il sistema deve fare, e requisiti non funzionali, che dicono come deve farlo [@sommerville2015].

## Attori del sistema

Gli attori che interagiscono con la piattaforma sono tre.

Il **visitatore** è chi non ha ancora un account. Può soltanto registrarsi o accedere: senza un account non si vede niente di niente. È una scelta voluta, legata alla presenza di dati di posizione, sulla quale torno più avanti.

L'**utente registrato** è l'attore principale. Costruisce il proprio catalogo, scorre il feed, cerca libri e persone, apre trattative di prestito e conversa con gli altri utenti. Nel corso di uno scambio lo stesso utente può trovarsi in due ruoli diversi: quello del *richiedente*, che vuole un libro, e quello del *proprietario*, che lo possiede fisicamente.

L'**amministratore** è un utente registrato con un ruolo aggiuntivo, che gli dà accesso a una dashboard da cui seguire l'andamento della piattaforma: libri catalogati, utenti iscritti, recensioni scritte e richieste di prestito, con le classifiche dei titoli più apprezzati.

## Requisiti funzionali

Sul piano funzionale i requisiti che ho individuato sono riassunti nella tabella 3.1. Ogni requisito ha un identificativo, che riprendo nei capitoli successivi quando descrivo come è stato soddisfatto.

: Tabella 3.1 – Requisiti funzionali.

| ID | Requisito | Descrizione |
|:----|:----------------------|:------------------------------------------------|
| RF1 | Registrazione | Creazione dell'account con email, username e password; scelta della posizione su mappa; consenso al trattamento dei dati. |
| RF2 | Onboarding | Al primo accesso l'utente sceglie i generi letterari preferiti, così che il feed sia personalizzato fin da subito. |
| RF3 | Gestione del profilo | Nome visualizzato, biografia, avatar; elenco dei libri letti, posseduti e consigliati; contatori di follower e seguiti. |
| RF4 | Inserimento dei libri | Aggiunta di un titolo al catalogo a partire dall'ISBN, con recupero automatico dei metadati e possibilità di caricare copertina e foto. |
| RF5 | Feed personalizzato | Elenco a scorrimento infinito dei libri, con priorità ai generi preferiti e senza ripetizioni; sezione separata per i libri degli utenti seguiti. |
| RF6 | Interazioni sociali | Like, «ho letto», «lo possiedo», recensione da una a cinque stelle, follow tra utenti. |
| RF7 | Ricerca testuale | Ricerca di libri nel catalogo per titolo, autore o ISBN. |
| RF8 | Ricerca geografica | Dato un titolo, elenco degli utenti che lo possiedono entro un raggio configurabile, ordinati per distanza e mostrati su mappa. |
| RF9 | Prestito con negoziazione | Offerta in Culture Points, controproposta, accettazione, rifiuto, annullamento e doppia conferma dello scambio fisico. |
| RF10 | Chat in tempo reale | Conversazioni private tra due utenti con consegna immediata dei messaggi e degli eventi della trattativa. |
| RF11 | Culture Points | Accredito di punti a chi inserisce libri; deposito, rimborso e trasferimento dei punti lungo il ciclo di vita del prestito. |
| RF12 | Dashboard amministrativa | Indicatori globali e classifiche dei libri, accessibili solo agli amministratori. |

## Requisiti non funzionali

Sul piano non funzionale mi sono concentrato su tre aspetti in particolare, ai quali se ne aggiungono altri due che riguardano più il progetto nel suo insieme che l'esperienza dell'utente.

**Prestazioni del feed (RNF1).** Il feed è la schermata che l'utente apre più spesso, e uno scorrimento che si inceppa vanifica l'intera impostazione dell'interfaccia. Il requisito è che il numero di interrogazioni al database non cresca con il numero di libri mostrati e che lo scorrimento non presenti pause percepibili tra un blocco di contenuti e il successivo.

**Riservatezza dei dati di geolocalizzazione (RNF2).** La posizione di una persona è un dato personale ai sensi del Regolamento generale sulla protezione dei dati [@gdpr2016] e, associata a un elenco di oggetti posseduti, diventa un'informazione delicata. Ho scelto di rendere i dati di posizione visibili solo a chi ha fatto l'accesso, di raccogliere un consenso esplicito al momento della registrazione e di conservarne la data.

**Usabilità da telefono (RNF3).** Il target usa applicazioni di questo tipo soprattutto dallo smartphone. L'interfaccia deve quindi adattarsi agli schermi piccoli, avere bersagli di tocco ampi e una navigazione che non abbia bisogno di spiegazioni. Nielsen indica tra le componenti dell'usabilità la facilità di apprendimento e l'efficienza d'uso [@nielsen1993]: ho cercato di ottenerle riproponendo schemi di interazione che l'utente conosce già.

**Riproducibilità dell'ambiente (RNF4).** Chiunque deve poter tirare su la propria istanza dell'applicazione in locale con un comando solo, senza installare a mano PHP, il database o gli altri servizi.

**Sicurezza di base (RNF5).** Password conservate solo in forma di hash, protezione dei form contro le richieste falsificate (CSRF), controllo che ogni operazione su una conversazione o su una trattativa sia eseguita da uno dei due partecipanti.

## Casi d'uso principali

Per chiarire come i requisiti si combinano tra loro descrivo i tre percorsi che un utente compie più spesso. Sono anche i percorsi su cui ho svolto le prove finali dell'intero flusso.

**Dall'iscrizione al primo feed.** Il visitatore compila il modulo di registrazione, indica la propria posizione cercando un indirizzo o toccando un punto sulla mappa e accetta l'informativa. Dopo il primo accesso viene portato alla schermata di onboarding, dove sceglie i generi che preferisce; solo a quel punto arriva al feed, che risulta così personalizzato già alla prima apertura.

**Aggiungere un libro al catalogo.** L'utente apre il modulo di inserimento e digita l'ISBN. Il sistema controlla se il titolo è già in catalogo; se non lo è, interroga le fonti esterne e riempie da solo i campi con quello che trova. L'utente sceglie i generi, eventualmente carica una copertina e qualche foto della propria copia, e conferma. Il libro entra nel catalogo condiviso e chi lo ha inserito riceve dieci Culture Points.

**Prendere in prestito un libro.** L'utente cerca un titolo nella sezione dedicata al prestito e vede, in elenco e su mappa, chi lo possiede nelle vicinanze. Sceglie un proprietario e gli fa un'offerta in Culture Points: si apre una conversazione in cui l'offerta compare come primo messaggio. Il proprietario può accettare, rifiutare o rilanciare. Raggiunto l'accordo, i due si scrivono per decidere dove e quando incontrarsi; a consegna avvenuta ciascuno conferma lo scambio, e solo dopo la seconda conferma i punti passano dal richiedente al proprietario.

La figura 3.1 riassume i passaggi dell'ultimo caso d'uso dal punto di vista degli stati che la richiesta attraversa; il meccanismo è descritto in dettaglio nel capitolo 5.

![Figura 3.1 – Ciclo di vita di una richiesta di prestito.](figure/d-stati.png){width=15cm}
