<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use Illuminate\Support\Facades\Log;

class SendOrderEmailsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $additionalEmails;

    public function __construct(Order $order, array $additionalEmails = [])
    {
        $this->order = $order;
        $this->additionalEmails = $additionalEmails;
    }

    public function handle()
    {
        // Fetch all order info using the show function
        $orderDetailsResponse = app()->make('App\Http\Controllers\Order\OrderController')->show($this->order->id);

        if ($orderDetailsResponse->getStatusCode() == 200) {
            // Get the order data from the response
            $orderDetails = $orderDetailsResponse->getData()->data;

            // Send email to the customer if they provided an email
            if ($this->order->user_id && $this->order->user->email) {
                Mail::to($this->order->user->email)->send(new OrderPlacedMail($orderDetails));
            }

            // Send email to the admin
            Mail::to('zantechbd@gmail.com')->send(new OrderPlacedMail($orderDetails, true));

            // Send emails to additional addresses
            foreach ($this->additionalEmails as $email) {
                Mail::to($email)->send(new OrderPlacedMail($orderDetails, true));
            }
        } else {
            // Handle failure if the order details could not be fetched
            Log::error('Failed to fetch order details for email: ' . $this->order->id);
        }
    }
}
