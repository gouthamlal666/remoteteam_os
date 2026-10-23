<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ClientFileController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\EmployeeFileController;
use App\Livewire;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Company logo (System settings) — public so it can show on the sign-in page.
Route::get('/branding/logo', function () {
    $path = Settings::get('general.logo_path');
    abort_unless($path && Storage::disk('local')->exists($path), 404);

    return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
})->name('branding.logo');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
    Route::livewire('/forgot-password', Livewire\Auth\ForgotPassword::class)->name('password.request');
    Route::livewire('/reset-password/{token}', Livewire\Auth\ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/dashboard');
    Route::livewire('/dashboard', Livewire\Dashboard::class)->name('dashboard');

    Route::livewire('/projects', Livewire\Projects\Index::class)->name('projects.index');
    Route::livewire('/projects/{project}', Livewire\Projects\Show::class)->name('projects.show');

    Route::livewire('/tasks', Livewire\Tasks\Index::class)->name('tasks.index');
    Route::livewire('/tasks/{task}', Livewire\Tasks\Show::class)->name('tasks.show');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::livewire('/clients', Livewire\Clients\Index::class)->name('clients.index');
    Route::livewire('/clients/{client}', Livewire\Clients\Show::class)->name('clients.show');
    Route::get('/clients/{client}/logo', [ClientFileController::class, 'logo'])->name('clients.logo');
    Route::get('/client-files/{file}', [ClientFileController::class, 'download'])->name('client-files.show');

    Route::livewire('/people', Livewire\Users\Index::class)->name('users.index');
    Route::livewire('/people/{user}', Livewire\Users\Show::class)->name('users.show');

    Route::get('/avatars/{user}', [EmployeeFileController::class, 'avatar'])->name('avatars.show');
    Route::get('/employee-documents/{document}', [EmployeeFileController::class, 'document'])->name('employee-documents.show');

    Route::livewire('/departments', Livewire\Departments\Index::class)->name('departments.index');

    Route::livewire('/chat/{conversation?}', Livewire\Chat\Index::class)->name('chat');
    Route::get('/message/{user}', DirectMessageController::class)->name('chat.with');
    Route::get('/chat-files/{attachment}', [ChatAttachmentController::class, 'show'])->name('chat.attachments.show');

    Route::livewire('/meetings', Livewire\Meetings\Index::class)->name('meetings.index');
    Route::livewire('/meetings/{meeting}', Livewire\Meetings\Show::class)->name('meetings.show');

    Route::livewire('/reports', Livewire\Reports\Index::class)->name('reports.index');

    Route::livewire('/activity', Livewire\Activity\Index::class)->name('activity.index');
    Route::livewire('/notifications', Livewire\Notifications::class)->name('notifications');
    Route::livewire('/settings', Livewire\Settings::class)->name('settings');
    Route::livewire('/system-settings', Livewire\SystemSettings::class)->name('system-settings');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
