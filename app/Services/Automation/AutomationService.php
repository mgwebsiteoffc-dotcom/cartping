<?php

namespace App\Services\Automation;

use App\Jobs\SendAutomationMessage;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Store;
use App\Models\WidgetSession;
use Illuminate\Support\Str;

/**
 * Matches a Shopify/web event against the store's automations and schedules
 * message sends (immediately, or after a configurable delay for recovery).
 */
class AutomationService
{
    /**
     * Handle a Shopify order lifecycle event (orders/create, orders/fulfilled,
     * orders/cancelled) or an abandoned-browse detection.
     */
    public function onOrderEvent(Store $store, string $topic, array $payload): void
    {
        $trigger = $this->mapTopicToTrigger($topic);

        if (! $trigger) {
            return;
        }

        $order = $this->upsertOrder($store, $payload);
        $contact = $this->contactForOrder($store, $order, $payload);

        $this->scheduleFor($store, $trigger, [
            'contact' => $contact,
            'order' => $order,
            'event' => ['topic' => $topic, 'payload' => $payload],
        ]);
    }

    public function onAbandonedBrowse(Store $store, WidgetSession $session): void
    {
        $this->scheduleFor($store, 'abandoned_browse', [
            'contact' => $session->contact,
            'order' => null,
            'event' => ['session_id' => $session->id],
        ]);
    }

    public function onAbandonedCheckout(Store $store, array $payload, ?Contact $contact): void
    {
        $this->scheduleFor($store, 'abandoned_checkout', [
            'contact' => $contact,
            'order' => null,
            'event' => ['payload' => $payload],
        ]);
    }

    public function onWelcome(Store $store, Contact $contact, string $source): void
    {
        $this->scheduleFor($store, 'welcome', [
            'contact' => $contact,
            'order' => null,
            'event' => ['source' => $source],
        ]);
    }

    /**
     * Evaluate all matching automations for a trigger and enqueue their runs.
     */
    protected function scheduleFor(Store $store, string $trigger, array $ctx): void
    {
        $contact = $ctx['contact'];
        $order = $ctx['order'];

        if (! $contact || ! $contact->hasOptedIn() && $trigger !== 'welcome') {
            // GDPR: no marketing consent → skip non-service automations.
            return;
        }

        $automations = Automation::where('store_id', $store->id)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            if (! $this->conditionsMatch($automation, $ctx['event'] ?? [])) {
                continue;
            }

            // Idempotency: one run per automation per order/contact.
            $exists = AutomationRun::where('store_id', $store->id)
                ->where('automation_id', $automation->id)
                ->where('contact_id', $contact->id)
                ->where('trigger_event->topic', $ctx['event']['topic'] ?? null)
                ->where('order_id', $order?->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $run = AutomationRun::create([
                'store_id' => $store->id,
                'automation_id' => $automation->id,
                'contact_id' => $contact->id,
                'order_id' => $order?->id,
                'trigger_event' => $ctx['event'] ?? [],
                'due_at' => $automation->dueAt(),
                'state' => 'scheduled',
            ]);

            dispatch(new SendAutomationMessage($run))
                ->onQueue('default')
                ->delay($automation->delay_after_minutes > 0 ? now()->addMinutes($automation->delay_after_minutes) : 0);
        }
    }

    protected function mapTopicToTrigger(string $topic): ?string
    {
        return match ($topic) {
            'orders/create' => 'order_created',
            'orders/fulfilled' => 'order_shipped',
            'orders/cancelled' => 'order_cancelled',
            default => null,
        };
    }

    protected function upsertOrder(Store $store, array $payload): ?Order
    {
        $shopifyOrderId = data_get($payload, 'id');
        if (! $shopifyOrderId) {
            return null;
        }

        $orderNumber = data_get($payload, 'order_number', $shopifyOrderId);

        return Order::updateOrCreate(
            ['store_id' => $store->id, 'shopify_order_id' => $shopifyOrderId],
            [
                'order_number' => $orderNumber,
                'name' => data_get($payload, 'name'),
                'status' => $this->orderStatus($payload),
                'financial_status' => data_get($payload, 'financial_status'),
                'fulfillment_status' => data_get($payload, 'fulfillment_status'),
                'currency' => data_get($payload, 'currency', $store->currency),
                'subtotal_price' => data_get($payload, 'subtotal_price'),
                'total_price' => data_get($payload, 'total_price'),
                'total_discounts' => data_get($payload, 'total_discounts'),
                'total_shipping' => data_get($payload, 'total_shipping_price_set.total_set.shop_money.amount'),
                'total_tax' => data_get($payload, 'total_tax'),
                'line_items' => collect(data_get($payload, 'line_items', []))->map(fn ($li) => [
                    'title' => $li['title'] ?? null,
                    'quantity' => $li['quantity'] ?? 1,
                    'price' => $li['price'] ?? null,
                ])->all(),
                'tracking_number' => data_get($payload, 'fulfillments.0.tracking_number'),
                'tracking_company' => data_get($payload, 'fulfillments.0.tracking_company'),
                'tracking_url' => data_get($payload, 'fulfillments.0.tracking_url'),
                'placed_at' => data_get($payload, 'created_at'),
                'raw' => $payload,
            ]
        );
    }

    protected function orderStatus(array $payload): string
    {
        if (data_get($payload, 'cancelled_at')) {
            return Order::STATUS_CANCELLED;
        }
        if (data_get($payload, 'fulfillment_status') === 'fulfilled') {
            return Order::STATUS_FULFILLED;
        }
        if (data_get($payload, 'financial_status') === 'paid') {
            return Order::STATUS_PAID;
        }

        return Order::STATUS_PENDING;
    }

    protected function contactForOrder(Store $store, ?Order $order, array $payload): ?Contact
    {
        if (! $order) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', (string) data_get($payload, 'shipping_address.phone', ''));
        $email = data_get($payload, 'email');

        if ($phone) {
            $contact = Contact::where('store_id', $store->id)->where('wa_id', $phone)->first();
            if ($contact) {
                $order->update(['contact_id' => $contact->id]);
                return $contact;
            }
        }

        if ($email) {
            // A contact may be linked by email in widget sessions.
            $session = WidgetSession::where('store_id', $store->id)
                ->where('metadata->email', $email)
                ->latest('last_active_at')
                ->first();

            if ($session?->contact_id) {
                $order->update(['contact_id' => $session->contact_id]);
                return $session->contact;
            }
        }

        return null;
    }

    protected function conditionsMatch(Automation $automation, array $event): bool
    {
        $conditions = $automation->conditions ?? [];

        foreach ($conditions as $key => $expected) {
            if (data_get($event, $key) != $expected) {
                return false;
            }
        }

        return true;
    }
}
