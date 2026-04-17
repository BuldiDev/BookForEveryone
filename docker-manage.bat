@echo off
chcp 65001 > nul
cls
echo =====================================
echo BookForEveryone - Docker Management
echo =====================================
echo.

:menu
echo Seleziona un'opzione:
echo 1. Avvia tutti i servizi
echo 2. Ferma tutti i servizi
echo 3. Riavvia tutti i servizi
echo 4. Mostra stato servizi
echo 5. Mostra logs
echo 6. Esci
echo.

set /p choice="Scelta: "

if "%choice%"=="1" goto start
if "%choice%"=="2" goto stop
if "%choice%"=="3" goto restart
if "%choice%"=="4" goto status
if "%choice%"=="5" goto logs
if "%choice%"=="6" goto end

echo Scelta non valida
goto menu

:start
echo Avvio di tutti i servizi...
docker-compose up -d --build
echo.
echo Servizi avviati!
echo - Applicazione: http://localhost:8000
echo - Mercure: http://localhost:3000
echo - MailPit: http://localhost:8025
echo - Database: localhost:3307
echo.
pause
goto menu

:stop
echo Arresto di tutti i servizi...
docker-compose down
echo Servizi arrestati!
pause
goto menu

:restart
echo Riavvio di tutti i servizi...
docker-compose restart
echo Servizi riavviati!
pause
goto menu

:status
docker-compose ps
pause
goto menu

:logs
docker-compose logs -f
pause
goto menu

:end
exit
