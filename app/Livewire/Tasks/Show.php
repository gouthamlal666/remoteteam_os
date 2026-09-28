<?php

namespace App\Livewire\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\TaskService;
use App\Services\TimeTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Task $task;

    public string $comment = '';

    /** Note sent with a submission, approval or revision request. */
    public string $note = '';

    /** Which review dialog is open: submit | approve | revision | null */
    public ?string $dialog = null;

    public $upload;

    public string $newItem = '';

    public string $logHours = '';

    public string $logDate = '';

    public string $logNote = '';

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->logDate = today()->toDateString();
    }

    // ── Checklist ──────────────────────────────────────────────

    public function addItem(): void
    {
        $this->authorize('upload', $this->task);
        $this->validate(['newItem' => ['required', 'string', 'max:200']]);

        $this->task->checklistItems()->create([
            'title' => $this->newItem,
            'position' => (int) $this->task->checklistItems()->max('position') + 1,
        ]);
        $this->newItem = '';
    }

    public function toggleItem(int $id): void
    {
        $this->authorize('upload', $this->task);
        $item = $this->task->checklistItems()->findOrFail($id);
        $item->update(['is_done' => ! $item->is_done, 'completed_by' => $item->is_done ? null : auth()->id()]);
    }

    public function deleteItem(int $id): void
    {
        $this->authorize('upload', $this->task);
        $this->task->checklistItems()->findOrFail($id)->delete();
    }

    // ── Time tracking ──────────────────────────────────────────

    public function startTimer(TimeTracker $tracker): void
    {
        $this->authorize('upload', $this->task);
        $tracker->start(auth()->user(), $this->task);

        // Starting the timer on a to-do task means work has begun.
        if ($this->task->isAssignedTo(auth()->user()) && in_array($this->task->status, [TaskStatus::Todo, TaskStatus::Revision], true)) {
            app(TaskService::class)->changeStatus(auth()->user(), $this->task, TaskStatus::InProgress);
        }

        $this->dispatch('timer-changed');
    }

    public function stopTimer(TimeTracker $tracker): void
    {
        $tracker->stop(auth()->user());
        $this->dispatch('timer-changed');
    }

    public function logTime(TimeTracker $tracker): void
    {
        $this->authorize('upload', $this->task);
        $this->validate([
            'logHours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'logDate' => ['required', 'date', 'before_or_equal:today'],
            'logNote' => ['nullable', 'string', 'max:200'],
        ]);

        $tracker->log(auth()->user(), $this->task, (int) round($this->logHours * 60), Carbon::parse($this->logDate), $this->logNote ?: null);
        $this->reset('logHours', 'logNote');
        $this->dispatch('notify', message: 'Time logged.');
    }

    public function deleteTimeEntry(int $id): void
    {
        $entry = $this->task->timeEntries()->findOrFail($id);
        abort_unless($entry->user_id === auth()->id() || auth()->user()->can('update', $this->task), 403);
        $entry->delete();
        $this->dispatch('timer-changed');
    }

    #[On('timer-changed')]
    public function timerChanged(): void {}

    #[On('task-saved')]
    public function refreshTask(): void
    {
        $this->task->refresh();
    }

    public function start(TaskService $tasks): void
    {
        $this->authorize('updateStatus', $this->task);
        abort_unless(in_array($this->task->status, [TaskStatus::Todo, TaskStatus::Revision], true), 422);

        $tasks->changeStatus(auth()->user(), $this->task, TaskStatus::InProgress);
        $this->dispatch('notify', message: 'Task started.');
    }

    public function openDialog(string $dialog): void
    {
        $this->dialog = in_array($dialog, ['submit', 'approve', 'revision'], true) ? $dialog : null;
        $this->note = '';
        $this->resetValidation();
    }

    public function confirmDialog(TaskService $tasks): void
    {
        $user = auth()->user();

        match ($this->dialog) {
            'submit' => $this->authorize('submit', $this->task),
            'approve', 'revision' => $this->authorize('review', $this->task),
            default => abort(422),
        };

        $this->validate(['note' => [$this->dialog === 'approve' ? 'nullable' : 'required', 'string', 'max:5000']]);

        match ($this->dialog) {
            'submit' => $tasks->submit($user, $this->task, $this->note),
            'approve' => $tasks->approve($user, $this->task, $this->note),
            'revision' => $tasks->requestRevision($user, $this->task, $this->note),
        };

        $message = ['submit' => 'Submitted for review.', 'approve' => 'Task approved.', 'revision' => 'Sent back for revision.'][$this->dialog];
        $this->dialog = null;
        $this->note = '';
        $this->dispatch('notify', message: $message);
    }

    public function addComment(TaskService $tasks): void
    {
        $this->authorize('comment', $this->task);
        $this->validate(['comment' => ['required', 'string', 'max:5000']]);

        $tasks->addComment(auth()->user(), $this->task, $this->comment);
        $this->comment = '';
    }

    public function updatedUpload(TaskService $tasks): void
    {
        $this->authorize('upload', $this->task);
        $this->validate(['upload' => ['file', 'max:20480']]);

        $tasks->attach(auth()->user(), $this->task, $this->upload);
        $this->upload = null;
        $this->dispatch('notify', message: 'File uploaded.');
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->task->comments()->findOrFail($commentId);
        abort_unless($comment->user_id === auth()->id() || auth()->user()->can('update', $this->task), 403);
        $comment->delete();
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = $this->task->attachments()->findOrFail($attachmentId);
        abort_unless(auth()->user()->can('update', $this->task) || $attachment->user_id === auth()->id(), 403);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    public function delete(TaskService $tasks)
    {
        $this->authorize('delete', $this->task);
        $project = $this->task->project;
        $tasks->delete(auth()->user(), $this->task);

        session()->flash('status', 'Task deleted.');

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function render()
    {
        $this->task->load(['project', 'creator', 'assignees', 'comments.user', 'attachments.user', 'checklistItems']);
        $entries = $this->task->timeEntries()->with('user:id,name')->latest('started_at')->get();

        return view('livewire.tasks.show', [
            'entries' => $entries,
            'loggedMinutes' => $entries->sum(fn (TimeEntry $e) => $e->currentMinutes()),
            'myTimer' => auth()->user()->timeEntries()->running()->where('task_id', $this->task->id)->first(),
            'activities' => $this->task->activities()->with(['user', 'project'])->latest('created_at')->limit(20)->get(),
        ])->title($this->task->title);
    }
}
