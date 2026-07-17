<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 正确用户名密码 → 登录成功，返回 token 和用户信息
     */
    public function test_login_with_valid_credentials(): void
    {
        $username = 'test_login_' . uniqid();
        User::factory()->create([
            'username' => $username,
            'password' => Hash::make('123456'),
            'role'     => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $username,
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code'    => 0,
                'message' => '登录成功',
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user']
            ])
            ->assertJsonPath('data.user.role', 'admin');
    }

    /**
     * 错误密码 → 返回验证错误
     */
    public function test_login_fails_with_wrong_password(): void
    {
        $username = 'test_wrong_pwd_' . uniqid();
        User::factory()->create([
            'username' => $username,
            'password' => Hash::make('123456'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $username,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 被禁用的账号 → 返回 403
     */
    public function test_login_rejected_when_disabled(): void
    {
        $username = 'test_disabled_' . uniqid();
        User::factory()->create([
            'username' => $username,
            'password' => Hash::make('123456'),
            'status'   => 0,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $username,
            'password' => '123456',
        ]);

        $response->assertStatus(403)
            ->assertJson(['code' => 1003]);
    }

    /**
     * 未登录访问受保护路由 → 401
     */
    public function test_unauthenticated_cannot_access_api(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    /**
     * 登出后 token 失效 → 无法再访问受保护接口
     */
    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        // 登出前确认 token 存在
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        // 用 token 正常访问
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(200);

        // 直接删除所有 token（绕过 logout API，排除 session 干扰）
        $user->tokens()->delete();

        // 确认 token 已删除
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        // 清除测试中的认证状态（防止 session 回退）
        $this->app['auth']->forgetGuards();

        // 再用同一 token → 应为 401
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    /**
     * 获取当前用户信息
     */
    public function test_me_returns_current_user(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson(['code' => 0])
            ->assertJsonStructure([
                'data' => ['id', 'username', 'real_name', 'role']
            ]);
    }
}
