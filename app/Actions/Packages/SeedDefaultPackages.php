<?php

namespace App\Actions\Packages;

use App\Enums\BillingInterval;
use App\Enums\PackageKind;
use App\Models\Package;
use App\Models\Team;
use Illuminate\Support\Collection;

class SeedDefaultPackages
{
    /**
     * Starter packages agencies typically sell to clients. Prices are placeholders to edit.
     *
     * @return list<array{name: string, description: string, kind: PackageKind, unit_cents: int, interval: BillingInterval|null}>
     */
    public function defaults(): array
    {
        return [
            [
                'name' => 'Website build',
                'description' => 'Design and build of a new client website.',
                'kind' => PackageKind::OneTime,
                'unit_cents' => 150000,
                'interval' => null,
            ],
            [
                'name' => 'Hosting',
                'description' => 'Monthly hosting for the live client website.',
                'kind' => PackageKind::Hosting,
                'unit_cents' => 1500,
                'interval' => BillingInterval::Monthly,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Monthly updates, backups, and small content changes.',
                'kind' => PackageKind::Maintenance,
                'unit_cents' => 4900,
                'interval' => BillingInterval::Monthly,
            ],
        ];
    }

    /**
     * Create the default catalog when the team has no packages yet.
     *
     * @return Collection<int, Package>
     */
    public function handle(Team $team): Collection
    {
        if ($team->packages()->exists()) {
            return collect();
        }

        return collect($this->defaults())->map(
            fn (array $package) => $team->packages()->create([
                'name' => $package['name'],
                'description' => $package['description'],
                'kind' => $package['kind'],
                'unit_cents' => $package['unit_cents'],
                'interval' => $package['interval'],
                'is_active' => true,
            ]),
        );
    }
}
