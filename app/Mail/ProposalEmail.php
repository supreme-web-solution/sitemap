<?php

namespace App\Mail;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProposalEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Proposal $proposal,
        public string $senderName,
        public ?string $senderEmail = null,
        public bool $isPreview = false,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $brand = $this->proposal->team->brand()['name'];
        $subject = $this->isPreview
            ? "[Preview] Proposal for {$this->proposal->lead->name}: {$this->proposal->title}"
            : "Proposal from {$brand}: {$this->proposal->title}";

        return new Envelope(
            replyTo: $this->senderEmail ? [new Address($this->senderEmail, $this->senderName)] : [],
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'mail.proposal',
            text: 'mail.proposal-text',
        );
    }
}
