<?php

namespace BuygoLineNotify\Services;

final class SampleService
{
    public function calculatePrice(array $items = [], float $discount = 0.0): float
    {
        if ($items === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $total += $price * $qty;
        }

        if ($discount > 0) {
            $total *= (1 - $discount);
        }

        return $total;
    }

    public function isValidDiscount(float $discount): bool
    {
        return $discount >= 0.0 && $discount <= 1.0;
    }
}

