<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * SaaS owner panel: overview of every store, plan management, user management.
 * Only platform staff (superadmin/admin) can access.
 */
class OwnerController extends Controller
{
    public function dashboard()
    {
        $totalStores = Store::count();
        $activeStores = Store::whereNull('disabled_at')->count();
        $totalContacts = Contact::count();
        $totalMessages = Message::count();
        $totalUsers = User::count();

        // MRR: sum of monthly price for stores on an active (non-free) plan.
        $paidStores = Store::with('plan')->whereNotNull('plan_id')->whereNull('disabled_at')->get();
        $totalRevenueEstimate = $paidStores->sum(fn ($s) => $s->plan?->price_monthly ?? 0);
        $paidCount = $paidStores->count();

        // MRR grouped by plan.
        $revenueByPlan = $paidStores
            ->groupBy(fn ($s) => $s->plan?->code ?? 'none')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'mrr' => $group->sum(fn ($s) => $s->plan?->price_monthly ?? 0),
            ]);

        return view('owner.dashboard', [
            'totalStores' => $totalStores,
            'activeStores' => $activeStores,
            'totalContacts' => $totalContacts,
            'totalMessages' => $totalMessages,
            'totalUsers' => $totalUsers,
            'totalRevenueEstimate' => $totalRevenueEstimate,
            'paidCount' => $paidCount,
            'revenueByPlan' => $revenueByPlan,
            'recentStores' => Store::with('plan')->latest()->limit(10)->get(),
        ]);
    }

    /* ------------------------------ Stores ------------------------------ */

    public function stores()
    {
        return view('owner.stores', [
            'stores' => Store::with('plan')->withCount('contacts')->latest()->paginate(20),
            'plans' => Plan::where('is_active', true)->get(),
        ]);
    }

    public function updateStore(Request $request, Store $store)
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'],
            'plan_expires_at' => ['nullable', 'date'],
        ]);

        $store->update([
            'plan_id' => $data['plan_id'] ?? null,
            'plan_expires_at' => $data['plan_expires_at'] ?? null,
        ]);

        return back()->with('status', "Store '{$store->myshopify_domain}' updated.");
    }

    /**
     * Enable / disable a store with a single toggle.
     */
    public function toggleStore(Store $store)
    {
        $store->update([
            'disabled_at' => $store->isDisabled() ? null : now(),
        ]);

        $state = $store->isDisabled() ? 'disabled' : 'enabled';

        return back()->with('status', "Store '{$store->myshopify_domain}' is now {$state}.");
    }

    /* ------------------------------ Plans -------------------------------- */

    public function plans()
    {
        return view('owner.plans', [
            'plans' => Plan::orderBy('price_monthly')->get(),
        ]);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string', 'unique:plans,code'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'limits' => ['nullable', 'json'],
            'features' => ['nullable', 'json'],
        ]);

        Plan::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'price_monthly' => $data['price_monthly'] ?? 0,
            'price_yearly' => $data['price_yearly'] ?? 0,
            'limits' => json_decode($data['limits'] ?? '{}', true) ?: [],
            'features' => json_decode($data['features'] ?? '{}', true) ?: [],
            'is_active' => true,
        ]);

        return back()->with('status', 'Plan created.');
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'limits' => ['nullable', 'json'],
            'features' => ['nullable', 'json'],
            'is_active' => ['boolean'],
        ]);

        $plan->update([
            'name' => $data['name'],
            'price_monthly' => $data['price_monthly'] ?? 0,
            'price_yearly' => $data['price_yearly'] ?? 0,
            'limits' => json_decode($data['limits'] ?? '{}', true) ?: [],
            'features' => json_decode($data['features'] ?? '{}', true) ?: [],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Plan updated.');
    }

    /* ------------------------------ Users -------------------------------- */

    public function users()
    {
        return view('owner.users', [
            'users' => User::with('store')->latest()->paginate(20),
            'stores' => Store::all(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:superadmin,admin,owner,agent,viewer'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'store_id' => $data['store_id'] ?? null,
            'active' => true,
        ]);

        return back()->with('status', 'User created.');
    }
}
