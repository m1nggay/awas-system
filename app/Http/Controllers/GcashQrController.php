<?php

namespace App\Http\Controllers;

use App\Services\GcashQr;
use finfo;

/** Serves the barangay's official GCash QR image to signed-in users. */
class GcashQrController extends Controller
{
    public function __invoke(GcashQr $qr)
    {
        $path = $qr->path();
        abort_if(!$path, 404, 'The GCash QR code has not been set up yet.');

        return response()->file($path, [
            'Content-Type'           => (new finfo(FILEINFO_MIME_TYPE))->file($path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=300',
        ]);
    }
}
