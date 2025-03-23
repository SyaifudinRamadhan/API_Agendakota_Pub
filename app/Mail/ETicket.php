<?php

namespace App\Mail;

use App\Models\Payment;
use DateTime;
use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ETicket extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct($paymentId)
    {
        $payment = Payment::where('id', $paymentId)->first();
        $pch = $payment->purchases()->first();
        $user = $payment->user()->first();
        $this->email = $user ? $user->email : null;
        $this->trxData = $payment;
        $this->eventData = $pch ? $pch->ticket()->first()->event()->first() : null;
        $this->ticketDatas = [];
        // visitDate()->first()->visit_date
        // seatNumber()->first()->seat_number
        foreach ($payment->purchases()->get() as $purchase) {
            $ticketDate = "";
            if($this->eventData->category === "Attraction" || $this->eventData->category === "Tour Travel (recurring)" || $this->eventData->category === "Daily Activities"){
                $visitDate = new DateTime($purchase->visitDate()->first()->visit_date, new DateTimeZone('Asia/Jakarta'));
                $ticketDate = $visitDate->format('d-m-Y');
            }else{
                $start = new DateTime($this->eventData->start_date, new DateTimeZone('Asia/Jakarta'));
                $end = new DateTime($this->eventData->end_date, new DateTimeZone('Asia/Jakarta'));
                $ticketDate = $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
            }
            $seatNumber = $purchase->seatNumber()->first();
            $ticket = $purchase->ticket()->first();
            $joinLink = $this->eventData->category === "Webinar" ? $ticket->secretInfo()->first()->meet_link : null;
            array_push($this->ticketDatas, [
                "ticketQr" => $purchase->id . "*~^|-|^~*" . $user->id,
                "ticketName" => $ticket->name,
                "ticketDate" => $ticketDate,
                "ticketSeatNumber" => $seatNumber ? $seatNumber->seat_number : null,
                "downloadLink" => env('DOWNLOAD_LINK') == "" ? null : env('DOWNLOAD_LINK') .'/'. $purchase->id,
                "joinLink" => $joinLink,
            ]);
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'E Ticket',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.e-ticket',
            with: [
                "email" => $this->email,

                "trxRef" => $this->trxData->order_id,
                "nominal" => number_format($this->trxData->price),

                "eventName" => $this->eventData ? $this->eventData->name : null,
                "eventLoc" => $this->eventData ? $this->eventData->location : null,
                "eventBanner" => $this->eventData ? $this->eventData->logo : null,

                "tickets" => $this->ticketDatas
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
