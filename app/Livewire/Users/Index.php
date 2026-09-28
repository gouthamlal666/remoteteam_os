<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('People')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    #[Url(except: '')]
    public string $departmentFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'member';

    public ?int $department_id = null;

    public string $job_title = '';

    public string $skills = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'departmentFilter'])) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('create', User::class);
        $this->reset('editingId', 'name', 'email', 'password', 'role', 'job_title', 'skills', 'is_active');
        $this->department_id = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(User $user): void
    {
        $this->authorize('update', $user);
        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role->value;
        $this->department_id = $user->department_id;
        $this->job_title = (string) $user->job_title;
        $this->skills = implode(', ', $user->skills ?? []);
        $this->is_active = $user->is_active;
        $this->showForm = true;
    }

    public function save(ActivityLogger $activity)
    {
        $actor = auth()->user();
        $user = $this->editingId ? User::findOrFail($this->editingId) : null;
        $this->authorize($user ? 'update' : 'create', $user ?? User::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$user ? 'nullable' : 'required', Password::min(Settings::minPasswordLength())],
            'role' => ['required', new Enum(Role::class)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'skills' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $role = Role::from($data['role']);
        abort_unless($actor->can('assignRole', [User::class, $role]), 403);

        // Nobody can deactivate or demote themselves.
        if ($user?->is($actor)) {
            $data['is_active'] = true;
            $data['role'] = $actor->role->value;
        }

        $data['skills'] = Str::of($data['skills'] ?? '')->explode(',')->map(fn ($s) => trim($s))->filter()->values()->all();

        if (blank($data['password'])) {
            unset($data['password']);
        }

        if ($user) {
            $passwordChanged = isset($data['password']);
            $user->update($data);

            if ($passwordChanged || ! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $activity->log($actor, 'user.updated', "updated {$user->name}'s profile", $user);
        } else {
            $user = User::create($data + [
                'email_verified_at' => now(),
                'timezone' => Settings::get('general.default_timezone'),
                'work_start' => Settings::get('work.day_start'),
                'work_end' => Settings::get('work.day_end'),
            ]);
            $activity->log($actor, 'user.created', "added {$user->name} as {$user->role->label()}", $user);
        }

        $this->showForm = false;

        if ($user->wasRecentlyCreated && $actor->can('manageEmployeeRecord', $user)) {
            session()->flash('status', "Added {$user->name}. Now fill in their employee record.");

            return $this->redirect(route('users.show', $user).'#employee', navigate: true);
        }

        $this->dispatch('notify', message: 'Saved '.$user->name.'.');
    }

    public function delete(int $userId, UserService $users): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('delete', $user);

        $moved = $users->delete(auth()->user(), $user);
        $this->showForm = false;

        $summary = collect($moved)->filter()->map(fn ($n, $what) => "$n $what")->join(', ');
        $this->dispatch('notify', message: "Deleted {$user->name}.".($summary ? " Transferred to you: {$summary}." : ''));
    }

    public function render()
    {
        $actor = auth()->user();

        $users = User::manageableBy($actor)
            ->with('department')
            ->when($actor->isManager(), fn ($q) => $q->with('employeeProfile')->withCount('employeeDocuments'))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('job_title', 'like', "%{$this->search}%")))
            ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->departmentFilter, fn ($q) => $q->where('department_id', $this->departmentFilter))
            ->orderByRaw("case role when 'super_admin' then 0 when 'admin' then 1 else 2 end")
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.users.index', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'roles' => $actor->isSuperAdmin() ? Role::cases() : [Role::Member],
            'actor' => $actor,
        ]);
    }
}
