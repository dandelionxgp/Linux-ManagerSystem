<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'category_id', 'status', 'operator', 'remark', 'confirmed_at'
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
