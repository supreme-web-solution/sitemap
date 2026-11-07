<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidClientEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $downloadUrl = null,
        public ?string $expiresOn = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $brand = $this->invoice->team->brand();
        $subject = $this->downloadUrl
            ? __('Payment received — your website is ready')
            : __('Payment received for :number', ['number' => $this->invoice->number]);

        return new Envelope(
            replyTo: filled($brand['email'])
                ? [new Address($brand['email'], $brand['name'])]
                : [],
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'mail.invoice-paid-client',
            text: 'mail.invoice-paid-client-text',
        );
    }
}
