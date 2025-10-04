<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EventRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Event $event;
    public string $pdfPath;
    public array $emailTexts;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Event $event, string $pdfPath, array $emailTexts)
    {
        $this->user = $user;
        $this->event = $event;
        $this->pdfPath = $pdfPath;
        $this->emailTexts = $emailTexts;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailTexts['subject'] ?? 'Iscrizione Confermata - ' . $this->event->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.event-registration',
            with: [
                'user' => $this->user,
                'event' => $this->event,
                'emailTexts' => $this->emailTexts,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('Omaggio_' . $this->user->first_name . '_' . $this->event->title . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}






