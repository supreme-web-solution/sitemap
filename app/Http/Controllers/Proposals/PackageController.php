<?php

namespace App\Http\Controllers\Proposals;

use App\Actions\Packages\SeedDefaultPackages;
use App\Enums\BillingInterval;
use App\Enums\PackageKind;
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
            'packages' => $currentTeam->packages()
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'kind' => $package->kind->value,
                    'kindLabel' => $package->kind->label(),
                    'description' => $package->description,
                    'price' => $package->unit_cents / 100,
                    'priceLabel' => Invoice::formatCents($package->unit_cents),
                    'interval' => $package->interval?->value,
                    'intervalLabel' => $package->interval?->label(),
                    'isActive' => $package->is_active,
                ]),
            'intervals' => collect(BillingInterval::cases())->map(fn (BillingInterval $interval) => ['value' => $interval->value, 'label' => $interval->label()])->values(),
            'kinds' => PackageKind::options(),
            'canSeedDefaults' => ! $currentTeam->packages()->exists(),
        ]);
    }

    public function store(SavePackageRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->packages()->create($request->packageAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package added.')]);

        return back();
    }

    /**
     * Add Website build, Hosting, and Maintenance when the catalog is empty.
     */
    public function seedDefaults(Team $currentTeam, SeedDefaultPackages $seedDefaultPackages): RedirectResponse
    {
        $created = $seedDefaultPackages->handle($currentTeam);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $created->isEmpty()
                ? __('Packages already exist. Nothing was added.')
                : __('Added starter packages. Edit the prices to match what you charge.'),
        ]);

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
