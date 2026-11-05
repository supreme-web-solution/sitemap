<?php

namespace App\Actions\Sites;

use App\Enums\LeadActivityType;
use App\Models\Site;
use App\Notifications\SiteContactNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordSiteContact
{
    /**
     * Save a message from the website contact form and email the team owner.
     *
     * A filled honeypot is ignored. Returns false when the message was not stored.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Site $site, array $input): bool
    {
        $data = Validator::make($input, [
            'name' => ['nullable', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'company_url' => ['nullable', 'string', 'max:200'],
        ])->validate();

        if (filled($data['company_url'] ?? null)) {
            return false;
        }

        $lead = $site->lead;
        $name = filled($data['name'] ?? null) ? $data['name'] : 'Someone';

        $lead->recordActivity(
            LeadActivityType::PreviewInterest,
            __(':who sent a message from the website', ['who' => $name]),
            [
                'name' => $data['name'] ?? null,
                'contact' => $data['contact'],
                'message' => $data['message'],
            ],
        );

        $site->team->owner()?->notify(new SiteContactNotification(
            $lead,
            $name,
            $data['contact'],
            $data['message'],
        ));

        return true;
    }
}
