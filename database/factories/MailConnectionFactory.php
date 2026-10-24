<?php

namespace Database\Factories;

use App\Enums\MailConnectionProvider;
use App\Enums\MailConnectionStatus;
use App\Models\MailConnection;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailConnection>
 */
class MailConnectionFactory extends Factory
{
    protected $model = MailConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'provider' => MailConnectionProvider::Imap,
            'email_address' => fake()->safeEmail(),
            'credentials' => [
                'host' => 'imap.example.com',
                'port' => 993,
                'encryption' => 'ssl',
                'username' => fake()->userName(),
                'password' => 'secret',
            ],
            'status' => MailConnectionStatus::Connected,
        ];
    }
}
