<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Client records (contacts, links, files and the password vault) are for
 * super admins and admins. Members only see a client's name and logo on the
 * projects they work on.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isManager();
    }

    /** Reading stored passwords (also requires a recent password confirmation). */
    public function viewCredentials(User $user, Client $client): bool
    {
        return $user->isManager();
    }

    /** The logo is shown on project cards, so anyone signed in may load it. */
    public function viewLogo(User $user, Client $client): bool
    {
        return true;
    }
}
