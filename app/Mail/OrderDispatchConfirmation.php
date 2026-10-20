<?php

namespace App\Mail;

use App\Models\Sales\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderDispatchConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build(): static
    {
        return $this->subject(__('compliance.dispatch_subject', ['order' => $this->order->order_number]))
            ->view('emails.orders.dispatch');
    }
}
