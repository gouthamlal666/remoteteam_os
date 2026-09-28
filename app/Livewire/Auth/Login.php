<?php

namespace App\Livewire\Auth;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        // Per account + IP (5/min) and per account from anywhere (20 per 15 min),
        // so rotating IP addresses doesn't allow unlimited password guessing.
        $key = 'login:'.Str::lower($this->email).'|'.request()->ip();
        $accountKey = 'login-account:'.Str::lower($this->email);

        foreach ([$key => 5, $accountKey => 20] as $limiterKey => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
                throw ValidationException::withMessages([
                    'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($limiterKey).' seconds.',
                ]);
            }
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key);
            RateLimiter::hit($accountKey, 15 * 60);

            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);
        session()->regenerate();
        app(ActivityLogger::class)->log(Auth::user(), 'auth.login', 'signed in', null, ['ip' => request()->ip()]);

        return $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
