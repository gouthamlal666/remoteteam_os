<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function show(MessageAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->message->conversation);

        $disk = Storage::disk($attachment->disk);

        // Images render inline in the chat; everything else downloads.
        return $attachment->isImage()
            ? $disk->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff'])
            : $disk->download($attachment->path, $attachment->original_name);
    }
}
