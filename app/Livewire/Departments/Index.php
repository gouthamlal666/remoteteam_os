<?php

namespace App\Livewire\Departments;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Departments')]
class Index extends Component
{
    public const COLORS = ['indigo', 'sky', 'emerald', 'amber', 'rose', 'violet', 'slate'];

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $color = 'indigo';

    public ?int $manager_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Department::class);
    }

    public function create(): void
    {
        $this->authorize('create', Department::class);
        $this->reset('editingId', 'name', 'description', 'color', 'manager_id');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(Department $department): void
    {
        $this->authorize('update', $department);
        $this->resetValidation();
        $this->editingId = $department->id;
        $this->name = $department->name;
        $this->description = (string) $department->description;
        $this->color = $department->color;
        $this->manager_id = $department->manager_id;
        $this->showForm = true;
    }

    public function save(ActivityLogger $activity): void
    {
        $department = $this->editingId ? Department::findOrFail($this->editingId) : null;
        $this->authorize($department ? 'update' : 'create', $department ?? Department::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', Rule::in(self::COLORS)],
            'manager_id' => ['nullable', Rule::exists('users', 'id')->where('role', Role::Admin->value)],
        ]);
        $data['slug'] = Str::slug($data['name']);

        if ($department) {
            $department->update($data);
            $activity->log(auth()->user(), 'department.updated', "updated department {$department->name}", $department);
        } else {
            $department = Department::create($data);
            $activity->log(auth()->user(), 'department.created', "created department {$department->name}", $department);
        }

        // A manager without a home department gets this one; otherwise their own
        // department is left alone (admins can manage several departments).
        if ($department->manager_id) {
            User::whereKey($department->manager_id)->whereNull('department_id')->update(['department_id' => $department->id]);
        }

        $this->showForm = false;
        $this->dispatch('notify', message: 'Department saved.');
    }

    public function delete(Department $department, ActivityLogger $activity): void
    {
        $this->authorize('delete', $department);
        $activity->log(auth()->user(), 'department.deleted', "deleted department {$department->name}");
        $department->delete();
        $this->dispatch('notify', message: 'Department deleted.');
    }

    public function render()
    {
        return view('livewire.departments.index', [
            'departments' => Department::with('manager')->withCount(['users', 'projects'])->orderBy('name')->get(),
            'admins' => User::role(Role::Admin)->active()->orderBy('name')->get(['id', 'name']),
            'canManage' => auth()->user()->isSuperAdmin(),
        ]);
    }
}
