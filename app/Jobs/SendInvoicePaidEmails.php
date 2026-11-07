<?php

namespace App\Jobs;

use App\Actions\Sites\HandOffSiteFiles;
use App\Mail\InvoicePaidClientEmail;
use App\Mail\InvoicePaidOwnerEmail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendInvoicePaidEmails implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    /**
     * Email the agency owner that payment landed, and the client a receipt
     * (with a website ZIP download when this invoice is the one-time handoff).
     */
    public function handle(HandOffSiteFiles $handOffSiteFiles): void
    {
        $invoice = $this->invoice->fresh(['lead.site', 'team', 'items']);

        if ($invoice === null) {
            return;
        }

        $lead = $invoice->lead;
        $downloadUrl = null;
        $expiresOn = null;
        $site = $lead->site;

        if (
            $site !== null
            && $site->transferred_at === null
            && $invoice->recurring_invoice_id === null
            && filled($lead->email)
        ) {
            $handoff = $handOffSiteFiles->handle($site, $lead->email);
            $downloadUrl = $handoff['downloadUrl'];
            $expiresOn = $handoff['expiresOn'];
            $invoice->setRelation('lead', $lead->fresh(['site']));
        }

        $owner = $invoice->team->owner();

        if ($owner instanceof User && filled($owner->email)) {
            Mail::to($owner->email)->queue(new InvoicePaidOwnerEmail($invoice));
        }

        if (filled($lead->email)) {
            Mail::to($lead->email)->queue(new InvoicePaidClientEmail(
                $invoice,
                $downloadUrl,
                $expiresOn,
            ));
        }
    }
}
