@extends('layouts.app')

@section('title', 'Widget')

@section('content')
    <h1>Smart WhatsApp Widget</h1>

    <section class="card">
        <form method="POST" action="{{ route('widget.update') }}" class="stack">
            @csrf

            <label>Widget type
                <select name="type">
                    <option value="simple_button" @selected($config->type === 'simple_button')>Simple button</option>
                    <option value="tooltip" @selected($config->type === 'tooltip')>Tooltip</option>
                    <option value="chat_widget" @selected($config->type === 'chat_widget')>Chat widget</option>
                    <option value="smart_contextual" @selected($config->type === 'smart_contextual')>Smart contextual</option>
                </select>
            </label>

            <label>Install mode
                <select name="install_mode">
                    <option value="script_tag" @selected($config->install_mode === 'script_tag')>Shopify ScriptTag</option>
                    <option value="app_embed" @selected($config->install_mode === 'app_embed')>App Embed Block</option>
                    <option value="manual" @selected($config->install_mode === 'manual')>Manual snippet</option>
                </select>
            </label>

            <label>Launcher color<input name="launcher[color]" value="{{ $config->launcher['color'] ?? '#25D366' }}"></label>
            <label>Entry popup delay (ms)<input type="number" name="entry_popup[delay_ms]" value="{{ $config->entry_popup['delay_ms'] ?? 4000 }}"></label>

            <label>Enabled<input type="checkbox" name="enabled" value="1" @checked($config->enabled)></label>

            <button class="btn primary" type="submit">Save configuration</button>
        </form>

        <form method="POST" action="{{ route('widget.install') }}" style="margin-top:1rem">
            @csrf
            <button class="btn primary" type="submit">Auto-install on store</button>
        </form>
    </section>

    <p class="muted">The widget renders as a simple button, tooltip, chat widget, or smart contextual launcher that changes CTAs based on the page type (product / cart / order / home). Entry popup shows after a delay offering a discount for WhatsApp number capture; exit popup triggers on exit-intent or inactivity with a QR code or capture form.</p>
@endsection
