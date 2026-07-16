<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'category_id', 'spec', 'unit',
        'purchase_price', 'sale_price', 'safety_stock',
        'current_stock', 'remark'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockInItems()
    {
        return $this->hasMany(StockInItem::class);
    }

    public function stockOutItems()
    {
        return $this->hasMany(StockOutItem::class);
    }
}
