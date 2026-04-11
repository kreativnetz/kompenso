<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ThesisSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{first_name: string, last_name: string, class: string}>  $authors
     */
    public function __construct(
        public string $sessionName,
        public ?string $schoolyearLabel,
        public string $title,
        public string $description,
        public string $editCode,
        public array $authors,
    ) {}

    public function envelope(): Envelope
    {
        $app = config('app.name', 'Mentor Match');

        return new Envelope(
            subject: "Themeneingabe bestätigt – Bearbeitungscode ({$app})",
        );
    }

    public function content(): Content
    {
        $logoPath = base_path('resources/images/mail/logo-mentormatch.svg');

        return new Content(
            html: 'emails.thesis-submitted',
            with: [
                'logoSrc' => $this->embed($logoPath),
            ],
        );
    }
}
