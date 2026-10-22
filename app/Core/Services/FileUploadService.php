<?php

declare(strict_types=1);

namespace App\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FileUploadService
{
    /**
     * Upload a validated file to an approved disk and folder.
     * Validate file size, MIME type and authorization in the calling FormRequest/Policy.
     * Returns a disk-relative path; never return private paths directly to clients.
     */
    public function upload(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $this->assertAllowedDisk($disk);
        $directory = $this->normalizeDirectory($directory);

        if (! $file->isValid()) {
            throw new InvalidArgumentException('The uploaded file is invalid.');
        }

        $extension = $file->guessExtension() ?: 'bin';
        $filename = (string) Str::uuid().'.'.$extension;
        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        return $path;
    }

    public function delete(string $path, string $disk = 'local'): bool
    {
        $this->assertAllowedDisk($disk);
        $path = $this->normalizeStoredPath($path);

        return Storage::disk($disk)->delete($path);
    }

    private function assertAllowedDisk(string $disk): void
    {
        if (! in_array($disk, ['local', 'public'], true)) {
            throw new InvalidArgumentException('Storage disk is not permitted.');
        }
    }

    private function normalizeDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || ! preg_match('/\A[a-zA-Z0-9_\/-]+\z/D', $directory) || $this->hasTraversal($directory)) {
            throw new InvalidArgumentException('Invalid upload directory.');
        }
        return $directory;
    }

    private function normalizeStoredPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || $this->hasTraversal($path)) {
            throw new InvalidArgumentException('Invalid stored path.');
        }
        return $path;
    }

    private function hasTraversal(string $path): bool
    {
        return in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true);
    }
}
