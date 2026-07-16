<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'recipient', 'operator', 'remark', 'status', 'reversed_from'
    ];

    public function items()
    {
        return $this->hasMany(StockOutItem::class);
    }

    public function reversed()
    {
        return $this->belongsTo(StockOut::class, 'reversed_from');
    }
}
