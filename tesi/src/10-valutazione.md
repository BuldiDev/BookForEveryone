# Valutazione dei risultati

In questo capitolo verifico quanto il risultato corrisponda agli obiettivi di partenza, ne discuto i punti di forza e i limiti e indico in quali direzioni il progetto potrebbe crescere.

## Rispondenza alla traccia e agli obiettivi

La traccia chiedeva un software di geolocalizzazione culturale per condividere il patrimonio librario degli utenti privati, e credo che l'elaborato ne copra tutte le dimensioni. Quella culturale, perché al centro di tutto c'è il libro inteso come bene da far circolare e non come oggetto da possedere una volta sola; quella di geolocalizzazione, perché la posizione degli utenti orienta concretamente la ricerca dei prestatori; e quella sociale, perché l'intera piattaforma è costruita attorno alla relazione tra persone, allo scambio e alla negoziazione diretta.

Con l'applicazione che ho realizzato si possono pubblicare e cercare patrimoni librari privati. Chi si iscrive costruisce la sua collezione, la ordina per genere e la mette a disposizione degli altri, con copertine e metadati al seguito. C'è poi la ricerca geografica, che individua utenti e libri nel raggio di pochi chilometri e riporta i contributi più vicini anche su una mappa. Sul prestito, invece, sono andato oltre il semplice mettere in contatto due persone. Chi è interessato fa un'offerta, il proprietario può rilanciare con una controproposta, e alla fine servono due conferme, una per parte, dopo che il libro è passato di mano. È una negoziazione a tutti gli effetti.

La tabella 10.1 riprende i requisiti del capitolo 3 e indica per ciascuno lo stato di realizzazione.

: Tabella 10.1 – Stato di realizzazione dei requisiti.

| ID | Requisito | Stato | Note |
|:----|:----------------------|:------------:|:----------------------------------|
| RF1 | Registrazione | Realizzato | Verifica dell'email implementata ma disattivata nell'ambiente di prova |
| RF2 | Onboarding | Realizzato | Almeno tre generi su venti |
| RF3 | Gestione del profilo | Realizzato | |
| RF4 | Inserimento dei libri | Realizzato | Due fonti esterne e completamento manuale |
| RF5 | Feed personalizzato | Realizzato | Sezioni «Per te» e «Seguiti» |
| RF6 | Interazioni sociali | Realizzato | |
| RF7 | Ricerca testuale | Realizzato | Per titolo, autore, ISBN |
| RF8 | Ricerca geografica | Realizzato | Raggio da 1 a 100 km, elenco e mappa |
| RF9 | Prestito con negoziazione | Realizzato | Cinque stati, alternanza e doppia conferma |
| RF10 | Chat in tempo reale | Realizzato | |
| RF11 | Culture Points | Realizzato | |
| RF12 | Dashboard amministrativa | Realizzato | Sola consultazione |
| RNF1 | Prestazioni del feed | Realizzato | Numero di query indipendente dalla dimensione del blocco |
| RNF2 | Riservatezza della posizione | Realizzato | Accesso obbligatorio e consenso datato |
| RNF3 | Usabilità da telefono | Realizzato | Interfaccia adattiva |
| RNF4 | Riproducibilità dell'ambiente | Realizzato | Docker Compose e script di popolamento |
| RNF5 | Sicurezza di base | Realizzato | Con i limiti discussi più avanti |

## Punti di forza

Il punto di forza maggiore, a mio avviso, sta nel modo in cui la piattaforma cresce. Il catalogo non esiste in partenza, se lo costruiscono gli utenti, e questo vuol dire che ogni nuova iscrizione rende il sistema un po' più utile anche a chi c'era già. I Culture Points spingono nella stessa direzione, dato che premiano proprio chi inserisce libri nuovi. Andando avanti, un meccanismo del genere può produrre una base dati locale più ricca e più aggiornata di quanto riuscirebbe a fare qualunque catalogo gestito dall'alto.

Anche il sistema di negoziazione del prestito, con offerta, controproposta e conferma fisica, offre un'infrastruttura per lo scambio tra privati che va parecchio oltre la semplice messa in contatto. La traccia leggibile che ogni trattativa lascia nella chat ha un valore pratico: se nasce un disaccordo, entrambi hanno davanti la stessa sequenza di eventi.

Sul piano tecnico ritengo riusciti soprattutto tre aspetti. Il feed, che è la parte più sollecitata, ha un costo in termini di interrogazioni che non dipende dal numero di libri mostrati. La comunicazione in tempo reale è ottenuta senza scrivere una riga di gestione delle connessioni, affidandosi a un hub dedicato e a frammenti HTML generati dagli stessi template delle pagine. E l'intero ambiente si ricrea da zero con pochi comandi, il che ha reso semplice sia lo sviluppo su più macchine sia la preparazione della versione dimostrativa.

## Limiti

Un progetto individuale sviluppato in due mesi ha inevitabilmente dei limiti, ed è corretto elencarli.

**Deployment su una sola macchina.** Un limite strutturale riguarda il deployment. Oggi tutto gira su Docker Compose, con il vantaggio già descritto dell'installazione con un comando. Il rovescio della medaglia è che database, cache Redis, hub Mercure e applicazione PHP finiscono tutti sulla stessa macchina, e in produzione con utenti veri una cosa del genere non sta in piedi. Per arrivarci servirebbe rimettere mano all'architettura: servizi separati su infrastrutture dedicate, *scaling* orizzontale predisposto e una strategia di alta disponibilità almeno per i componenti critici, a partire dalla messaggistica in tempo reale.

**Moderazione assente.** Il secondo limite ha a che fare con la moderazione, che al momento semplicemente non c'è. Su una piattaforma dove sono gli utenti a caricare i contenuti, prima o poi si presentano casi scomodi: copertine sbagliate o messe apposta per ingannare, profili aperti solo per accumulare punti senza alcuna intenzione di scambiare qualcosa, messaggi che nessuno dovrebbe ricevere. Senza la possibilità di segnalare questi casi e senza un pannello amministrativo da cui intervenire, la qualità e la sicurezza della piattaforma finirebbero per dipendere interamente dalla buona fede degli utenti, un'ipotesi che nel mondo reale non ci si può permettere di dare per scontata.

**Riservatezza del canale in tempo reale.** Nell'ambiente di sviluppo l'hub Mercure accetta sottoscrizioni anche senza credenziali e gli aggiornamenti delle conversazioni non sono marcati come privati. Le pagine e le azioni della chat sono protette dai controlli di Symfony, ma il canale su cui viaggiano le notifiche andrebbe irrobustito prima di un uso reale: aggiornamenti privati e un token di sottoscrizione, rilasciato a ciascun utente, che lo autorizzi ad ascoltare le sole conversazioni di cui fa parte. Il protocollo prevede già entrambe le cose; si tratta di attivarle.

**Controlli sul saldo dei punti.** All'apertura di una trattativa il sistema verifica che il richiedente abbia almeno un punto, quello del deposito, ma non che ne abbia abbastanza per coprire l'intera offerta, né al momento della controproposta né al completamento. Un controllo di capienza a ogni passaggio, o il blocco dell'importo offerto fin dall'inizio, chiuderebbe la possibilità di un saldo negativo.

**Precisione e staticità della posizione.** La posizione è quella indicata alla registrazione e viene mostrata sulla mappa così com'è. Sarebbe opportuno poterla aggiornare con facilità e, soprattutto, mostrarla agli altri in forma approssimata, per esempio arrotondata a una zona di qualche centinaio di metri, conservando il dato esatto soltanto per il calcolo delle distanze.

**Ricerca geografica senza indice spaziale.** La distanza viene calcolata per ogni possessore del titolo cercato. Con i volumi dell'ambiente di prova è istantaneo; su una base utenti molto più ampia converrebbe restringere prima i candidati con un filtro grossolano su intervalli di latitudine e longitudine, sfruttando un indice, o passare ai tipi geospaziali del database.

**Assenza di test automatici.** Il collaudo è stato svolto a mano, ripercorrendo più volte l'intero flusso. Il progetto è predisposto per i test automatici ma non ne contiene ancora; le parti che ne beneficerebbero di più sono le transizioni del prestito e i movimenti dei punti, dove un errore ha conseguenze dirette per gli utenti.

**Restituzione del libro.** Il modello attuale considera concluso lo scambio quando il libro passa di mano: la copia esce dalla disponibilità del proprietario e la trattativa si chiude. Non c'è ancora la gestione del ritorno del volume, con una scadenza e una conferma di restituzione, che trasformerebbe lo scambio in un prestito nel senso più stretto del termine.

## Sviluppi futuri

L'architettura lascia spazio a diversi sviluppi futuri, molti dei quali sono la risposta diretta ai limiti appena elencati.

Un **sistema di reputazione** basato sugli scambi conclusi è il primo che realizzerei: i dati necessari esistono già, perché ogni trattativa completata è registrata con i suoi due partecipanti. Un indicatore di affidabilità accanto al nome aiuterebbe a scegliere a chi rivolgersi e scoraggerebbe i comportamenti scorretti.

Subito dopo vengono gli **strumenti di moderazione**: la segnalazione di contenuti e utenti, e un'estensione della dashboard che oggi è di sola lettura, da cui poter correggere una scheda, rimuovere una foto o sospendere un profilo.

Un'altra cosa a cui ho pensato senza poi metterla in pratica riguarda l'**algoritmo del feed**. Al momento i generi preferiti si scelgono in fase di registrazione e da lì non si muovono più. Sarebbe interessante renderli dinamici: osservando nel tempo cosa l'utente mette tra i like, quali scambi porta a termine e su quali contenuti si ferma più a lungo, il sistema potrebbe ricalcolare da solo il peso dei singoli generi e avvicinare i suggerimenti ai gusti effettivi, senza che nessuno debba mettere mano alle impostazioni. Nella stessa direzione, il feed potrebbe tenere conto della distanza, dando più peso ai libri effettivamente disponibili nelle vicinanze.

Sul fronte della comunicazione mancano le **notifiche**: oggi ci si accorge di una nuova offerta solo aprendo la chat. Un contatore dei messaggi non letti e le notifiche push del browser renderebbero i tempi di risposta molto più brevi. L'infrastruttura per farlo, Mercure e la coda di messaggi, è già in piedi.

Infine, **statistiche più dettagliate** sull'attività della comunità locale e una **visualizzazione su mappa più ricca**, per esempio una mappa di tutti i libri disponibili in una zona e non solo dei possessori di un titolo, sono funzionalità che si innestano in modo naturale su quanto già esiste.

## Campi di applicazione

Oltre all'uso individuale, la piattaforma si presta con naturalezza a contesti di comunità più strutturati. In ambito scolastico o universitario potrebbe diventare uno strumento per far circolare libri di testo e letture tra studenti vicini, abbattendo i costi d'acquisto; associazioni culturali, gruppi di lettura e biblioteche di quartiere potrebbero usarla per dare visibilità ai propri scaffali e organizzare scambi sul territorio; in un condominio, in un quartiere o in un piccolo paese, la forte componente locale la trasforma in un modo concreto per far incontrare persone che condividono le stesse passioni ma che, senza uno strumento del genere, non saprebbero nemmeno di avere a pochi passi i libri che cercano.

Il vantaggio di fondo, in tutti questi scenari, non cambia. Si tratta sempre di valorizzare un patrimonio che esiste già ed è sparso sul territorio, rimettendolo a girare dentro una rete di prossimità anziché lasciarlo fermo dov'è. I guadagni stanno su più piani. Libri rimasti fermi per anni ricominciano a circolare, ed è già di per sé uno spreco in meno. Nel farlo si conoscono persone che leggono le stesse cose. E si finisce per costruire un catalogo collettivo che si arricchisce da solo, perché ogni persona in più che si iscrive ci lascia dentro qualcosa.

Ce n'è poi un secondo, meno evidente ma per me altrettanto importante. Da un interesse condiviso possono nascere legami sociali veri, e non è poco in un periodo in cui i rapporti tendono a esaurirsi davanti a uno schermo.

# Conclusioni

L'obiettivo di questo lavoro era costruire un software di geolocalizzazione culturale che permettesse a utenti privati di condividere i propri libri. Il risultato è BookForEveryone: una piattaforma in cui ci si iscrive, si costruisce una libreria personale a partire dai codici ISBN, si scoprono i libri degli altri scorrendo un feed, si cerca chi possiede un certo titolo nelle vicinanze e si concorda il prestito attraverso una trattativa che si svolge in chat, in tempo reale, e che si chiude soltanto quando entrambe le parti confermano che il libro è passato di mano.

L'idea da cui sono partito è che le abitudini digitali che di solito vengono contrapposte alla lettura possano invece essere messe al suo servizio. I giovani non hanno smesso di leggere: è cambiato il modo in cui vengono a sapere che un certo libro esiste. Se lo strumento parla la lingua a cui sono abituati, con un feed, i profili, i like e lo scorrimento verticale, la distanza tra chi legge già e chi vorrebbe farlo si accorcia, e un gesto nato per passare da un video al successivo può finire per portare qualcuno a bussare alla porta di un vicino per farsi prestare un romanzo.

Dal punto di vista tecnico il progetto mi ha messo davanti a problemi che sui libri si incontrano come definizioni e che qui ho dovuto risolvere in pratica: le query che si moltiplicano con i dati, lo stato da condividere tra richieste indipendenti, la consegna di un'informazione a due persone nello stesso istante, la coerenza di un'operazione che tocca più tabelle, la convivenza di sei servizi sulla stessa macchina. Per ciascuno ho cercato la soluzione più semplice che funzionasse davvero: un insieme in memoria per ricordare che cosa è già stato mostrato, una formula trigonometrica in una query per ordinare per distanza, due campi booleani per garantire che nessuno dei due utenti dipenda dalla parola dell'altro.

Non tutto è risolto. La moderazione, la sicurezza del canale in tempo reale, la gestione della restituzione e un'architettura adatta a un uso reale su larga scala restano lavoro da fare, e li ho indicati senza reticenze perché conoscere i limiti di ciò che si è costruito fa parte del costruirlo bene. Quello che c'è, però, è completo e funzionante dall'inizio alla fine: si avvia con un comando, è popolato di dati verosimili ed è pubblicato con il suo codice sorgente, a disposizione di chiunque voglia provarlo, studiarlo o portarlo avanti.

Sul piano personale, questo progetto è stato il punto in cui le materie del percorso di studi hanno smesso di essere esami separati e sono diventate parti di una stessa cosa. Lo schema relazionale, il protocollo HTTP, la programmazione a oggetti, le code di messaggi: li ho ritrovati tutti, uno accanto all'altro, dentro un'unica applicazione, e ho capito fino in fondo ciascuno di essi soltanto quando ho dovuto farlo lavorare insieme agli altri.
