<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeFileController extends Controller
{
    /** Profile photos are visible to any signed-in teammate. */
    public function avatar(User $user): StreamedResponse
    {
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function document(EmployeeDocument $document): StreamedResponse
    {
        Gate::authorize('viewEmployeeRecord', $document->user);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
