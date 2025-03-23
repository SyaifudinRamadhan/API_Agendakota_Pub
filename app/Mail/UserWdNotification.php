<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserWdNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $eventName,
        $eventId,
        $orgName,
        $orgId,
        $nominal,
        $accNumber,
        $username,
        $email,
        $forTrx = false,
        $approved = false,
    ) {
        $this->eventName = $eventName;
        $this->eventId = $eventId;
        $this->orgName = $orgName;
        $this->orgId = $orgId;
        $this->nominal = $nominal;
        $this->accNumber = $accNumber;
        $this->username = $username;
        $this->email = $email;
        $this->forTrx = $forTrx;
        $this->approved = $approved;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'User Wd Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.userWdNotification',
            with: [
                "username" => $this->username,
                "email" =>  $this->email,
                "eventName" => $this->eventName,
                "eventId" => $this->eventId,
                "orgName" => $this->orgName,
                "orgId" => $this->orgId,
                "nominal" => $this->nominal,
                "accNumber" => $this->accNumber,
                "forTrx" => $this->forTrx,
                "approved" => $this->approved,
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
