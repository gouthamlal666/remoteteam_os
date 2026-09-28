<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Services\TimeTracker;
use Livewire\Attributes\On;
use Livewire\Component;

class Topbar extends Component
{
    public string $title = '';

    /** Newest notification id seen by this page, so only new ones pop up. */
    public ?string $newestSeen = null;

    public function mount(string $title = ''): void
    {
        $this->title = $title;
        $this->newestSeen = auth()->user()->unreadNotifications()->latest()->value('id');
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

    public function stopTimer(TimeTracker $tracker): void
    {
        $entry = $tracker->stop(auth()->user());
        $this->dispatch('timer-changed');

        if ($entry) {
            $this->dispatch('notify', message: 'Logged '.TimeEntry::format($entry->minutes).' on '.$entry->task->title);
        }
    }

    #[On('timer-changed')]
    public function refresh(): void {}

    /** Called by wire:poll — pushes a browser notification for anything new. */
    public function check(): void
    {
        $latest = auth()->user()->unreadNotifications()->latest()->first();

        if ($latest && $latest->id !== $this->newestSeen) {
            $this->newestSeen = $latest->id;
            $this->dispatch('browser-notify', title: $latest->data['title'], body: $latest->data['body'], url: $latest->data['url']);
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.topbar', [
            'notifications' => $user->notifications()->latest()->limit(12)->get(),
            'unread' => $user->unreadNotifications()->count(),
            'timer' => $user->runningTimer(),
        ]);
    }
}
