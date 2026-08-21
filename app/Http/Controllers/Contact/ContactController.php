<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

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
}
