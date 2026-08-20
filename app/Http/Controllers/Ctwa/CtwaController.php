<?php

namespace App\Http\Controllers\Ctwa;

use App\Http\Controllers\Controller;
use App\Models\CtwaAd;
use Illuminate\Http\Request;

class CtwaController extends Controller
{
    public function index()
    {
        $store = request()->user('store');

        return view('ctwa.index', [
            'store' => $store,
            'ads' => CtwaAd::where('store_id', $store->id)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'meta_campaign_id' => ['nullable', 'string'],
            'destination_wa_number' => ['nullable', 'string'],
            'ctwa_template_id' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $ad = CtwaAd::create(array_merge($data, [
            'store_id' => $store->id,
            'tracking_url' => url('/c/wa/'.$store->id.'?'),
        ]));

        return back()->with('status', 'CTWA ad created. Use the tracking URL in your Meta campaign.');
    }
}
