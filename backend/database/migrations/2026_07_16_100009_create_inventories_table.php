<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique()->comment('盘点单号');
            $table->unsignedBigInteger('category_id')->nullable()->comment('盘点范围(NULL=全部)');
            $table->tinyInteger('status')->default(1)->comment('1-新建 2-已录入 3-已确认');
            $table->string('operator', 50)->comment('经办人');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->timestamp('confirmed_at')->nullable()->comment('确认时间');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
