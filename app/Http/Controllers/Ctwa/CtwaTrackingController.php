<?php

namespace App\Http\Controllers\Ctwa;

use App\Events\CtwaClickRecorded;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\ClickEvent;
use App\Models\CtwaAd;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CTWA attribution pipeline.
 *  - click():  ad click -> record fingerprint + set a browser cookie -> 302 to wa.me
 *  - lead():   called by the widget/webhook when a WhatsApp conversation starts
 */
class CtwaTrackingController extends Controller
{
    public function click(Request $request, string $ad)
    {
        $ad = CtwaAd::find($ad) ?? CtwaAd::where('tracking_url', $request->fullUrl())->first();

        if (! $ad || ! $ad->is_active) {
            abort(404);
        }

        $fingerprint = $request->input('fid')
            ?? $request->input('fbclid')
            ?? Str::random(32);

        $click = ClickEvent::create([
            'store_id' => $ad->store_id,
            'ctwa_ad_id' => $ad->id,
            'click_id' => (string) Str::uuid(),
            'fingerprint' => hash('sha256', $fingerprint),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'clicked_at' => now(),
            'converted' => false,
        ]);

        CtwaClickRecorded::dispatch($ad->store, $click);

        $number = $ad->destination_wa_number
            ?: preg_replace('/\D+/', '', $ad->store->whatsappConnection?->phone_number_id ?? '');

        $waLink = 'https://wa.me/'.ltrim($number, '+');
        $prefill = rawurlencode($this->prefillText($ad));

        return redirect()->away($waLink.($prefill ? '?text='.$prefill : ''));
    }

    public function lead(Request $request, string $ad)
    {
        $ad = CtwaAd::find($ad);

        if (! $ad) {
            return response()->json(['error' => 'unknown_ad'], 404);
        }

        AnalyticsEvent::record('ctwa.lead', [
            'store_id' => $ad->store_id,
            'ad_id' => $ad->id,
            'payload' => [
                'click_id' => $request->input('click_id'),
                'wa_id' => $request->input('wa_id'),
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    protected function prefillText(CtwaAd $ad): string
    {
        return "Hi! I'm interested in the {$ad->name} offer.";
    }
}
