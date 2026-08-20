<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates raw AnalyticsEvent rows into dashboard metrics: message
 * performance, automation conversion, template A/B, CTWA ROAS, widget CTR,
 * AI resolution rates, escalation reasons and revenue attribution.
 */
class AnalyticsService
{
    public function dashboard(Store $store, int $days = 30): array
    {
        $from = now()->subDays($days);
        $events = AnalyticsEvent::where('store_id', $store->id)
            ->where('occurred_at', '>=', $from)
            ->get();

        return [
            'period' => ['days' => $days, 'from' => $from->toIso8601String(), 'to' => now()->toIso8601String()],
            'messages' => $this->messages($events),
            'automations' => $this->automations($events),
            'templates' => $this->templates($events),
            'ctwa' => $this->ctwa($events),
            'widget' => $this->widget($events),
            'ai' => $this->ai($events),
            'revenue' => $this->revenue($events),
        ];
    }

    protected function messages(Collection $e): array
    {
        return [
            'sent' => $e->where('event', 'message.sent')->count(),
            'received' => $e->where('event', 'message.received')->count(),
            'delivered' => $e->where('event', 'message.delivered')->count(),
            'read' => $e->where('event', 'message.read')->count(),
        ];
    }

    protected function automations(Collection $e): array
    {
        $sent = $e->where('event', 'automation.converted_sent');
        $triggered = $sent->where('automation_id', '!=', null);

        return [
            'runs' => $triggered->count(),
            'converted' => $sent->where('value', '>', 0)->count(),
            'conversion_rate' => $triggered->count() ? round($sent->where('value', '>', 0)->count() / $triggered->count() * 100, 1) : 0,
        ];
    }

    protected function templates(Collection $e): array
    {
        return [
            'sends' => $e->where('event', 'template.sent')->count(),
            'delivered' => $e->where('event', 'template.delivered')->count(),
            'by_template' => $e->where('event', 'template.sent')->groupBy('template_id')
                ->map->count()->toArray(),
        ];
    }

    protected function ctwa(Collection $e): array
    {
        $clicks = $e->where('event', 'ctwa.click');
        $revenue = $e->where('event', 'revenue.attributed');
        $spend = $clicks->sum('value') ?: 0; // replaced by ad spend where known

        $revenueTotal = $revenue->sum('value');

        return [
            'clicks' => $clicks->count(),
            'leads' => $e->where('event', 'ctwa.lead')->count(),
            'revenue' => $revenueTotal,
            'roas' => $spend > 0 ? round($revenueTotal / $spend, 2) : null,
        ];
    }

    protected function widget(Collection $e): array
    {
        $impressions = $e->where('event', 'widget.loaded')->count();
        $clicks = $e->where('event', 'widget.click')->count();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions ? round($clicks / $impressions * 100, 2) : 0,
            'popups_shown' => $e->where('event', 'popup.shown')->count(),
            'optins' => $e->where('event', 'consent.opt_in')->count(),
        ];
    }

    protected function ai(Collection $e): array
    {
        $actions = $e->where('event', 'ai.action');
        $escalations = $e->where('event', 'agent.escalated');

        return [
            'actions' => $actions->count(),
            'resolved' => $actions->where('payload->tool_calls', '!=', null)->count(),
            'escalations' => $escalations->count(),
            'resolution_rate' => $actions->count()
                ? round(($actions->count() - $escalations->count()) / $actions->count() * 100, 1)
                : 0,
            'escalation_reasons' => $escalations->pluck('payload.reason')->countBy()->toArray(),
        ];
    }

    protected function revenue(Collection $e): array
    {
        return [
            'attributed' => $e->where('event', 'revenue.attributed')->sum('value'),
            'orders' => $e->where('event', 'revenue.attributed')->count(),
        ];
    }
}
