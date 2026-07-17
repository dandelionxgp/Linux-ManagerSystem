<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * 商品批量导入
 *
 * 支持中文和英文列名。
 * Excel 第一行为标题行，从第二行开始为数据。
 */
class ProductImport implements ToCollection, WithHeadingRow, WithValidation
{
    /**
     * 逐行处理导入数据
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // 跳过全空行
            if (empty(array_filter($row->toArray()))) {
                continue;
            }

            Product::create([
                'code'           => $row['code'] ?? $row['商品编码'] ?? $row['编码'] ?? '',
                'name'           => $row['name'] ?? $row['商品名称'] ?? $row['名称'] ?? '',
                'category_id'    => $row['category_id'] ?? $row['分类id'] ?? null,
                'spec'           => $row['spec'] ?? $row['规格'] ?? $row['规格型号'] ?? null,
                'unit'           => $row['unit'] ?? $row['单位'] ?? '件',
                'purchase_price' => $row['purchase_price'] ?? $row['参考进价'] ?? $row['进价'] ?? 0,
                'sale_price'     => $row['sale_price'] ?? $row['参考售价'] ?? $row['售价'] ?? 0,
                'safety_stock'   => $row['safety_stock'] ?? $row['安全库存'] ?? $row['安全库存量'] ?? 0,
                'remark'         => $row['remark'] ?? $row['备注'] ?? null,
            ]);
        }
    }

    /**
     * 导入校验规则
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:products,code',
            'name' => 'required|string|max:200',
        ];
    }

    /**
     * 自定义校验错误消息
     */
    public function customValidationMessages(): array
    {
        return [
            'code.required' => '商品编码不能为空',
            'code.unique'   => '商品编码已存在',
            'name.required' => '商品名称不能为空',
        ];
    }
}
