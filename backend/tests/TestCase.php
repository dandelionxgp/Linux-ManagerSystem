<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * 每个测试类自行决定是否使用 RefreshDatabase trait
     */

    /**
     * 创建一个已认证的用户并返回
     *
     * @param  string  $role  admin | manager | viewer
     * @return User
     */
    public function actingAsUser(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 1]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    /**
     * 快速创建一个 admin 并认证
     */
    public function actingAsAdmin(): User
    {
        return $this->actingAsUser('admin');
    }

    /**
     * 快速创建一个 manager 并认证
     */
    public function actingAsManager(): User
    {
        return $this->actingAsUser('manager');
    }

    /**
     * 快速创建一个 viewer 并认证
     */
    public function actingAsViewer(): User
    {
        return $this->actingAsUser('viewer');
    }

    /**
     * 创建商品（不经过 API，直接写库）
     */
    public function createProduct(array $overrides = []): \App\Models\Product
    {
        return \App\Models\Product::create(array_merge([
            'code'           => 'PROD-' . uniqid(),
            'name'           => '测试商品 ' . uniqid(),
            'spec'           => '标准规格',
            'unit'           => '件',
            'purchase_price' => '10.00',
            'sale_price'     => '15.00',
            'safety_stock'   => 10,
            'current_stock'  => 100,
        ], $overrides));
    }
}
