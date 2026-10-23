<?php

namespace Database\Factories;

use App\Models\FollowUpSequence;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUpSequence>
 */
class FollowUpSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Preview follow-up',
            'is_active' => true,
        ];
    }

    /**
     * Add ordered email steps after the sequence is created.
     */
    public function withSteps(int $count = 2): static
    {
        return $this->afterCreating(function (FollowUpSequence $sequence) use ($count) {
            for ($position = 0; $position < $count; $position++) {
                $sequence->steps()->create([
                    'position' => $position,
                    'delay_days' => $position === 0 ? 0 : 2,
                    'subject' => 'Follow-up '.$position,
                    'body' => 'Hello {{business_name}}',
                ]);
            }
        });
    }
}
