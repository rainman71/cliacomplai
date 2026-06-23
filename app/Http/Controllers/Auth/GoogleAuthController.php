<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /** Kick off the OAuth flow, hinting the Workspace domain when configured. */
    public function redirect()
    {
        $driver = Socialite::driver('google');

        if ($domain = config('services.google.allowed_domain')) {
            $driver->with(['hd' => $domain]); // pre-selects the Workspace domain
        }

        return $driver->redirect();
    }

    /** Handle the callback: enforce domain, then find-or-create the user and log in. */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['google' => 'Google sign-in failed. Please try again.']);
        }

        $email = $googleUser->getEmail();
        $allowed = config('services.google.allowed_domain');

        if ($allowed && ! hash_equals(strtolower($allowed), strtolower(Str::after($email, '@')))) {
            return redirect()->route('login')
                ->withErrors(['google' => "Only @{$allowed} accounts may sign in to this app."]);
        }

        $user = User::where('google_sub', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            // Account is created; lab access is granted separately by an admin (per-lab
            // membership). Until then the user just sees an empty portfolio.
            $user = new User(['active' => true]);
        }

        $user->fill([
            'name' => $googleUser->getName() ?: $email,
            'email' => $email,
            'google_sub' => $googleUser->getId(),
        ]);

        // SSO users never use a password, but the column is required — give it a random one.
        if (empty($user->password)) {
            $user->password = Str::random(40);
        }

        $user->save();

        if (! $user->active) {
            return redirect()->route('login')->withErrors([
                'google' => 'Your account is disabled. Contact a compliance administrator.',
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('portfolio'));
    }
}
