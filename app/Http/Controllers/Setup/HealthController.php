<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Services\Setup\SetupChecklistService;

class HealthController extends Controller
{
    public function __construct(protected SetupChecklistService $checklist)
    {
    }

    public function index()
    {
        $store = request()->user('store');

        $items = $this->checklist->checklist($store);
        $okCount = collect($items)->where('ok', true)->count();

        return view('setup.health', [
            'store' => $store,
            'items' => $items,
            'okCount' => $okCount,
            'totalCount' => count($items),
        ]);
    }
}
