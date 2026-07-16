<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('操作人ID');
            $table->string('action', 50)->comment('操作类型: create/update/delete/login/logout');
            $table->string('module', 50)->comment('操作模块: product/stock_in/stock_out/inventory/user');
            $table->unsignedBigInteger('target_id')->nullable()->comment('操作对象ID');
            $table->string('description', 500)->comment('操作描述');
            $table->string('ip_address', 45)->nullable()->comment('操作IP');
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index('user_id');
            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
