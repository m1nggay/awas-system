<?php

namespace App\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Private storage for uploaded images: membership applicants' valid-ID photos
 * and selfies, payment receipt screenshots, the GCash QR code.
 *
 * Files are kept in the database (stored_files) so they survive on hosts
 * whose disk is wiped on every restart. They are never publicly reachable;
 * signed-in users get them only through authenticated routes. Files saved on
 * disk by older versions (storage/app/<folder>) are still found and are
 * copied into the database the first time they are read.
 */
class ApplicationFiles
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Folder under storage/app (applications by default). */
    protected string $folder = 'applications';

    /** Same validation and naming rules, different private folder (e.g. payment receipts, GCash QR). */
    public function in(string $folder): static
    {
        $copy = clone $this;
        $copy->folder = $folder;
        return $copy;
    }

    public function directory(): string
    {
        $dir = storage_path('app/' . $this->folder);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** True only for the random names this class generates — blocks path traversal. */
    public function isValidName(?string $name): bool
    {
        return is_string($name) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) === 1;
    }

    /** Location an older version would have saved the file on disk. */
    protected function legacyPath(string $name): string
    {
        return $this->directory() . '/' . $name;
    }

    /** The file's bytes, or null when it doesn't exist. */
    public function contents(?string $name): ?string
    {
        if (!$this->isValidName($name)) {
            return null;
        }
        $data = DB::table('stored_files')->where('name', $name)->where('folder', $this->folder)->value('data');
        if ($data !== null) {
            $bytes = base64_decode($data, true);
            return $bytes === false ? null : $bytes;
        }

        // Saved on disk by an older version: copy it into the database so it survives a restart.
        $path = $this->legacyPath($name);
        if (!is_file($path) || ($bytes = file_get_contents($path)) === false) {
            return null;
        }
        try {
            $this->put($name, $bytes);
        } catch (\Throwable $e) {
            // Another request copied it at the same moment — the file is still served.
        }
        return $bytes;
    }

    public function exists(?string $name): bool
    {
        return $this->isValidName($name)
            && (DB::table('stored_files')->where('name', $name)->where('folder', $this->folder)->exists()
                || is_file($this->legacyPath($name)));
    }

    /** Sends the image to the browser; 404 when it doesn't exist. */
    public function response(?string $name, array $headers = [], string $missing = 'File not found.')
    {
        $bytes = $this->contents($name);
        abort_if($bytes === null, 404, $missing);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(isset(self::ALLOWED_TYPES[$mime]), 404, $missing);

        return response($bytes, 200, $headers + [
            'Content-Type'           => $mime,
            'Content-Length'         => strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(?string $name): void
    {
        if (!$this->isValidName($name)) {
            return;
        }
        DB::table('stored_files')->where('name', $name)->where('folder', $this->folder)->delete();
        $path = $this->legacyPath($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected function put(string $name, string $bytes): void
    {
        DB::table('stored_files')->insert([
            'name'       => $name,
            'folder'     => $this->folder,
            'mime'       => (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes),
            'size'       => strlen($bytes),
            'data'       => base64_encode($bytes),
            'created_at' => now(),
        ]);
    }

    /**
     * Validates and stores one image from either a file upload or a camera
     * capture posted as a data URL. Returns the stored file name, or null
     * after pushing a message onto $errors.
     *
     * $orientation 'landscape' (valid ID) or 'portrait' (face photo) rejects
     * a picture taken the other way round.
     */
    public function store(?UploadedFile $upload, string $dataUrl, string $label, bool $required, array &$errors, string $orientation = ''): ?string
    {
        $bytes = null;
        $tmpPath = null;

        if ($upload !== null) {
            if (!$upload->isValid()) {
                $errors[] = match ($upload->getError()) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "The $label file is too large. The maximum size is 5 MB.",
                    UPLOAD_ERR_PARTIAL => "The $label file was only partially uploaded. Please try again.",
                    default => "The $label could not be uploaded (upload error {$upload->getError()}). Please try again.",
                };
                return null;
            }
            if ($upload->getSize() > self::MAX_BYTES) {
                $errors[] = "The $label file is too large. The maximum size is 5 MB.";
                return null;
            }
            $tmpPath = $upload->getRealPath();
        } elseif ($dataUrl !== '') {
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
                $errors[] = "The captured $label photo was not in a valid format. Please capture it again.";
                return null;
            }
            $bytes = base64_decode($m[2], true);
            if ($bytes === false || $bytes === '') {
                $errors[] = "The captured $label photo could not be read. Please capture it again.";
                return null;
            }
            if (strlen($bytes) > self::MAX_BYTES) {
                $errors[] = "The captured $label photo is too large. The maximum size is 5 MB.";
                return null;
            }
        } else {
            if ($required) {
                $errors[] = $label === 'face verification'
                    ? 'Please take a selfie for face verification.'
                    : "Please upload or capture a photo of the $label.";
            }
            return null;
        }

        // Trust the file's real content, never the client-sent name or MIME type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $tmpPath !== null ? $finfo->file($tmpPath) : $finfo->buffer($bytes);
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            $errors[] = "The $label must be a JPG, PNG or WEBP image. Other file types are not accepted.";
            return null;
        }
        $imageInfo = $tmpPath !== null ? @getimagesize($tmpPath) : @getimagesizefromstring($bytes);
        if ($imageInfo === false) {
            $errors[] = "The $label file is not a readable image. Please choose a different photo.";
            return null;
        }
        if ($orientation !== '') {
            [$width, $height] = $this->displayedSize($imageInfo, $tmpPath ?? null, $mime);
            if ($orientation === 'landscape' && $width <= $height) {
                $errors[] = "The $label must be a landscape (horizontal) photo. Hold the ID sideways so the whole card fills the frame.";
                return null;
            }
            if ($orientation === 'portrait' && $height <= $width) {
                $errors[] = "The $label photo must be portrait (vertical). Please do the face verification again.";
                return null;
            }
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_TYPES[$mime];
        try {
            $this->put($name, $bytes ?? file_get_contents($tmpPath));
        } catch (\Throwable $e) {
            Log::error("ApplicationFiles: could not save $name: " . $e->getMessage());
            $errors[] = "The $label could not be saved. Please try again.";
            return null;
        }
        return $name;
    }

    /**
     * Width and height as the photo is shown: phone cameras often save a
     * picture sideways with a rotation tag (EXIF orientation 5–8).
     */
    private function displayedSize(array $imageInfo, ?string $path, string $mime): array
    {
        [$width, $height] = $imageInfo;
        if ($path !== null && $mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (!empty($exif['Orientation']) && in_array((int)$exif['Orientation'], [5, 6, 7, 8], true)) {
                return [$height, $width];
            }
        }
        return [$width, $height];
    }
}
