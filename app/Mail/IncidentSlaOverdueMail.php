<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class IncidentSlaOverdueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $incidents,
        public array $summary,
        public string $recipientName
    ) {}

    public function build(): self
    {
        return $this->subject('AlertBook - incidents en retard SLA')
            ->view('emails.incidents.sla-overdue')
            ->with([
                'incidents' => $this->incidents,
                'summary' => $this->summary,
                'recipientName' => $this->recipientName,
            ]);
    }
}
