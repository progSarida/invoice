<?php
namespace App\Filament\Company\Resources\TemplateResource\Concerns;

use Illuminate\Support\Facades\Storage;

trait HandlesTemplateFile
{
    protected function handleFileUpload(mixed $record, array $state, ?string $oldPath = null): void
    {
        $tmpPath      = $state['upload_path'] ?? null;
        $originalName = $state['original_filename'] ?? null;

        if (!$tmpPath) {
            return;
        }

        $disk      = Storage::disk(config('filesystems.default'));
        $filename  = $originalName ?? basename($tmpPath);
        $finalPath = 'templates/' . $record->id . '/' . $filename;

        // Elimina il vecchio file se esiste ed è diverso dal nuovo
        if ($oldPath && $oldPath !== $finalPath && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        $stream = $disk->readStream($tmpPath);

        if ($stream === false || $stream === null) {
            throw new \RuntimeException("Impossibile leggere il file temporaneo: {$tmpPath}");
        }

        try {
            $disk->writeStream($finalPath, $stream, [
                'ContentType' => $disk->mimeType($tmpPath),
            ]);

            if (!$disk->exists($finalPath)) {
                throw new \RuntimeException("Scrittura fallita, file non trovato: {$finalPath}");
            }

            $disk->delete($tmpPath);

            $record->update([
                'path'     => $finalPath,
                'filename' => $filename,
            ]);

        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}