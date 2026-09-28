<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Settings;

/**
 * Conversations are private to their members — nobody, including super
 * admins, can read a chat they are not part of.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasMember($user);
    }

    public function createChannel(User $user): bool
    {
        return $user->isManager();
    }

    /** Anyone active can start a one-to-one chat with anyone else who is active (see Security settings). */
    public function messageUser(User $user, User $target): bool
    {
        if (! $target->is_active || $user->is($target)) {
            return false;
        }

        // Organisations can limit members to messaging admins only (System settings → Security).
        return ! ($user->isMember() && $target->isMember()) || Settings::get('security.member_direct_messages');
    }

    /** Channel creators and super admins can rename channels and add or remove people. */
    public function manage(User $user, Conversation $conversation): bool
    {
        return ! $conversation->isDirect()
            && ($user->isSuperAdmin() || $conversation->created_by === $user->id)
            && $conversation->hasMember($user);
    }

    /** Anyone in the conversation can pin; managers can also pin in channels they belong to. */
    public function pin(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
