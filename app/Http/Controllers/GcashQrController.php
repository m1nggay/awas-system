<?php

namespace App\Http\Controllers;

use App\Services\GcashQr;

/** Serves the barangay's official GCash QR image to signed-in users. */
class GcashQrController extends Controller
{
    public function __invoke(GcashQr $qr)
    {
        return $qr->files()->response($qr->fileName(), [
            'Cache-Control' => 'private, max-age=300',
        ], 'The GCash QR code has not been set up yet.');
    }
}
