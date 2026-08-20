<?php

namespace App\Http\Controllers\Template;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\Templates\TemplateService;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(protected TemplateService $templates)
    {
    }

    public function index()
    {
        $store = request()->user('store');

        return view('templates.index', [
            'store' => $store,
            'templates' => Template::where('store_id', $store->id)
                ->with('variants')
                ->latest()
                ->get(),
        ]);
    }

    /**
     * AI-generate a template (with A/B variants) from a merchant brief.
     */
    public function generate(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'brief' => ['required', 'string', 'max:2000'],
        ]);

        $template = $this->templates->createFromAi($store, $data['brief']);

        return back()->with('status', 'Template generated. Review it and submit for approval.');
    }

    public function createAbVariants(Template $template)
    {
        $this->templates->createAbVariants(request()->user('store'), $template);

        return back()->with('status', 'A/B variants created.');
    }

    public function submit(Template $template)
    {
        $this->templates->approve(request()->user('store'), $template, 'provider');

        return back()->with('status', 'Template submitted to the provider for approval.');
    }

    public function approve(Template $template, Request $request)
    {
        $level = $request->input('level', 'internal');

        $this->templates->approve(request()->user('store'), $template, $level);

        return back()->with('status', "Approval level '{$level}' recorded.");
    }
}
