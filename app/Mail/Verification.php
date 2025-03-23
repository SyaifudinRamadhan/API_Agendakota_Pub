<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Verification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct($username, $tokenVerify, $isOtp = false)
    {
        $this->username = $username;
        $this->token = $tokenVerify;
        $this->isOtp = $isOtp;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $data = [
            'name' => $this->username,
            'is_otp' => $this->isOtp,
        ];
        if($this->isOtp){
            $data += [
                'otp_code' => $this->token
            ];
        }else{
            $data += [
                'url' => route('verify', [$this->token]),
            ];
        }
        return new Content(
            markdown: 'emails.verifications',
            with: $data
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
