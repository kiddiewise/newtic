<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminOrderNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderBadgeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['count'=>AdminOrderNotifications::count($request->user())])
            ->header('Cache-Control','private, no-store, max-age=0');
    }
}
