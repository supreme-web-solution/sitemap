<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteDomain>
 */
class SiteDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'hostname' => fake()->unique()->domainWord().'.example.com',
            'verification_token' => Str::random(32),
        ];
    }

    /**
     * Indicate that ownership of the domain has been proven.
     */
    public function verified(): static
    {
        return $this->afterMaking(function (SiteDomain $domain) {
            $domain->verified_at = now();
            $domain->verified_hostname = $domain->hostname;
        });
    }
}
