<?php

namespace App\Libraries;

use App\Models\BlockModel;
use App\Models\CategoryModel;
use App\Models\ServiceModel;

class DashboardStats
{
    /**
     * @return array{
     *     totalBlocks: int,
     *     publishedBlocks: int,
     *     draftBlocks: int,
     *     totalCategories: int,
     *     totalServices: int,
     *     publishedServices: int,
     *     recentBlocks: list<array<string, mixed>>
     * }
     */
    public function snapshot(): array
    {
        $blocks   = model(BlockModel::class, false);
        $counts   = $blocks->statusCounts();
        $services = model(ServiceModel::class, false)->statusCounts();

        return [
            'totalBlocks'        => $counts['total'],
            'publishedBlocks'    => $counts['published'],
            'draftBlocks'        => $counts['draft'],
            'totalCategories'    => model(CategoryModel::class, false)->countAllResults(),
            'totalServices'      => $services['total'],
            'publishedServices'  => $services['published'],
            'recentBlocks'       => $blocks->recent(5),
        ];
    }
}
