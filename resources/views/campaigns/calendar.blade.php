@extends('layouts.app')

@section('title', 'Broadcast calendar')

@section('content')
    <div class="row">
        <h1>Broadcast schedule</h1>
        <div class="row">
            <a class="btn" href="{{ route('campaigns.calendar', ['month' => $prevMonth]) }}">← {{ \Illuminate\Support\Carbon::parse($prevMonth.'-01')->format('M') }}</a>
            <span class="month-label">{{ $monthLabel }}</span>
            <a class="btn" href="{{ route('campaigns.calendar', ['month' => $nextMonth]) }}">{{ \Illuminate\Support\Carbon::parse($nextMonth.'-01')->format('M') }} →</a>
        </div>
    </div>

    <div class="row" style="margin:10px 0">
        <a class="btn" href="{{ route('campaigns.index') }}">List view</a>
        <a class="btn primary" href="{{ route('campaigns.index') }}#new">+ New broadcast</a>
    </div>

    <div class="cal-wrap">
        <table class="cal-grid">
            <thead>
            <tr>
                <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($weeks as $week)
                <tr>
                    @foreach ($week as $day)
                        <td class="{{ $day['inMonth'] ? '' : 'muted-day' }} {{ $day['isToday'] ? 'today' : '' }}">
                            <div class="cal-day-num">{{ $day['inMonth'] ? $day['day'] : '' }}</div>
                            <div class="cal-events">
                                @foreach ($day['campaigns'] as $c)
                                    <a class="cal-event" href="{{ route('campaigns.show', $c) }}" title="{{ $c->name }}">
                                        <span class="cal-event-time">{{ $c->schedule_at->format('H:i') }}</span>
                                        {{ $c->name }}
                                    </a>
                                @endforeach
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
