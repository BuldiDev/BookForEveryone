# Bibliografia e sitografia {.unnumbered}

::: {#refs}
:::

# Appendice A – Installazione e avvio {.unnumbered}

Il progetto richiede soltanto Docker e Docker Compose. Il codice si ottiene dal repository pubblico [@repo].

**Avvio con i dati di prova già presenti.** Il repository include un database dimostrativo già popolato, per cui nella maggior parte dei casi basta un comando:

```bash
docker-compose up -d
```

L'applicazione risponde all'indirizzo `http://localhost:8000`. Le credenziali dell'utente di prova sono riportate nel file `README.md` del repository.

**Primo avvio o ricostruzione da zero.** Su un'immagine appena costruita il volume delle dipendenze è vuoto: le librerie PHP vanno installate prima di popolare il database.

```bash
docker-compose up -d --build
docker compose exec php composer install
.\setup-database.bat
```

Lo script di preparazione del database richiede qualche minuto, perché scarica i libri e le relative copertine da Open Library. I passaggi che esegue sono descritti nella tabella 7.2.

: Tabella A.1 – Comandi di uso frequente.

| Operazione | Comando |
|:------------------------------|:------------------------------------------------|
| Avvio dei servizi | `docker-compose up -d` |
| Arresto dei servizi | `docker-compose down` |
| Aggiornamento dello schema senza perdere i dati | `docker compose exec php php bin/console doctrine:migrations:migrate` |
| Ridistribuzione delle copie tra gli utenti | `docker compose exec php php bin/console app:distribute-books` |
| Elenco delle rotte | `docker compose exec php php bin/console debug:router` |

# Appendice B – Rotte principali dell'applicazione {.unnumbered}

: Tabella B.1 – Rotte principali, raggruppate per area funzionale.

| Metodo | Percorso | Funzione |
|:-----|:-------------------------------|:-------------------------------------|
| GET, POST | `/register` | Registrazione di un nuovo utente |
| GET, POST | `/login` | Accesso |
| GET | `/logout` | Uscita |
| GET | `/onboarding` | Scelta dei generi preferiti |
| POST | `/onboarding/save` | Salvataggio dei generi |
| GET | `/` | Feed, prima pagina |
| GET | `/feed/load-more` | Blocco successivo del feed (JSON) |
| GET | `/search` | Ricerca testuale nel catalogo |
| GET | `/book/fetch-isbn/{isbn}` | Dati bibliografici da ISBN (JSON) |
| GET, POST | `/book/add` | Inserimento di un libro |
| GET | `/book/{id}` | Scheda di dettaglio |
| POST | `/book/{id}/action/{action}` | Like, «letto», «posseggo» (JSON) |
| GET | `/book/{id}/reviews` | Recensioni di un libro (JSON) |
| POST | `/book/{id}/review` | Invio o aggiornamento della recensione |
| GET | `/profile/{username}` | Profilo pubblico |
| GET | `/profile/{username}/followers` | Elenco dei follower |
| GET | `/profile/{username}/following` | Elenco dei seguiti |
| POST | `/user/{id}/follow` | Segui o smetti di seguire |
| GET, POST | `/settings` | Impostazioni del profilo |
| GET | `/borrow` | Pagina «Prendi in prestito» |
| GET | `/borrow/search-books` | Suggerimenti di titoli (JSON) |
| GET | `/borrow/book/{id}/owners` | Proprietari entro il raggio (JSON) |
| POST | `/loan-request/new/{bookId}/{ownerId}` | Nuova offerta |
| POST | `/loan-request/{id}/counter` | Controproposta |
| POST | `/loan-request/{id}/accept` | Accettazione |
| POST | `/loan-request/{id}/decline` | Rifiuto |
| POST | `/loan-request/{id}/cancel` | Annullamento |
| POST | `/loan-request/{id}/confirm-exchange` | Conferma dello scambio |
| GET | `/chat` | Elenco delle conversazioni |
| GET | `/chat/new/{id}` | Apertura di una conversazione |
| GET | `/chat/{id}` | Conversazione |
| POST | `/chat/{id}/send` | Invio di un messaggio |
| GET | `/chat/{id}/loan-action-bar` | Barra delle azioni del prestito |
| GET | `/admin/stats` | Dashboard amministrativa |

# Appendice C – Glossario {.unnumbered}

**API (Application Programming Interface).** Interfaccia con cui un programma mette le proprie funzioni a disposizione di altri programmi; qui, i servizi web di Google Books e Open Library.

**Batch loading.** Caricamento in un'unica interrogazione dei dati relativi a un insieme di elementi, in alternativa a un'interrogazione per ciascun elemento.

**Container.** Ambiente di esecuzione isolato che contiene un servizio e tutto ciò che gli serve per funzionare.

**CSRF (Cross-Site Request Forgery).** Attacco in cui un sito esterno induce il browser dell'utente a inviare una richiesta a sua insaputa; si previene con un token che solo il sito legittimo conosce.

**Culture Points (CP).** Valuta interna della piattaforma, priva di valore economico, che si guadagna inserendo libri e si scambia nei prestiti.

**Dependency injection.** Tecnica per cui una classe riceve dall'esterno gli oggetti di cui ha bisogno invece di crearli da sé.

**Entità.** Classe PHP che Doctrine fa corrispondere a una tabella del database.

**Hub.** Nel protocollo Mercure, il server che riceve gli aggiornamenti pubblicati e li consegna ai client in ascolto.

**ISBN (International Standard Book Number).** Codice numerico internazionale che identifica un'edizione di un libro.

**JWT (JSON Web Token).** Formato compatto per trasmettere informazioni firmate, usato qui per autorizzare la pubblicazione sull'hub.

**Lazy loading.** Caricamento di un dato rimandato al momento in cui viene effettivamente usato.

**ORM (Object-Relational Mapping).** Tecnica che fa corrispondere gli oggetti di un programma alle righe di un database relazionale.

**Query N+1.** Difetto prestazionale in cui, dopo una query che restituisce N elementi, se ne eseguono altre N per i dati collegati.

**SSE (Server-Sent Events).** Tecnologia standard con cui un server invia eventi a un browser lungo una connessione HTTP che resta aperta.

**Turbo Stream.** Frammento HTML che indica a Turbo quale modifica applicare a un elemento della pagina.
