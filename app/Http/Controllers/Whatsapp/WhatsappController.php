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
            'token' => ['required', 'string'],
            'phone_number_id' => ['nullable', 'string'],
            'waba_id' => ['nullable', 'string'],
        ]);

        $connection = WhatsappConnection::updateOrCreate(
            ['store_id' => $store->id],
            [
                'provider' => $data['provider'],
                'token' => $data['token'],
                'phone_number_id' => $data['phone_number_id'],
                'waba_id' => $data['waba_id'],
                'is_connected' => false,
            ]
        );

        $ping = $whatsapp->forConnection($connection)->ping();
        $connection->update(['is_connected' => $ping['ok'] ?? false, 'connected_at' => now()]);

        return back()->with('status', $ping['ok'] ?? false
            ? 'WhatsApp connected successfully.'
            : 'Connected but the provider ping failed — check your credentials.');
    }
}
