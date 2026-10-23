<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientFileController extends Controller
{
    public function logo(Client $client): StreamedResponse
    {
        Gate::authorize('viewLogo', $client);
        abort_unless($client->logo_path && Storage::disk('local')->exists($client->logo_path), 404);

        return Storage::disk('local')->response($client->logo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(ClientFile $file): StreamedResponse
    {
        Gate::authorize('view', $file->client);
        abort_if($file->isLink(), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    /**
     * Inline preview for images, PDFs and videos only (never SVG/HTML). Uses a
     * file response so browsers can seek within videos (HTTP range requests).
     */
    public function view(ClientFile $file): BinaryFileResponse
    {
        Gate::authorize('view', $file->client);
        abort_unless($file->viewUrl() && Storage::disk($file->disk)->exists($file->path), 404);

        return response()->file(Storage::disk($file->disk)->path($file->path), [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="'.addcslashes((string) $file->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
