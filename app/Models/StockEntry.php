<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEntry extends Model
{
    protected $fillable = [
        'supplier_id',
        'reference_number',
        'entry_date',
        'notes',
        'total_cost',
        'grand_total', 'amount_paid', 'status', 'due_date'
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    // Add this method to BOTH Invoice.php and StockEntry.php
    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
