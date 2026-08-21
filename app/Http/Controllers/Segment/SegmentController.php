<?php

namespace App\Http\Controllers\Segment;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Services\Segments\SegmentResolver;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    public function __construct(protected SegmentResolver $resolver)
    {
    }

    public function index()
    {
        $store = request()->user('store');

        return view('segments.index', [
            'store' => $store,
            'segments' => Segment::where('store_id', $store->id)
                ->orderBy('updated_at', 'desc')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'conditions' => ['nullable', 'json'],
        ]);

        $conditions = $data['conditions']
            ? json_decode($data['conditions'], true)
            : [['group' => 0, 'field' => 'opted_in', 'operator' => 'exists', 'value' => null]];

        $segment = Segment::create([
            'store_id' => $store->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'conditions' => $conditions,
            'is_active' => true,
        ]);

        $segment->update(['contact_count' => $this->resolver->count($segment)]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $segment->id, 'count' => $segment->contact_count]);
        }

        return back()->with('status', "Segment '{$segment->name}' created ({$segment->contact_count} contacts).");
    }

    public function preview(Request $request)
    {
        $store = request()->user('store');

        $conditions = json_decode($request->input('conditions', '[]'), true) ?: [];
        $count = $this->resolver->queryForConditions($store->id, $conditions)->count();

        return response()->json(['count' => $count]);
    }

    public function refresh(Segment $segment)
    {
        abort_unless($segment->store_id === request()->user('store')->id, 403);

        $segment->update(['contact_count' => $this->resolver->count($segment)]);

        return back()->with('status', "Segment '{$segment->name}' refreshed: {$segment->contact_count} contacts.");
    }

    public function destroy(Segment $segment)
    {
        abort_unless($segment->store_id === request()->user('store')->id, 403);
        $segment->delete();

        return back()->with('status', 'Segment deleted.');
    }
}
