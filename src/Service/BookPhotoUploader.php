<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class BookPhotoUploader
{
    public function __construct(
        private string $booksUploadDir,
        private string $booksUploadPath,
        private SluggerInterface $slugger,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->booksUploadDir, $newFilename);

        return $this->booksUploadPath . '/' . $newFilename;
    }

    public function getUploadDir(): string
    {
        return $this->booksUploadDir;
    }
}
