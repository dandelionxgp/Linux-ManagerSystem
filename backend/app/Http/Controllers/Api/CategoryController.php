<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * 分类树形列表
     */
    public function index(Request $request)
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'code' => 0,
            'data' => $categories
        ]);
    }

    /**
     * 新增分类
     */
    public function store(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:100',
            'sort_order' => 'sometimes|integer',
        ]);

        $category = Category::create([
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '分类创建成功',
            'data' => $category
        ]);
    }

    /**
     * 分类详情
     */
    public function show(string $id)
    {
        $category = Category::with(['parent', 'children'])->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $category
        ]);
    }

    /**
     * 编辑分类
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $id,
            'name' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer',
        ]);

        $category->update($request->only(['parent_id', 'name', 'sort_order']));

        return response()->json([
            'code' => 0,
            'message' => '分类更新成功',
            'data' => $category
        ]);
    }

    /**
     * 删除分类
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);

        // 有子分类时禁止删除
        if ($category->children()->count() > 0) {
            return response()->json([
                'code' => 1001,
                'message' => '该分类下有子分类，请先删除子分类'
            ]);
        }

        // 有关联商品时禁止删除
        if ($category->products()->count() > 0) {
            return response()->json([
                'code' => 1001,
                'message' => '该分类下有商品，请先转移商品'
            ]);
        }

        $category->delete();

        return response()->json([
            'code' => 0,
            'message' => '分类已删除'
        ]);
    }
}
