<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\ClientFile;
use App\Models\ClientFolder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Client records and their files. Files live on the private "local" disk and
 * are only served through ClientFileController after a permission check.
 * Audit entries never include secret values.
 */
class ClientService
{
    public function __construct(private ActivityLogger $activity) {}

    /** @param array<string, mixed> $data */
    public function save(User $actor, ?Client $client, array $data): Client
    {
        $isNew = ! $client;
        $client ??= new Client(['created_by' => $actor->id]);
        $client->fill($data)->save();

        $this->activity->log($actor, $isNew ? 'client.created' : 'client.updated', ($isNew ? 'added client ' : 'updated client ').$client->name);

        return $client;
    }

    /** Creates a client from just a name (the "+ New client" shortcut on projects). */
    public function quickCreate(User $actor, string $name): Client
    {
        return Client::firstOrCreate(['name' => trim($name)], ['status' => 'active', 'created_by' => $actor->id]);
    }

    public function setLogo(Client $client, UploadedFile $logo): void
    {
        $old = $client->logo_path;
        $client->update(['logo_path' => $logo->store("clients/{$client->id}/logo", 'local')]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    public function removeLogo(Client $client): void
    {
        if ($client->logo_path) {
            Storage::disk('local')->delete($client->logo_path);
            $client->update(['logo_path' => null]);
        }
    }

    /** Largest upload allowed, in KB: our 100 MB cap or PHP's upload_max_filesize, whichever is lower. */
    public static function maxUploadKb(): int
    {
        $ini = trim((string) ini_get('upload_max_filesize'));
        $bytes = match (strtolower(substr($ini, -1))) {
            'g' => (int) $ini * 1024 ** 3,
            'm' => (int) $ini * 1024 ** 2,
            'k' => (int) $ini * 1024,
            default => (int) $ini,
        };

        return (int) min(100 * 1024, $bytes > 0 ? intdiv($bytes, 1024) : 100 * 1024);
    }

    public function addFile(User $actor, Client $client, UploadedFile $file, string $type, string $title, ?ClientFolder $folder = null): ClientFile
    {
        $record = $client->files()->create([
            'folder_id' => $folder?->id,
            'kind' => 'file',
            'uploaded_by' => $actor->id,
            'type' => $type,
            'title' => $title,
            'disk' => 'local',
            'path' => $file->store("clients/{$client->id}/files", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->activity->log($actor, 'client.file', "added \"{$title}\" to client {$client->name}");

        return $record;
    }

    /** Saves a Google Drive / YouTube / Vimeo / Loom (or any) link into the Drive. */
    public function addLink(User $actor, Client $client, string $url, ?string $title, ?ClientFolder $folder = null): ClientFile
    {
        $item = $client->files()->make([
            'folder_id' => $folder?->id,
            'kind' => 'link',
            'uploaded_by' => $actor->id,
            'type' => 'other',
            'url' => $url,
            'size' => 0,
        ]);
        $item->title = $title ?: ($item->linkProvider().' '.($item->isVideo() ? 'video' : 'link'));
        $item->save();

        $this->activity->log($actor, 'client.file', "added \"{$item->title}\" to client {$client->name}");

        return $item;
    }

    public function createFolder(User $actor, Client $client, string $name, ?ClientFolder $parent = null): ClientFolder
    {
        return $client->folders()->create(['name' => trim($name), 'parent_id' => $parent?->id, 'created_by' => $actor->id]);
    }

    /** Deletes a folder; anything inside it moves up to the parent folder (nothing is lost). */
    public function deleteFolder(ClientFolder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $folder->items()->update(['folder_id' => $folder->parent_id]);
            $folder->children()->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });
    }

    public function deleteFile(User $actor, ClientFile $file): void
    {
        if ($file->path) {
            Storage::disk($file->disk)->delete($file->path);
        }
        $this->activity->log($actor, 'client.file_deleted', "removed \"{$file->title}\" from client {$file->client->name}");
        $file->delete();
    }

    /** Returns the decrypted password and records who looked at it. */
    public function revealCredential(User $actor, ClientCredential $credential): string
    {
        $this->activity->log($actor, 'client.credential_viewed', "viewed the \"{$credential->label}\" password for client {$credential->client->name}");

        return (string) $credential->password;
    }

    /** Deletes the client and its stored files; its projects stay and become internal. */
    public function delete(User $actor, Client $client): void
    {
        DB::transaction(function () use ($actor, $client) {
            foreach ($client->files as $file) {
                if ($file->path) {
                    Storage::disk($file->disk)->delete($file->path);
                }
            }
            $this->removeLogo($client);

            $this->activity->log($actor, 'client.deleted', "deleted client {$client->name}");
            $client->delete();
        });
    }
}
