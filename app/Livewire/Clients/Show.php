<?php

namespace App\Livewire\Clients;

use App\Livewire\Forms\ClientForm;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\ClientFile;
use App\Models\ClientLink;
use App\Services\ActivityLogger;
use App\Services\ClientService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    /** How long the password vault stays unlocked after confirming your password. */
    private const VAULT_MINUTES = 15;

    private const PHONE = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'];

    public Client $client;

    public ClientForm $form;

    #[Url]
    public string $tab = 'overview';

    public bool $showForm = false;

    public $logo;

    // Contact dialog
    public bool $showContact = false;

    public ?int $contactId = null;

    public string $contactName = '';

    public string $contactRole = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public bool $contactPrimary = false;

    // Link dialog
    public bool $showLink = false;

    public ?int $linkId = null;

    public string $linkType = 'website';

    public string $linkLabel = '';

    public string $linkUrl = '';

    // Credential dialog (the password field is only ever filled when typing a new one)
    public bool $showCredential = false;

    public ?int $credentialId = null;

    public string $credLabel = '';

    public string $credUrl = '';

    public string $credUsername = '';

    public string $credPassword = '';

    public string $credNotes = '';

    // File upload
    public $upload;

    public string $fileType = 'contract';

    public string $fileTitle = '';

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);
        $this->tab = in_array($this->tab, ['overview', 'projects', 'links', 'vault', 'files'], true) ? $this->tab : 'overview';
    }

    // ── Client details ─────────────────────────────────────────

    public function edit(): void
    {
        $this->authorize('update', $this->client);
        $this->form->setClient($this->client);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveClient(ClientService $clients): void
    {
        $this->authorize('update', $this->client);
        $this->client = $clients->save(auth()->user(), $this->client, $this->form->payload());
        $this->showForm = false;
        $this->dispatch('notify', message: 'Client saved.');
    }

    public function updatedLogo(ClientService $clients): void
    {
        $this->authorize('update', $this->client);
        $this->validate(['logo' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
        $clients->setLogo($this->client, $this->logo);
        $this->logo = null;
        $this->dispatch('notify', message: 'Logo updated.');
    }

    public function removeLogo(ClientService $clients): void
    {
        $this->authorize('update', $this->client);
        $clients->removeLogo($this->client);
    }

    public function delete(ClientService $clients)
    {
        $this->authorize('delete', $this->client);
        $name = $this->client->name;
        $clients->delete(auth()->user(), $this->client);
        session()->flash('status', "Deleted {$name}. Their projects were kept as internal projects.");

        return $this->redirectRoute('clients.index', navigate: true);
    }

    // ── Contacts ───────────────────────────────────────────────

    public function editContact(?int $id = null): void
    {
        $this->authorize('update', $this->client);
        $contact = $id ? $this->client->contacts()->findOrFail($id) : null;

        $this->contactId = $contact?->id;
        $this->contactName = (string) $contact?->name;
        $this->contactRole = (string) $contact?->role;
        $this->contactEmail = (string) $contact?->email;
        $this->contactPhone = (string) $contact?->phone;
        $this->contactPrimary = $contact ? $contact->is_primary : $this->client->contacts()->doesntExist();
        $this->resetValidation();
        $this->showContact = true;
    }

    public function saveContact(): void
    {
        $this->authorize('update', $this->client);
        $data = $this->validate([
            'contactName' => ['required', 'string', 'max:150'],
            'contactRole' => ['nullable', 'string', 'max:100'],
            'contactEmail' => ['nullable', 'email', 'max:150'],
            'contactPhone' => self::PHONE,
            'contactPrimary' => ['boolean'],
        ], ['contactPhone.regex' => 'Enter a valid phone number.']);

        if ($data['contactPrimary']) {
            $this->client->contacts()->update(['is_primary' => false]);
        }

        $this->client->contacts()->updateOrCreate(['id' => $this->contactId], [
            'name' => $data['contactName'],
            'role' => $data['contactRole'] ?: null,
            'email' => $data['contactEmail'] ?: null,
            'phone' => $data['contactPhone'] ?: null,
            'is_primary' => $data['contactPrimary'],
        ]);

        $this->showContact = false;
        $this->dispatch('notify', message: 'Contact saved.');
    }

    public function deleteContact(int $id): void
    {
        $this->authorize('update', $this->client);
        $this->client->contacts()->findOrFail($id)->delete();
    }

    // ── Links ──────────────────────────────────────────────────

    public function editLink(?int $id = null): void
    {
        $this->authorize('update', $this->client);
        $link = $id ? $this->client->links()->findOrFail($id) : null;

        $this->linkId = $link?->id;
        $this->linkType = $link?->type ?? 'website';
        $this->linkLabel = (string) $link?->label;
        $this->linkUrl = (string) $link?->url;
        $this->resetValidation();
        $this->showLink = true;
    }

    public function saveLink(): void
    {
        $this->authorize('update', $this->client);
        $data = $this->validate([
            'linkType' => ['required', Rule::in(array_keys(ClientLink::TYPES))],
            'linkLabel' => ['nullable', 'string', 'max:150'],
            'linkUrl' => ['required', 'url:http,https', 'max:2048'],
        ], [], ['linkUrl' => 'link']);

        $this->client->links()->updateOrCreate(['id' => $this->linkId], [
            'type' => $data['linkType'],
            'label' => $data['linkLabel'] ?: ClientLink::TYPES[$data['linkType']][0],
            'url' => $data['linkUrl'],
        ]);

        $this->showLink = false;
        $this->dispatch('notify', message: 'Link saved.');
    }

    public function deleteLink(int $id): void
    {
        $this->authorize('update', $this->client);
        $this->client->links()->findOrFail($id)->delete();
    }

    // ── Password vault ─────────────────────────────────────────

    public function vaultUnlocked(): bool
    {
        return (int) session('client_vault_unlocked_until', 0) > now()->timestamp;
    }

    /** Confirms the signed-in person's own password before secrets can be shown. */
    public function unlockVault(string $password, ActivityLogger $activity): bool
    {
        $this->authorize('viewCredentials', $this->client);

        $key = 'client-vault:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('vault', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return false;
        }

        if (! Hash::check($password, auth()->user()->getAuthPassword())) {
            RateLimiter::hit($key, 5 * 60);
            $this->addError('vault', 'That password is not correct.');

            return false;
        }

        RateLimiter::clear($key);
        session(['client_vault_unlocked_until' => now()->addMinutes(self::VAULT_MINUTES)->timestamp]);
        $activity->log(auth()->user(), 'client.vault_unlocked', "unlocked the password vault for client {$this->client->name}");
        $this->resetErrorBag('vault');

        return true;
    }

    public function lockVault(): void
    {
        session()->forget('client_vault_unlocked_until');
    }

    /**
     * Returns one secret to the browser (never stored in component state).
     *
     * @return array{password: string, notes: ?string}|null
     */
    public function reveal(int $id, ClientService $clients): ?array
    {
        $this->authorize('viewCredentials', $this->client);

        if (! $this->vaultUnlocked()) {
            return null;
        }

        $credential = $this->client->credentials()->findOrFail($id);

        return ['password' => $clients->revealCredential(auth()->user(), $credential), 'notes' => $credential->notes];
    }

    public function editCredential(?int $id = null): void
    {
        $this->authorize('viewCredentials', $this->client);
        $credential = $id ? $this->client->credentials()->findOrFail($id) : null;

        $this->credentialId = $credential?->id;
        $this->credLabel = (string) $credential?->label;
        $this->credUrl = (string) $credential?->url;
        $this->credUsername = (string) $credential?->username;
        $this->credPassword = '';
        $this->credNotes = '';
        $this->resetValidation();
        $this->showCredential = true;
    }

    public function saveCredential(ActivityLogger $activity): void
    {
        $this->authorize('viewCredentials', $this->client);
        abort_unless($this->vaultUnlocked(), 403, 'Unlock the vault first.');

        $data = $this->validate([
            'credLabel' => ['required', 'string', 'max:150'],
            'credUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'credUsername' => ['nullable', 'string', 'max:255'],
            'credPassword' => [$this->credentialId ? 'nullable' : 'required', 'string', 'max:1000'],
            'credNotes' => ['nullable', 'string', 'max:5000'],
        ], [], ['credLabel' => 'name', 'credUrl' => 'login URL', 'credPassword' => 'password']);

        $values = [
            'label' => $data['credLabel'],
            'url' => $data['credUrl'] ?: null,
            'username' => $data['credUsername'] ?: null,
        ];
        // Blank password / notes while editing keep the stored (encrypted) values.
        if (filled($data['credPassword'])) {
            $values['password'] = $data['credPassword'];
        }
        if (filled($data['credNotes'])) {
            $values['notes'] = $data['credNotes'];
        }

        $credential = $this->credentialId
            ? tap($this->client->credentials()->findOrFail($this->credentialId))->update($values)
            : $this->client->credentials()->create($values + ['created_by' => auth()->id()]);

        $activity->log(auth()->user(), 'client.credential_saved', "saved the \"{$credential->label}\" login for client {$this->client->name}");

        $this->reset('credPassword', 'credNotes');
        $this->showCredential = false;
        $this->dispatch('notify', message: 'Login saved securely.');
    }

    public function deleteCredential(int $id, ActivityLogger $activity): void
    {
        $this->authorize('viewCredentials', $this->client);
        $credential = $this->client->credentials()->findOrFail($id);
        $activity->log(auth()->user(), 'client.credential_deleted', "deleted the \"{$credential->label}\" login for client {$this->client->name}");
        $credential->delete();
    }

    // ── Files ──────────────────────────────────────────────────

    public function uploadFile(ClientService $clients): void
    {
        $this->authorize('update', $this->client);
        $this->validate([
            'upload' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp,gif,zip,ai,psd,eps,mp4,mov'],
            'fileType' => ['required', Rule::in(array_keys(ClientFile::TYPES))],
            'fileTitle' => ['nullable', 'string', 'max:150'],
        ], [], ['upload' => 'file']);

        $clients->addFile(auth()->user(), $this->client, $this->upload, $this->fileType,
            $this->fileTitle ?: pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME));

        $this->reset('upload', 'fileTitle');
        $this->dispatch('notify', message: 'File uploaded.');
    }

    public function deleteFile(int $id, ClientService $clients): void
    {
        $this->authorize('update', $this->client);
        $clients->deleteFile(auth()->user(), $this->client->files()->findOrFail($id));
    }

    public function render()
    {
        $this->client->load(['contacts', 'links', 'credentials.creator', 'files.uploader', 'creator']);

        return view('livewire.clients.show', [
            'summary' => $this->client->workSummary(),
            'projects' => $this->tab === 'projects' || $this->tab === 'overview'
                ? $this->client->projects()->withProgressCounts()->with('members')->orderByRaw('deadline is null, deadline')->get()
                : collect(),
            'unlocked' => $this->vaultUnlocked(),
            'credentialCount' => $this->client->credentials->count(),
        ])->title($this->client->name);
    }
}
