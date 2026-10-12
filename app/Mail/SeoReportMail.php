<?php

namespace App\Mail;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Reporte SEO por email: top oportunidades de quick-win de un proyecto,
 * con las sugerencias de metadatos de la IA.
 */
class SeoReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public Collection $opportunities,
        public string $windowFrom = '',
        public string $windowTo = ''
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎯 Oportunidades SEO — ' . ($this->project->name ?? $this->project->domain_url),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seo-report',
        );
    }
}
