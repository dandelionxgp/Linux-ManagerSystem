<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('商品编码/条码');
            $table->string('name', 200)->comment('商品名称');
            $table->unsignedBigInteger('category_id')->nullable()->comment('分类ID');
            $table->string('spec', 100)->nullable()->comment('规格型号');
            $table->string('unit', 20)->default('件')->comment('单位');
            $table->decimal('purchase_price', 10, 2)->default(0)->comment('参考进价');
            $table->decimal('sale_price', 10, 2)->default(0)->comment('参考售价');
            $table->integer('safety_stock')->default(0)->comment('安全库存量');
            $table->integer('current_stock')->default(0)->comment('当前库存');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index('category_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
