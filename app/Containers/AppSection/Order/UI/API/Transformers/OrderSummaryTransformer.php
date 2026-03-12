<?php

namespace App\Containers\AppSection\Order\UI\API\Transformers;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class OrderSummaryTransformer extends ParentTransformer
{
    public function transform(OrderSummary $order): array
    {
        return [
            'id' => $order->getHashedKey(),
            'line' => $order->line,
            'line_label' => $order->line_label,
            'total' => $order->total,
            'completed' => $order->completed,
            'remaining' => $order->remaining,
            'estimated_done' => $order->estimated_done,
            'rush_orders' => [
                'completed' => $order->rush_completed,
                'total' => $order->rush_total,
            ],
            'progress' => $order->progress,
        ];
    }
}
