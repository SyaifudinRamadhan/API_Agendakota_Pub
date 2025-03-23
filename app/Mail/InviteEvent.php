<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteEvent extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct($username, $senderMail, $eventName, $ticketName, $token = null, $userPass = null)
    {
        $this->token = $token;
        $this->username = $username;
        $this->eventName = $eventName;
        $this->ticketName = $ticketName;
        $this->senderMail = $senderMail;
        $this->userPass = $userPass;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invite Event',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.inviteEvent',
            with: [
                "sender" => $this->senderMail,
                "username" => $this->username,
                "eventName" => $this->eventName,
                "ticketName" => $this->ticketName,
                "userPass" => $this->userPass,
                "url" => $this->token != null ? 
                    route('verifyAndRedirect', [$this->token, 'invitation']) 
                    : env('FRONTEND_URL')."/auth-user?redirect_to=invitation"
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
