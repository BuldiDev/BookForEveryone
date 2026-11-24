# Contesto e stato dell'arte

Il contesto applicativo dell'elaborato guarda in due direzioni contemporaneamente: i social media con le loro nuove comunità di lettori da una parte, l'economia della condivisione applicata a un bene culturale durevole dall'altra. In questo capitolo descrivo entrambe, passo in rassegna le piattaforme esistenti che ho analizzato prima di scrivere il codice e chiarisco in che cosa BookForEveryone se ne distingue.

## Lettura e social network

Le piattaforme di video brevi hanno imposto un modello di interazione molto preciso: un contenuto alla volta a tutto schermo, uno scorrimento verticale che non finisce mai, poche azioni immediate (il like, il follow, la condivisione) sempre nello stesso punto dello schermo. È un modello costruito per ridurre a zero lo sforzo necessario a passare al contenuto successivo, e proprio per questo viene spesso indicato tra le cause della difficoltà a mantenere l'attenzione su attività lunghe [@mark2023].

Sulle stesse piattaforme, però, la lettura ha trovato uno spazio inatteso. Sotto l'etichetta BookTok si raccoglie una comunità di lettori, in gran parte giovani, che usa il formato del video breve per consigliare titoli, commentare trame e mostrare le proprie librerie. Jerasa e Boffone la descrivono come una pratica di alfabetizzazione digitale che si svolge fuori dalla scuola e nella quale i ragazzi scelgono liberamente cosa leggere, influenzandosi a vicenda [@jerasa2021]. Quello che cambia rispetto al passato è il modo in cui si viene a sapere che un certo libro esiste: trovare un titolo nuovo non è più il risultato di una ricerca, è una cosa che capita davanti agli occhi mentre si scorre.

Da questa osservazione ho ricavato la scelta di fondo dell'interfaccia. Se una piattaforma parla quella stessa lingua, con feed, profili, like e scorrimento verticale, la distanza tra chi legge già e chi vorrebbe farlo si accorcia da sola. Anche lo scorrimento infinito non l'ho scelto per moda: una paginazione classica avrebbe messo una barriera tra una pagina e l'altra, e ogni clic su «pagina successiva» è un'occasione per abbandonare. Scorrere verso il basso, invece, è un gesto che il target compie già senza pensarci. È lo stesso principio che la letteratura sull'usabilità riassume da tempo nella raccomandazione di non costringere l'utente a fermarsi a ragionare su come si usa un'interfaccia [@krug2014].

## L'economia della condivisione

Il secondo filone in cui il progetto si colloca è quello della cosiddetta *sharing economy*, il modello che mette la condivisione e il riuso davanti al possesso esclusivo. Botsman e Rogers, che hanno reso popolare l'espressione «consumo collaborativo», lo descrivono come un sistema in cui l'accesso a un bene conta più della sua proprietà e in cui la tecnologia serve a mettere in contatto, su larga scala, chi ha qualcosa di inutilizzato e chi ne ha bisogno [@botsman2010]. Belk ne dà una definizione più stretta: persone che coordinano l'acquisizione e la distribuzione di una risorsa in cambio di un compenso, anche non monetario [@belk2014]. È una precisazione utile per questo progetto, perché il prestito in BookForEveryone non è gratuito in senso assoluto: viene compensato con una valuta interna, i Culture Points, che non ha valore economico ma regola lo scambio.

Vinted per i vestiti e BlaBlaCar per i passaggi in auto hanno dimostrato una cosa semplice: se lo strumento digitale rende facile e affidabile l'incontro tra chi ha e chi cerca, le persone sono disposte a condividere quello che possiedono [@vinted; @blablacar]. Lo studio di Hamari, Sjöklint e Ukkonen sulle motivazioni di chi partecipa al consumo collaborativo aggiunge un elemento che ho tenuto presente: accanto al vantaggio pratico contano il divertimento nell'usare la piattaforma e la percezione di fare qualcosa di sostenibile [@hamari2016]. Portare lo stesso ragionamento sul libro aggiunge proprio una dimensione di sostenibilità, perché prendere in prestito la copia che sta già a due strade di distanza evita di comprarne una nuova e rimette in circolo qualcosa che altrimenti resterebbe fermo su uno scaffale.

## La ludicizzazione come incentivo

Una piattaforma in cui il catalogo viene costruito dagli utenti ha un problema di avvio: finché nessuno inserisce libri non c'è niente da cercare, e finché non c'è niente da cercare nessuno ha motivo di iscriversi. Per spingere la partecipazione ho fatto ricorso a un elemento tipico della ludicizzazione, definita da Deterding e colleghi come l'uso di elementi propri del design dei giochi in contesti che giochi non sono [@deterding2011]. I Culture Points sono esattamente questo: un punteggio che cresce quando si contribuisce al catalogo e che si spende quando si chiede qualcosa agli altri. Non c'è una classifica né una competizione; i punti servono a far sì che lo scambio abbia un costo percepito e che chi dà molto alla comunità possa anche chiedere molto.

## Piattaforme esistenti

Prima di scrivere una riga di codice ho analizzato alcune piattaforme già esistenti per prendere spunto. Le ho scelte non perché facciano la stessa cosa, ma perché ciascuna risolve bene un pezzo del problema.

**Vinted** è stato il riferimento per il meccanismo di offerta e negoziazione. Su Vinted chi è interessato a un articolo può fare un'offerta, e il venditore può accettarla, rifiutarla o rilanciare con una controproposta; tutto avviene dentro la conversazione tra i due utenti, dove ogni passaggio lascia una traccia visibile. È esattamente la struttura che ho riprodotto per il prestito, sostituendo il denaro con i Culture Points.

**Instagram, TikTok e YouTube** sono stati il riferimento per la struttura del feed: un elemento alla volta al centro dello schermo, le azioni rapide impilate sul lato destro, il caricamento continuo dei contenuti successivi.

**Goodreads** è la più nota piattaforma di catalogazione sociale dei libri [@goodreads]: gli utenti registrano ciò che hanno letto, assegnano un voto e scrivono recensioni. Ne ho ripreso l'idea della libreria personale con stati distinti (letto, posseduto) e delle recensioni a stelle, ma Goodreads si ferma alla dimensione informativa: non sa dove si trovano fisicamente le copie e non prevede alcuno scambio.

**BookCrossing** è l'esperienza storica di circolazione libera dei libri: i volumi vengono etichettati con un codice e «liberati» in luoghi pubblici, perché qualcun altro li trovi e ne registri il passaggio [@bookcrossing]. L'idea di fondo, il libro come bene che viaggia, è la stessa del mio progetto; manca però l'incontro tra le persone e manca qualunque garanzia che il libro arrivi a chi lo sta cercando.

**BlaBlaCar** è stato infine il riferimento per l'aspetto più delicato, quello dell'incontro di persona tra sconosciuti: anche lì la piattaforma si limita a far accordare due utenti che poi si vedono nel mondo reale, e la fiducia viene costruita con profili pubblici e conferme reciproche.

La tabella 2.1 mette a confronto queste piattaforme rispetto alle funzioni che la traccia richiedeva.

: Tabella 2.1 – Confronto tra BookForEveryone e le piattaforme analizzate.

| Funzione | Goodreads | BookCrossing | Vinted | BookForEveryone |
|:-----------------------------|:--------:|:--------:|:--------:|:--------:|
| Catalogo personale di libri | sì | parziale | no | sì |
| Recensioni e valutazioni | sì | parziale | no | sì |
| Feed a scorrimento continuo | no | no | sì | sì |
| Ricerca per vicinanza geografica | no | no | no | sì |
| Scambio di copie fisiche | no | sì | sì (vendita) | sì (prestito) |
| Negoziazione con controproposta | no | no | sì | sì |
| Chat tra utenti in tempo reale | no | no | sì | sì |
| Valuta interna non monetaria | no | no | no | sì |

## Posizionamento del progetto

Il campo di applicazione di BookForEveryone resta la condivisione culturale su scala locale. Una biblioteca digitale è un'altra cosa, e lo è anche un marketplace puro. Qui l'idea è che le collezioni private diventino visibili a chi abita nel raggio di pochi isolati, e che prendere in prestito valga come alternativa al comprare.

Rispetto alle piattaforme analizzate, il progetto mette insieme elementi che di solito vivono separati: la catalogazione sociale di Goodreads, la circolazione dei volumi di BookCrossing, la negoziazione di Vinted e l'interfaccia dei social di video brevi. Il vincolo che tiene tutto insieme è quello locale: dato che il prestito si conclude di persona, la geolocalizzazione smette di essere un'aggiunta e diventa il criterio con cui si ordinano i risultati. Da questa scelta discende tutto il resto, dal modo in cui è salvata la posizione degli utenti al funzionamento della ricerca dei prestatori.
