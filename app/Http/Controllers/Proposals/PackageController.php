<?php

namespace App\Http\Controllers\Proposals;

use App\Enums\BillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\SavePackageRequest;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    /**
     * List the services the team sells.
     */
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('packages/Index', [
            'packages' => $currentTeam->packages()->orderBy('name')->get()->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => $package->unit_cents / 100,
                'priceLabel' => Invoice::formatCents($package->unit_cents),
                'interval' => $package->interval?->value,
                'intervalLabel' => $package->interval?->label(),
                'isActive' => $package->is_active,
            ]),
            'intervals' => collect(BillingInterval::cases())->map(fn (BillingInterval $interval) => ['value' => $interval->value, 'label' => $interval->label()])->values(),
        ]);
    }

    public function store(SavePackageRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->packages()->create($request->packageAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package added.')]);

        return back();
    }

    public function update(SavePackageRequest $request, Team $currentTeam, Package $package): RedirectResponse
    {
        $package->update($request->packageAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Package $package): RedirectResponse
    {
        $package->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package deleted. Proposals that used it are not changed.')]);

        return back();
    }
}