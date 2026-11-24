# Implementazione

Questo capitolo descrive come sono state realizzate le funzionalità principali. Per ciascuna riporto il problema da risolvere, la soluzione adottata e gli estratti di codice più significativi, presi dal repository del progetto [@repo] e accorciati dove serviva per farli stare nella pagina.

## Registrazione, posizione e onboarding

Il modulo di registrazione raccoglie email, username e password e, insieme a questi, due informazioni meno consuete: la posizione dell'utente e il consenso al trattamento dei dati.

La posizione viene scelta su una mappa Leaflet inserita nel modulo. L'utente può digitare un indirizzo, che viene cercato tramite il servizio Nominatim con una breve attesa tra un tasto e l'altro per non inviare una richiesta a ogni carattere, oppure toccare direttamente un punto della mappa. In entrambi i casi un segnaposto si sposta nel punto scelto e le coordinate vengono scritte in due campi nascosti del form, che viaggiano verso il server insieme agli altri dati.

```javascript
function setMarker(lat, lng) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng]).addTo(map);
    latInput.value = lat;
    lngInput.value = lng;
}

map.on('click', function (e) {
    setMarker(e.latlng.lat, e.latlng.lng);
    map.setView(e.latlng, Math.max(map.getZoom(), 13));
});
```

Ho preferito far scegliere il punto all'utente invece di leggere in automatico la posizione del dispositivo: in questo modo ciascuno decide con quale precisione farsi trovare, e può indicare una piazza o una via vicina anziché il portone di casa.

Lato server, il controller trasforma la password in un hash prima di salvarla e registra il momento del consenso. La casella dell'informativa è un campo del form non collegato all'entità e con un vincolo che la rende obbligatoria: senza spunta la registrazione non va a buon fine.

```php
if ($form->isSubmitted() && $form->isValid()) {
    // Hasha la password in chiaro prima di salvarla nel database
    $user->setPassword(
        $passwordHasher->hashPassword(
            $user, $form->get('plainPassword')->getData()
        )
    );
    // Registra il momento in cui l'utente ha prestato il consenso
    $user->setPrivacyConsentAt(new \DateTimeImmutable());

    $em->persist($user);
    $em->flush();
    return $this->redirectToRoute('app_login');
}
```

L'algoritmo di hash non è fissato nel codice: la configurazione di sicurezza usa l'impostazione `auto`, con cui Symfony sceglie l'algoritmo più robusto disponibile e, quando in futuro ne adotterà uno migliore, ricalcola l'hash al primo accesso dell'utente.

Il progetto include anche il componente per la verifica dell'indirizzo email, ed è per questo che l'entità `User` ha il flag `isVerified`. La verifica funziona, ma nella versione consegnata l'ho tenuta disattivata per non dover confermare un indirizzo ogni volta che si crea un utente di prova: chi installa il progetto in locale può così registrarsi ed entrare subito.

Dopo il primo accesso scatta l'onboarding. Il controller del feed controlla il flag `onboardingCompleted` e, se è falso, rimanda alla schermata in cui l'utente deve scegliere almeno tre generi tra i venti disponibili. È un passaggio necessario perché il feed risulti già personalizzato alla prima apertura.

## Inserimento di un libro tramite ISBN

L'inserimento di un libro ruota attorno al codice ISBN. Quando l'utente lo inserisce e avvia la ricerca, il browser chiama in modo asincrono una rotta dedicata, che esegue una ricerca a cascata su quattro livelli, rappresentata nella figura 6.1.

![Figura 6.1 – Flusso della ricerca dei dati bibliografici a partire dall'ISBN.](figure/d-isbn.png){width=15cm}

Per prima cosa l'ISBN viene normalizzato, togliendo trattini e spazi, e cercato nel catalogo locale: se il libro esiste già, l'interfaccia lo segnala e propone di aprire la scheda esistente invece di crearne un duplicato. Altrimenti si passa alle fonti esterne, ma attraverso la cache: il risultato di ogni ricerca resta in Redis per ventiquattro ore, così digitare due volte lo stesso codice non costa due chiamate di rete.

```php
// Normalizza l'ISBN rimuovendo trattini e spazi
$isbn = preg_replace('/[^0-9X]/i', '', $isbn);

// Verifica se il libro è già presente nel database
$existing = $bookRepo->findByIsbn($isbn);
if ($existing) {
    return new JsonResponse(['exists' => true,
                             'bookId' => $existing->getId()]);
}

// Recupera i dati dalle API esterne (con cache di 24 ore)
$bookData = $cache->get('isbn_api_' . $isbn,
    function (ItemInterface $item) use ($isbn, $httpClient) {
        $item->expiresAfter(86400);
        return $this->fetchBookDataFromApis($isbn, $httpClient);
    });
```

La funzione che interroga le fonti esterne prova prima Google Books e, se non ottiene nulla, ripiega su Open Library. Le due fonti organizzano i dati in modo diverso, per cui il codice li riporta a una forma comune: titolo, autore, descrizione, numero di pagine, editore, anno, copertina e categorie. Con Open Library servono fino a tre richieste, perché il nome dell'autore e la descrizione stanno in documenti separati da quello dell'edizione. Ogni chiamata ha un tempo massimo di attesa ed è racchiusa in un blocco che intercetta gli errori: se una fonte non risponde si passa alla successiva, e la mancanza di un dato facoltativo non fa fallire l'intera ricerca.

```php
// --- Tentativo 1: Google Books API ---
try {
    $response = $httpClient->request('GET',
        'https://www.googleapis.com/books/v1/volumes',
        ['query' => ['q' => 'isbn:' . $isbn], 'timeout' => 10]);

    if ($response->getStatusCode() === 200) {
        $data = $response->toArray();
        if (!empty($data['items'])) {
            $info = $data['items'][0]['volumeInfo'] ?? [];
            $bookData = [
                'title'     => $info['title'] ?? '',
                'authors'   => $info['authors'][0] ?? '',
                'pageCount' => $info['pageCount'] ?? null,
                'coverUrl'  => $info['imageLinks']['thumbnail'] ?? null,
                // ...
            ];
        }
    }
} catch (\Exception $e) {
    // Google Books non disponibile, si tenta con Open Library
}
```

I campi del form si riempiono da soli con quello che è stato trovato. È stato lavorando su questa parte che mi sono accorto di una cosa poco piacevole: i dati che arrivavano dalle API esterne erano spesso monchi e le copertine, quando c'erano, lasciavano molto a desiderare. Quel limite, però, mi ha fatto cambiare prospettiva. Invece di intestardirmi a cercare fonti migliori, ho pensato di girare il problema agli utenti stessi, dando loro modo di caricare foto e di riempire a mano i campi che le API non coprivano. Da lì viene l'impostazione dell'intera piattaforma: il catalogo lo costruisce chi la usa, e più gente partecipa più diventa ricco.

Al momento del salvataggio il controller ripete il controllo sui duplicati, interroga di nuovo le fonti esterne per i metadati, salva la copertina e le eventuali foto della copia e accredita dieci Culture Points a chi ha inserito il libro. Riscaricare i metadati lato server, invece di fidarsi di quelli arrivati dal browser, evita che i campi precompilati vengano alterati prima dell'invio. I file caricati vengono rinominati con una versione «pulita» del nome originale seguita da un identificativo univoco, così due foto con lo stesso nome non si sovrascrivono, e le miniature nei formati usati dall'interfaccia vengono generate alla prima richiesta e poi conservate.

## Il feed personalizzato

Il primo vero scoglio è stato il feed personalizzato. Sulla carta sembrava la parte più semplice: mostrare a ogni utente i libri dei generi che aveva scelto durante la registrazione. Nella pratica ha consumato più tempo di qualunque altra parte del progetto. I problemi da risolvere erano tre: la lentezza, le ripetizioni e la fluidità dello scorrimento. La figura 6.2 mostra il percorso completo di una richiesta di feed nella versione finale.

![Figura 6.2 – Passaggi eseguiti dal server per comporre un blocco del feed.](figure/d-feed.png){width=15cm}

### Il problema delle query N+1

Il problema iniziale era la lentezza. Per ogni singolo libro partivano query separate, e servivano solo a sapere se quell'utente lo avesse messo tra i like, recensito, letto o posseduto, oltre al voto medio e al numero di like da mostrare sulla scheda. È il classico schema delle query N+1: una query per ottenere l'elenco e poi un gruppo di query per ciascun elemento, con il risultato che il numero di accessi al database cresce in proporzione al numero di libri mostrati.

Ho rifatto il tutto usando il caricamento in blocco (*batch loading*). Il controller ricava prima gli identificativi dei libri del blocco e poi chiede, una volta sola per tutti, le quattro informazioni di contorno.

```php
// Carica in blocco interazioni, statistiche, seguiti e recensioni:
// poche query per l'intero blocco invece di un gruppo per ogni libro
$userBooks    = $userBookRepo->findByUserForBooks($user, $bookIds);
$bookStats    = $bookRepo->getBookStats($bookIds);
$followingIds = $userRepo->getFollowingIds($user);
$userReviews  = $reviewRepo->findByUserForBooks($user, $bookIds);
```

Ciascun metodo restituisce un array indicizzato per identificativo del libro, così che nel template basti un accesso diretto per sapere, per esempio, se il cuore di una scheda va disegnato pieno o vuoto. Le statistiche dei libri sono calcolate con due query SQL aggregate, una per i voti medi e una per i like.

```php
$rows = $conn->executeQuery(
    'SELECT book_id, ROUND(AVG(rating), 1) AS avg_rating
       FROM review WHERE book_id IN (:ids) GROUP BY book_id',
    ['ids' => $bookIds],
    ['ids' => ArrayParameterType::INTEGER]
)->fetchAllAssociative();
```

Il numero di chiamate al database è crollato. La figura 6.3 mette a confronto i due approcci con un conteggio fatto sul codice: nel primo caso le query crescono di quattro unità per ogni libro in più, nel secondo restano le stesse qualunque sia la dimensione del blocco. Per un blocco di trenta libri si passa da oltre cento interrogazioni a sette. A queste si aggiunge la cache: le statistiche dei libri restano valide per sessanta secondi e l'elenco degli utenti seguiti per centoventi, per cui in molte richieste alcune di quelle sette query non partono nemmeno.

![Figura 6.3 – Query SQL per blocco di feed: una query per libro contro caricamento in blocco (conteggio sul codice, cache esclusa).](figure/g-query.png){width=14cm}

### Selezione e ordinamento in due fasi

Anche la query principale, quella che decide quali libri mostrare, è stata riscritta. Lavora in due fasi. Nella prima una query SQL nativa restituisce soltanto gli identificativi, già ordinati e limitati al numero richiesto; nella seconda una singola query DQL carica le entità complete per quegli identificativi, insieme ad autore dell'inserimento, generi e foto.

```php
// pref = 0 se il libro ha un genere preferito, 1 altrimenti
$sql = 'SELECT b.id, '
     . 'MAX(CASE WHEN bg2.genre_id IS NOT NULL THEN 0 ELSE 1 END) AS pref, '
     . 'CRC32(CONCAT(b.id, :seed)) AS rnd '
     . 'FROM book b '
     . 'LEFT JOIN book_genre bg2 ON bg2.book_id = b.id '
     . '      AND bg2.genre_id IN (:genreIds) ';

if (!empty($excludeIds)) {
    $sql .= 'WHERE b.id NOT IN (:excludeIds) ';
}
$sql .= 'GROUP BY b.id ORDER BY pref ASC, rnd ASC LIMIT ' . (int) $limit;
```

Ci sono due accorgimenti in queste poche righe. Il primo è la colonna `pref`: vale zero se il libro appartiene ad almeno uno dei generi preferiti dell'utente e uno altrimenti, e ordinare prima su di essa porta in cima i libri dei generi scelti senza escludere gli altri, che compaiono quando i primi sono finiti. Il secondo è l'ordinamento pseudo-casuale. Un semplice `ORDER BY RAND()` darebbe un ordine diverso a ogni richiesta, mentre a me serviva un ordine che sembrasse casuale ma restasse lo stesso per tutta la sessione di scorrimento. L'ho ottenuto calcolando il CRC32 dell'identificativo del libro concatenato a un numero, il *seed*, generato all'apertura del feed e conservato in sessione: a parità di seed l'ordine è sempre lo stesso, cambiando seed cambia completamente.

La separazione in due fasi evita un problema noto di Doctrine: quando una query con `LIMIT` include relazioni a molti, il limite viene applicato alle righe del risultato e non agli oggetti, restituendo meno libri di quelli chiesti. Limitando prima gli identificativi e caricando poi le entità il problema non si pone. Resta solo da ripristinare l'ordine, perché la clausola `IN` non lo garantisce; lo fa un ordinamento in memoria sugli oggetti caricati.

### La cronologia dei libri già visti

Sistemata la lentezza è arrivato il secondo problema. Il feed continuava a riproporre sempre gli stessi titoli, semplicemente perché non si ricordava cosa avesse già fatto vedere. A quel punto ho tirato dentro Redis e l'ho usato per segnarmi i libri già passati sotto gli occhi di ciascun utente. Quando un libro compare nel feed, il suo identificativo viene aggiunto a un insieme Redis intestato a quell'utente e ci resta per trenta giorni; le query successive lo escludono da sole.

Tutta la logica sta in un servizio di un centinaio di righe, `FeedHistoryService`, di cui riporto i due metodi principali.

```php
/** Aggiunge un blocco di libri all'insieme dei libri già visti. */
public function markBooksAsSeen(User $user, array $bookIds): void
{
    if (empty($bookIds)) {
        return;
    }
    $key = sprintf('user:%d:seen_books', $user->getId());
    $this->redis->sadd($key, $bookIds);

    // Rinnova la scadenza a 30 giorni a ogni aggiornamento
    $this->redis->expire($key, 60 * 60 * 24 * 30);
}

/** Restituisce gli ID dei libri già mostrati all'utente. */
public function getSeenBookIds(User $user): array
{
    $key = sprintf('user:%d:seen_books', $user->getId());
    return array_map('intval', $this->redis->smembers($key));
}
```

Questa scelta ha una conseguenza che all'inizio non avevo previsto e che si è rivelata un vantaggio: il feed non ha più bisogno di un numero di pagina. Ogni richiesta chiede semplicemente «i prossimi trenta libri che questo utente non ha ancora visto». Con la paginazione classica basata su `OFFSET`, se nel frattempo qualcuno inserisce un libro tutte le pagine successive slittano di una posizione e l'utente rischia di vedere un titolo due volte o di saltarne uno; escludendo gli identificativi già mostrati questo non può succedere.

Se poi qualcuno arriva in fondo a tutto ciò che è disponibile, la cronologia si svuota e si riparte da capo. Il controller se ne accorge quando la query restituisce meno libri del previsto: azzera l'insieme e completa il blocco con una seconda selezione.

```php
$seenBookIds = $feedHistory->getSeenBookIds($user);
$books = $bookRepo->getFeedForUser($user, 1, 30, $seed, $seenBookIds);

// Se i libri non visti sono terminati, azzera la storia e ricomincia
if (count($books) < 30 && !empty($seenBookIds)) {
    $feedHistory->resetHistory($user);
    $remaining  = 30 - count($books);
    $alreadyIds = array_map(fn($b) => $b->getId(), $books);
    $extra = $bookRepo->getFeedForUser($user, 1, $remaining,
                                       $seed, $alreadyIds);
    $books = array_merge($books, $extra);
}
```

Accanto alla sezione «Per te» il feed ha una seconda sezione, «Seguiti», che mostra i libri con cui hanno interagito gli utenti seguiti o che questi hanno inserito, dal più recente. Usa lo stesso meccanismo di cronologia e lo stesso caricamento in blocco; cambia soltanto la query di selezione.

### Lo scorrimento infinito

Terzo nodo, lo scorrimento infinito. Avevo guardato come si comportano YouTube e Instagram e nella prima versione il server rispondeva con un libro per volta a ogni caricamento. Impostazione sbagliata, perché ogni richiesta si portava dietro dai 200 ai 300 millisecondi di latenza anche in locale. Se scorri veloce, quell'attesa rende l'esperienza invivibile.

Ho quindi introdotto un caricamento a blocchi, in cui ogni richiesta restituisce più libri in una sola risposta. Sulla dimensione del blocco sono tornato più volte, provando sia quindici sia trenta elementi; nella versione consegnata ogni risposta ne contiene trenta. La figura 6.4 mostra quanto cambia il numero di richieste al server: per scorrere centoventi libri ne bastano quattro invece di centoventi.

![Figura 6.4 – Richieste HTTP necessarie in funzione dei libri scorsi: un libro per richiesta contro blocchi da trenta.](figure/g-richieste.png){width=14cm}

Restava però un'interruzione percepibile alla fine di ogni blocco, che ho eliminato con un precaricamento: la richiesta del blocco successivo non parte quando l'utente arriva in fondo, ma quando raggiunge la metà del blocco corrente, cioè il quindicesimo libro. In questo modo i quindici successivi che ha ancora davanti gli danno tutto il tempo perché i nuovi contenuti arrivino in background, e lo scorrimento resta continuo e senza pause visibili.

```javascript
const onScroll = () => {
    if (isLoading || !hasMore) return;

    const items = feed.querySelectorAll('.feed-item');
    if (!items.length) return;

    // Punto di innesco: metà del blocco corrente (caricamento anticipato)
    const batchSize   = items.length - batchStart;
    const triggerIdx  = batchStart + Math.floor(batchSize / 2);
    const triggerItem = items[Math.min(triggerIdx, items.length - 1)];

    const rect          = triggerItem.getBoundingClientRect();
    const containerRect = scrollContainer.getBoundingClientRect();
    if (rect.top < containerRect.bottom) {
        loadMoreBooks();
    }
};
```

La funzione `loadMoreBooks` chiama la rotta `/feed/load-more`, che esegue esattamente gli stessi passaggi del caricamento iniziale ma invece della pagina intera restituisce un documento JSON con due campi: il frammento HTML delle nuove schede, già pronto per essere accodato, e un indicatore che dice se ci sono altri libri. Due variabili di stato, `isLoading` e `hasMore`, impediscono che uno scorrimento rapido faccia partire più richieste contemporaneamente.

## Le interazioni sociali

Nella fase di costruzione della struttura di base ho aggiunto le interazioni sociali: mettere like a un libro, seguire altri utenti, segnare un libro come letto o come posseduto, scrivere una recensione. Per gestirle tutte ho usato chiamate asincrone via Fetch API, così nessuno è costretto a ricaricare la pagina a ogni clic. Il funzionamento è sempre lo stesso. L'azione parte, aggiorna il database e torna indietro sotto forma di risposta JSON, e il frontend cambia lo stato del pulsante sul momento.

Like, «letto» e «posseggo» passano da un'unica rotta, che riceve il tipo di azione come parametro e inverte il flag corrispondente sul record `UserBook`, creandolo se non esiste ancora.

```php
#[Route('/book/{id}/action/{action}', name: 'app_book_action',
        methods: ['POST'], requirements: ['id' => '\d+'])]
public function action(Book $book, string $action, /* ... */): JsonResponse
{
    $userBook = $userBookRepo->findOrCreate($this->getUser(), $book);

    match ($action) {
        'like' => $userBook->toggleLike(),
        'read' => $userBook->toggleHasRead(),
        'own'  => $userBook->toggleOwnsPhysically(),
        default => null,
    };
    $em->persist($userBook);
    $em->flush();

    return new JsonResponse([
        'liked'          => $userBook->isLiked(),
        'hasRead'        => $userBook->hasRead(),
        'ownsPhysically' => $userBook->ownsPhysically(),
        'likesCount'     => $book->getLikesCount(),
    ]);
}
```

Il parametro `Book $book` nella firma è un altro automatismo di Symfony: il framework legge l'identificativo dall'indirizzo, carica l'entità corrispondente e, se non esiste, risponde da solo con un errore 404.

La recensione segue la stessa logica ma con una regola in più: se l'utente ha già recensito quel libro, la nuova valutazione aggiorna la precedente invece di aggiungersene un'altra. Il vincolo di unicità sul database resta come rete di sicurezza. Il follow, infine, dopo aver modificato la relazione invalida le voci di cache che ne dipendono, cioè i contatori del profilo di entrambi gli utenti e l'elenco dei seguiti di chi ha fatto clic, così che il dato mostrato non resti indietro di due minuti rispetto a quello reale.

## La ricerca geografica dei prestatori

Nella ricerca dei libri da prendere in prestito ho curato in modo particolare la vicinanza geografica. Il volume va consegnato a mano, e ordinare i risultati partendo da chi è più vicino aumenta le probabilità che lo scambio avvenga davvero.

La ricerca avviene in due passi. Nel primo l'utente digita un titolo e sceglie il libro da un elenco di suggerimenti. Nel secondo il server restituisce gli utenti che possiedono fisicamente quel libro entro un certo raggio, che parte da 25 chilometri e può essere portato fino a 100 con un cursore.

La distanza tra due punti sulla superficie terrestre, note la latitudine φ e la longitudine λ di ciascuno, è la lunghezza dell'arco di cerchio massimo che li congiunge (distanza ortodromica). Considerando la Terra una sfera di raggio *R* = 6371 km, si calcola con la legge sferica dei coseni:

::: {custom-style="Formula"}
*d* = *R* · arccos( sin φ~1~ · sin φ~2~ + cos φ~1~ · cos φ~2~ · cos(λ~2~ − λ~1~) )
:::

È una formulazione equivalente alla più nota formula dell'emisenoverso (*haversine*), che viene preferita quando i punti sono molto vicini perché numericamente più stabile [@sinnott1984]. Alle distanze che interessano qui, dell'ordine dei chilometri, la differenza tra le due è trascurabile; l'unica cautela necessaria è limitare a 1 l'argomento dell'arcocoseno, che per effetto degli arrotondamenti può superarlo di pochissimo quando i due punti coincidono, producendo un errore.

Ho scelto di far eseguire il calcolo direttamente al database, dentro la query, invece di caricare tutti i possessori in memoria e filtrarli con il PHP: in questo modo filtro per raggio e ordinamento per distanza avvengono in un solo passaggio.

```sql
SELECT b.id AS book_id, u.id AS user_id, u.latitude, u.longitude,
       (6371 * ACOS(LEAST(1,
            COS(RADIANS(:lat)) * COS(RADIANS(u.latitude))
              * COS(RADIANS(u.longitude) - RADIANS(:lng))
          + SIN(RADIANS(:lat)) * SIN(RADIANS(u.latitude))
       ))) AS distance
  FROM book b
  INNER JOIN user_book ub ON ub.book_id = b.id
  INNER JOIN `user` u     ON u.id = ub.user_id
 WHERE ub.owns_physically = 1
   AND u.latitude IS NOT NULL AND u.longitude IS NOT NULL
   AND b.title LIKE :search
HAVING distance <= :radius
 ORDER BY b.title ASC, distance ASC
```

La condizione sulla distanza sta nella clausola `HAVING` e non in `WHERE` perché `distance` è una colonna calcolata, che al momento della valutazione di `WHERE` non esiste ancora. Il risultato viene poi completato con due query in blocco, una per i libri e una per gli utenti, e restituito al browser in formato JSON; dall'elenco viene tolto l'utente che sta cercando, che altrimenti si vedrebbe proposto tra i prestatori di un libro che possiede lui stesso.

Nel browser i risultati compaiono in due forme: un elenco laterale, con la distanza di ciascun proprietario arrotondata al decimo di chilometro, e una mappa Leaflet centrata sulla posizione dell'utente, con un cerchio che rappresenta il raggio di ricerca e un segnaposto per ogni proprietario. Accanto a ciascuno c'è il pulsante per avviare la trattativa.

## Il sistema di prestito

La macchina a stati progettata nel capitolo 5 è realizzata da un unico controller, `LoanRequestController`, con un metodo per ciascuna transizione. Tutti i metodi seguono la stessa scaletta: verificano il token CSRF, verificano che chi agisce sia uno dei due partecipanti, verificano che lo stato corrente ammetta quella transizione, modificano la richiesta, creano il messaggio di sistema, salvano e pubblicano l'aggiornamento.

L'apertura di una trattativa è il metodo con più controlli.

```php
// Verifica che il proprietario abbia ancora il libro
if (!$userBook || !$userBook->ownsPhysically()) {
    $this->addFlash('error', 'Il proprietario non ha più questo libro.');
    return $this->redirectToRoute('app_borrow');
}
// Verifica saldo Culture Points sufficiente
if ($me->getCulturePoints() < 1) {
    $this->addFlash('error', 'Ti serve almeno 1 Culture Point.');
    return $this->redirectToRoute('app_borrow');
}
// Evita richieste duplicate: se esiste già, porta alla chat
$existing = $loanRepo->findActiveBetweenForBook($me, $ownerUser, $bookId);
if ($existing) {
    return $this->redirectToRoute('app_chat_show',
        ['id' => $existing->getConversation()->getId()]);
}

// Scala 1 Culture Point al richiedente come deposito dell'offerta
$me->addCulturePoints(-1);
```

Superati i controlli, il metodo recupera la conversazione tra i due utenti o la crea se non esiste, costruisce la richiesta con l'offerta iniziale e registra che l'ultima mossa è del richiedente.

La regola dell'alternanza è applicata sia nella controproposta sia nell'accettazione con la stessa condizione, che si appoggia a un metodo dell'entità.

```php
// Impedisce di rispondere alla propria offerta
if ($loanRequest->isLastOfferByUser($me)) {
    $this->addFlash('error', 'Devi aspettare la risposta dell\'altra parte.');
    return $this->redirectToRoute('app_chat_show', [/* ... */]);
}

$points = max(1, $request->request->getInt('offered_points', 1));
$loanRequest->setOfferedPoints($points);
$loanRequest->setLastOfferBy(
    $loanRequest->isRequester($me) ? 'requester' : 'owner'
);
```

Il passaggio più delicato è la conferma dello scambio. Il metodo registra la conferma di chi ha premuto il pulsante e controlla se a quel punto ci sono entrambe: solo in quel caso chiude la trattativa, trasferisce i punti e toglie il libro dalla disponibilità del proprietario.

```php
if ($loanRequest->isRequester($me)) {
    $loanRequest->setRequesterConfirmed(true);
} elseif ($loanRequest->isOwner($me)) {
    $loanRequest->setOwnerConfirmed(true);
} else {
    throw $this->createAccessDeniedException();
}

// Se entrambi hanno confermato, completa lo scambio e trasferisci i punti
if ($loanRequest->isRequesterConfirmed()
    && $loanRequest->isOwnerConfirmed()) {
    $loanRequest->setStatus(LoanRequest::STATUS_COMPLETED);

    $points = $loanRequest->getOfferedPoints() ?? 0;
    $loanRequest->getRequester()->addCulturePoints(-$points);
    $loanRequest->getOwner()->addCulturePoints($points);

    // Il libro non è più disponibile presso il proprietario
    $userBook = $userBookRepo->findByUserAndBook(
        $loanRequest->getOwner(), $loanRequest->getBook());
    if ($userBook) {
        $userBook->setOwnsPhysically(false);
    }
}
$em->flush();
```

Tutte le modifiche di una transizione, cioè lo stato della richiesta, i saldi dei due utenti, il flag di possesso e il nuovo messaggio, vengono scritte con una sola chiamata a `flush()`. Doctrine le raccoglie in un'unica transazione: o vanno a buon fine tutte, o nessuna. Non può quindi succedere che i punti vengano scalati a un utente senza essere accreditati all'altro.

Ogni transizione produce infine un messaggio di sistema. Un metodo privato del controller lo costruisce associandolo alla richiesta e salvando nel campo JSON i dati dell'evento.

```php
$msg = $this->createLoanMessage($em, $conversation, $me, $loanRequest,
    'loan_offer', 'Offerta: ' . $offeredPoints . ' CP', [
        'points'    => $offeredPoints,
        'bookTitle' => $book->getTitle(),
        'bookCover' => $book->getCoverUrl(),
    ]);
```

## La chat in tempo reale

L'idea della chat in tempo reale viene da una constatazione piuttosto ovvia: due sconosciuti che devono mettersi d'accordo su dove e quando incontrarsi hanno bisogno di parlarsi direttamente. Se avessi delegato questo passaggio a una serie di notifiche o di email, i tempi si sarebbero allungati e parecchie trattative sarebbero morte per strada. L'obiettivo era chiaro fin dall'inizio, cioè che i messaggi comparissero all'istante senza toccare il tasto di ricarica.

La figura 6.5 mostra che cosa succede quando un utente invia un messaggio.

![Figura 6.5 – Sequenza di invio e consegna di un messaggio di chat.](figure/d-sequenza.png){width=15cm}

All'apertura della conversazione il template dichiara due sorgenti di aggiornamenti, una per i messaggi e una per lo stato della trattativa. Turbo le trasforma in altrettante connessioni verso l'hub Mercure.

```twig
<turbo-stream-source
    src="{{ mercure('chat/conversation/' ~ conversation.id) }}">
</turbo-stream-source>
<turbo-stream-source
    src="{{ mercure('chat/conversation/' ~ conversation.id ~ '/loan') }}">
</turbo-stream-source>
```

L'invio è un normale form. Il controller verifica che il mittente partecipi alla conversazione e che il token CSRF sia valido, salva il messaggio e pubblica sull'hub il frammento HTML che lo rappresenta.

```php
$message = new Message();
$message->setConversation($conversation);
$message->setSender($me);
$message->setContent($content);
$em->persist($message);
$em->flush();

// Renderizza il frammento HTML e lo pubblica sul canale Mercure
$html = $this->renderView('chat/message_stream.html.twig',
                          ['message' => $message]);
$hub->publish(new Update(
    'chat/conversation/' . $conversation->getId(),
    $html,
));
```

Il frammento è un Turbo Stream: dice a Turbo di aggiungere il contenuto in coda all'elemento che contiene l'elenco dei messaggi.

```twig
<turbo-stream action="append" target="messages-list">
    <template>
        <div class="chat-bubble" id="message-{{ message.id }}"
             data-sender-id="{{ message.sender.id }}">
            <div class="chat-bubble__content">{{ message.content }}</div>
            <div class="chat-bubble__time">
                {{ message.createdAt|date('H:i') }}
            </div>
        </div>
    </template>
</turbo-stream>
```

Mettendo insieme le due cose, ogni nuovo messaggio compare in fondo alla chat nel momento esatto in cui arriva il segnale da Mercure, senza alcun intervento da parte dell'utente.

C'è un dettaglio che mi ha fatto perdere un po' di tempo. Lo stesso identico frammento arriva a entrambi i browser, ma un messaggio va disegnato a destra per chi l'ha scritto e a sinistra per chi lo riceve, e il server, quando lo pubblica, non può sapere chi lo leggerà. Ho risolto lasciando nel frammento solo l'identificativo del mittente e facendo decidere al browser: un `MutationObserver` controlla ogni elemento appena aggiunto, confronta il mittente con l'utente corrente e assegna la classe giusta. Lo stesso osservatore scarta gli eventuali doppioni, che possono presentarsi quando il messaggio arriva sia dal canale in tempo reale sia dal ricaricamento della pagina, e fa scorrere la conversazione fino in fondo.

```javascript
if (node.classList.contains('chat-bubble')) {
    const senderId = node.dataset.senderId;
    node.classList.remove('chat-bubble--mine', 'chat-bubble--theirs');
    node.classList.add(senderId === currentUserId
        ? 'chat-bubble--mine' : 'chat-bubble--theirs');
}
```

Lo stesso problema, in forma più seria, riguarda la barra delle azioni del prestito: i pulsanti disponibili dipendono da chi guarda (chi ha fatto l'ultima offerta vede «in attesa di risposta», l'altro vede «accetta», «rifiuta» e «controproposta»). Qui non bastava cambiare una classe, e ho adottato una soluzione diversa. Sul secondo canale non viaggia la barra, ma un semplice segnale che dice «la trattativa è cambiata». Quando il browser lo riceve, chiede al server la propria versione della barra con una normale richiesta autenticata, e la sostituisce a quella vecchia. In questo modo ciascuno dei due vede i pulsanti che gli spettano, e l'autorizzazione resta dov'è giusto che stia, cioè sul server.

## La dashboard amministrativa

Chi amministra la piattaforma ha a disposizione una dashboard da cui seguirne l'andamento. La pagina mostra quattro indicatori globali (libri catalogati, utenti iscritti, recensioni scritte, richieste di prestito), quattro classifiche dei primi dieci titoli per voto medio, like, lettori e recensioni, disegnate come grafici a barre orizzontali, e un catalogo completo con ricerca.

Tutti i dati delle classifiche arrivano da una sola query, che contiene un accorgimento degno di nota. Collegare direttamente la tabella dei libri sia alle recensioni sia alle interazioni moltiplicherebbe le righe tra loro (un libro con dieci recensioni e venti like produrrebbe duecento righe) falsando conteggi e medie. Per evitarlo, ciascuna delle due tabelle viene prima aggregata per libro in una sottoquery, e solo i risultati già aggregati vengono collegati al libro.

```sql
SELECT b.id, b.title,
       ROUND(r_agg.avg_rating, 1)        AS avg_rating,
       COALESCE(r_agg.reviews_count, 0)  AS reviews_count,
       COALESCE(ub_agg.likes_count, 0)   AS likes_count,
       COALESCE(ub_agg.readers_count, 0) AS readers_count
  FROM book b
  LEFT JOIN (SELECT book_id, AVG(rating) AS avg_rating,
                    COUNT(*) AS reviews_count
               FROM review GROUP BY book_id) r_agg
         ON r_agg.book_id = b.id
  LEFT JOIN (SELECT book_id, SUM(liked) AS likes_count,
                    SUM(has_read) AS readers_count
               FROM user_book GROUP BY book_id) ub_agg
         ON ub_agg.book_id = b.id
```

L'accesso è riservato: l'intero controller è marcato con l'attributo `#[IsGranted('ROLE_ADMIN')]`, e la stessa regola è ripetuta nella configurazione di sicurezza per tutti gli indirizzi che iniziano con `/admin`.

## Sicurezza e riservatezza

La privacy l'ho impostata in maniera piuttosto rigida. Senza un account non si vede niente di niente; solo dopo l'accesso si arriva alla parte in cui compaiono i dati personali degli altri utenti, la loro posizione compresa. Discorso opposto per il codice, che ho lasciato su un repository Git aperto a chiunque, schema del database e campi geospaziali inclusi: la riservatezza riguarda i dati delle persone, non il funzionamento del sistema.

La regola è scritta una volta sola, nella configurazione di sicurezza di Symfony, come elenco di percorsi e ruoli richiesti. Viene applicata la prima regola che corrisponde, per cui l'ultima riga funziona da regola generale.

```yaml
access_control:
    - { path: ^/login,       roles: PUBLIC_ACCESS }
    - { path: ^/register,    roles: PUBLIC_ACCESS }
    - { path: ^/media/cache, roles: PUBLIC_ACCESS }
    - { path: ^/admin,       roles: ROLE_ADMIN }
    - { path: ^/,            roles: ROLE_USER }
```

Alla regola generale si aggiungono i controlli puntuali nei controller. Ogni operazione su una conversazione verifica che l'utente ne faccia parte, e ogni transizione del prestito verifica che l'utente sia il richiedente o il proprietario: conoscere l'identificativo di una conversazione altrui non basta per leggerla né per intervenire.

Le altre misure adottate sono quelle di base per un'applicazione web:

- **password**: conservate soltanto come hash, mai in chiaro, con algoritmo scelto e aggiornato dal framework;
- **CSRF**: tutti i form e tutte le azioni che modificano dati richiedono un token che il server genera e verifica, così che un sito esterno non possa far eseguire operazioni all'utente a sua insaputa [@owaspcsrf]; ogni azione ha un proprio identificativo di token, e quelli del prestito includono anche il numero della richiesta;
- **SQL injection**: tutte le query, comprese quelle native, passano i valori come parametri e non li concatenano mai al testo dell'istruzione;
- **XSS**: i template Twig applicano l'escape a tutto ciò che stampano, compresi i testi dei messaggi e delle recensioni scritti dagli utenti;
- **sessione**: il login prevede l'opzione «ricordami», con una durata limitata a una settimana;
- **dati personali**: consenso esplicito alla registrazione, con data e ora conservate, come richiesto dal principio di responsabilizzazione del regolamento europeo [@gdpr2016].
