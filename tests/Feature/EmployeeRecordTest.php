<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Livewire\Users\EmployeeRecord;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->super = User::factory()->superAdmin()->create();
        $this->employee = User::factory()->create();
    }

    public function test_super_admin_fills_in_details_photo_and_documents(): void
    {
        Livewire::actingAs($this->super)->test(EmployeeRecord::class, ['user' => $this->employee])
            ->call('edit')
            ->set('phone', '+91 98765 43210')
            ->set('details.employee_code', 'RT-0099')
            ->set('details.date_of_birth', '1998-04-12')
            ->set('details.guardian_name', 'Joseph George')
            ->set('details.guardian_relation', 'Father')
            ->set('details.guardian_phone', '+91 90000 11111')
            ->call('save')
            ->assertHasNoErrors()
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->assertHasNoErrors()
            ->set('documentType', 'id_proof')
            ->set('documentTitle', 'Passport')
            ->set('documentFile', UploadedFile::fake()->create('passport.pdf', 200, 'application/pdf'))
            ->call('addDocument')
            ->assertHasNoErrors()
            ->assertSee('Passport');

        $employee = $this->employee->fresh('employeeProfile');
        $this->assertSame('+91 98765 43210', $employee->phone);
        $this->assertSame('Joseph George', $employee->employeeProfile->guardian_name);
        Storage::disk('local')->assertExists($employee->avatar_path);
        Storage::disk('local')->assertExists($employee->employeeDocuments->first()->path);

        // Audit log records the change but not the personal values.
        $this->assertDatabaseHas('activities', ['action' => 'employee.updated']);
        $this->assertDatabaseMissing('activities', ['description' => 'Joseph George']);
    }

    public function test_invalid_phone_and_unsafe_files_are_rejected(): void
    {
        Livewire::actingAs($this->super)->test(EmployeeRecord::class, ['user' => $this->employee])
            ->call('edit')
            ->set('details.guardian_phone', 'call me maybe')
            ->call('save')
            ->assertHasErrors('details.guardian_phone')
            ->set('photo', UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml'))
            ->assertHasErrors('photo')
            ->set('documentFile', UploadedFile::fake()->create('script.exe', 5))
            ->call('addDocument')
            ->assertHasErrors('documentFile');
    }

    public function test_employee_sees_own_record_but_cannot_edit_it(): void
    {
        Livewire::actingAs($this->employee)->test(EmployeeRecord::class, ['user' => $this->employee])
            ->assertSee('Employee record')
            ->call('edit')
            ->assertForbidden();

        // …but can change their own photo.
        Livewire::actingAs($this->employee)->test(EmployeeRecord::class, ['user' => $this->employee])
            ->set('photo', UploadedFile::fake()->image('me.png'))
            ->assertHasNoErrors();
    }

    public function test_admins_manage_member_records_but_colleagues_cannot_see_them(): void
    {
        $admin = User::factory()->admin()->create();
        $doc = $this->employee->employeeDocuments()->create([
            'type' => 'id_proof', 'title' => 'ID', 'path' => 'employee-documents/x.pdf', 'original_name' => 'x.pdf',
        ]);

        Livewire::actingAs($admin)->test(EmployeeRecord::class, ['user' => $this->employee])
            ->call('edit')->set('details.guardian_name', 'Guardian')->call('save')->assertHasNoErrors();

        // …but not the records of other admins or the super admin.
        Livewire::actingAs($admin)->test(EmployeeRecord::class, ['user' => $this->super])->assertForbidden();

        $colleague = User::factory()->create();
        $this->actingAs($colleague)->get(route('users.show', $this->employee))->assertOk()->assertDontSee('Employee record');
        $this->actingAs($colleague)->get(route('employee-documents.show', $doc))->assertForbidden();
        Livewire::actingAs($colleague)->test(EmployeeRecord::class, ['user' => $this->employee])->assertForbidden();
    }

    public function test_photo_shows_everywhere_and_is_served_only_to_signed_in_users(): void
    {
        Livewire::actingAs($this->employee)->test(Settings::class)
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->assertHasNoErrors();

        $url = $this->employee->fresh()->avatarUrl();
        $this->assertNotNull($url);

        $this->actingAs($this->super)->get($url)->assertOk();
        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_deleting_the_person_removes_their_files(): void
    {
        $this->employee->update(['avatar_path' => UploadedFile::fake()->image('a.jpg')->store('avatars', 'local')]);
        $doc = UploadedFile::fake()->create('cv.pdf', 10)->store('employee-documents', 'local');
        $this->employee->employeeDocuments()->create(['type' => 'resume', 'title' => 'CV', 'path' => $doc, 'original_name' => 'cv.pdf']);
        $avatar = $this->employee->avatar_path;

        app(UserService::class)->delete($this->super, $this->employee);

        Storage::disk('local')->assertMissing($avatar);
        Storage::disk('local')->assertMissing($doc);
        $this->assertDatabaseCount('employee_documents', 0);
    }
}
