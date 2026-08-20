<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentConfig;
use App\Models\KnowledgeBaseChunk;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index()
    {
        $store = request()->user('store');

        $config = $store->agentConfig ?? AgentConfig::firstOrCreate(
            ['store_id' => $store->id],
            ['enabled' => true, 'autonomous' => true]
        );

        return view('agent.index', [
            'store' => $store,
            'config' => $config,
            'kb' => KnowledgeBaseChunk::where('store_id', $store->id)->latest()->get(),
            'all_tools' => array_keys(app(\App\Services\Ai\Tools\ToolRegistry::class)->all()),
        ]);
    }

    public function update(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'enabled' => ['boolean'],
            'autonomous' => ['boolean'],
            'persona' => ['nullable', 'string'],
            'greeting' => ['nullable', 'string'],
            'enabled_tools' => ['nullable', 'array'],
            'escalation_triggers' => ['nullable', 'array'],
            'rag_enabled' => ['boolean'],
        ]);

        AgentConfig::updateOrCreate(
            ['store_id' => $store->id],
            array_merge($data, [
                'enabled' => $request->boolean('enabled'),
                'autonomous' => $request->boolean('autonomous'),
                'rag_enabled' => $request->boolean('rag_enabled'),
            ])
        );

        return back()->with('status', 'AI agent configuration saved.');
    }
}
