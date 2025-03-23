<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ResetPasssword extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct($name, $tokenReset)
    {
        //
        $this->name = $name;
        $this->token = $tokenReset;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('emails.resetPassword', [
            'name' => $this->name,
            'url' => $this->token
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
