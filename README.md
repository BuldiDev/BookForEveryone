# BookForEveryone

Piattaforma di condivisione libri con geolocalizzazione, chat real-time e sistema di prestiti.

## Installazione

1. Installa [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. Clona il progetto

## Avvio

**Prima volta:**
```bash
docker-compose up -d --build
.\setup-database.bat
```

**Avvii successivi:**
```bash
docker-compose up -d
```

Apri http://localhost:8000

## Login

- Username: `bookforeveryone`
- Password: `prova`

(Tutti gli utenti di test hanno password `prova`)

## Note

- **Database**: Salvato in `docker/db/data/` - se condividi l'intera cartella, il database è già pronto!
- **Fermare**: `docker-compose down`
- **Reset DB**: `docker-compose down -v` poi `.\setup-database.bat`
- **Utenti test**: 1000 utenti posizionati a Mazara del Vallo (Sicilia)
- **Libri**: 50 libri con copertine da Open Library

## Stack

- Symfony 7 + PHP 8.4
- MariaDB 11.8
- Redis (cache/queue)
- Mercure (real-time)
- Docker
