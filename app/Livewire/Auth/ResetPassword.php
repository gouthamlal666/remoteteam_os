<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Reset password')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword()
    {
        $key = 'reset-password:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.');

            return;
        }
        RateLimiter::hit($key, 10 * 60);

        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(Settings::minPasswordLength())],
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'password_confirmation' => $this->password_confirmation, 'token' => $this->token],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // One message for unknown email, wrong token and expired link, so this can't be used to find accounts.
            throw ValidationException::withMessages(['email' => $status === Password::RESET_THROTTLED
                ? __($status)
                : 'This reset link is invalid or has expired. Request a new one.']);
        }

        session()->flash('status', 'Password reset — you can sign in now.');

        return $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
