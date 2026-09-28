<?php

namespace App\Livewire\Users;

use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * HR record shown on a person's profile: photo, personal details, guardian /
 * emergency contact and documents. Super admins edit; the employee can view
 * their own record and change their photo.
 */
class EmployeeRecord extends Component
{
    use WithFileUploads;

    private const PHONE = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'];

    #[Locked]
    public int $userId;

    public bool $editing = false;

    public $photo;

    // Details form
    public string $phone = '';

    public array $details = [];

    // Document upload form
    public bool $addingDocument = false;

    public $documentFile;

    public string $documentType = 'id_proof';

    public string $documentTitle = '';

    public string $documentExpires = '';

    public function mount(User $user): void
    {
        $this->authorize('viewEmployeeRecord', $user);
        $this->userId = $user->id;
    }

    private function user(): User
    {
        return User::with('employeeProfile')->findOrFail($this->userId);
    }

    public function edit(): void
    {
        $user = $this->user();
        $this->authorize('manageEmployeeRecord', $user);

        $profile = $user->employeeProfile ?? new EmployeeProfile;
        $this->phone = (string) $user->phone;
        $this->details = collect((new EmployeeProfile)->getFillable())->reject(fn ($f) => $f === 'user_id')
            ->mapWithKeys(fn ($f) => [$f => match (true) {
                $profile->$f instanceof \DateTimeInterface => $profile->$f->format('Y-m-d'),
                default => (string) ($profile->$f ?? ''),
            }])->all();

        $this->resetValidation();
        $this->editing = true;
    }

    public function save(EmployeeService $employees): void
    {
        $user = $this->user();
        $this->authorize('manageEmployeeRecord', $user);

        $this->validate([
            'phone' => self::PHONE,
            'details.employee_code' => ['nullable', 'string', 'max:30', Rule::unique('employee_profiles', 'employee_code')->ignore($user->id, 'user_id')],
            'details.employment_type' => ['nullable', Rule::in(array_keys(EmployeeProfile::EMPLOYMENT_TYPES))],
            'details.date_of_joining' => ['nullable', 'date'],
            'details.date_of_birth' => ['nullable', 'date', 'before:today'],
            'details.gender' => ['nullable', Rule::in(array_keys(EmployeeProfile::GENDERS))],
            'details.blood_group' => ['nullable', Rule::in(EmployeeProfile::BLOOD_GROUPS)],
            'details.alternate_phone' => self::PHONE,
            'details.personal_email' => ['nullable', 'email', 'max:150'],
            'details.address_line' => ['nullable', 'string', 'max:255'],
            'details.city' => ['nullable', 'string', 'max:100'],
            'details.state' => ['nullable', 'string', 'max:100'],
            'details.country' => ['nullable', 'string', 'max:100'],
            'details.postal_code' => ['nullable', 'string', 'max:20'],
            'details.guardian_name' => ['nullable', 'string', 'max:150'],
            'details.guardian_relation' => ['nullable', 'string', 'max:50'],
            'details.guardian_phone' => self::PHONE,
            'details.notes' => ['nullable', 'string', 'max:5000'],
        ], [
            '*.regex' => 'Enter a valid phone number (digits, spaces, +, - and brackets).',
            'details.*.regex' => 'Enter a valid phone number (digits, spaces, +, - and brackets).',
        ], [
            'details.guardian_phone' => 'guardian phone',
            'details.alternate_phone' => 'alternate phone',
            'details.date_of_birth' => 'date of birth',
            'details.employee_code' => 'employee ID',
        ]);

        $clean = collect($this->details)->map(fn ($v) => $v === '' ? null : $v)->all();
        $employees->saveDetails(auth()->user(), $user, $this->phone, $clean);

        $this->editing = false;
        $this->dispatch('notify', message: 'Employee details saved.');
    }

    public function updatedPhoto(EmployeeService $employees): void
    {
        $user = $this->user();
        $this->authorize('updateAvatar', $user);
        $this->validate(['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072']]);

        $employees->setAvatar(auth()->user(), $user, $this->photo);
        $this->photo = null;
        $this->dispatch('notify', message: 'Photo updated.');
    }

    public function removePhoto(EmployeeService $employees): void
    {
        $user = $this->user();
        $this->authorize('updateAvatar', $user);
        $employees->removeAvatar($user);
    }

    public function addDocument(EmployeeService $employees): void
    {
        $user = $this->user();
        $this->authorize('manageEmployeeRecord', $user);

        $this->validate([
            'documentFile' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'documentType' => ['required', Rule::in(array_keys(EmployeeDocument::TYPES))],
            'documentTitle' => ['nullable', 'string', 'max:150'],
            'documentExpires' => ['nullable', 'date'],
        ], [], ['documentFile' => 'document']);

        $title = $this->documentTitle ?: EmployeeDocument::TYPES[$this->documentType];
        $employees->addDocument(auth()->user(), $user, $this->documentFile, $this->documentType, $title, $this->documentExpires ?: null);

        $this->reset('documentFile', 'documentTitle', 'documentExpires', 'addingDocument');
        $this->documentType = 'id_proof';
        $this->dispatch('notify', message: 'Document uploaded.');
    }

    public function deleteDocument(int $documentId, EmployeeService $employees): void
    {
        $user = $this->user();
        $this->authorize('manageEmployeeRecord', $user);
        $employees->deleteDocument(auth()->user(), $user->employeeDocuments()->findOrFail($documentId));
    }

    public function render()
    {
        $user = $this->user();

        return view('livewire.users.employee-record', [
            'user' => $user,
            'profile' => $user->employeeProfile,
            'documents' => $user->employeeDocuments()->with('uploader:id,name')->get(),
            'canManage' => auth()->user()->can('manageEmployeeRecord', $user),
            'canChangePhoto' => auth()->user()->can('updateAvatar', $user),
        ]);
    }
}
