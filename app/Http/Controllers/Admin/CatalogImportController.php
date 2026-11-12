<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogImport;
use App\Models\CatalogImportRow;
use App\Support\WooCatalogImportService;
use App\Support\WooCsvReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogImportController extends Controller
{
    public function index(): View
    {
        $batches = CatalogImport::query()->latest()->limit(20)->get();

        return view('admin.product-imports.index', compact('batches'));
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'extensions:csv', 'max:25600'],
        ], ['csv.extensions' => 'WooCommerce Ürünler → Dışa Aktar seçeneğinden bir CSV yükleyin.']);

        $file = $request->file('csv');
        $path = $file->store('catalog-imports', 'local');
        $batch = null;

        try {
            DB::transaction(function () use ($request, $file, $path, &$batch) {
                $batch = CatalogImport::create([
                    'user_id' => $request->user()->id,
                    'source_site' => 'polariskursun.com',
                    'original_filename' => mb_substr($file->getClientOriginalName(), 0, 190),
                    'file_path' => $path,
                    'status' => 'preview',
                ]);

                $buffer = [];
                $total = 0;

                foreach (WooCsvReader::rows(Storage::disk('local')->path($path)) as $item) {
                    if (++$total > 10000) {
                        throw ValidationException::withMessages([
                            'csv' => 'Tek dosyada en fazla 10.000 satır aktarılabilir. Dosyayı bölerek yükleyin.',
                        ]);
                    }
                    $data = $item['data'];
                    $buffer[] = [
                        'catalog_import_id' => $batch->id,
                        'row_number' => $item['row_number'],
                        'woo_id' => mb_substr(WooCsvReader::get($data, 'ID'), 0, 80) ?: null,
                        'row_type' => mb_substr(WooCsvReader::type($data) ?: 'simple', 0, 30),
                        'status' => 'pending',
                        'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (count($buffer) >= 100) {
                        DB::table('catalog_import_rows')->insert($buffer);
                        $buffer = [];
                    }
                }
                if ($buffer) DB::table('catalog_import_rows')->insert($buffer);
                if (!$total) {
                    throw ValidationException::withMessages(['csv' => 'CSV dosyasında ürün satırı bulunamadı.']);
                }
                $batch->update(['total_rows' => $total]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($e instanceof ValidationException) throw $e;
            throw ValidationException::withMessages([
                'csv' => 'CSV içe aktarılamadı: '.$e->getMessage(),
            ]);
        }

        return redirect()->route('admin.product-imports.show', $batch)
            ->with('success', 'Dosya analiz edildi. Henüz hiçbir ürüne dokunulmadı.');
    }

    public function show(CatalogImport $catalogImport): View
    {
        $import = $catalogImport;
        $samples = $import->rows()->orderBy('id')->limit(8)->get();
        $recent = $import->rows()->where(function ($query) {
            $query->whereIn('status', ['failed', 'skipped'])
                ->orWhere(function ($query) {
                    $query->whereIn('status', ['created','updated'])
                        ->where('message', '!=', 'Aktarım başarılı.');
                });
        })->orderByDesc('id')->limit(25)->get();
        $types = $import->rows()->selectRaw('row_type, COUNT(*) as total')
            ->groupBy('row_type')->pluck('total', 'row_type')->all();

        return view('admin.product-imports.show', compact('import', 'samples', 'recent', 'types'));
    }

    public function start(Request $request, CatalogImport $catalogImport): RedirectResponse
    {
        if ($catalogImport->status !== 'preview') {
            return redirect()->route('admin.product-imports.show', $catalogImport)
                ->with('success', 'Aktarım daha önce başlatıldı; mevcut ilerlemeye devam edebilirsiniz.');
        }
        $request->validate(['publish_products' => ['sometimes','boolean']]);
        $catalogImport->update([
            'status' => 'running',
            'publish_products' => $request->boolean('publish_products'),
        ]);

        return redirect()->route('admin.product-imports.show', $catalogImport)
            ->with('success', 'Aktarım başladı. İlerleme tamamlanana kadar bu sayfayı açık tutun; daha sonra kaldığınız yerden devam edebilirsiniz.');
    }

    public function step(Request $request, CatalogImport $catalogImport, WooCatalogImportService $service): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($catalogImport->status, ['running','completed'], true), 409);
        // Only retry rows abandoned by a crashed PHP request. Never
        // requeue rows still being processed by another active request.
        $catalogImport->rows()->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(2))
            ->update(['status' => 'pending']);
        $limit = 2;
        $processed = 0;

        while ($processed < $limit) {
            // Mark row in a transaction so repeat browser requests cannot
            // import the same row concurrently.
            $row = DB::transaction(function () use ($catalogImport) {
                $candidate = $catalogImport->rows()
                    ->where('status', 'pending')->orderBy('id')->lockForUpdate()->first();
                if ($candidate) $candidate->update(['status' => 'processing']);
                return $candidate;
            });
            if (!$row) break;

            try {
                $result = $service->apply($catalogImport, $row);
                $status = $result['status'];
                $row->update([
                    'status' => $status,
                    'product_id' => $result['product_id'],
                    'message' => mb_substr($result['message'], 0, 1500),
                ]);
            } catch (\Throwable $e) {
                $status = 'failed';
                $row->update([
                    'status' => 'failed',
                    'message' => mb_substr($e->getMessage(), 0, 1500),
                ]);
            }
            $catalogImport->increment('processed_rows');
            $catalogImport->increment($status.'_rows');
            $processed++;
        }

        // Recover an interrupted step on the next request rather than leave
        // orphaned "processing" rows after the browser closes or PHP crashes.
        if (!$catalogImport->rows()->whereIn('status', ['pending', 'processing'])->exists()) {
            $catalogImport->update(['status' => 'completed']);
        }

        $catalogImport->refresh();
        $summary = [
            'status' => $catalogImport->status,
            'total' => $catalogImport->total_rows,
            'processed' => $catalogImport->processed_rows,
            'created' => $catalogImport->created_rows,
            'updated' => $catalogImport->updated_rows,
            'skipped' => $catalogImport->skipped_rows,
            'failed' => $catalogImport->failed_rows,
            'percent' => $catalogImport->total_rows > 0
                ? (int) round(100 * $catalogImport->processed_rows / $catalogImport->total_rows) : 0,
            'url' => route('admin.product-imports.show', $catalogImport),
        ];

        if ($request->expectsJson()) return response()->json($summary);
        return redirect()->route('admin.product-imports.show', $catalogImport);
    }
}
