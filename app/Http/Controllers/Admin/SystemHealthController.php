<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SystemHealthSnapshot;
use Illuminate\Http\Response;

final class SystemHealthController extends Controller
{
    public function __invoke(SystemHealthSnapshot $snapshot): Response
    {
        return response()->view('admin.system-health', $snapshot->collect())
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
