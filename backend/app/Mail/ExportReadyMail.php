<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $contents, public string $extension, public string $mime) {}

    public function build(): self
    {
        return $this->subject('Tailors export')->text('mail.export-ready')->attachData($this->contents, "tailors-export.{$this->extension}", ['mime' => $this->mime]);
    }
}
