<?php

namespace App\Mail;

use App\Models\Sales\ContractWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WithdrawalReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContractWithdrawal $withdrawal) {}

    public function build(): static
    {
        return $this->subject(__('compliance.receipt_subject', ['order' => $this->withdrawal->order_number]))
            ->text('emails.returns.withdrawal');
    }
}
