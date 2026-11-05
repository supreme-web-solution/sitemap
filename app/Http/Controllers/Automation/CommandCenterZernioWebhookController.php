<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCommandCenterZernioInbound;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class CommandCenterZernioWebhookController extends Controller
{
    /**
     * Platform Command Center WhatsApp inbound (Zernio → webhooks queue).
     */
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('command_center.whatsapp.webhook_secret');

        if ($secret !== '') {
            $signature = (string) $request->header('X-Zernio-Signature');
            $signature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            abort_unless($signature !== '' && hash_equals($expected, $signature), 400, 'Invalid signature.');
        }

        $eventId = (string) $request->header('X-Zernio-Event-Id');

        if ($eventId !== '' && ! Cache::add("zernio-cc-event:{$eventId}", true, now()->addDay())) {
            return response('duplicate');
        }

        ProcessCommandCenterZernioInbound::dispatch($request->json()->all());

        return response('ok');
    }
}
