<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DirectMessageController extends Controller
{
    /** Opens (creating if needed) the one-to-one chat with $user. */
    public function __invoke(User $user, ChatService $chat): RedirectResponse
    {
        Gate::authorize('messageUser', [Conversation::class, $user]);

        return redirect()->route('chat', $chat->directBetween(auth()->user(), $user));
    }
}
