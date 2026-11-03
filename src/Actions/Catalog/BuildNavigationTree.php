<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Core\Models\Collection;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Storefront\Data\NavigationItem;

final class BuildNavigationTree
{
    /**
     * Build the navigation tree for a collection group.
     *
     * Walks the tree one level at a time by parent_id rather than filtering
     * on withDepth(), which returns -1 under Lunar's group scoping. Costs one
     * query for the group plus two (collections, default urls) per level.
     *
     * @return list<NavigationItem>
     */
    public function handle(?string $group = null, ?int $depth = null): array
    {
        $group ??= config('storefront.navigation.group', 'main');
        $depth ??= config('storefront.navigation.depth', 2);

        $groupId = CollectionGroup::where('handle', $group)->value('id');

        if ($groupId === null || $depth < 1) {
            return [];
        }

        return $this->level($groupId, null, $depth);
    }

    /**
     * @return list<NavigationItem>
     */
    private function level(int $groupId, ?int $parentId, int $remaining): array
    {
        // Nodes of every parent at this level are fetched together, so
        // levels are walked breadth-first and stitched afterwards.
        $levels = [];
        $parentIds = [$parentId];

        for ($i = 0; $i < $remaining && $parentIds !== []; $i++) {
            $nodes = Collection::query()
                ->whereVisible()
                ->where('collection_group_id', $groupId)
                ->when(
                    $i === 0,
                    fn ($query) => $query->whereNull('parent_id'),
                    fn ($query) => $query->whereIn('parent_id', $parentIds),
                )
                ->with('defaultUrl')
                ->orderBy('_lft')
                ->get();

            $levels[] = $nodes->groupBy('parent_id');
            $parentIds = $nodes->pluck('id')->all();
        }

        return $this->stitch($levels, 0, null);
    }

    /**
     * @param  list<\Illuminate\Support\Collection<int|string, mixed>>  $levels
     * @return list<NavigationItem>
     */
    private function stitch(array $levels, int $index, ?int $parentId): array
    {
        $siblings = $levels[$index][$parentId] ?? collect();

        return $siblings
            ->map(fn (Collection $node) => new NavigationItem(
                id: $node->id,
                name: (string) $node->translate('name'),
                slug: $node->defaultUrl?->slug,
                children: isset($levels[$index + 1]) ? $this->stitch($levels, $index + 1, $node->id) : [],
            ))
            ->values()
            ->all();
    }
}
