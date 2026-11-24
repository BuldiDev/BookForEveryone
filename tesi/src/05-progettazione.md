# Progettazione

Nella fase di progettazione ho disegnato lo schema del database, con le relative entità e relazioni, e definito l'architettura generale del sistema. Questo capitolo descrive entrambe, insieme ai due meccanismi che ho dovuto progettare a tavolino prima di poterli scrivere: la macchina a stati del prestito e le regole dei Culture Points.

## Architettura generale

L'applicazione è composta da sei servizi che collaborano all'interno di una rete virtuale privata, più il browser dell'utente e le due API bibliografiche esterne. La figura 5.1 mostra come sono collegati.

![Figura 5.1 – Architettura a container dell'applicazione.](figure/d-architettura.png){width=15cm}

Il percorso di una richiesta ordinaria è il seguente. Il browser si rivolge a **nginx**, l'unico servizio che espone la porta dell'applicazione verso l'esterno. Nginx serve direttamente i file statici (fogli di stile, script, immagini caricate) e inoltra tutto il resto, con il protocollo FastCGI, al container **php**, dove gira Symfony. Symfony elabora la richiesta interrogando **MariaDB** per i dati persistenti e **Redis** per la cache e la cronologia del feed, e restituisce una pagina HTML oppure un documento JSON.

Il percorso degli aggiornamenti in tempo reale è diverso. Quando un utente apre una conversazione, il suo browser stabilisce una seconda connessione, questa volta direttamente con l'hub **Mercure**, e resta in ascolto. Quando Symfony ha qualcosa da comunicare, per esempio un nuovo messaggio, lo pubblica sull'hub, che lo consegna a tutti i browser in ascolto su quell'argomento. Il container PHP non tiene quindi aperte connessioni di lunga durata: fa quello che sa fare meglio, cioè rispondere in fretta a richieste brevi, e delega all'hub la gestione dei canali persistenti.

Il sesto servizio, **messenger**, è costruito dalla stessa immagine del container PHP ma esegue un comando diverso: resta in ascolto sulla coda di messaggi ospitata da Redis e porta a termine le operazioni che l'applicazione ha scelto di rimandare, come l'invio delle email. Separare questo lavoro in un processo proprio significa che una richiesta web non deve mai aspettare la fine di un'operazione lenta [@kleppmann2017].

## Organizzazione del codice

All'interno del container PHP il codice segue la struttura standard di un progetto Symfony, che ricalca il pattern Model-View-Controller. La tabella 5.1 elenca le cartelle principali e la figura 5.2 dà un'idea di quanto pesa ciascuna parte in termini di righe di codice.

: Tabella 5.1 – Organizzazione del codice sorgente.

| Cartella | Contenuto | File |
|:--------------------|:-----------------------------------------------|:----:|
| `src/Entity` | Classi che rappresentano i dati del dominio | 9 |
| `src/Repository` | Interrogazioni al database, una classe per entità | 9 |
| `src/Controller` | Gestione delle richieste HTTP | 11 |
| `src/Service` | Logica riusabile: cronologia del feed, caricamento immagini | 2 |
| `src/Form` | Definizione dei moduli di registrazione e inserimento libro | 2 |
| `src/Command` | Comandi da terminale per popolare il database di prova | 6 |
| `src/DataFixtures` | Dati iniziali: generi e primo nucleo di libri | 3 |
| `templates` | Viste Twig, organizzate per area funzionale | 28 |
| `assets` | JavaScript dell'interfaccia | 3 |
| `migrations` | Evoluzione dello schema del database | 11 |

![Figura 5.2 – Composizione del codice sorgente per tipologia di file (righe, commenti inclusi).](figure/g-codice.png){width=14cm}

Ho cercato di rispettare una divisione netta delle responsabilità. I controller non contengono query: quando hanno bisogno di dati li chiedono a un repository, che è l'unico posto in cui compaiono DQL e SQL. Le entità, a loro volta, non sanno nulla del database né dell'HTTP: sono classi con i propri attributi e i propri metodi, secondo i principi della programmazione orientata agli oggetti. Dove una regola riguarda il solo stato dell'oggetto l'ho messa nell'entità stessa: è `LoanRequest`, per esempio, a sapere se un certo utente è quello che ha fatto l'ultima offerta, ed è `UserBook` a sapere come si inverte lo stato di un like.

## Il modello dei dati

Il database è costruito attorno alle entità principali del dominio, con la relazione tra utenti e libri affidata a un'entità intermedia che porta con sé attributi propri: il like, il flag di lettura e quello di possesso fisico della copia. Le entità dello schema relazionale sono nove in tutto, a cui si aggiungono quattro tabelle di collegamento per le relazioni molti-a-molti. La figura 5.3 ne riporta lo schema, nella notazione entità-relazioni introdotta da Chen [@chen1976] e adattata alla rappresentazione per tabelle [@atzeni2018].

![Figura 5.3 – Schema entità-relazioni della base di dati.](figure/d-er.png){width=15cm}

### User

La più importante è `User`. Accanto ai dati anagrafici, vale a dire username, email, password salvata come hash, nome visualizzato, bio e avatar, si porta dietro il saldo di Culture Points e alcuni flag. Il primo, `isVerified`, dice se l'email è stata confermata; il secondo, `onboardingCompleted`, segnala chi ha già scelto i generi preferiti; un terzo, `isAdmin`, abilita l'accesso alla dashboard. C'è poi `privacyConsentAt`, la data e l'ora in cui l'utente ha dato il consenso al trattamento dei dati: ho preferito registrare il momento e non un semplice sì o no, perché in questo modo resta traccia di quando il consenso è stato espresso.

Per la posizione ho preferito due campi numerici distinti, uno per la latitudine e uno per la longitudine. MariaDB offre anche tipi di dato geospaziali dedicati, ma per il tipo di interrogazione che mi serviva, una distanza da un punto, due colonne numeriche sono sufficienti, si leggono a occhio e non richiedono estensioni particolari a Doctrine.

Le relazioni molti-a-molti che coinvolgono `User` sono tre. Una va verso `Genre` passando dalla tabella `user_preferred_genre` ed è quella dei generi preferiti. Un'altra va verso `UserBook` e copre tutto ciò che riguarda le interazioni con i libri. La terza è la più curiosa, perché punta a `User` stesso tramite `user_following`: è questa autoreferenza a realizzare il follow, e mi ha evitato di dover creare un'entità apposta.

```php
/** Utenti che questo utente segue */
#[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'followers')]
#[ORM\JoinTable(name: 'user_following')]
private Collection $following;

/** Utenti che seguono questo utente */
#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'following')]
private Collection $followers;
```

### Book e le sue collezioni

`Book`, dal canto suo, tiene i metadati bibliografici del volume, quindi titolo, autore, ISBN, descrizione, anno di pubblicazione, editore, numero di pagine e l'URL della copertina recuperata dall'esterno. L'entità conserva anche il riferimento a chi ha inserito il libro, con una relazione molti-a-uno verso `User`. Da ogni volume partono poi tre collezioni: le foto caricate dagli utenti (`BookPhoto`), le recensioni (`Review`) e i record di interazione (`UserBook`).

`Genre` è poco più di una tabella di catalogo, con un nome e uno *slug*, cioè la versione del nome adatta a comparire in un indirizzo web, ed è collegata sia ai libri sia agli utenti attraverso le rispettive relazioni molti-a-molti. I generi sono venti e vengono caricati all'installazione.

`Review` serve a dare un voto da una a cinque stelle, con la possibilità di aggiungere un commento scritto. Sulla coppia utente-libro ho messo un vincolo di unicità, altrimenti la stessa persona potrebbe recensire lo stesso titolo più volte di fila. Il voto medio, invece, non l'ho salvato da nessuna parte: è un dato derivato, e conservarlo in una colonna avrebbe significato doverlo riallineare a ogni nuova recensione. Lo faccio calcolare al momento, con una funzione di aggregazione quando servono i voti di molti libri insieme e scorrendo le recensioni collegate quando serve quello di un libro solo.

### UserBook: la relazione con attributi

La relazione tra utenti e libri è il cuore dello schema. Un utente può aver messo like a un libro, averlo letto, possederlo fisicamente, o qualunque combinazione delle tre cose. Una semplice tabella di collegamento non basta, perché la relazione ha attributi propri; ho quindi introdotto l'entità `UserBook`, con tre campi booleani e un vincolo di unicità sulla coppia utente-libro che garantisce l'esistenza di un solo record per ogni coppia.

```php
#[ORM\Entity(repositoryClass: UserBookRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_book',
                       columns: ['user_id', 'book_id'])]
class UserBook
{
    #[ORM\Column(type: 'boolean')]
    private bool $liked = false;

    #[ORM\Column(name: 'has_read', type: 'boolean')]
    private bool $hasRead = false;

    /** L'utente possiede fisicamente il libro (disponibile al prestito) */
    #[ORM\Column(type: 'boolean')]
    private bool $ownsPhysically = false;
    // ...
}
```

È il campo `ownsPhysically` a tenere insieme la parte sociale e quella di prestito: un libro è disponibile al prestito presso un utente esattamente quando esiste un record `UserBook` con quel flag attivo. La ricerca dei prestatori parte da lì, e la conclusione di uno scambio lo riporta a falso per il proprietario.

### Conversation, Message e LoanRequest

Di tutte le entità la più affollata di riferimenti è `LoanRequest`, che ne mette insieme quattro: il richiedente, il proprietario del volume, il libro su cui si sta trattando e la conversazione dentro cui la trattativa si svolge. Oltre allo stato della macchina a stati si tiene i punti dell'offerta che in quel momento è sul tavolo, più un campo `lastOfferBy` grazie al quale so sempre chi ha fatto l'ultima mossa e, di riflesso, chi deve rispondere. Due campi booleani, `requesterConfirmed` e `ownerConfirmed`, registrano le conferme dello scambio fisico.

`Conversation` è più lineare e raccoglie i messaggi scambiati tra due utenti, collegati a lei da una relazione molti-a-molti appoggiata alla tabella `conversation_participant`. Tra due utenti esiste al più una conversazione: prima di crearne una nuova il sistema controlla sempre se ce n'è già una.

Resta `Message`, che punta alla conversazione di appartenenza e al mittente ma aggiunge un campo `type`. Serve a distinguere i messaggi scritti dagli utenti da quelli che genera il sistema, i quali nascono in automatico ogni volta che il prestito cambia stato, tra offerta, controproposta, accettazione, rifiuto, conferma e completamento. C'è poi un campo JSON in cui salvo una fotografia dei dati importanti nel momento esatto dell'evento, ossia titolo del libro, copertina e punti offerti. Così un messaggio di sistema resta leggibile anche se quei dati vengono cambiati mesi dopo. La relazione tra `Message` e `LoanRequest`, infine, è opzionale, e l'ho configurata perché i messaggi di sistema sopravvivano nella chat anche quando la richiesta viene eliminata.

```php
/** Tipo: "text" per i messaggi normali, o codice evento per i prestiti */
#[ORM\Column(length: 20, options: ['default' => 'text'])]
private string $type = 'text';

/** Richiesta di prestito collegata (null per i messaggi di testo) */
#[ORM\ManyToOne(targetEntity: LoanRequest::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
private ?LoanRequest $loanRequest = null;

/** Dati dell'evento salvati al momento in cui si verifica */
#[ORM\Column(type: Types::JSON, nullable: true)]
private ?array $metadata = null;
```

La tabella 5.2 riassume le nove entità.

: Tabella 5.2 – Entità dello schema relazionale.

| Entità | Che cosa rappresenta | Vincoli e particolarità |
|:--------------|:-------------------------------------|:----------------------------------|
| `User` | Utente registrato | Email e username unici; follow per autoreferenza |
| `Book` | Titolo del catalogo condiviso | ISBN usato per evitare duplicati |
| `Genre` | Genere letterario | Nome e slug unici; venti valori precaricati |
| `UserBook` | Interazione di un utente con un libro | Unicità sulla coppia utente-libro |
| `BookPhoto` | Foto di una copia caricata da un utente | Ordinate per posizione |
| `Review` | Voto da 1 a 5 con commento facoltativo | Unicità sulla coppia utente-libro |
| `Conversation` | Chat privata tra due utenti | Una sola per coppia di utenti |
| `Message` | Messaggio di testo o evento di sistema | Campo JSON con i dati dell'evento |
| `LoanRequest` | Trattativa di prestito | Stato, offerta corrente, doppia conferma |

## La macchina a stati del prestito

La prima versione del prestito era piuttosto rozza: un utente chiedeva un libro in prestito e l'altro poteva solo accettare o rifiutare. Dopo averla provata l'ho ripensata da capo, ispirandomi ancora a Vinted, dove chi è interessato fa un'offerta e il venditore può accettare, rifiutare o rilanciare con una controproposta. Questo modello si sposa bene con i Culture Points, perché il valore dello scambio non è fisso ma viene negoziato tra le parti.

Ho modellato l'intero meccanismo come una macchina a stati, già mostrata nella figura 3.1: una richiesta può essere in attesa (`pending`), accettata (`accepted`), completata (`completed`), rifiutata (`declined`) o annullata (`cancelled`). Gli ultimi tre sono stati terminali, dai quali non si esce più. La tabella 5.3 elenca le transizioni ammesse, chi può eseguirle e quali effetti producono.

: Tabella 5.3 – Transizioni della richiesta di prestito.

| Azione | Da → a | Chi può eseguirla | Effetto sui punti |
|:----------------|:-------------------|:---------------------------|:----------------------|
| Nuova offerta | – → `pending` | Richiedente, con almeno 1 CP | −1 CP al richiedente (deposito) |
| Controproposta | `pending` → `pending` | Chi **non** ha fatto l'ultima offerta | Nessuno |
| Accettazione | `pending` → `accepted` | Chi **non** ha fatto l'ultima offerta | Nessuno |
| Rifiuto | `pending` → `declined` | Uno dei due partecipanti | +1 CP al richiedente (rimborso) |
| Annullamento | `accepted` → `cancelled` | Uno dei due partecipanti | +1 CP al richiedente (rimborso) |
| Conferma dello scambio | `accepted` → `accepted` | Ciascun partecipante, una volta | Nessuno |
| Seconda conferma | `accepted` → `completed` | L'altro partecipante | I punti concordati passano al proprietario |

Due regole meritano una spiegazione.

La prima è quella dell'**alternanza**. Chi ha appena fatto un'offerta non può né accettarla né modificarla: deve aspettare la risposta dell'altro. È il campo `lastOfferBy` a renderla applicabile, ed è la regola che impedisce a una delle due parti di chiudere la trattativa da sola alle proprie condizioni.

La seconda è la **doppia conferma**. Quando una trattativa viene accettata, nessun punto passa di mano finché entrambi gli utenti non confermano di aver effettuato fisicamente lo scambio del libro. I Culture Points si spostano soltanto quando entrambi hanno dichiarato che il libro è passato di mano, così nessuno dei due resta esposto alla decisione unilaterale dell'altro. Senza questo accorgimento, il proprietario potrebbe dichiarare concluso lo scambio prima di consegnare il libro, o il richiedente prima di averlo ricevuto. L'ho gestita con i due campi booleani su `LoanRequest`, uno per chi chiede il libro e uno per chi lo presta. Finché non sono entrambi attivi non succede niente; quando lo diventano, la trattativa si chiude e i punti vengono trasferiti.

Ogni transizione genera in automatico un messaggio di sistema nella chat tra i due utenti, lasciando una traccia leggibile di tutta la trattativa, e pubblica un aggiornamento via Mercure così che entrambi vedano il cambiamento in tempo reale.

## I Culture Points

I Culture Points sono un sistema di punti interno che serve a due cose. La prima è incentivare la partecipazione: gli utenti guadagnano punti aggiungendo libri, così il catalogo condiviso si arricchisce premiando direttamente chi contribuisce. La seconda ha a che fare con lo spam. Se aprire una trattativa non costasse nulla, chiunque potrebbe avviarne trenta in una sera tanto per provare, riempiendo di richieste le conversazioni di mezza piattaforma. Per questo aprire una trattativa di prestito costa un Culture Point.

Le regole sono poche:

- inserire un nuovo libro nel catalogo accredita **10 punti**;
- aprire una trattativa richiede almeno un punto e ne **trattiene uno** come deposito;
- se la richiesta viene rifiutata o annullata, il punto **torna indietro** da solo;
- se lo scambio si conclude, i punti concordati **passano dal richiedente al proprietario** del libro.

La figura 5.4 segue il saldo di due utenti, entrambi partiti da 20 punti, lungo una trattativa chiusa a 10 punti dopo una controproposta.

![Figura 5.4 – Andamento dei Culture Points di richiedente e proprietario lungo una trattativa conclusa a 10 punti.](figure/g-punti.png){width=14cm}

Nel codice il saldo è un semplice campo intero sull'entità `User`, e tutte le variazioni passano dal controller del prestito, che a seconda della transizione di stato scala, rimborsa o trasferisce. Un effetto collaterale che ho trovato interessante è che un utente appena iscritto parte da zero: per poter chiedere un libro deve prima averne inserito almeno uno. Il sistema spinge così, fin dal primo giorno, a dare qualcosa alla comunità prima di chiedere.
