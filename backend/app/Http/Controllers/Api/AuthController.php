<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 登录
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['用户名或密码错误'],
            ]);
        }

        if (!$user->status) {
            return response()->json([
                'code' => 1003,
                'message' => '账号已被禁用'
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
                'user' => $user->only(['id', 'username', 'real_name', 'role'])
            ]
        ]);
    }

    /**
     * 登出
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'code' => 0,
            'message' => '已退出登录'
        ]);
    }

    /**
     * 当前用户信息
     */
    public function me(Request $request)
    {
        return response()->json([
            'code' => 0,
            'data' => $request->user()->only(['id', 'username', 'real_name', 'role'])
        ]);
    }

    /**
     * 注册（仅 admin 可用，或开放注册）
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users',
            'real_name' => 'required|string|max:50',
            'password' => 'required|string|min:6',
            'role' => 'sometimes|string|in:admin,manager,viewer',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->username,
            'real_name' => $request->real_name,
            'password' => $request->password,
            'role' => $request->role ?? 'viewer',
            'status' => 1,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '注册成功',
            'data' => $user->only(['id', 'username', 'real_name', 'role'])
        ]);
    }
}
