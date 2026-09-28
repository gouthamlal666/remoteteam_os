<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notifications')]
class Notifications extends Component
{
    use WithPagination;

    #[Url(except: false)]
    public bool $unreadOnly = false;

    public function updatingUnreadOnly(): void
    {
        $this->resetPage();
    }

    public function open(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function delete(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->delete();
    }

    public function clearRead(): void
    {
        auth()->user()->readNotifications()->delete();
        $this->resetPage();
        $this->dispatch('notify', message: 'Cleared read notifications.');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notifications', [
            'notifications' => ($this->unreadOnly ? $user->unreadNotifications() : $user->notifications())->latest()->paginate(25),
        ]);
    }
}
