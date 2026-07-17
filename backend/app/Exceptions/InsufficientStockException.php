<?php

namespace App\Exceptions;

/**
 * 库存不足异常
 *
 * 用于在出库/冲销操作中，当商品库存不足时抛出。
 * 可在控制器和 bootstrap/app.php 的异常处理器中捕获并返回友好 JSON 响应。
 */
class InsufficientStockException extends \Exception
{
    // 无需额外逻辑；仅用于类型区分，便于 controller / exception handler 捕获
}
