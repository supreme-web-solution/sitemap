<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property PaymentProvider $provider
 * @property array<string, string> $credentials
 * @property string|null $label
 * @property string $webhook_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['provider', 'credentials', 'label', 'webhook_key'])]
#[Hidden(['credentials'])]
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    /**
     * Get one stored credential, or null when it is not set.
     */
    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * Show the end of the main secret so owners can recognise which key is connected.
     */
    public function hint(): string
    {
        $secret = $this->credential($this->provider === PaymentProvider::PayPal ? 'client_id' : 'secret_key') ?? '';

        return $secret === '' ? '' : '...'.substr($secret, -4);
    }

    /**
     * Get the team the account belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'credentials' => 'encrypted:array',
        ];
    }
}
