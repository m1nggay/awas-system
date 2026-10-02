<?php

namespace App\Services;

/**
 * The barangay's official GCash QR code (uploaded by an administrator in
 * System Settings) and the account details shown next to it.
 */
class GcashQr
{
    public const FOLDER = 'gcash';

    public function __construct(private Settings $settings, private ApplicationFiles $files)
    {
    }

    public function files(): ApplicationFiles
    {
        return $this->files->in(self::FOLDER);
    }

    public function fileName(): ?string
    {
        $name = (string)$this->settings->get('gcash_qr_file', '');
        return $this->files()->isValidName($name) ? $name : null;
    }

    public function isConfigured(): bool
    {
        return $this->files()->exists($this->fileName());
    }

    public function accountName(): string
    {
        return (string)$this->settings->get('gcash_account_name', '');
    }

    public function number(): string
    {
        return (string)$this->settings->get('gcash_number', '');
    }
}
