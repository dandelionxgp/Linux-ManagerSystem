<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id')->comment('盘点单ID');
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->integer('system_qty')->comment('系统库存数');
            $table->integer('actual_qty')->nullable()->comment('实际盘点数');
            $table->integer('diff_qty')->default(0)->comment('差异数');
            $table->timestamps();

            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
