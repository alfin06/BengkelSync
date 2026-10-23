<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\UserTracking;
use Illuminate\Support\Str;

class Invoice extends Model
{
    // These are the only columns allowed to be saved via Invoice::create()
    protected $fillable = [
        'work_order_id',
        'parts_total',
        'labor_fee',
        'grand_total',
        'discount',
        'payment_method',
        'notes',
        'status',
        'due_date',
        'amount_paid',
        'customer_reference'
    ];
    use UserTracking;

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
    
    // Accessor for Parts
    public function getPartsAttribute()
    {
        return $this->details->where('type', 'part')->values();
    }

    // Accessor for Services
    public function getServicesAttribute()
    {
        return $this->details->where('type', 'service')->values();
    }

    // Update the calculation logic to include per-item discounts
    public function calculateTotals()
    {
        $total = $this->details()->get()->sum(function ($detail) {
            // (Price * Qty) - Discount = Subtotal
            return ($detail->price * $detail->qty) - $detail->discount;
        });

        $this->update([
            'total_amount' => $total,
            'final_amount' => $total - $this->global_discount 
        ]);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // This tells Laravel to find invoices by the UUID column instead of ID by default
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}