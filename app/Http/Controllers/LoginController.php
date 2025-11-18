<?php
//app/Http/Controllers/LoginController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function show()
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        return view('auth.login', ['title' => 'Sign in']);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $data = $this->validateLogin($request);

        $login    = $data['login'];
        $password = $data['password'];
        $remember = (bool) ($data['remember'] ?? false);

        // Simple throttle: 5 attempts, 60s decay
        $throttleKey = Str::lower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $login, 'password' => $password], $remember)) {
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);

            return redirect()->intended('/');
        }

        RateLimiter::hit($throttleKey, 60);

        throw ValidationException::withMessages([
            'login' => 'These credentials do not match our records.',
        ]);
    }

public function destroy(Request $request)
{
    \Illuminate\Support\Facades\Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login'); // <-- use 'login' (defined in routes)
}

    /**
     * Request validation for login.
     */
    protected function validateLogin(Request $request): array
    {
        return $request->validate([
            'login'    => ['required', 'string', 'max:255'], // username or email
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);
    }
}
