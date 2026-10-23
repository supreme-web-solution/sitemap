<?php

namespace Database\Factories;

use App\Models\NicheBrief;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NicheBrief>
 */
class NicheBriefFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'industry' => 'plumbers',
            'location' => 'Austin, TX',
            'status' => 'done',
            'brief' => [
                'summary' => 'Local plumbers who need a website.',
                'keywords' => ['emergency plumber Austin'],
                'objections' => ['Already have a site'],
            ],
        ];
    }
}
