<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Silber\Bouncer\BouncerFacade as Bouncer;

class AuthController extends Controller
{
    use ResolvesFrontendView;

    public function showLogin(Request $request): View
    {
        return view($this->frontendView($request, 'auth.login'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'email.required' => __('ui.auth.validation.email_required'),
            'email.email' => __('ui.auth.validation.email_invalid'),
            'password.required' => __('ui.auth.validation.password_required'),
        ]);

        if (! Auth::attempt([
            'email' => (string) $credentials['email'],
            'password' => (string) $credentials['password'],
        ], (bool) ($credentials['remember'] ?? false))) {
            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();

        return redirect()->to($this->resolveIntendedPath($request));
    }

    public function showRegister(Request $request): View
    {
        return view($this->frontendView($request, 'auth.register'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'first_name.required' => __('ui.auth.validation.first_name_required'),
            'last_name.required' => __('ui.auth.validation.last_name_required'),
            'email.required' => __('ui.auth.validation.email_required'),
            'email.email' => __('ui.auth.validation.email_invalid'),
            'email.unique' => __('ui.auth.validation.email_unique'),
            'password.required' => __('ui.auth.validation.password_required'),
            'password.confirmed' => __('ui.auth.validation.password_confirmed'),
        ]);

        $user = User::query()->create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Bouncer::assign('customer')->to($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($this->resolveIntendedPath($request));
    }

    private function resolveIntendedPath(Request $request): string
    {
        $intended = (string) $request->input('intended', '');

        if ($intended !== '') {
            $parts = parse_url($intended);
            $host = (string) ($parts['host'] ?? '');
            $path = (string) ($parts['path'] ?? '');
            $query = (string) ($parts['query'] ?? '');

            if ($host === '' && $path !== '' && str_starts_with($path, '/')) {
                return $path.($query !== '' ? '?'.$query : '');
            }
        }

        return route('account.dashboard');
    }
}
