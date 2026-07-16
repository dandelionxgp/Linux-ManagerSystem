<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'supplier', 'total_amount', 'operator',
        'remark', 'status', 'reversed_from'
    ];

    public function items()
    {
        return $this->hasMany(StockInItem::class);
    }

    public function reversed()
    {
        return $this->belongsTo(StockIn::class, 'reversed_from');
    }
}
