<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryHierarchy
{
    /**
     * Tree operations are computed from category IDs, not user-controlled paths.
     * The category table is small and the entire tree can be safely inspected.
     */
    public static function all(): Collection
    {
        return Category::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function descendantIds(Category $root, ?Collection $all = null, bool $activeOnly = true): array
    {
        $all ??= self::all();
        $ids = [$root->id];
        $frontier = [$root->id];
        while ($frontier) {
            $children = $all->filter(fn (Category $c) =>
                in_array($c->parent_id, $frontier, true) && (!$activeOnly || $c->is_active)
            )->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    /** Root to leaf, including the category itself. */
    public static function ancestors(Category $category, ?Collection $all = null): array
    {
        $all ??= self::all();
        $byId = $all->keyBy('id');
        $chain = [];
        $visited = [];
        $current = $category;
        while ($current && !isset($visited[$current->id])) {
            $visited[$current->id] = true;
            array_unshift($chain, $current);
            $current = $current->parent_id ? $byId->get($current->parent_id) : null;
        }

        return $chain;
    }

    public static function visible(Category $category, ?Collection $all = null): bool
    {
        foreach (self::ancestors($category, $all) as $ancestor) {
            if (! $ancestor->is_active) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ordered options with depth. No existing category is renamed or moved
     * unless the administrator explicitly chooses a different parent.
     */
    public static function choices(?Collection $all = null): array
    {
        $all ??= self::all();
        $byParent = $all->groupBy(fn (Category $c) => (string) ($c->parent_id ?? 'root'));
        $choices = [];
        $visited = [];
        $walk = function (string $parent, int $depth) use (&$walk, &$choices, &$visited, $byParent): void {
            foreach ($byParent->get($parent, collect()) as $category) {
                if (isset($visited[$category->id])) {
                    continue;
                }
                $visited[$category->id] = true;
                $choices[] = [
                    'category' => $category,
                    'depth' => $depth,
                    'label' => str_repeat('— ', $depth).$category->name,
                ];
                $walk((string) $category->id, $depth + 1);
            }
        };
        $walk('root', 0);

        return $choices;
    }
}
