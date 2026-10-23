<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientFolder;
use App\Services\ClientService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * A Google-Drive-style space for a client: nested folders, uploaded files with
 * inline previews (images, PDFs, videos) and saved links (Google Drive,
 * YouTube, Vimeo, Loom…). Super admins and admins only.
 */
class Drive extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $clientId;

    /** Open folder id ('' = Drive root). */
    #[Url(as: 'folder', except: '')]
    public string $folder = '';

    /** all | image | pdf | video | document | link */
    #[Url(as: 'type', except: 'all')]
    public string $filter = 'all';

    public string $search = '';

    /** @var array<TemporaryUploadedFile> */
    public array $uploads = [];

    public bool $showFolder = false;

    public ?int $folderId = null;

    public string $folderName = '';

    public bool $showLink = false;

    public string $linkUrl = '';

    public string $linkTitle = '';

    public bool $showRename = false;

    public ?int $itemId = null;

    public string $itemTitle = '';

    public ?string $moveTo = null;

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);
        $this->clientId = $client->id;

        if ($this->folder !== '' && ! $client->folders()->whereKey((int) $this->folder)->exists()) {
            $this->folder = '';
        }
    }

    private function client(): Client
    {
        return Client::findOrFail($this->clientId);
    }

    private function currentFolder(): ?ClientFolder
    {
        return $this->folder === '' ? null : $this->client()->folders()->find((int) $this->folder);
    }

    private function authorizeEdit(): Client
    {
        $client = $this->client();
        $this->authorize('update', $client);

        return $client;
    }

    public function openFolder(?int $id = null): void
    {
        $this->folder = $id && $this->client()->folders()->whereKey($id)->exists() ? (string) $id : '';
        $this->search = '';
    }

    // ── Uploads ────────────────────────────────────────────────

    /** Runs as soon as files are picked or dropped: validates and saves them into the open folder. */
    public function updatedUploads(ClientService $clients): void
    {
        $client = $this->authorizeEdit();

        $key = 'client-drive-upload:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('uploads', 'Too many uploads. Try again in a minute.');
            $this->uploads = [];

            return;
        }

        $this->validate([
            'uploads' => ['array', 'max:20'],
            'uploads.*' => ['file', 'max:'.ClientService::maxUploadKb(), 'mimes:'.ClientFile::ALLOWED_EXTENSIONS],
        ], [
            'uploads.*.max' => 'Each file can be up to '.round(ClientService::maxUploadKb() / 1024).' MB. For bigger videos, add a Google Drive or YouTube link instead.',
            'uploads.*.mimes' => 'That file type is not allowed.',
        ]);

        foreach ($this->uploads as $upload) {
            RateLimiter::hit($key, 60);
            $clients->addFile(auth()->user(), $client, $upload, 'other', pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME), $this->currentFolder());
        }

        $count = count($this->uploads);
        $this->uploads = [];
        $this->dispatch('notify', message: "Uploaded {$count} ".str('file')->plural($count).'.');
    }

    // ── Links (Google Drive, YouTube, Vimeo, Loom…) ────────────

    public function addLink(ClientService $clients): void
    {
        $client = $this->authorizeEdit();
        $this->validate([
            'linkUrl' => ['required', 'url:http,https', 'max:2048'],
            'linkTitle' => ['nullable', 'string', 'max:150'],
        ], [], ['linkUrl' => 'link']);

        $clients->addLink(auth()->user(), $client, $this->linkUrl, $this->linkTitle ?: null, $this->currentFolder());
        $this->reset('linkUrl', 'linkTitle', 'showLink');
        $this->dispatch('notify', message: 'Link added.');
    }

    // ── Folders ────────────────────────────────────────────────

    public function editFolder(?int $id = null): void
    {
        $this->authorizeEdit();
        $folder = $id ? $this->client()->folders()->findOrFail($id) : null;
        $this->folderId = $folder?->id;
        $this->folderName = (string) $folder?->name;
        $this->resetValidation();
        $this->showFolder = true;
    }

    public function saveFolder(ClientService $clients): void
    {
        $client = $this->authorizeEdit();
        $this->validate(['folderName' => ['required', 'string', 'max:100']], [], ['folderName' => 'folder name']);

        if ($this->folderId) {
            $client->folders()->findOrFail($this->folderId)->update(['name' => trim($this->folderName)]);
        } else {
            $clients->createFolder(auth()->user(), $client, $this->folderName, $this->currentFolder());
        }

        $this->reset('showFolder', 'folderId', 'folderName');
    }

    public function createSuggestedFolders(ClientService $clients): void
    {
        $client = $this->authorizeEdit();
        $existing = $client->folders()->whereNull('parent_id')->pluck('name')->map(fn ($n) => strtolower($n));

        foreach (ClientFolder::SUGGESTED as $name) {
            if (! $existing->contains(strtolower($name))) {
                $clients->createFolder(auth()->user(), $client, $name);
            }
        }
    }

    public function deleteFolder(int $id, ClientService $clients): void
    {
        $folder = $this->authorizeEdit()->folders()->findOrFail($id);
        $clients->deleteFolder($folder);

        if ($this->folder === (string) $id) {
            $this->folder = (string) ($folder->parent_id ?? '');
        }
    }

    // ── Items ──────────────────────────────────────────────────

    public function editItem(int $id): void
    {
        $item = $this->authorizeEdit()->files()->findOrFail($id);
        $this->itemId = $item->id;
        $this->itemTitle = $item->title;
        $this->moveTo = (string) ($item->folder_id ?? '');
        $this->resetValidation();
        $this->showRename = true;
    }

    public function saveItem(): void
    {
        $client = $this->authorizeEdit();
        $this->validate([
            'itemTitle' => ['required', 'string', 'max:150'],
            'moveTo' => ['nullable', Rule::in(array_merge([''], $client->folders()->pluck('id')->map(fn ($id) => (string) $id)->all()))],
        ], [], ['itemTitle' => 'name']);

        $client->files()->findOrFail($this->itemId)->update([
            'title' => $this->itemTitle,
            'folder_id' => $this->moveTo ?: null,
        ]);

        $this->reset('showRename', 'itemId', 'itemTitle', 'moveTo');
        $this->dispatch('notify', message: 'Saved.');
    }

    public function deleteItem(int $id, ClientService $clients): void
    {
        $clients->deleteFile(auth()->user(), $this->authorizeEdit()->files()->findOrFail($id));
    }

    public function render()
    {
        $client = $this->client();
        $current = $this->currentFolder();
        $searching = trim($this->search) !== '';

        $items = $client->files()->with('uploader:id,name')
            ->when(! $searching, fn ($q) => $current ? $q->where('folder_id', $current->id) : $q->whereNull('folder_id'))
            ->when($searching, fn ($q) => $q->where('title', 'like', '%'.trim($this->search).'%'))
            ->get()
            ->when($this->filter !== 'all', fn ($c) => $c->filter(fn (ClientFile $f) => $f->category() === $this->filter))
            ->values();

        $folders = $searching ? collect() : $client->folders()
            ->where('parent_id', $current?->id)
            ->withCount(['items', 'children'])->get();

        return view('livewire.clients.drive', [
            'client' => $client,
            'current' => $current,
            'breadcrumbs' => $current ? $current->ancestors()->push($current) : collect(),
            'folders' => $folders,
            'items' => $items,
            'allFolders' => $client->folders()->get(['id', 'name', 'parent_id']),
            'totalItems' => $client->files()->count(),
            'canEdit' => auth()->user()->can('update', $client),
            'maxMb' => (int) round(ClientService::maxUploadKb() / 1024),
        ]);
    }
}
