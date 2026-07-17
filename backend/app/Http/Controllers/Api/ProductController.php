<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * 商品列表（分页 + 搜索 + 分类筛选）
     */
    public function index(Request $request)
    {
        $query = Product::with('category:id,name');

        // 按商品名称 / 编码搜索
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        // 按分类筛选
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('id', 'desc')
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'code' => 0,
            'data' => $products,
        ]);
    }

    /**
     * 新增商品
     */
    public function store(Request $request)
    {
        $request->validate([
            'code'           => 'required|string|max:50|unique:products',
            'name'           => 'required|string|max:200',
            'category_id'    => 'nullable|exists:categories,id',
            'spec'           => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:20',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'safety_stock'   => 'nullable|integer|min:0',
            'remark'         => 'nullable|string|max:500',
        ]);

        $product = Product::create($request->only([
            'code', 'name', 'category_id', 'spec', 'unit',
            'purchase_price', 'sale_price', 'safety_stock', 'remark',
        ]));

        return response()->json([
            'code'    => 0,
            'message' => '商品创建成功',
            'data'    => $product->load('category:id,name'),
        ]);
    }

    /**
     * 商品详情（含分类名称）
     */
    public function show(string $id)
    {
        $product = Product::with('category:id,name')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $product,
        ]);
    }

    /**
     * 编辑商品
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'code'           => 'sometimes|string|max:50|unique:products,code,' . $id,
            'name'           => 'sometimes|string|max:200',
            'category_id'    => 'nullable|exists:categories,id',
            'spec'           => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:20',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'safety_stock'   => 'nullable|integer|min:0',
            'remark'         => 'nullable|string|max:500',
        ]);

        $product->update($request->only([
            'code', 'name', 'category_id', 'spec', 'unit',
            'purchase_price', 'sale_price', 'safety_stock', 'remark',
        ]));

        return response()->json([
            'code'    => 0,
            'message' => '商品更新成功',
            'data'    => $product->load('category:id,name'),
        ]);
    }

    /**
     * 删除商品（软删除）
     *
     * 有出入库记录的商品禁止删除。
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);

        // 检查是否有关联的出入库记录
        if ($product->stockInItems()->count() > 0 || $product->stockOutItems()->count() > 0) {
            return response()->json([
                'code'    => 1001,
                'message' => '该商品已有出入库记录，不能删除',
            ]);
        }

        $product->delete();

        return response()->json([
            'code'    => 0,
            'message' => '商品已删除',
        ]);
    }

    /**
     * 批量导入商品（Excel）
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls|max:10240',
        ]);

        try {
            $import = new \App\Imports\ProductImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

            return response()->json([
                'code'    => 0,
                'message' => '导入成功',
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $messages = [];
            foreach ($failures as $failure) {
                $messages[] = "第 {$failure->row()} 行: " . implode(', ', $failure->errors());
            }

            return response()->json([
                'code'    => 1001,
                'message' => '导入失败：' . implode('；', $messages),
            ]);
        }
    }
}
