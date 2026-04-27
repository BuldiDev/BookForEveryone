<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Servizio per il caricamento delle foto dei libri sul filesystem.
 *
 * Genera un nome file sicuro e univoco combinando lo slug del nome originale
 * con un identificatore unico (uniqid), evitando collisioni e caratteri problematici.
 * Il percorso di destinazione è configurato in services.yaml tramite i parametri
 * books_upload_dir (percorso assoluto) e books_upload_path (percorso relativo web).
 */
class BookPhotoUploader
{
    public function __construct(
        private string $booksUploadDir,  // es. /var/www/html/public/uploads/books
        private string $booksUploadPath, // es. /uploads/books
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * Carica il file nella cartella di destinazione e restituisce
     * il percorso relativo da usare come URL nella risposta HTTP.
     *
     * @param UploadedFile $file Il file caricato dall'utente via form
     * @return string Percorso relativo del file salvato (es. /uploads/books/foto-abc123.jpg)
     */
    public function upload(UploadedFile $file): string
    {
        // Estrae il nome originale senza estensione e lo converte in slug URL-safe
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $this->slugger->slug($originalFilename);

        // Aggiunge un identificatore unico per evitare sovrascritture in caso di nomi identici
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Sposta il file temporaneo nella cartella di upload permanente
        $file->move($this->booksUploadDir, $newFilename);

        // Restituisce il percorso relativo accessibile via browser
        return $this->booksUploadPath . '/' . $newFilename;
    }

    /** Restituisce il percorso assoluto della cartella di upload */
    public function getUploadDir(): string
    {
        return $this->booksUploadDir;
    }
}
