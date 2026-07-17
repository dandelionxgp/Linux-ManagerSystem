<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * 操作日志列表（分页 + 多条件筛选）
     *
     * 筛选维度：操作模块、操作类型、关键词(描述)、日期范围
     */
    public function index(Request $request)
    {
        $query = OperationLog::with('user:id,username,real_name');

        // 按模块筛选
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        // 按操作类型筛选
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // 按关键词搜索（描述 or 操作人）
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('description', 'like', "%{$kw}%")
                  ->orWhereHas('user', function ($uq) use ($kw) {
                      $uq->where('username', 'like', "%{$kw}%")
                        ->orWhere('real_name', 'like', "%{$kw}%");
                  });
            });
        }

        // 日期范围筛选
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        $list = $query->orderBy('id', 'desc')
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'code' => 0,
            'data' => $list,
        ]);
    }

    /**
     * 可用的筛选选项（模块和操作类型列表，供前端下拉框使用）
     */
    public function options()
    {
        $modules = OperationLog::select('module')->distinct()->pluck('module');
        $actions = OperationLog::select('action')->distinct()->pluck('action');

        return response()->json([
            'code' => 0,
            'data' => [
                'modules' => $modules,
                'actions' => $actions,
            ],
        ]);
    }
}
