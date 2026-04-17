# BookForEveryone

## Avvio Automatico

### Prima volta (setup completo)

```bash
docker-compose up -d --build
.\setup-database.bat
```

### Avvii successivi (database già pronto)

```bash
docker-compose up -d
```

Apri http://localhost:8000

**Credenziali di test:**
- Username: `bookforeveryone`
- Password: `prova`

**Nota importante**: Il database è salvato in `docker/db/data/`. Quando condividi l'intera cartella del progetto, il database è già incluso e pronto all'uso!

**Geolocalizzazione**: Gli utenti di test sono posizionati nell'area di Mazara del Vallo (Sicilia).
