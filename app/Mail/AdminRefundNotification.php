<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminRefundNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $username,
        $email,
        $eventName,
        $purchaseId,
        $ticketName,
        $ticketId,
        $textMessage
    ) {
        $this->username = $username;
        $this->email = $email;
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
            subject: 'Admin Refund Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.adminRefundNotification',
            with: [
                "username" => $this->username,
                "email" => $this->email,
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
