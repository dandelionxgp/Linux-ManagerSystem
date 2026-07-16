<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('id')->comment('登录用户名');
            $table->string('real_name', 50)->nullable()->after('name')->comment('真实姓名');
            $table->string('role', 20)->default('viewer')->after('password')->comment('角色: admin/manager/viewer');
            $table->tinyInteger('status')->default(1)->after('role')->comment('1-启用 0-禁用');
            $table->timestamp('last_login_at')->nullable()->after('status')->comment('最后登录时间');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'real_name', 'role', 'status', 'last_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
