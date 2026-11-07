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
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'company_url' => ['nullable', 'string', 'max:200'],
            'website' => ['nullable', 'string', 'max:200'],
        ], [
            'name.required' => __('Please add your name.'),
            'contact.required' => __('Please add your phone or email.'),
            'message.required' => __('Please add a short message.'),
        ])->validate();

        // Bots fill hidden honeypot fields; drop those submissions quietly.
        if (filled($data['company_url'] ?? null) || filled($data['website'] ?? null)) {
            return false;
        }

        $lead = $site->lead;
        $name = trim((string) $data['name']);

        $lead->recordActivity(
            LeadActivityType::PreviewInterest,
            __(':who sent a message from the website', ['who' => $name]),
            [
                'name' => $name,
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
