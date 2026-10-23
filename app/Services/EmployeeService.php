<?php

namespace App\Services;

use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Employee HR records. Files live on the private "local" disk and are only
 * served through authorised routes. Audit entries record *that* something
 * changed, never the personal values themselves.
 */
class EmployeeService
{
    public function __construct(private ActivityLogger $activity) {}

    /** @param array<string, mixed> $details */
    public function saveDetails(User $actor, User $user, ?string $phone, array $details): EmployeeProfile
    {
        return DB::transaction(function () use ($actor, $user, $phone, $details) {
            $user->update(['phone' => $phone ?: null]);
            // Only profile fields: never let the client choose which user the record belongs to.
            $details = Arr::only($details, array_diff((new EmployeeProfile)->getFillable(), ['user_id']));
            $profile = $user->employeeProfile()->updateOrCreate([], $details);
            $this->activity->log($actor, 'employee.updated', "updated the employee record of {$user->name}", $user);

            return $profile;
        });
    }

    public function setAvatar(User $actor, User $user, UploadedFile $photo): void
    {
        $old = $user->avatar_path;
        $user->update(['avatar_path' => $photo->store('avatars', 'local')]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        if (! $actor->is($user)) {
            $this->activity->log($actor, 'employee.photo', "updated {$user->name}'s photo", $user);
        }
    }

    public function removeAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
    }

    public function addDocument(User $actor, User $user, UploadedFile $file, string $type, string $title, ?string $expiresAt): EmployeeDocument
    {
        $document = $user->employeeDocuments()->create([
            'uploaded_by' => $actor->id,
            'type' => $type,
            'title' => $title,
            'disk' => 'local',
            'path' => $file->store("employee-documents/{$user->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'expires_at' => $expiresAt ?: null,
        ]);

        $this->activity->log($actor, 'employee.document', "added document \"{$title}\" to {$user->name}'s record", $user);

        return $document;
    }

    public function deleteDocument(User $actor, EmployeeDocument $document): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $this->activity->log($actor, 'employee.document_deleted', "removed document \"{$document->title}\" from {$document->user->name}'s record", $document->user);
        $document->delete();
    }

    /** Removes every stored file for a person (used when their account is deleted). */
    public function purgeFiles(User $user): void
    {
        $this->removeAvatar($user);
        foreach ($user->employeeDocuments as $document) {
            Storage::disk($document->disk)->delete($document->path);
        }
    }
}
