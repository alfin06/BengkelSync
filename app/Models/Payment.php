<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['amount', 'payment_method', 'payment_date', 'notes'];

    // This links the payment to either an Invoice or a StockEntry
    public function payable()
    {
        return $this->morphTo();
    }
}