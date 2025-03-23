<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserRefundNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $statuses,
        $eventName,
        $purchaseId,
        $ticketName,
        $ticketId,
        $textMessage
    ) {
        $this->statuses = $statuses;
        $this->eventName = $eventName;
        $this->purchaseId = $purchaseId;
        $this->ticketName = $ticketName;
        $this->ticketId = $ticketId;
        $this->textMessage = $textMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'User Refund Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.userRefundNotification',
            with: [
                "statuses" => $this->statuses,
                "stateMessage" => $this->statuses == 'Approved / Accepted' ? "We will contact you immediately to confirm and process your refund" : "Sorry, we can't accept your refund request",
                "eventName" => $this->eventName,
                "purchaseId" => $this->purchaseId,
                "ticketName" => $this->ticketName,
                "ticketId" => $this->ticketId,
                "textMessage" => $this->textMessage,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
