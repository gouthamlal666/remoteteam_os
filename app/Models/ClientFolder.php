<?php

namespace App\Models;

use Database\Factories\ClientFolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['client_id', 'parent_id', 'name', 'created_by'])]
class ClientFolder extends Model
{
    /** @use HasFactory<ClientFolderFactory> */
    use HasFactory;

    /** Folders offered when a client's Drive is empty. */
    public const SUGGESTED = ['Logos & branding', 'Images', 'Documents', 'Videos', 'Contracts & invoices'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientFile::class, 'folder_id');
    }

    /** @return Collection<int, self> this folder's ancestors, root first (for breadcrumbs). */
    public function ancestors(): Collection
    {
        $chain = collect();
        $folder = $this->parent;

        while ($folder) {
            $chain->prepend($folder);
            $folder = $folder->parent;
        }

        return $chain;
    }

    /** @return array<int> ids of this folder and every folder inside it */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            array_push($ids, ...$child->descendantIds());
        }

        return $ids;
    }
}
