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
    private const STAGING_HOST = 'powderblue-dinosaur-538060.hostingersite.com';

    public static function showsTestReviews(): bool
    {
        return request()->getHost() === self::STAGING_HOST
            && parse_url((string) config('app.url'), PHP_URL_HOST) === self::STAGING_HOST;
    }

    public static function constrain(Builder|Relation $query): void
    {
        $query->where('status', 'approved');

        if (! self::showsTestReviews()) {
            $query->where('is_test', false);
        }
    }

    /** Two aggregate subqueries, rather than one query per product card. */
    public static function withRatingSummary(Builder|Relation $query): Builder|Relation
    {
        $query->withCount([
            'reviews as review_count' => fn (Builder $reviewQuery) => self::constrain($reviewQuery),
        ])->withAvg([
            'reviews as review_average' => fn (Builder $reviewQuery) => self::constrain($reviewQuery),
        ], 'rating');

        if (self::showsTestReviews()) {
            $query->withCount([
                'reviews as review_test_count' => fn (Builder $reviewQuery) =>
                    $reviewQuery->where('status', 'approved')->where('is_test', true),
            ]);
        }

        return $query;
    }
}
