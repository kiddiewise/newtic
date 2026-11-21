<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerPhotoDraftPruningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function photo(string $location, int $daysOld): string
    {
        Storage::disk('local')->put($location, 'private image test bytes');
        $path = Storage::disk('local')->path($location);
        touch($path, now()->subDays($daysOld)->timestamp);
        clearstatcache(true, $path);

        return $location;
    }

    public function test_cleanup_deletes_only_old_uuid_drafts_not_fresh_or_order_photos(): void
    {
        $expired = $this->photo('customer-photos/drafts/'.Str::uuid().'/old.jpg', 10);
        $fresh = $this->photo('customer-photos/drafts/'.Str::uuid().'/new.jpg', 2);
        $order = $this->photo('customer-photos/orders/100/1/order.jpg', 100);
        $unexpected = $this->photo('customer-photos/drafts/not-a-uuid/keep.jpg', 100);
        $other = $this->photo('other-files/important.jpg', 100);

        $this->artisan('customer-photos:prune-drafts', ['--days' => 7])->assertSuccessful();
        Storage::disk('local')->assertMissing($expired);
        foreach ([$fresh, $order, $unexpected, $other] as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_dry_run_and_limit_do_not_delete_more_than_requested(): void
    {
        $old = [
            $this->photo('customer-photos/drafts/'.Str::uuid().'/old1.png', 12),
            $this->photo('customer-photos/drafts/'.Str::uuid().'/old2.png', 12),
        ];
        $this->artisan('customer-photos:prune-drafts', [
            '--days' => 7, '--dry-run' => true,
        ])->assertSuccessful();
        foreach ($old as $p) Storage::disk('local')->assertExists($p);

        $this->artisan('customer-photos:prune-drafts', [
            '--days' => 7, '--limit' => 1,
        ])->assertSuccessful();
        $this->assertSame(1, (int) Storage::disk('local')->exists($old[0])
            + (int) Storage::disk('local')->exists($old[1]));

        $this->artisan('customer-photos:prune-drafts', [
            '--days' => 0,
        ])->assertFailed();
    }
}
