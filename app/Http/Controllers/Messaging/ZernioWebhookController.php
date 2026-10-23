<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessZernioEvent;
use App\Models\MessagingAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ZernioWebhookController extends Controller
{
    /**
     * Receive Zernio events for one team. Only verifies and queues; the work happens in a job so the reply is instant.
     */
    public function __invoke(Request $request, string $key): Response
    {
        $account = MessagingAccount::query()->where('webhook_key', $key)->firstOrFail();

        $signature = (string) $request->header('X-Zernio-Signature');
        $signature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        $expected = hash_hmac('sha256', $request->getContent(), $account->webhook_secret);

        abort_unless($signature !== '' && hash_equals($expected, $signature), 400, 'Invalid signature.');

        $eventId = (string) $request->header('X-Zernio-Event-Id');

        if ($eventId !== '' && ! Cache::add("zernio-event:{$account->id}:{$eventId}", true, now()->addDay())) {
            return response('duplicate');
        }

        ProcessZernioEvent::dispatch($account->team_id, $request->json()->all());

        return response('ok');
    }
}
