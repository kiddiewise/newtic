<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HostingerRestore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class BackupRestoreController extends Controller
{
    public function index(HostingerRestore $service): View
    {
        $jobs = [];
        foreach (array_slice(array_reverse(glob($service->root().'/*/manifest.json') ?: []), 0, 20) as $file) {
            try {
                $entry = json_decode((string) file_get_contents($file), true);
                if (is_array($entry)) $jobs[] = $entry;
            } catch (Throwable) {
                // A malformed private manifest must not break the admin interface.
            }
        }

        return view('admin.backup-restore', compact('jobs'));
    }

    public function upload(Request $request, HostingerRestore $service): RedirectResponse
    {
        $request->validate([
            'database' => ['nullable', 'file', 'max:262144'],
            'files' => ['nullable', 'file', 'max:262144'],
        ]);

        $sql = $request->file('database');
        $archive = $request->file('files');
        if (!$sql && !$archive) {
            return back()->withErrors(['database' => 'Veritabanı veya site dosyası seçmelisiniz.']);
        }
        if ($sql && !str_ends_with(strtolower($sql->getClientOriginalName()), '.sql.gz')) {
            return back()->withErrors(['database' => 'Hostinger veritabanı yedeği .sql.gz olmalı.']);
        }
        if ($archive && !str_ends_with(strtolower($archive->getClientOriginalName()), '.tar.gz')) {
            return back()->withErrors(['files' => 'Hostinger dosya yedeği .tar.gz olmalı.']);
        }

        $id = bin2hex(random_bytes(16));
        $directory = $service->directory($id);
        File::ensureDirectoryExists($directory, 0700);

        try {
            foreach (['database.sql.gz' => $sql, 'files.tar.gz' => $archive] as $name => $uploaded) {
                if (!$uploaded) continue;
                $uploaded->move($directory, $name);
                $file = $directory.'/'.$name;
                $handle = fopen($file, 'rb');
                $magic = $handle ? fread($handle, 2) : null;
                if ($handle) fclose($handle);
                if ($magic !== "\x1f\x8b") throw new RuntimeException('Dosya geçerli bir GZIP arşivi değil.');
                @chmod($file, 0600);
            }

            $preview = $service->preview($id);
            $service->write([
                'id' => $id,
                'created_by' => (int) $request->user()->id,
                'created_at' => now()->toIso8601String(),
                'status' => 'ready',
                'database' => $preview['database'],
                'media' => $preview['media'],
                'images' => $preview['images'],
                'bytes' => $preview['bytes'],
                'message' => 'Dosyalar doğrulandı. Geri yükleme için onay bekleniyor.',
            ]);
        } catch (Throwable $e) {
            File::deleteDirectory($directory);
            report($e);
            return back()->withErrors(['database' => 'Yedek kontrol edilemedi. Dosyaların Hostinger kaynaklı ve sağlam olduğundan emin olun.']);
        }

        return redirect()->route('admin.backup-restore.index', ['restore' => $id])
            ->with('success', 'Yedek dosyaları kontrol edildi. Geri yükleme için ayrıca onay vermelisiniz.');
    }

    public function confirm(Request $request, HostingerRestore $service, string $id): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:GERI YUKLE'],
            'safety_copy' => ['accepted'],
        ], [
            'confirmation.in' => 'Onay kutusuna GERI YUKLE yazmalısınız.',
            'safety_copy.accepted' => 'Önce Hostinger üzerinden güncel kurtarma yedeği aldığınızı onaylayın.',
        ]);

        try {
            $job = $service->read($id);
            if ((int) $job['created_by'] !== (int) $request->user()->id || $job['status'] !== 'ready') {
                throw new RuntimeException('Bu geri yükleme kaydı artık onaylanamaz.');
            }

            $job['status'] = 'queued';
            $job['message'] = 'Geri yükleme sıraya alındı. Cron görevi işlemi başlatacak.';
            $job['queued_at'] = now()->toIso8601String();
            $service->write($job);
        } catch (Throwable) {
            return back()->withErrors(['confirmation' => 'Kayıt bulunamadı veya daha önce onaylandı.']);
        }

        return redirect()->route('admin.backup-restore.index')
            ->with('success', 'Geri yükleme sıraya alındı. İşlem sonucunu bu sayfadan kontrol edin.');
    }

    public function delete(Request $request, HostingerRestore $service, string $id): RedirectResponse
    {
        try {
            $job = $service->read($id);
            if ((int) $job['created_by'] !== (int) $request->user()->id
                || !in_array($job['status'], ['ready', 'failed', 'done'], true)) {
                throw new RuntimeException('Bu kayıt silinemez.');
            }
            File::deleteDirectory($service->directory($id));
        } catch (Throwable) {
            return back()->withErrors(['restore' => 'Yedek kaydı silinemedi.']);
        }

        return back()->with('success', 'Geçici yedek dosyaları silindi.');
    }
}
