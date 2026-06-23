<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Local-only shortcut to sign in without Google. 404s in any non-local environment,
 * so it is safe to leave registered (and keeps routes cacheable for production).
 */
class DevLoginController extends Controller
{
    public function __invoke()
    {
        abort_unless(app()->environment('local'), 404);

        $user = User::where('is_super_admin', true)->first() ?? User::first();
        abort_if(! $user, 404, 'No user to log in as. Run: php artisan db:seed');

        Auth::login($user);

        return redirect()->route('portfolio');
    }
}
