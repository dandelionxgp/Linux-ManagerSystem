<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOutItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'stock_out_id', 'product_id', 'quantity', 'unit_price', 'subtotal', 'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function stockOut()
    {
        return $this->belongsTo(StockOut::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
