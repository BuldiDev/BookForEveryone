# Database MariaDB

Questa cartella contiene i dati persistenti del database MariaDB.

## Struttura

- `data/` - Dati del database MariaDB (creato automaticamente al primo avvio)
- `dump.sql` - Dump SQL di backup (se presente)
- `seed_books.sql` - Seed iniziale dei libri (se presente)

## Importante

La cartella `data/` contiene:
- Tutti i dati del database (utenti, libri, prestiti, chat, ecc.)
- File binari di MariaDB (~500MB con dati di test)
- **Non modificare manualmente** questi file

## Condivisione del progetto

Quando condividi l'intero progetto **inclusa questa cartella `data/`**, chi riceve il progetto avrà il database già pronto con tutti i dati di test!

Non serve eseguire `setup-database.bat` - basta fare `docker-compose up -d` e tutto funziona subito.

## Reset del database

Per resettare il database:

1. Ferma i container: `docker-compose down -v`
2. Cancella la cartella `data/`: `Remove-Item -Recurse -Force docker/db/data`
3. Riavvia: `docker-compose up -d`
4. Esegui setup: `.\setup-database.bat`
