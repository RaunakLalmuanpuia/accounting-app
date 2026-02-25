<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceDownloadController extends Controller
{
    /**
     * Serve a PDF invoice from local storage.
     *
     * FIX: Previously the controller expected an encrypted `token` query param,
     *      but GetInvoices was generating signed routes with a plain `path` param —
     *      meaning every PDF link in the invoice list was broken with a 403.
     *
     *      Standardised on encrypted token everywhere:
     *        - This controller decrypts `token` → real storage path.
     *        - GetInvoices now encrypts the path into `token` (no raw path exposed).
     *        - InvoicePdfService must also use encrypt() when building its URLs.
     *
     *      Signed routes were considered but rejected: they embed the raw storage
     *      path in the URL (visible to the browser), whereas encrypt() keeps it opaque.
     */
    public function download(Request $request)
    {
        // Decrypt the token to recover the real storage path
        try {
            $path = decrypt($request->query('token'));
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            abort(403, 'This download link is invalid or has expired.');
        }

        // Guard against path traversal / unexpected prefixes
        if (! $path || ! str_starts_with($path, 'invoices/')) {
            abort(403, 'Invalid file path.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'Invoice PDF not found. It may have been replaced — regenerate it from the chat.');
        }

        // Produce a clean, human-readable filename from the storage path.
        // e.g. "invoices/2024/INV-20240101-00042.pdf" → "INV-20240101-00042.pdf"
        $filename = basename($path);

        return response(Storage::disk('local')->get($path))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
