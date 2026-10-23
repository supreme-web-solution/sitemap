<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Invoices\MarkInvoicePaid;
use App\Actions\Invoices\RefundInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Http\Controllers\Controller;
use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvoiceActionController extends Controller
{
    /**
     * Issue a draft invoice so the customer can pay it, optionally emailing the link.
     */
    public function send(Request $request, Team $currentTeam, Invoice $invoice): RedirectResponse
    {
        $this->ensureStatus($invoice, [InvoiceStatus::Draft, InvoiceStatus::Open], __('This invoice can no longer be sent.'));

        $emailing = $request->boolean('email');
        $lead = $invoice->lead;

        if ($emailing && blank($lead->email)) {
            throw ValidationException::withMessages(['invoice' => __('This lead has no email address. Send the link yourself, or add an email first.')]);
        }

        if ($invoice->total_cents < Invoice::MINIMUM_CENTS) {
            throw ValidationException::withMessages(['invoice' => __('The invoice total must be at least :amount.', ['amount' => Invoice::formatCents(Invoice::MINIMUM_CENTS)])]);
        }

        $invoice->forceFill(['status' => InvoiceStatus::Open, 'sent_at' => $invoice->sent_at ?? now(), 'emailed_at' => $emailing ? now() : $invoice->emailed_at])->save();

        if ($emailing) {
            Mail::to($lead->email)->queue(new InvoiceEmail($invoice, $request->user()->name, $request->user()->email));
        }

        $lead->recordActivity(
            LeadActivityType::InvoiceSent,
            __('Invoice :number issued (:amount)', ['number' => $invoice->number, 'amount' => Invoice::formatCents($invoice->total_cents)]).($emailing ? __(' and emailed') : ''),
            ['invoice_id' => $invoice->id],
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $emailing ? __('Invoice emailed to :email.', ['email' => $lead->email]) : __('Invoice is ready. Copy the pay link to share it.')]);

        return back();
    }

    /**
     * Record a payment received outside the app, such as a bank transfer or cash.
     */
    public function markPaid(Request $request, Team $currentTeam, Invoice $invoice, MarkInvoicePaid $markPaid): RedirectResponse
    {
        $this->ensureStatus($invoice, [InvoiceStatus::Open], __('Only invoices awaiting payment can be marked as paid.'));

        $markPaid->handle($invoice, 'manual', null, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice marked as paid.')]);

        return back();
    }

    /**
     * Cancel an invoice. Its pay link stops working.
     */
    public function void(Team $currentTeam, Invoice $invoice): RedirectResponse
    {
        $this->ensureStatus($invoice, [InvoiceStatus::Draft, InvoiceStatus::Open], __('A paid invoice cannot be voided.'));

        $invoice->forceFill(['status' => InvoiceStatus::Void, 'pending_reference' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice voided.')]);

        return back();
    }

    /**
     * Return a paid invoice to the customer.
     */
    public function refund(Request $request, Team $currentTeam, Invoice $invoice, RefundInvoice $refund): RedirectResponse
    {
        try {
            $refund->handle($invoice, $request->user()->id);
        } catch (PaymentException $exception) {
            throw ValidationException::withMessages(['invoice' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice refunded.')]);

        return back();
    }

    /**
     * @param  list<InvoiceStatus>  $allowed
     */
    protected function ensureStatus(Invoice $invoice, array $allowed, string $message): void
    {
        if (! in_array($invoice->status, $allowed, true)) {
            throw ValidationException::withMessages(['invoice' => $message]);
        }
    }
}
