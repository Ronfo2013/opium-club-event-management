<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $pdfPath)
    {
        $this->user = $user;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Benvenuto a ' . $this->user->event->title . ' - Opium Club Pordenone',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'event' => $this->user->event,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('biglietto_' . $this->user->first_name . '_' . $this->user->last_name . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}





