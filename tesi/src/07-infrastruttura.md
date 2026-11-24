# Infrastruttura e ambiente di esecuzione

Tutto il progetto gira su Docker Compose, e la scelta era voluta: volevo che chiunque potesse tirare su la propria istanza di BookForEveryone in locale con un comando solo, abbassando il più possibile la barriera per chi avesse voglia di provarla o di contribuire. In questo capitolo descrivo come sono composti i servizi, quali problemi mi ha dato la containerizzazione e come viene preparato il database di prova.

## La composizione dei servizi

Il file `compose.yaml` descrive i sei servizi dell'applicazione, la rete che li collega e i volumi in cui conservano i dati. La tabella 7.1 li riassume.

: Tabella 7.1 – Servizi definiti in Docker Compose.

| Servizio | Immagine | Compito | Porta esposta |
|:-----------|:------------------|:-----------------------------------|:------:|
| `nginx` | `nginx:alpine` | Web server e file statici | 8000 |
| `php` | costruita dal `Dockerfile` | Esecuzione dell'applicazione Symfony | – |
| `database` | `mariadb:11.8.3` | Database relazionale | 3307 |
| `redis` | `redis:7-alpine` | Cache, cronologia del feed, coda | 6379 |
| `mercure` | `dunglas/mercure` | Hub per gli aggiornamenti in tempo reale | 3000 |
| `messenger` | costruita dal `Dockerfile` | Consumer della coda di messaggi | – |

Ogni servizio vive isolato e per parlare con gli altri deve usare la rete virtuale di Docker Compose, indirizzandoli con il nome del servizio come nome di host. È per questo che, dentro i container, il database si raggiunge all'indirizzo `database:3306` e Redis a `redis:6379`, mentre dal computer che ospita Docker gli stessi servizi rispondono su `localhost`. Configurare le variabili d'ambiente in modo che Symfony sapesse dove trovare il database, Redis e il server Mercure ha richiesto di capire bene come Docker Compose risolve i nomi interni alla rete.

```yaml
php:
  build:
    context: .
    dockerfile: Dockerfile
  volumes:
    - .:/var/www
    - vendor_data:/var/www/vendor
    - var_data:/var/www/var
  depends_on:
    database:
      condition: service_healthy
    redis:
      condition: service_healthy
  environment:
    DATABASE_URL: mysql://bookforeveryone:***@database:3306/bookforeveryone
    REDIS_URL: redis://redis:6379
    MESSENGER_TRANSPORT_DSN: redis://redis:6379/messages
    MERCURE_URL: http://mercure:3000/.well-known/mercure
    MERCURE_PUBLIC_URL: http://localhost:3000/.well-known/mercure
```

Il caso di Mercure è istruttivo, perché l'hub ha due indirizzi. `MERCURE_URL` è quello che usa Symfony per pubblicare, dall'interno della rete Docker; `MERCURE_PUBLIC_URL` è quello che viene scritto nelle pagine e che userà il browser per mettersi in ascolto, dall'esterno. Confonderli è facile, e il sintomo è una chat in cui i messaggi vengono salvati ma non compaiono.

Un secondo aspetto è l'ordine di avvio. Il container PHP dichiara di dipendere dal database e da Redis, ma non gli basta che siano partiti: devono essere *pronti*. Per questo entrambi hanno un controllo di salute (*healthcheck*), un comando che Docker esegue a intervalli regolari, e la dipendenza è subordinata al suo esito positivo.

```yaml
database:
  image: mariadb:11.8.3
  healthcheck:
    test: ["CMD", "mariadb-admin", "ping", "-h", "localhost",
           "-u", "bookforeveryone", "-pbookforeveryone"]
    interval: 10s
    timeout: 5s
    retries: 5
    start_period: 60s
```

Le credenziali e le chiavi che compaiono nel file di composizione sono valori di comodo per l'ambiente di sviluppo locale, pensati perché il progetto parta senza configurazione; un'installazione reale dovrebbe sostituirli con valori propri, forniti attraverso file non tracciati o un sistema di gestione dei segreti.

## Il web server

Nginx ha una configurazione di una trentina di righe. Riceve le richieste sulla porta 80 del container, che Docker collega alla porta 8000 del computer. Se l'indirizzo richiesto corrisponde a un file esistente nella cartella pubblica, per esempio un foglio di stile o una copertina caricata, lo serve direttamente; in tutti gli altri casi passa la richiesta al *front controller* di Symfony, l'unico file PHP esposto, attraverso il protocollo FastCGI [@nginxdoc].

```nginx
location / {
    try_files $uri /index.php$is_args$args;
}

location ~ ^/index\.php(/|$) {
    fastcgi_pass php:9000;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    internal;
}

location ~ \.php$ {
    return 404;
}
```

L'ultima regola nega l'accesso a qualunque altro file PHP: anche se per errore ne finisse uno nella cartella pubblica, non verrebbe eseguito.

## L'immagine PHP e la configurazione di OPcache

L'immagine dei container `php` e `messenger` parte da quella ufficiale di PHP 8.4 in versione FPM, a cui il `Dockerfile` aggiunge le estensioni necessarie (tra cui quelle per il database, per le immagini, per l'internazionalizzazione e per Redis) e Composer, il gestore delle dipendenze.

La difficoltà più rilevante dell'ultima fase di sviluppo ha riguardato proprio questa immagine, e in particolare la configurazione di OPcache. OPcache è un'estensione di PHP che tiene in memoria il *bytecode* già compilato, così da non dover ricompilare gli stessi file a ogni richiesta [@phpdoc]. In produzione è esattamente quello che si vuole. In sviluppo, invece, mi ha fatto perdere un pomeriggio intero: modificavo il codice, ricaricavo la pagina e PHP continuava tranquillamente a eseguire la versione vecchia rimasta in cache.

Ho risolto riconfigurando OPcache nel `Dockerfile` perché validasse i timestamp a ogni richiesta, vale a dire che controllasse la data di modifica del file prima di servire la copia in cache. Così ho tenuto il guadagno prestazionale dove serviva, senza che OPcache mi si mettesse di traverso durante lo sviluppo.

```dockerfile
# OPcache: mantiene il bytecode in memoria ma ricontrolla i file modificati
RUN echo "opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=1\n\
opcache.revalidate_freq=0\n\
opcache.interned_strings_buffer=16\n\
realpath_cache_size=4096K\n\
realpath_cache_ttl=600" > /usr/local/etc/php/conf.d/opcache-perf.ini
```

Le due righe decisive sono `validate_timestamps=1`, che attiva il controllo, e `revalidate_freq=0`, che lo fa eseguire a ogni richiesta invece che ogni tot secondi. Per un ambiente di produzione basterebbe portare la prima a zero per eliminare anche quel controllo.

## I volumi e il problema dei permessi

Sempre sul fronte infrastrutturale mi sono scontrato con i volumi Docker della cartella delle dipendenze PHP (`vendor`) e di quella dei file generati da Symfony (`var`), dove il framework scrive cache e log. All'inizio le avevo montate come cartelle condivise tra il computer e il container, insieme al resto del codice. Il risultato erano continui conflitti di permessi tra l'utente del sistema operativo e quello interno al container, che rendevano i file di volta in volta illeggibili o non scrivibili, oltre a letture molto lente: quelle due cartelle contengono migliaia di piccoli file, ed è il caso peggiore per una cartella condivisa.

Le ho convertite in volumi nativi di Docker, gestiti direttamente dal motore di containerizzazione senza passare dal filesystem del computer, e i conflitti sono spariti insieme a buona parte della lentezza.

```yaml
volumes:
  - .:/var/www                      # codice: condiviso con il computer
  - vendor_data:/var/www/vendor     # dipendenze: volume nativo
  - var_data:/var/www/var           # cache e log: volume nativo
```

La composizione che ne risulta è un compromesso che funziona bene: il codice sorgente resta condiviso, per cui una modifica fatta nell'editor è subito visibile al container, mentre le due cartelle più pesanti e meno interessanti da modificare vivono dentro Docker. C'è una conseguenza di cui tenere conto, e che ho documentato nelle istruzioni di installazione: su un'immagine appena costruita il volume delle dipendenze è vuoto, e va riempito con `composer install` prima di qualunque altro comando.

## Il database di prova

Un'applicazione sociale vuota non si può valutare: il feed non ha niente da mostrare e la ricerca per vicinanza non trova nessuno. Per questo ho dedicato del tempo a costruire un insieme di dati di prova realistici e, soprattutto, rigenerabili.

Il lavoro è affidato a sei comandi da terminale scritti come classi Symfony, più le *fixture* che caricano i venti generi e un primo nucleo di libri. Uno script li esegue nell'ordine giusto.

: Tabella 7.2 – Passaggi dello script di preparazione del database.

| Passo | Comando | Che cosa fa |
|:--:|:--------------------------------|:--------------------------------------------|
| 1 | `doctrine:migrations:migrate` | Crea lo schema applicando le undici migrazioni |
| 2 | `doctrine:fixtures:load` | Carica i generi e i libri iniziali |
| 3 | `app:populate-users` | Crea mille utenti con nomi e saldi verosimili |
| 4 | `app:populate-books 50` | Scarica cinquanta libri con copertina da Open Library |
| 5 | `app:populate-likes` | Assegna like, letture e copie possedute |
| 6 | `app:populate-reviews` | Genera recensioni rispettando il vincolo di unicità |
| 7 | `app:set-user-locations` | Colloca gli utenti entro circa 20 km da un punto |

Al termine il database contiene un migliaio di utenti e una settantina di libri con copertine vere. Gli utenti vengono collocati attorno a un unico centro, Mazara del Vallo, così che la ricerca per vicinanza restituisca risultati qualunque libro si scelga; un comando ulteriore, `app:distribute-books`, permette di ridistribuire le copie tra gli utenti con una densità a piacere. L'utente di prova con cui si accede ha un saldo di Culture Points sufficiente a sperimentare l'intero ciclo del prestito.

L'insieme completo dei comandi per avviare il progetto da zero è riportato nell'appendice A.
