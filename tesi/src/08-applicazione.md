# L'applicazione in uso

Dopo aver visto come è costruita, in questo capitolo mostro l'applicazione dal punto di vista di chi la usa. All'interfaccia ho dedicato molto tempo, ragionando soprattutto in termini di usabilità: volevo una navigazione che non avesse bisogno di spiegazioni e interazioni che non facessero aspettare. Le schermate sono riprese dall'ambiente di prova e seguono l'ordine delle voci del menu.

## La struttura dell'interfaccia

L'applicazione si regge su quattro aree, a cui si aggiunge la dashboard amministrativa. La registrazione include una fase di onboarding in cui l'utente sceglie i generi che preferisce. Il profilo raccoglie il catalogo personale e qualche statistica sull'attività. Il feed propone i libri filtrati per genere, con scorrimento infinito e precaricamento automatico. La quarta area è quella del prestito, dove si cercano i libri disponibili e si apre una trattativa con il proprietario, trattativa che si svolge per intero dentro la chat.

Tutte le pagine condividono la stessa cornice: un menu laterale con le sezioni, l'elenco degli utenti seguiti subito sotto e, in alto a destra, il saldo dei Culture Points sempre visibile. Su schermi stretti la cornice si riorganizza per lasciare tutto lo spazio al contenuto. Ho scelto un tema scuro, che è quello a cui il target è abituato nelle applicazioni di video brevi e che fa risaltare le copertine, l'elemento visivo più importante di tutta la piattaforma.

## Il feed

![Figura 8.1 – Il feed «Per te»: una scheda alla volta, con le azioni rapide sul lato destro.](figure/Feed.PNG){width=15cm}

La schermata principale (figura 8.1) propone un libro alla volta al centro dello schermo. Ogni scheda mostra la copertina a tutta altezza e, sovrapposti in basso, il nome di chi ha consigliato il libro, il titolo, l'autore, il voto medio e l'inizio della descrizione. Le azioni sono impilate sul lato destro, nello stesso punto in cui il target è abituato a trovarle: il profilo di chi ha inserito il libro con il pulsante per seguirlo, il like con il suo contatore, «letto», «posseggo», le informazioni complete, il voto e la condivisione.

In alto due etichette permettono di passare dalla sezione «Per te», basata sui generi preferiti, alla sezione «Seguiti», che mostra l'attività degli utenti che si seguono. Le due frecce sul bordo destro servono a chi usa il mouse per passare alla scheda precedente o successiva; da telefono basta scorrere.

## La ricerca nel catalogo

![Figura 8.2 – La ricerca testuale nel catalogo.](figure/Cerca.PNG){width=15cm}

La sezione di ricerca (figura 8.2) consente di individuare un titolo presente nel catalogo cercandolo per titolo, autore o ISBN. I risultati sono mostrati come griglia di copertine con titolo e autore, e ciascuno porta alla scheda di dettaglio del volume, dove si trovano la descrizione completa, i dati editoriali, le foto caricate dagli utenti e le recensioni.

## L'aggiunta di un libro

![Figura 8.3 – Il modulo di inserimento di un libro, con ricerca automatica per ISBN.](figure/Aggiungi.PNG){width=15cm}

Il modulo di inserimento (figura 8.3) parte dal campo dell'ISBN. Inserito il codice e avviata la ricerca, i campi sottostanti si riempiono da soli con i dati recuperati dalle fonti esterne: titolo, autore, numero di pagine, editore e anno di pubblicazione. Nell'esempio della figura si vede bene il limite di cui ho parlato nel capitolo 6: la fonte ha restituito titolo, autore, pagine e anno, ma non la descrizione né l'editore, che restano da completare a mano. All'utente resta da scegliere i generi, con un campo di ricerca che suggerisce quelli esistenti, e da caricare, se vuole, una copertina migliore e alcune foto della propria copia. Se il libro è già in catalogo il modulo lo segnala subito, prima che l'utente perda tempo a compilare il resto.

## La ricerca dei prestatori

![Figura 8.4 – «Prendi in prestito»: i proprietari di un titolo in elenco e sulla mappa.](figure/Prestito.PNG){width=15cm}

La sezione dedicata al prestito (figura 8.4) è divisa in due: a sinistra la ricerca e i risultati, a destra la mappa. Scelto un titolo, l'elenco mostra gli utenti che lo possiedono con la distanza di ciascuno, dal più vicino al più lontano, e la mappa li rappresenta come segnaposto attorno alla posizione di chi cerca. Un cursore permette di allargare o restringere il raggio. Accanto a ogni proprietario c'è il pulsante per fare un'offerta, con il numero di Culture Points che si è disposti a dare.

## La chat e la trattativa

![Figura 8.5 – Una trattativa completa nella chat: offerta, controproposte, accettazione, doppia conferma e completamento.](figure/Chat.PNG){width=15cm}

La figura 8.5 mostra una trattativa dall'inizio alla fine, ed è probabilmente la schermata che meglio riassume il progetto. Dall'alto verso il basso si leggono l'offerta iniziale di 6 punti per *Fahrenheit 451*, due controproposte che portano il valore a 10, l'accettazione con l'invito ad accordarsi per l'incontro, le conferme dello scambio da parte dei due utenti e infine il messaggio che sancisce il completamento, con i punti trasferiti.

Ognuno di questi riquadri è un messaggio di sistema, generato dal passaggio di stato corrispondente. Tra l'uno e l'altro i due utenti possono scriversi liberamente con il campo in basso, per decidere dove e quando vedersi. I colori aiutano a leggere la sequenza a colpo d'occhio: neutro per le offerte ancora aperte, verde per l'accordo e per il completamento.

## Il profilo

![Figura 8.6 – Il profilo pubblico di un utente.](figure/Profilo.PNG){width=15cm}

La pagina di profilo (figura 8.6) raccoglie le informazioni pubbliche dell'utente: nome, username, biografia e contatori dei libri letti, delle copie possedute, dei follower e dei seguiti. Sotto sono elencate le sue raccolte, distinte tra libri consigliati, letti e posseduti, con le rispettive copertine. Dalla stessa pagina si può seguire l'utente o aprire una conversazione con lui.

## La dashboard amministrativa

![Figura 8.7 – La dashboard riservata agli amministratori.](figure/Statistiche.PNG){width=15cm}

La dashboard (figura 8.7) dà a chi amministra la piattaforma una visione d'insieme: quattro indicatori con i totali di libri, utenti, recensioni e prestiti, quattro grafici con i primi dieci titoli per valutazione media, like, lettori e recensioni ricevute, e in fondo il catalogo completo con ricerca per titolo o autore. La voce «Statistiche» compare nel menu soltanto agli utenti con ruolo di amministratore.
