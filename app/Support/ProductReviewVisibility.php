<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A demo review is visible only on the exact designated staging hostname.
 * Never expose demo ratings on the customer-facing production domain.
 */
final class ProductReviewVisibility
{
    // Sample reviews remain available to administrators, not customer-facing pages.
    public static function showsTestReviews(): bool
    {
        return false;
    }

    public static function constrain(Builder|Relation $query): void
    {
        $query->where('status', 'approved');

        $query->where('is_test', false);
    }

    /** Two aggregate subqueries, rather than one query per product card. */
    public static function withRatingSummary(Builder|Relation $query): Builder|Relation
    {
        $query->withCount([
            'reviews as review_count' => fn (Builder $reviewQuery) => self::constrain($reviewQuery),
        ])->withAvg([
            'reviews as review_average' => fn (Builder $reviewQuery) => self::constrain($reviewQuery),
        ], 'rating');


        return $query;
    }
}
