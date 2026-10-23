<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
