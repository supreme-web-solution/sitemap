<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Create Starter, Pro and Agency when no plans exist yet.
     *
     * Starter is the default. Assigning it caps every team that does not have a
     * plan of its own, so run this only when you intend that:
     * php artisan db:seed --class=PlanSeeder
     */
    public function run(): void
    {
        if (Plan::query()->exists()) {
            return;
        }

        Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Find leads and build websites, with a monthly cap.',
            'price_cents' => 0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'limits' => [
                UsageMetric::LeadSearches->value => 25,
                UsageMetric::SiteGenerations->value => 10,
                UsageMetric::ReviewFetches->value => 25,
                UsageMetric::Audits->value => 25,
                UsageMetric::Emails->value => 50,
                UsageMetric::Messages->value => 0,
                UsageMetric::AiEdits->value => 0,
                UsageMetric::Leads->value => 100,
                UsageMetric::Sites->value => 10,
                UsageMetric::Domains->value => 3,
                UsageMetric::Members->value => 3,
            ],
        ]);

        Plan::query()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Client portal, messaging and AI editing for growing agencies.',
            'price_cents' => 0,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
            'features' => [
                PlanFeature::ClientPortal->value,
                PlanFeature::Messaging->value,
                PlanFeature::AiEditing->value,
            ],
            'limits' => [
                UsageMetric::LeadSearches->value => 150,
                UsageMetric::SiteGenerations->value => 60,
                UsageMetric::ReviewFetches->value => 150,
                UsageMetric::Audits->value => 150,
                UsageMetric::Emails->value => 500,
                UsageMetric::Messages->value => 500,
                UsageMetric::AiEdits->value => 100,
                UsageMetric::Leads->value => 1000,
                UsageMetric::Sites->value => 60,
                UsageMetric::Domains->value => 60,
                UsageMetric::Members->value => 10,
            ],
        ]);

        Plan::query()->create([
            'name' => 'Agency',
            'slug' => 'agency',
            'description' => 'White-label, higher caps, for agencies that resell under their own name.',
            'price_cents' => 0,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 3,
            'features' => array_map(fn (PlanFeature $feature) => $feature->value, PlanFeature::cases()),
            'limits' => [],
        ]);
    }
}
