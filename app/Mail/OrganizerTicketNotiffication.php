<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class OrganizerTicketNotiffication extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct($trxId)
    {
        $this->eventName = "";
        $this->username = "";
        $this->email = "";
        $this->tickets = [];
        $this->nominal = "";

        $trx = Payment::where('id', $trxId)->first();
        $user = $trx->user()->first();
        $this->username = $user->name;
        $this->email = $user->email;
        $this->nominal = $trx->price;
        $pchs = $trx->purchases()->get();
        $index = 0;
        foreach ($pchs as $pch) {
            $ticket = $pch->ticket()->first();
            if($index === 0){
                $this->eventName = $ticket->event()->first()->name;
            }
            $this->tickets[$pch->ticket_id] = [
                "name" => $ticket->name,
                "count" => isset($this->tickets[$pch->ticket_id]) ? $this->tickets[$pch->ticket_id]["count"] + 1 : 1,
                "nominal" => $pch->amount
            ];
            $index++;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Organizer Ticket Notiffication',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organizer-ticket-notiffication',
            with: [
                'eventName' => $this->eventName,
                'username' => $this->username,
                'email' => $this->email,
                'tickets' => $this->tickets,
                'nominal' => $this->nominal
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
