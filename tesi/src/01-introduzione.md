# Introduzione

## Motivazioni

Chi ha a che fare con i ragazzi di oggi lo nota subito: la soglia di attenzione si è accorciata. È un fenomeno di cui si parla molto e la causa è abbastanza chiara. Conviviamo ogni giorno con una quantità enorme di contenuti brevissimi e visivamente aggressivi, dai video di TikTok agli Instagram Reels agli YouTube Shorts. Si scorre, si ottiene una gratificazione immediata, si passa oltre. Quando uno si abitua a ricevere qualcosa ogni pochi secondi, poi mettersi lì a leggere per due ore gli costa una fatica sproporzionata, e la lettura finisce per essere tra le prime cose a rimetterci. Gli studi sull'attenzione davanti agli schermi descrivono lo stesso andamento: il tempo che dedichiamo a una singola attività prima di spostarci su un'altra si è ridotto in modo sensibile negli ultimi vent'anni [@mark2023].

Fermare qui il ragionamento sarebbe però una lettura parziale della faccenda, perché sulle stesse piattaforme che vengono accusate di aver rovinato l'attenzione dei ragazzi sono nati anche fenomeni come BookTok, dove ragazzi e ragazze si passano titoli a vicenda, girano video in cui recensiscono quello che hanno appena finito e discutono di letteratura con lo stesso linguaggio che usano per tutto il resto. La letteratura scientifica ha iniziato a descrivere BookTok come una vera pratica di lettura extrascolastica, capace di costruire comunità attorno ai libri [@jerasa2021]. Ai libri, insomma, si continua a tenere. Si è spostato il luogo in cui se ne parla, non l'interesse.

È in mezzo a queste due tendenze che nasce BookForEveryone. Dai social più usati l'applicazione prende le logiche visive, dal feed a scorrimento infinito ai like, dal follow ai profili personali, e le mette al servizio di un oggetto che va nella direzione opposta, perché il libro è lento. Non è una contraddizione, è proprio il punto: se il mio target passa già le sue giornate dentro interfacce di quel tipo, chiedergli di adattarsi a qualcosa di completamente diverso significa perderlo alla prima schermata. Tanto vale sfruttare le sue abitudini e portarle verso uno scambio culturale concreto.

C'è poi una seconda motivazione, più materiale. Il libro ha una caratteristica che lo distingue dalla maggior parte degli altri beni: il suo valore non si consuma con l'uso e può passare intatto da una persona all'altra. Eppure, nella stragrande maggioranza dei casi, dopo la prima lettura finisce a prendere polvere su uno scaffale, inaccessibile a chiunque altro. I libri che stanno nelle case delle famiglie restano una risorsa culturale ampiamente sprecata, e non perché manchi l'interesse a farli girare. Quello che manca è uno strumento capace di rendere visibile e raggiungibile ciò che già c'è.

## La traccia e gli obiettivi del progetto

L'elaborato sviluppa la traccia n. 14 del tema «Sharing technologies»: *Sviluppo di un software di geolocalizzazione culturale per condividere il patrimonio librario degli utenti privati*. Dalla traccia ho ricavato tre dimensioni, che hanno guidato tutte le scelte successive.

La prima è quella **culturale**: al centro di tutto c'è il libro inteso come bene da far circolare e non come oggetto da possedere una volta sola. La seconda è quella di **geolocalizzazione**: il volume va consegnato a mano, e ha poco senso proporre a un utente di Palermo una copia che sta a Bolzano; la posizione degli utenti deve orientare concretamente la ricerca dei prestatori. La terza è quella **sociale**: l'intera piattaforma è costruita attorno alla relazione tra persone, allo scambio e alla negoziazione diretta.

Da queste tre dimensioni discendono gli obiettivi operativi che mi sono dato all'avvio del lavoro:

1. permettere a chi si iscrive di **pubblicare e ordinare la propria collezione**, con copertine e metadati recuperati in automatico a partire dal codice ISBN;
2. offrire un **feed personalizzato** sui generi preferiti, con le stesse dinamiche di scorrimento a cui il target è abituato;
3. realizzare una **ricerca geografica** che individui gli utenti che possiedono un certo titolo nel raggio di pochi chilometri e li mostri anche su una mappa;
4. andare oltre la semplice messa in contatto, costruendo un **sistema di prestito con negoziazione**: offerta, controproposta e doppia conferma dello scambio avvenuto;
5. dare ai due utenti una **chat in tempo reale** in cui accordarsi su luogo e orario dell'incontro;
6. introdurre un meccanismo di incentivo, i **Culture Points**, che premi chi arricchisce il catalogo e scoraggi le richieste fatte a vuoto;
7. tutelare la **riservatezza dei dati di posizione**, rendendoli visibili solo a chi ha effettuato l'accesso;
8. consegnare un'applicazione **completa e avviabile con un solo comando**, già popolata di dati di prova realistici.

Tutte le funzionalità individuate in fase di progettazione sono state effettivamente implementate e collaudate lungo l'intero flusso, dalla registrazione di un nuovo utente fino al trasferimento dei Culture Points al termine di uno scambio. Il codice sorgente è pubblico ed è disponibile su GitHub [@repo].

## Il contributo dell'elaborato

Quello che ne è uscito non è un prototipo a metà. È un'applicazione completa, che si avvia con un solo comando e che arriva già popolata di dati di test: circa un migliaio di utenti e alcune decine di libri con copertine vere. Chi la installa riesce a verificarne il funzionamento da subito, in condizioni molto vicine a quelle d'uso reale.

Dal punto di vista tecnico, i punti che ritengo più interessanti, e ai quali dedico più spazio nei capitoli che seguono, sono quattro:

- l'ottimizzazione del **feed**, passato da una raffica di query per ogni libro a un numero costante di interrogazioni grazie al caricamento in blocco, con la cronologia dei contenuti già visti affidata a Redis;
- la **ricerca per vicinanza**, risolta calcolando la distanza sulla sfera terrestre direttamente nella query SQL;
- la **macchina a stati del prestito**, con il vincolo che nessun punto passi di mano finché entrambe le parti non confermano lo scambio fisico;
- la **comunicazione in tempo reale** tramite Mercure e Turbo Streams, in cui è il server a spingere verso il browser frammenti di HTML già pronti.

## Struttura dell'elaborato

Il [capitolo 2](#contesto-e-stato-dellarte) inquadra il contesto teorico e applicativo: le comunità di lettori sui social, l'economia della condivisione e le piattaforme esistenti da cui ho preso spunto. Il [capitolo 3](#analisi-dei-requisiti) raccoglie i requisiti funzionali e non funzionali. Il [capitolo 4](#tecnologie-e-strumenti) presenta le tecnologie e gli strumenti impiegati, con le ragioni di ciascuna scelta. Il [capitolo 5](#progettazione) descrive l'architettura del sistema e lo schema della base di dati. Il [capitolo 6](#implementazione) entra nel dettaglio dell'implementazione, con estratti del codice. Il [capitolo 7](#infrastruttura-e-ambiente-di-esecuzione) è dedicato all'infrastruttura a container. Il [capitolo 8](#lapplicazione-in-uso) mostra l'applicazione in uso attraverso le sue schermate. Il [capitolo 9](#il-processo-di-sviluppo) ricostruisce il processo di sviluppo, le difficoltà incontrate e le competenze del percorso di studi che ho utilizzato. Il [capitolo 10](#valutazione-dei-risultati) valuta i risultati, i limiti e gli sviluppi futuri, e il [capitolo 11](#conclusioni) tira le somme.
