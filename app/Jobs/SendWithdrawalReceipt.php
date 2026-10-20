<?php

namespace App\Jobs;

use App\Mail\WithdrawalReceipt;
use App\Models\Sales\ContractWithdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendWithdrawalReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $withdrawalId) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $withdrawal = ContractWithdrawal::query()->findOrFail($this->withdrawalId);
        if ($withdrawal->receipt_sent_at) {
            return;
        }

        Mail::to($withdrawal->email)->send((new WithdrawalReceipt($withdrawal))->locale($withdrawal->locale));
        $withdrawal->update(['receipt_sent_at' => now()]);
    }
}
