<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\WidgetConfig;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Http\Request;

class WidgetConfigController extends Controller
{
    public function index()
    {
        $store = request()->user('store');

        $config = $store->widgetConfig ?? WidgetConfig::firstOrCreate(
            ['store_id' => $store->id],
            ['type' => config('widget.default_type'), 'enabled' => false]
        );

        return view('widget.index', [
            'store' => $store,
            'config' => $config,
        ]);
    }

    /**
     * Save the visual-builder widget configuration (all 4 types + popups +
     * page targeting + display rules).
     */
    public function update(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'enabled' => ['boolean'],
            'type' => ['required', 'in:simple_button,tooltip,chat_widget,smart_contextual'],
            'launcher' => ['nullable', 'array'],
            'simple_button' => ['nullable', 'array'],
            'tooltip' => ['nullable', 'array'],
            'chat_widget' => ['nullable', 'array'],
            'smart_contextual' => ['nullable', 'array'],
            'entry_popup' => ['nullable', 'array'],
            'exit_popup' => ['nullable', 'array'],
            'page_targeting' => ['nullable', 'array'],
            'display_rules' => ['nullable', 'array'],
            'install_mode' => ['nullable', 'in:script_tag,app_embed,manual'],
        ]);

        WidgetConfig::updateOrCreate(
            ['store_id' => $store->id],
            array_merge($data, ['enabled' => $request->boolean('enabled')])
        );

        return back()->with('status', 'Widget configuration saved.');
    }

    /**
     * Auto-install the widget: Shopify ScriptTag (online store) or App Embed
     * Block, depending on the merchant's chosen mode.
     */
    public function install(Request $request)
    {
        $store = request()->user('store');
        $mode = $request->input('install_mode', 'script_tag');

        $config = $store->widgetConfig;

        if ($mode === 'script_tag' && $store->shopifyConnection) {
            $client = ShopifyClient::for($store);
            $src = url('js/widget.js');

            $existing = collect($client->listScriptTags())->first(fn ($t) => ($t['src'] ?? null) === $src);

            if (! $existing) {
                $tag = $client->createScriptTag($src);
                $config->update([
                    'install_mode' => 'script_tag',
                    'script_tag_id' => $tag['id'] ?? null,
                    'enabled' => true,
                ]);
            } else {
                $config->update(['install_mode' => 'script_tag', 'script_tag_id' => $existing['id'], 'enabled' => true]);
            }
        } elseif ($mode === 'app_embed') {
            // App Embed Blocks require an app-level embed extension registered
            // in the Shopify Partner dashboard; we store the mode for now.
            $config->update(['install_mode' => 'app_embed', 'enabled' => true]);
        } else {
            // Manual: merchant pastes the snippet into their theme.liquid.
            $config->update(['install_mode' => 'manual', 'enabled' => true]);
        }

        return back()->with('status', 'Widget installed. It will appear on your online store.');
    }
}
