<?php

namespace App\Http\Controllers\Messaging;

use App\Enums\LeadActivityType;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Jobs\SendLeadMessage;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadMessageController extends Controller
{
    /**
     * Queue an SMS or WhatsApp message to a lead who agreed to receive them.
     */
    public function store(Request $request, Team $currentTeam, Lead $lead, UsageMeter $usage): RedirectResponse
    {
        $usage->ensureFeature($currentTeam, PlanFeature::Messaging);

        $data = $request->validate([
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'body' => ['nullable', 'string', 'max:1000'],
            'template_name' => ['nullable', 'string', 'max:100'],
            'template_language' => ['nullable', 'string', 'max:10'],
            'template_params' => ['nullable', 'array', 'max:10'],
            'template_params.*' => ['string', 'max:200'],
        ]);

        $channel = MessageChannel::from($data['channel']);
        $account = $currentTeam->messagingAccount;

        if ($account === null || ($channel === MessageChannel::Sms ? blank($account->sms_from) : blank($account->whatsapp_account_id))) {
            throw ValidationException::withMessages(['channel' => __('Set up :channel on the Messaging page first.', ['channel' => $channel->label()])]);
        }

        if ($lead->phone_e164 === null) {
            throw ValidationException::withMessages(['channel' => __('This lead has no valid phone number.')]);
        }

        if ($lead->messaging_opted_out_at !== null) {
            throw ValidationException::withMessages(['channel' => __('This contact opted out of messages.')]);
        }

        if ($lead->messaging_consent_at === null) {
            throw ValidationException::withMessages(['channel' => __('Record that this contact agreed to receive messages first.')]);
        }

        $conversationId = $channel === MessageChannel::WhatsApp
            ? Message::query()->where('lead_id', $lead->id)->where('channel', $channel)->whereNotNull('external_conversation_id')->latest('id')->value('external_conversation_id')
            : null;

        $template = null;

        if ($channel === MessageChannel::WhatsApp && $conversationId === null) {
            if (blank($data['template_name'] ?? null)) {
                throw ValidationException::withMessages(['template_name' => __('Start a WhatsApp chat with an approved template.')]);
            }

            $template = [
                'name' => $data['template_name'],
                'language' => $data['template_language'] ?? 'en_US',
                'params' => array_values($data['template_params'] ?? []),
            ];
        } elseif (blank($data['body'] ?? null)) {
            throw ValidationException::withMessages(['body' => __('Write a message.')]);
        }

        $usage->consume($currentTeam, UsageMetric::Messages);

        $sendAt = $account->isQuiet() ? $account->nextSendableAt() : null;

        $message = Message::query()->create([
            'team_id' => $currentTeam->id,
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'channel' => $channel,
            'direction' => Message::OUTBOUND,
            'status' => MessageStatus::Queued,
            'body' => $data['body'] ?? __('Template: :name', ['name' => $template['name'] ?? '']),
            'phone' => $lead->phone_e164,
            'external_conversation_id' => $conversationId,
            'template' => $template,
            'send_after' => $sendAt,
        ]);

        SendLeadMessage::dispatch($message)->delay($sendAt);

        $lead->recordActivity(
            LeadActivityType::MessageSent,
            __(':channel message queued: :text', ['channel' => $channel->label(), 'text' => Str::limit($message->body, 100)]),
            ['message_id' => $message->id],
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $sendAt === null
            ? __('Message queued.')
            : __('Message queued. It goes out when quiet hours end.')]);

        return back();
    }
}
