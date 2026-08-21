<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Platform (owner/superadmin) authentication. Separate from the merchant store
 * login. Owner accounts live on the users table with role superadmin/admin and
 * store_id = null.
 */
class PlatformAuthController extends Controller
{
    public function showLogin()
    {
        return view('owner.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])
            ->whereIn('role', ['superadmin', 'admin'])
            ->first();

        if ($user && $user->active && Hash::check($data['password'], $user->password)) {
            Auth::guard('web')->login($user, $request->boolean('remember'));

            return redirect()->route('owner.dashboard');
        }

        return back()->withErrors(['email' => 'Invalid owner credentials.'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('owner.login');
    }
}
