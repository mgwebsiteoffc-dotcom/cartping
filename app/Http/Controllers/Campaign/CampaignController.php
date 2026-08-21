<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Template;
use App\Services\Automation\CampaignService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns)
    {
    }

    public function index()
    {
        $store = request()->user('store');

        return view('campaigns.index', [
            'store' => $store,
            'campaigns' => Campaign::where('store_id', $store->id)->latest()->paginate(20),
            'templates' => Template::where('store_id', $store->id)->get(),
            'segments' => \App\Models\Segment::where('store_id', $store->id)->get(),
            'contactsCount' => Contact::where('store_id', $store->id)->where('consent_state', 'OPT_IN')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'message_body' => ['nullable', 'string'],
            'audience_type' => ['required', 'in:all,tag,manual,segment'],
            'audience_tag' => ['nullable', 'string'],
            'audience_segment_id' => ['nullable', 'exists:segments,id'],
            'audience_numbers' => ['nullable', 'string'],
            'schedule_at' => ['nullable', 'date'],
            'send_limit_per_hour' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $audience = match ($data['audience_type']) {
            'tag' => ['type' => 'tag', 'value' => $data['audience_tag']],
            'segment' => ['type' => 'segment', 'value' => $data['audience_segment_id']],
            'manual' => ['type' => 'manual', 'value' => array_filter(array_map('trim', explode(',', $data['audience_numbers'] ?? '')))],
            default => ['type' => 'all'],
        };

        $campaign = Campaign::create([
            'store_id' => $store->id,
            'name' => $data['name'],
            'template_id' => $data['template_id'] ?? null,
            'message_body' => $data['message_body'] ?? null,
            'audience' => $audience,
            'schedule_at' => $data['schedule_at'] ?? null,
            'send_limit_per_hour' => $data['send_limit_per_hour'] ?? 500,
            'status' => 'draft',
        ]);

        // If no schedule, send now.
        if (! $campaign->schedule_at) {
            $this->campaigns->sendNow($campaign);
            return back()->with('status', "Campaign '{$campaign->name}' started.");
        }

        $this->campaigns->prepare($campaign);

        return back()->with('status', "Campaign '{$campaign->name}' scheduled for {$campaign->schedule_at}.");
    }

    public function show(Campaign $campaign)
    {
        $this->authorizeOwner($campaign);

        return view('campaigns.show', [
            'store' => request()->user('store'),
            'campaign' => $campaign->load('deliveries.contact', 'template'),
        ]);
    }

    /**
     * Calendar view of scheduled campaigns for a month (defaults to current).
     */
    public function calendar(Request $request)
    {
        $store = request()->user('store');

        // Month navigation: ?month=YYYY-MM
        $month = $request->input('month', now()->format('Y-m'));
        $start = \Illuminate\Support\Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        // Campaigns with a schedule in this month (or already running/scheduled).
        $campaigns = Campaign::where('store_id', $store->id)
            ->whereNotNull('schedule_at')
            ->whereBetween('schedule_at', [$start, $end])
            ->orderBy('schedule_at')
            ->get();

        // Index by day-of-month for the grid.
        $byDay = $campaigns->groupBy(fn ($c) => $c->schedule_at->day);

        // Build the calendar weeks (weeks starting Monday).
        $weeks = [];
        $cursor = (clone $start)->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $today = now()->toDateString();

        while ($cursor <= $end) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $date = (clone $cursor)->addDays($d);
                $week[] = [
                    'date' => $date->toDateString(),
                    'day' => $date->day,
                    'inMonth' => $date->month === (int) $start->format('m'),
                    'isToday' => $date->toDateString() === $today,
                    'campaigns' => $byDay->get($date->day, collect()),
                ];
            }
            $weeks[] = $week;
            $cursor->addWeek();
        }

        return view('campaigns.calendar', [
            'store' => $store,
            'weeks' => $weeks,
            'monthLabel' => $start->format('F Y'),
            'prevMonth' => (clone $start)->subMonth()->format('Y-m'),
            'nextMonth' => (clone $start)->addMonth()->format('Y-m'),
            'currentMonth' => $month,
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorizeOwner($campaign);
        $campaign->update(['status' => 'cancelled', 'finished_at' => now()]);

        return back()->with('status', 'Campaign cancelled.');
    }

    protected function authorizeOwner(Campaign $campaign): void
    {
        abort_unless($campaign->store_id === request()->user('store')->id, 403);
    }
}
