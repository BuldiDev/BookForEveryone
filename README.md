# BookForEveryone

## Avviare il progetto

### 1. Avviare il database (MariaDB)

```bash
mysqld --port=3307
```

### 2. Avviare Redis

```bash
redis-server
```

Oppure, se Redis è installato come servizio Windows, verificare che sia attivo:

```powershell
Get-Service Redis
```

### 3. Avviare il server

```bash
php -S 127.0.0.1:8000 -t public
```
