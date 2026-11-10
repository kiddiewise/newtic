<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);
        $customers = User::query()->where('is_admin', false)
            ->withCount('orders')
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term) {
                $safe = '%'.addcslashes($term, '%_\\').'%';
                $q->where('name', 'like', $safe)->orWhere('email', 'like', $safe);
            }))->latest('id')->paginate(20)->withQueryString();

        return view('admin.customers', compact('customers', 'term'));
    }
}
