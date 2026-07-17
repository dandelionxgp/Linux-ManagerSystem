<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 用户列表
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('real_name', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('id', 'desc')
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'code' => 0,
            'data' => $users
        ]);
    }

    /**
     * 新增用户
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users',
            'real_name' => 'required|string|max:50',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,manager,viewer',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->username,
            'email' => $request->username . '@local.host',
            'real_name' => $request->real_name,
            'password' => $request->password,
            'role' => $request->role,
            'status' => 1,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '用户创建成功',
            'data' => $user->only(['id', 'username', 'real_name', 'role', 'status'])
        ]);
    }

    /**
     * 用户详情
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $user->only(['id', 'username', 'real_name', 'role', 'status', 'last_login_at'])
        ]);
    }

    /**
     * 编辑用户
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'real_name' => 'sometimes|string|max:50',
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|string|in:admin,manager,viewer',
            'status' => 'sometimes|integer|in:0,1',
        ]);

        $data = $request->only(['real_name', 'role', 'status']);
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        return response()->json([
            'code' => 0,
            'message' => '用户更新成功',
            'data' => $user->only(['id', 'username', 'real_name', 'role', 'status'])
        ]);
    }

    /**
     * 删除用户
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        // 不允许删除自己
        if ($user->id === auth()->id()) {
            return response()->json([
                'code' => 1001,
                'message' => '不能删除自己的账号'
            ]);
        }

        $user->delete();

        return response()->json([
            'code' => 0,
            'message' => '用户已删除'
        ]);
    }
}
