# BookForEveryone - Guida Installazione Docker

## Prerequisiti

### 1. Installare Docker Desktop per Windows

1. Scarica Docker Desktop da: https://www.docker.com/products/docker-desktop/
2. Esegui l'installer
3. Riavvia il computer se richiesto
4. Avvia Docker Desktop
5. Attendi che Docker sia completamente avviato (icona Docker nella system tray diventa verde)

### 2. Verifica installazione

Apri PowerShell e verifica:

```powershell
docker --version
docker-compose --version
```

## Avvio Applicazione

### Opzione 1: Script automatico (Consigliato)

```powershell
.\docker-manage.ps1
```

Seleziona l'opzione 1 per avviare tutti i servizi.

### Opzione 2: Comandi manuali

#### Prima volta (con build):

```powershell
docker-compose up -d --build
```

#### Avvii successivi:

```powershell
docker-compose up -d
```

#### Fermare i servizi:

```powershell
docker-compose down
```

#### Riavviare i servizi:

```powershell
docker-compose restart
```

## Accesso ai Servizi

Dopo l'avvio, i servizi saranno disponibili su:

- **Applicazione Web**: http://localhost:8000
- **Mercure Hub**: http://localhost:3000
- **MailPit (Test Email)**: http://localhost:8025
- **Database MariaDB**: localhost:3307
  - User: bookforeveryone
  - Password: bookforeveryone
  - Database: bookforeveryone
- **Redis**: localhost:6379

### Credenziali di Login

Dopo aver eseguito `setup-database.bat`, usa queste credenziali per accedere:

- **Username**: `bookforeveryone`
- **Password**: `prova`

Tutti gli altri utenti di test hanno la stessa password: `prova`

## Comandi Utili

### Visualizzare i logs

```powershell
# Tutti i servizi
docker-compose logs -f

# Servizio specifico
docker-compose logs -f php
docker-compose logs -f nginx
docker-compose logs -f database
docker-compose logs -f redis
docker-compose logs -f mercure
```

### Stato dei container

```powershell
docker-compose ps
```

### Eseguire comandi Symfony

```powershell
# Accedere al container PHP
docker-compose exec php bash

# Eseguire comandi Symfony (da dentro il container)
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console app:populate-users

# O direttamente da fuori
docker-compose exec php php bin/console cache:clear
```

### Accedere al database

```powershell
docker-compose exec database mysql -u bookforeveryone -pbookforeveryone bookforeveryone
```

### Resettare tutto

```powershell
# Ferma e rimuove tutto (inclusi i volumi)
docker-compose down -v

# Ricostruisci e riavvia
docker-compose up -d --build
```

## Risoluzione Problemi

### Porta già in uso

Se una porta è già occupata, modifica il file `compose.override.yaml`:

```yaml
services:
  nginx:
    ports:
      - "8080:80"  # Cambia 8000 in 8080
```

### Container non si avvia

```powershell
# Visualizza i logs per capire l'errore
docker-compose logs [nome_servizio]

# Ricostruisci il container
docker-compose up -d --build [nome_servizio]
```

### Problemi di permessi

Su Windows, assicurati che Docker Desktop abbia accesso alla cartella del progetto nelle impostazioni.

## Note Importanti

1. **Prima esecuzione**: Il primo avvio richiederà più tempo per scaricare le immagini Docker
2. **Database**: I dati del database sono persistiti nella cartella `docker/db/data/`
3. **Modifiche al codice**: Le modifiche al codice PHP sono immediatamente visibili (volume montato)
4. **Modifiche al Dockerfile**: Richiedono un rebuild con `--build`

## Migrazioni Database

Alla prima esecuzione, esegui le migrazioni:

```powershell
docker-compose exec php php bin/console doctrine:migrations:migrate
docker-compose exec php php bin/console doctrine:fixtures:load --append
docker-compose exec php php bin/console app:populate-users
docker-compose exec php php bin/console app:populate-books 50
docker-compose exec php php bin/console app:set-user-locations
```

O semplicemente:

```powershell
.\setup-database.bat
```

**Nota**: Il comando `app:set-user-locations` posiziona tutti gli utenti nell'area di **Mazara del Vallo (Sicilia)** con una variazione casuale di ~20km, così potrai testare la funzionalità di prestito/scambio libri locali.

## Sviluppo

Il progetto è montato come volume, quindi:
- Modifiche ai file PHP sono visibili immediatamente
- Modifiche agli asset potrebbero richiedere `php bin/console asset-map:compile`
- I log di Symfony sono in `var/log/`
- La cache è in `var/cache/` e può essere pulita con `cache:clear`
