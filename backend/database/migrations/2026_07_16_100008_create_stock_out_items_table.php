<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_out_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_out_id')->comment('出库单ID');
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->integer('quantity')->comment('出库数量');
            $table->decimal('unit_price', 10, 2)->comment('出库成本价');
            $table->decimal('subtotal', 12, 2)->comment('小计');
            $table->timestamp('created_at')->nullable();

            $table->foreign('stock_out_id')->references('id')->on('stock_outs')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
            $table->index('stock_out_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_out_items');
    }
};
