<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable
{
    public $orderDetails;
    public $isAdmin;

    public function __construct($orderDetails, $isAdmin = false)
    {
        $this->orderDetails = $orderDetails;
        $this->isAdmin = $isAdmin;
    }

    public function build()
    {
        // Customize the email subject, recipient, etc. based on $this->isAdmin
        return $this->subject($this->isAdmin ? 'New Order Received' : 'Order Confirmation')
            ->view('emails.order_placed')
            ->with([
                'order' => $this->orderDetails,
                'isAdmin' => $this->isAdmin,
            ]);
    }
}
