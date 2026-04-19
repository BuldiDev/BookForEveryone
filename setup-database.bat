@echo off
echo =====================================
echo BookForEveryone - Database Setup
echo =====================================
echo.

echo [0a/6] Pulizia copertine vecchie...
docker-compose exec -T php sh -c "rm -rf /var/www/public/uploads/books/* 2>/dev/null || true"
echo.

echo - Copertine vecchie eliminate
echo [0b/6] Pulizia database...
docker-compose exec -T php php bin/console doctrine:schema:drop --full-database --force
if %errorlevel% neq 0 (
    echo AVVISO: Nessun database da eliminare
)
echo.

echo - Database pulito
echo [1/6] Esecuzione migrazioni...
docker-compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
if %errorlevel% neq 0 (
    echo ERRORE: Migrazioni fallite
    pause
    exit /b 1
)
echo.

echo - Migrazioni completate
echo [2/6] Caricamento generi...
docker-compose exec -T php php bin/console doctrine:fixtures:load --append --no-interaction
if %errorlevel% neq 0 (
    echo ERRORE: Caricamento generi fallito
    pause
    exit /b 1
)
echo.

echo - Generi caricati
echo [3/6] Popolamento utenti...
docker-compose exec -T php php bin/console app:populate-users
if %errorlevel% neq 0 (
    echo ERRORE: Popolamento utenti fallito
    pause
    exit /b 1
)
echo.

echo - Utenti creati
echo [4/6] Popolamento libri...
docker-compose exec -T php php bin/console app:populate-books 50
if %errorlevel% neq 0 (
    echo ERRORE: Popolamento libri fallito
    pause
    exit /b 1
)
echo.

echo - Libri creati
echo [5/6] Popolamento likes...
docker-compose exec -T php php bin/console app:populate-likes
if %errorlevel% neq 0 (
    echo ERRORE: Popolamento likes fallito
    pause
    exit /b 1
)
echo.

echo - Likes creati
echo [6/6] Impostazione posizioni utenti...
docker-compose exec -T php php bin/console app:set-user-locations
if %errorlevel% neq 0 (
    echo ERRORE: Impostazione posizioni fallita
    pause
    exit /b 1
)
echo.

echo - Posizioni impostate
echo =====================================
echo Setup completato con successo!
echo =====================================
echo.
echo Apri http://localhost:8000
echo.
pause
