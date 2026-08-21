<?php

namespace App\Services\Automation;

use App\Jobs\SendCampaignDelivery;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\Contact;
use App\Models\Store;
use App\Models\Template;

/**
 * Builds the audience for a campaign and dispatches deliveries through the
 * queue, respecting the hourly send limit.
 */
class CampaignService
{
    /**
     * Resolve the recipient contacts from the campaign audience definition.
     * audience: { type: all | tag | segment | manual, value: ... }
     */
    public function resolveAudience(Campaign $campaign): \Illuminate\Support\Collection
    {
        $audience = $campaign->audience ?? [];
        $type = $audience['type'] ?? 'all';

        $query = Contact::query()->where('store_id', $campaign->store_id);

        // Only opted-in contacts are eligible for marketing broadcasts.
        $query->where('consent_state', 'OPT_IN');

        switch ($type) {
            case 'tag':
                $tag = $audience['value'] ?? null;
                if ($tag) {
                    $query->whereJsonContains('tags', $tag);
                }
                break;

            case 'manual':
                $numbers = collect($audience['value'] ?? [])
                    ->map(fn ($n) => preg_replace('/\D+/', '', (string) $n))
                    ->filter()
                    ->values();
                if ($numbers->isNotEmpty()) {
                    $query->whereIn('wa_id', $numbers);
                }
                break;

            case 'segment':
                $query->whereJsonContains('tags', $audience['value'] ?? null); // segment ~ tag for now
                break;

            case 'all':
            default:
                break;
        }

        return $query->get();
    }

    /**
     * Prepare a campaign: compute the audience and create pending deliveries.
     */
    public function prepare(Campaign $campaign): int
    {
        $contacts = $this->resolveAudience($campaign);

        foreach ($contacts as $contact) {
            CampaignDelivery::firstOrCreate(
                ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
                ['store_id' => $campaign->store_id, 'status' => 'pending']
            );
        }

        $campaign->update([
            'total_recipients' => $contacts->count(),
            'status' => $campaign->schedule_at && $campaign->schedule_at->isFuture() ? 'scheduled' : 'sending',
        ]);

        return $contacts->count();
    }

    /**
     * Dispatch pending deliveries to the queue (called by the scheduler or
     * right after scheduling). Rate-limits per hour.
     */
    public function dispatchPending(Campaign $campaign): int
    {
        $limit = max(1, $campaign->send_limit_per_hour ?: 500);
        $sentThisHour = $campaign->deliveries()
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('sent_at', '>=', now()->subHour())
            ->count();

        $available = max(0, $limit - $sentThisHour);

        $pending = $campaign->deliveries()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($available)
            ->get();

        $dispatched = 0;
        foreach ($pending as $delivery) {
            dispatch(new SendCampaignDelivery($delivery))->onQueue('default');
            $dispatched++;
        }

        if ($pending->count() < $available) {
            // No more pending → campaign done.
            $remaining = $campaign->deliveries()->where('status', 'pending')->count();
            if ($remaining === 0) {
                $campaign->update(['status' => 'completed', 'finished_at' => now()]);
            }
        }

        return $dispatched;
    }

    /**
     * Send a campaign immediately (used when no schedule is set).
     */
    public function sendNow(Campaign $campaign): void
    {
        $this->prepare($campaign);
        $this->dispatchPending($campaign);
    }
}
