<?php

namespace App\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Private storage for membership applicants' valid-ID photos and selfies.
 * Files live in storage/app/applications — outside public/, so they are
 * never directly reachable; admins view them through an authenticated route.
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

    public function path(?string $name): ?string
    {
        return $this->isValidName($name) ? $this->directory() . '/' . $name : null;
    }

    public function delete(?string $name): void
    {
        $path = $this->path($name);
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Validates and stores one image from either a file upload or a camera
     * capture posted as a data URL. Returns the stored file name, or null
     * after pushing a message onto $errors.
     */
    public function store(?UploadedFile $upload, string $dataUrl, string $label, bool $required, array &$errors): ?string
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
                $errors[] = $label === 'valid ID'
                    ? 'Please upload or capture a photo of your valid ID.'
                    : 'Please take a selfie for face verification.';
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

        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_TYPES[$mime];
        try {
            if ($upload !== null) {
                $upload->move($this->directory(), $name);
            } elseif (file_put_contents($this->directory() . '/' . $name, $bytes) === false) {
                throw new \RuntimeException('write failed');
            }
        } catch (\Throwable $e) {
            Log::error("ApplicationFiles: could not save $name: " . $e->getMessage());
            $errors[] = "The $label could not be saved. Please try again.";
            return null;
        }
        @chmod($this->directory() . '/' . $name, 0640);
        return $name;
    }
}
