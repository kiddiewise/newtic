<?php

namespace App\Http\Controllers;

use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use Illuminate\Http\Response;

final class LegalPageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = MerchantLegalPage::published($slug);
        abort_unless($page, 404);

        return response()->view('store.legal-page', [
            'page' => $page,
            'merchant' => MerchantLegalProfile::current(),
        ]);
    }
}
