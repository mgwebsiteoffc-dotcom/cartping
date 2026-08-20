<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $store = request()->user('store');

        return view('analytics.index', [
            'store' => $store,
            'period' => (int) $request->input('days', 30),
        ]);
    }

    public function data(Request $request)
    {
        $store = request()->user('store');

        return response()->json(
            $this->analytics->dashboard($store, (int) $request->input('days', 30))
        );
    }
}
