<?php

namespace App\Services\Menu;

use App\Models\Restaurant;

class MenuResolver
{
    public function __construct(
        private readonly WooCommerceMenuService $wooCommerceMenuService,
    ) {}

    /**
     * Get the available menu item names for a restaurant.
     *
     * @return array<int, string>
     */
    public function getItems(Restaurant $restaurant): array
    {
        if ($restaurant->woocommerce_domain !== null) {
            return $this->wooCommerceMenuService->getProductNames($restaurant->woocommerce_domain);
        }

        return $restaurant->menuItems()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();
    }
}
