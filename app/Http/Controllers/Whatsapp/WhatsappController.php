<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    public function settings(WhatsappManager $whatsapp)
    {
        $store = request()->user('store');

        return view('settings.whatsapp', [
            'store' => $store,
            'connection' => $store->whatsappConnection,
            'providers' => config('whatsapp.providers'),
        ]);
    }

    public function updateSettings(Request $request, WhatsappManager $whatsapp)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'provider' => ['required', 'in:meta,whatify'],
            'token' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'phone_number_id' => ['nullable', 'string'],
            'waba_id' => ['nullable', 'string'],
        ]);

        $isWhatify = $data['provider'] === WhatsappConnection::PROVIDER_WHATIFY;
        $credential = $isWhatify ? ($data['api_key'] ?? '') : ($data['token'] ?? '');

        if (empty($credential)) {
            return back()->withErrors(['whatsapp' => $isWhatify
                ? 'Please enter your Whatify API key.'
                : 'Please enter your Meta access token.']);
        }

        // Meta also needs the phone number ID to connect.
        if (! $isWhatify && empty($data['phone_number_id'])) {
            return back()->withErrors(['whatsapp' => 'Meta requires a Phone number ID. Find it in your WhatsApp Business account (Meta) → API Setup.']);
        }

        $connection = WhatsappConnection::updateOrCreate(
            ['store_id' => $store->id],
            [
                'provider' => $data['provider'],
                'token' => $data['token'],
                'api_key' => $data['api_key'],
                'api_secret' => $data['api_secret'],
                'phone_number_id' => $data['phone_number_id'],
                'waba_id' => $data['waba_id'],
                'is_connected' => false,
            ]
        );

        $ping = $whatsapp->forConnection($connection)->ping();

        // Persist the attempt so the user keeps their input, but reflect the
        // connection status honestly.
        $connection->update([
            'is_connected' => (bool) ($ping['ok'] ?? false),
            'connected_at' => ($ping['ok'] ?? false) ? now() : $connection->connected_at,
        ]);

        if (! ($ping['ok'] ?? false)) {
            $detail = $ping['error'] ?? 'unknown error';

            return back()->withErrors(['whatsapp' => 'Provider ping failed. '.$detail]);
        }

        return back()->with('status', 'WhatsApp connected successfully.');
    }
}
