<?php

namespace App\Mail;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SiteTransferEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public string $downloadUrl,
        public string $expiresOn,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $brand = $this->site->team->brand();

        return new Envelope(
            replyTo: filled($brand['email'])
                ? [new Address($brand['email'], $brand['name'])]
                : [],
            subject: __('Your website for :name is ready', ['name' => $this->site->lead->name]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'mail.site-transfer',
            text: 'mail.site-transfer-text',
        );
    }
}
