@extends('layouts.app')

@section('title', 'AI Agent')

@section('content')
    <h1>AI Store Agent</h1>

    <div class="layout">
        <section class="card">
            <form method="POST" action="{{ route('agent.update') }}" class="stack">
                @csrf
                <label>Name<input name="name" value="{{ $config->name }}"></label>
                <label>Model<input name="model" value="{{ $config->model ?: config('ai.model') }}"></label>
                <label>Temperature<input type="number" step="0.1" min="0" max="2" name="temperature" value="{{ $config->temperature }}"></label>
                <label>Persona<textarea name="persona" rows="4">{{ $config->persona }}</textarea></label>
                <label>Greeting<input name="greeting" value="{{ $config->greeting }}"></label>

                <label>Enabled tools
                    <select name="enabled_tools[]" multiple>
                        @foreach ($all_tools as $tool)
                            <option value="{{ $tool }}" @selected($config->isToolEnabled($tool))>{{ $tool }}</option>
                        @endforeach
                    </select>
                </label>

                <label>RAG enabled<input type="checkbox" name="rag_enabled" value="1" @checked($config->rag_enabled)></label>
                <label>Autonomous<input type="checkbox" name="autonomous" value="1" @checked($config->autonomous)></label>
                <label>Agent enabled<input type="checkbox" name="enabled" value="1" @checked($config->enabled)></label>

                <button class="btn primary" type="submit">Save</button>
            </form>
        </section>

        <section class="card">
            <h2>Knowledge base</h2>
            @forelse ($kb as $chunk)
                <div class="kb"><strong>{{ $chunk->title }}</strong><p>{{ $chunk->content }}</p></div>
            @empty
                <p class="muted">No knowledge base entries yet.</p>
            @endforelse
        </section>
    </div>
@endsection
