<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Services\Automation\FlowRunner;
use Illuminate\Http\Request;

class FlowController extends Controller
{
    public function index()
    {
        $store = request()->user('store');

        return view('flows.index', [
            'store' => $store,
            'flows' => Flow::where('store_id', $store->id)
                ->withCount('runs')
                ->orderBy('updated_at', 'desc')
                ->get(),
            'templates' => \App\Models\Template::where('store_id', $store->id)->get(),
        ]);
    }

    public function builder(Flow $flow)
    {
        $store = request()->user('store');

        return view('flows.builder', [
            'store' => $store,
            'flow' => $flow,
            'templates' => \App\Models\Template::where('store_id', $store->id)->get(),
        ]);
    }

    public function create(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'in:welcome,new_message,keyword'],
            'trigger_value' => ['nullable', 'string'],
        ]);

        $flow = Flow::create([
            'store_id' => $store->id,
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'trigger_value' => $data['trigger_value'],
            'nodes' => [
                [
                    'id' => uniqid('node_'),
                    'type' => 'start',
                    'label' => 'Start',
                    'data' => [],
                    'next' => null,
                    'true_next' => null,
                    'false_next' => null,
                ],
            ],
            'is_active' => false,
        ]);

        return redirect()->route('flows.builder', $flow);
    }

    /**
     * Save the graph definition (nodes + edges) from the visual builder.
     */
    public function save(Flow $flow, Request $request)
    {
        $this->authorizeOwner($flow);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nodes' => ['required', 'array'],
            'is_active' => ['boolean'],
        ]);

        $flow->update([
            'name' => $data['name'] ?? $flow->name,
            'nodes' => $data['nodes'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $flow->id]);
        }

        return back()->with('status', 'Flow saved.');
    }

    /**
     * Test-run a flow against a phone number (sends the first message).
     */
    public function testRun(Flow $flow, Request $request)
    {
        $this->authorizeOwner($flow);

        $store = request()->user('store');
        $number = preg_replace('/\D+/', '', $request->input('test_number', ''));

        if (! $number) {
            return back()->withErrors(['test_number' => 'Enter a test number.']);
        }

        $contact = \App\Models\Contact::firstOrCreate(
            ['store_id' => $store->id, 'wa_id' => $number],
            ['profile_name' => 'Test']
        );

        app(FlowRunner::class)->run($store, $flow, $contact);

        return back()->with('status', 'Test flow started. Watch the test number for the first message.');
    }

    public function runs(Flow $flow)
    {
        $this->authorizeOwner($flow);

        return view('flows.runs', [
            'store' => request()->user('store'),
            'flow' => $flow,
            'runs' => FlowRun::where('flow_id', $flow->id)->with('contact')->latest()->paginate(20),
        ]);
    }

    protected function authorizeOwner(Flow $flow): void
    {
        abort_unless($flow->store_id === request()->user('store')->id, 403);
    }
}
