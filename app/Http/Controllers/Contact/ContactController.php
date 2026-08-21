<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $store = request()->user('store');

        $query = Contact::where('store_id', $store->id)
            ->with('latestConversation');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($b) use ($q) {
                $b->where('wa_id', 'like', "%{$q}%")
                    ->orWhere('profile_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        $contacts = $query->orderBy('last_seen_at', 'desc')->paginate(25);

        // All distinct tags for the filter + management.
        $allTags = Contact::where('store_id', $store->id)
            ->get('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->filter()
            ->values();

        return view('contacts.index', [
            'store' => $store,
            'contacts' => $contacts,
            'allTags' => $allTags,
        ]);
    }

    public function show(Contact $contact)
    {
        abort_unless($contact->store_id === request()->user('store')->id, 403);

        return view('contacts.show', [
            'store' => request()->user('store'),
            'contact' => $contact->load(['shopifyCustomer', 'conversations', 'latestConversation']),
        ]);
    }

    public function updateTags(Contact $contact, Request $request)
    {
        abort_unless($contact->store_id === request()->user('store')->id, 403);

        $data = $request->validate(['tags' => ['nullable', 'string']]);
        $tags = array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))));

        $contact->update(['tags' => $tags]);

        return back()->with('status', 'Tags updated.');
    }

    /**
     * Export contacts to CSV (optionally filtered by tag/search).
     */
    public function export(Request $request)
    {
        $store = request()->user('store');

        $query = Contact::where('store_id', $store->id);

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(fn ($b) => $b->where('wa_id', 'like', "%{$q}%")
                ->orWhere('profile_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        $contacts = $query->get(['wa_id', 'profile_name', 'email', 'phone', 'consent_state', 'tags', 'last_seen_at']);

        $headers = ['wa_id', 'profile_name', 'email', 'phone', 'consent_state', 'tags', 'last_seen_at'];

        $out = fopen('php://temp', 'w');
        fputcsv($out, $headers);

        foreach ($contacts as $c) {
            fputcsv($out, [
                $c->wa_id,
                $c->profile_name,
                $c->email,
                $c->phone,
                $c->consent_state,
                implode('|', $c->tags ?? []),
                $c->last_seen_at?->toDateTimeString(),
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contacts-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Import contacts from a CSV upload. Columns: wa_id (required), profile_name,
     * email, phone, tags (pipe-separated), consent (OPT_IN/OPT_OUT/NOT_REQUIRED).
     * Upserts by (store_id, wa_id).
     */
    public function import(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $data['csv']->getRealPath();
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => trim(strtolower($h)), $header ?: []);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $assoc = array_combine($header, $row);
                $waId = preg_replace('/\D+/', '', (string) ($assoc['wa_id'] ?? ''));
                if (! $waId) {
                    $skipped++;
                    continue;
                }

                $tags = isset($assoc['tags']) && $assoc['tags'] !== ''
                    ? array_values(array_filter(array_map('trim', explode('|', $assoc['tags']))))
                    : [];

                $consent = strtoupper(trim((string) ($assoc['consent'] ?? '')));
                $consent = in_array($consent, ['OPT_IN', 'OPT_OUT', 'NOT_REQUIRED', 'NA'], true) ? $consent : 'NA';
                $optIn = $consent === 'OPT_IN';

                $contact = Contact::firstOrNew([
                    'store_id' => $store->id,
                    'wa_id' => $waId,
                ]);

                $contact->fill([
                    'profile_name' => $assoc['profile_name'] ?? $contact->profile_name,
                    'email' => $assoc['email'] ?? $contact->email,
                    'phone' => $assoc['phone'] ?? $contact->phone,
                    'tags' => $tags ?: ($contact->tags ?? []),
                    'consent_state' => $consent,
                    'opt_in_at' => $optIn ? ($contact->opt_in_at ?? now()) : $contact->opt_in_at,
                    'opt_in_source' => $optIn && ! $contact->opt_in_source ? 'imported' : $contact->opt_in_source,
                    'first_seen_at' => $contact->first_seen_at ?? now(),
                    'last_seen_at' => now(),
                ]);

                if ($contact->exists) {
                    $contact->save();
                    $updated++;
                } else {
                    $contact->save();
                    $created++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return back()->withErrors(['csv' => 'Import failed: '.$e->getMessage()]);
        }

        fclose($handle);

        return back()->with('status', "Imported: {$created} created, {$updated} updated, {$skipped} skipped.");
    }
}
