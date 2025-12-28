<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationUrl;

    public function __construct($user)
    {
        $this->user = $user;
        $this->verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->user->id, 'hash' => sha1($this->user->email)]
        );
    }

    public function build()
    {
        return $this->subject('Verify Your Email')
            ->view('emails.verify-email')
            ->with([
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
            ]);
    }
}
