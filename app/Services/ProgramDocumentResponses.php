<?php

namespace App\Services;

use App\Models\ProgramDocument;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramDocumentResponses
{
    public function view(ProgramDocument $document): StreamedResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);
        $filename = $this->filename($document);

        return $disk->response($document->file_path, $filename, [
            'Content-Type' => $disk->mimeType($document->file_path),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function filename(ProgramDocument $document): string
    {
        $name = trim(str_replace(['/', '\\', '"', "\r", "\n"], '-', $document->nama_dokumen));
        $name = $name !== '' ? $name : basename($document->file_path);
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$extension;
        }

        return $name;
    }
}
