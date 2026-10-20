<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class ContractWithdrawal extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'receipt_sent_at' => 'immutable_datetime'];
    }
}
