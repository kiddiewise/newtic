<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ProductCsvTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;

class ProductCsvController extends Controller
{
    public function index(): View
    {
        return view('admin.products.bulk', [
            'categories' => Category::query()->orderBy('name')->get(['name', 'slug']),
            'limit' => ProductCsvTransfer::MAX_ROWS,
        ]);
    }

    public function export(ProductCsvTransfer $service): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($service) {
                $stream = fopen('php://output', 'wb');
                $service->export($stream);
                fclose($stream);
            },
            'newtic-urunler-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function template(ProductCsvTransfer $service): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($service) {
                $stream = fopen('php://output', 'wb');
                $service->export($stream, true);
                fclose($stream);
            },
            'newtic-toplu-urun-sablonu.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function upload(Request $request, ProductCsvTransfer $service): RedirectResponse
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'extensions:csv', 'max:10240'],
        ], [
            'csv.extensions' => 'Yalnızca NEWTIC şablonundaki .csv dosyalarını yükleyin.',
            'csv.max' => 'CSV dosyası en fazla 10 MB olabilir.',
        ]);
        /** @var UploadedFile $file */
        $file = $data['csv'];
        try {
            $result = $service->import($file->getRealPath());
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);
            throw ValidationException::withMessages(['csv' =>
                'Aktarım sırasında bir hata oluştu. Tüm işlem geri alındı; dosyayı kontrol edip yeniden deneyin.'
            ]);
        }

        return redirect()->route('admin.products.bulk.index')->with(
            'success',
            $result['total'].' satır işlendi: '.$result['created'].' yeni ürün, '.$result['updated'].' güncellenen ürün. Diğer ürünler korunmuştur.'
        );
    }
}
