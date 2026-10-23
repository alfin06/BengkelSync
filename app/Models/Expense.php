<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UserTracking;

class Expense extends Model
{
    use SoftDeletes;
    use UserTracking;

    protected $fillable = ['user_id', 'description', 'amount', 'category', 'expense_date', 'payment_source'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}