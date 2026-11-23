<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NewticReleaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class SoftwareUpdateController extends Controller
{
    public function index(NewticReleaseManager $releases): View
    {
        return view('admin.software-updates', [
            'currentVersion' => $releases->currentVersion(),
            'latestRelease' => $releases->latest(),
            'hasUpdate' => $releases->hasUpdate(),
            'updateState' => $releases->state(),
        ]);
    }

    public function check(NewticReleaseManager $releases): RedirectResponse
    {
        $release = $releases->refresh();
        if ($release === null) {
            return back()->withErrors(['update' => 'Güncelleme sunucusuna ulaşılamadı veya yayınlanmış paket bulunmuyor.']);
        }
        return back()->with('success', $releases->hasUpdate()
            ? 'Yeni sürüm bulundu: NEWTIC '.$release['version']
            : 'NEWTIC için şu an yeni bir sürüm yok.');
    }

    public function install(Request $request, NewticReleaseManager $releases): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:GUNCELLE'],
            'backup_confirmed' => ['accepted'],
        ], [
            'confirmation.in' => 'Onay alanına GUNCELLE yazın.',
            'backup_confirmed.accepted' => 'Güncel yedeğinizin olduğunu onaylamalısınız.',
        ]);

        try {
            $state = $releases->state();
            if (in_array($state['status'] ?? '', ['queued', 'running'], true)) {
                throw new RuntimeException('Zaten bir güncelleme işlemi devam ediyor.');
            }

            // A fresh official API result prevents installing stale cached metadata.
            $release = $releases->refresh();
            if ($release === null
                || version_compare($release['version'], $releases->currentVersion(), '<=')) {
                throw new RuntimeException('Yeni ve doğrulanabilir bir güncelleme bulunamadı.');
            }
            if (!extension_loaded('zip')) {
                throw new RuntimeException('Bu sunucuda ZIP uzantısı etkin değil. Güncellemeyi barındırma sağlayıcınızla tamamlayın.');
            }

            $releases->writeState([
                'id' => bin2hex(random_bytes(16)),
                'status' => 'queued',
                'version' => $release['version'],
                'tag' => $release['tag'],
                'url' => $release['url'],
                'sha256' => $release['sha256'],
                'requested_by' => (int) $request->user()->id,
                'requested_at' => now()->toIso8601String(),
                'message' => 'Güncelleme sıraya alındı. Cron görevi otomatik uygulayacak.',
            ]);
            return redirect()->route('admin.software-updates.index')
                ->with('success', 'Güncelleme sıraya alındı. İşlem durumunu bu sayfada görebilirsiniz.');
        } catch (Throwable $e) {
            return back()->withErrors(['update' => $e->getMessage()]);
        }
    }
}
