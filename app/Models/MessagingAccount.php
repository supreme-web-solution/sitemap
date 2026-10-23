<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A team's own Zernio connection for SMS and WhatsApp.
 *
 * @property int $id
 * @property int $team_id
 * @property string $api_key
 * @property string $webhook_secret
 * @property string $webhook_key
 * @property string|null $sms_from
 * @property string|null $whatsapp_account_id
 * @property string $timezone
 * @property int $quiet_start_hour
 * @property int $quiet_end_hour
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['api_key', 'webhook_secret', 'webhook_key', 'sms_from', 'whatsapp_account_id', 'timezone', 'quiet_start_hour', 'quiet_end_hour'])]
#[Hidden(['api_key', 'webhook_secret'])]
class MessagingAccount extends Model
{
    /**
     * Whether the recipient's local time falls inside the hours when texting is not allowed.
     */
    public function isQuiet(?CarbonInterface $at = null): bool
    {
        $start = $this->quiet_start_hour;
        $end = $this->quiet_end_hour;

        if ($start === $end) {
            return false;
        }

        $hour = ($at ?? now())->copy()->setTimezone($this->timezone)->hour;

        return $start < $end ? ($hour >= $start && $hour < $end) : ($hour >= $start || $hour < $end);
    }

    /**
     * The next moment messages may go out: now, or when the quiet hours end.
     */
    public function nextSendableAt(?CarbonInterface $at = null): Carbon
    {
        $local = Carbon::instance(($at ?? now())->copy()->setTimezone($this->timezone));

        if (! $this->isQuiet($at)) {
            return $local->setTimezone(config('app.timezone'));
        }

        $target = $local->copy()->setTime($this->quiet_end_hour, 0);

        return ($target->lessThanOrEqualTo($local) ? $target->addDay() : $target)->setTimezone(config('app.timezone'));
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
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'quiet_start_hour' => 'integer',
            'quiet_end_hour' => 'integer',
        ];
    }
}
