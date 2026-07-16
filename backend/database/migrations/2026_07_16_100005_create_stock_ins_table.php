<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique()->comment('入库单号');
            $table->string('supplier', 200)->nullable()->comment('供应商');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('总金额');
            $table->string('operator', 50)->comment('经办人');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('1-正常 2-已冲销');
            $table->unsignedBigInteger('reversed_from')->nullable()->comment('被冲销原单ID');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
    }
};
