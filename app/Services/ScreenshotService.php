<?php

namespace App\Services;

use App\Exceptions\ScreenshotStorageException;
use App\Models\Run;
use App\Models\Screenshot;
use App\Models\SessionCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Receiving and storing screenshots: PNG/JPEG/WebP, ≤5 MB,
 * magic-byte checks, files on the screenshots disk.
 * Two entry points: UploadedFile (REST multipart) and base64 (MCP).
 */
class ScreenshotService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Read a multipart request file into bytes (validation is separate, in check()).
     */
    public function readUpload(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['screenshot' => 'Cannot read uploaded screenshot']);
        }

        return (string) file_get_contents($realPath);
    }

    /**
     * Validate bytes without side effects: size, magic bytes, dimensions.
     * Call BEFORE any mutation: a failed request changes nothing.
     *
     * Errors are ValidationException, not abort(): REST renders a 422 with the text,
     * the MCP layer passes the text to the agent (abort hides behind "internal error").
     *
     * @return array{width: int|null, height: int|null, bytes: int|null, extension: string}
     */
    public function check(string $bytes, string $fallbackExtension = 'png'): array
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['screenshot' => 'Screenshot exceeds 5 MB limit']);
        }
        if (! $this->looksLikeImage($bytes)) {
            throw ValidationException::withMessages(['screenshot' => 'Unsupported screenshot format (PNG/JPEG/WebP expected)']);
        }

        $size = @getimagesizefromstring($bytes) ?: null;

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'bytes' => strlen($bytes),
            'extension' => $this->normalizeExtension($bytes, $fallbackExtension),
        ];
    }

    /**
     * Write validated bytes to disk (after check()).
     *
     * The disk is configured with throw=false, so the put() result
     * is checked explicitly — silence here would mean a DB record about a
     * nonexistent file (silent evidence loss on a full disk).
     *
     * @param  array{width: int|null, height: int|null, bytes: int|null, extension: string}  $meta
     * @return array{path: string, width: int|null, height: int|null, bytes: int|null}
     *
     * @throws ScreenshotStorageException
     */
    public function write(string $bytes, array $meta, Run $run, SessionCase $sessionCase, int $stepIndex): array
    {
        $directory = sprintf('%d/%s/case-%d', $run->session_id, $run->side->value, $sessionCase->case_id);
        // His + microseconds: a retake within the same second must not land on the
        // old file's path (otherwise deleteFiles would remove the freshly written screenshot)
        $name = sprintf('step-%02d-%s.%s', $stepIndex, now()->format('Hisu'), $meta['extension']);
        $path = $directory.'/'.$name;

        $put = Storage::disk(Screenshot::DISK)->put($path, $bytes);
        $stored = $put === true;
        if ($stored) {
            try {
                // partial write on a full disk: file length mismatch — not evidence
                $stored = Storage::disk(Screenshot::DISK)->size($path) === strlen($bytes);
            } catch (\Throwable) {
                $stored = false;
            }
        }
        // Storage failure is ScreenshotStorageException, not abort(): both
        // transports catch the explicit type (REST → 500 with the message,
        // MCP → fail()), so no generic HttpException can be swallowed here.
        if (! $stored) {
            throw new ScreenshotStorageException('Failed to store screenshot on disk (storage full or unavailable)');
        }

        return [
            'path' => $path,
            'width' => $meta['width'],
            'height' => $meta['height'],
            'bytes' => $meta['bytes'],
        ];
    }

    /**
     * Delete screenshot files by relative path. Call after
     * successfully deleting the DB records: at worst an orphan file remains,
     * but there will never be a record pointing to a missing file.
     *
     * @param  iterable<string>  $paths
     */
    public static function deleteFiles(iterable $paths): void
    {
        $paths = array_values(array_filter([...$paths]));
        if ($paths !== []) {
            Storage::disk(Screenshot::DISK)->delete($paths);
        }
    }

    /**
     * Extension from the actual content — do not trust the client.
     */
    private function normalizeExtension(string $bytes, string $fallback): string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG") => 'png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, 'RIFF') => 'webp',
            default => in_array($fallback, ['png', 'jpg', 'jpeg', 'webp'], true) ? $fallback : 'png',
        };
    }

    private function looksLikeImage(string $bytes): bool
    {
        return str_starts_with($bytes, "\x89PNG")
            || str_starts_with($bytes, "\xFF\xD8\xFF")
            || (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP');
    }
}
