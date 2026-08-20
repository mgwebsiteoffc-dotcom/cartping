<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ShopifyConnection;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /* ----------------------------- Mode 2: manual ----------------------- */

    public function showSignin()
    {
        return view('auth.signin');
    }

    public function signin(Request $request)
    {
        $credentials = $request->validate([
            'myshopify_domain' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Manual mode merchants sign in with their shop domain.
        $store = Store::where('myshopify_domain', $credentials['myshopify_domain'])->first();

        if ($store && \Illuminate\Support\Facades\Hash::check($credentials['password'], $store->password)) {
            Auth::guard('store')->login($store);
            return redirect()->route('dashboard.index');
        }

        return back()->withErrors(['myshopify_domain' => 'Invalid credentials.'])->withInput();
    }

    public function showSignup()
    {
        return view('auth.signup');
    }

    public function signup(Request $request)
    {
        $data = $request->validate([
            'myshopify_domain' => ['required', 'string', 'unique:stores,myshopify_domain'],
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $store = Store::create([
            'name' => $data['name'],
            'myshopify_domain' => Str::lower($data['myshopify_domain']),
            'contact_email' => $data['contact_email'],
            'password' => $data['password'],
            'currency' => 'USD',
            'timezone' => 'UTC',
            'onboarding_step' => 1,
        ]);

        Auth::guard('store')->login($store);

        return redirect()->route('onboarding.index');
    }

    public function showManual()
    {
        return view('auth.manual');
    }

    /**
     * Mode 2: manual custom-app connection.
     * Merchant pastes their Shopify access token AND chooses a WhatsApp
     * provider + credentials in one go.
     */
    public function manualConnect(Request $request, WhatsappManager $whatsapp)
    {
        $data = $request->validate([
            'myshopify_domain' => ['required', 'string', 'unique:stores,myshopify_domain'],
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'shopify_access_token' => ['required', 'string'],
            'whatsapp_provider' => ['required', 'in:meta,whatify'],
            'whatsapp_token' => ['required', 'string'],
            'phone_number_id' => ['nullable', 'string'],
        ]);

        $store = Store::create([
            'name' => $data['name'],
            'myshopify_domain' => Str::lower($data['myshopify_domain']),
            'contact_email' => $data['contact_email'],
            'password' => $data['password'],
            'access_token' => $data['shopify_access_token'],
            'currency' => 'USD',
            'timezone' => 'UTC',
            'onboarding_step' => 2,
        ]);

        ShopifyConnection::create([
            'store_id' => $store->id,
            'mode' => ShopifyConnection::MODE_MANUAL,
            'shop' => $store->myshopify_domain,
            'access_token' => $data['shopify_access_token'],
        ]);

        $connection = WhatsappConnection::create([
            'store_id' => $store->id,
            'provider' => $data['whatsapp_provider'],
            'phone_number_id' => $data['phone_number_id'],
            'token' => $data['whatsapp_token'],
            'is_connected' => false,
        ]);

        // Validate the provider credentials.
        $ping = $whatsapp->forConnection($connection)->ping();
        $connection->update(['is_connected' => $ping['ok'] ?? false]);

        Auth::guard('store')->login($store);

        return redirect()->route('onboarding.index');
    }

    public function logout(Request $request)
    {
        Auth::guard('store')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.signin');
    }
}
