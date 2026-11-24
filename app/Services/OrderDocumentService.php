<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShopAppearance;
use App\Support\StoreIdentity;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class OrderDocumentService
{
    /**
     * Every document is rendered from the immutable order snapshot. No external
     * images, QR services, browser cookies or customer-provided HTML are used.
     */
    public function render(Order $order, string $mode): string
    {
        if (!in_array($mode, ['proforma', 'packing'], true)) {
            throw new \InvalidArgumentException('Unsupported order document.');
        }

        $order->loadMissing('items');
        $adminUrl = route('admin.orders.show', ['order' => $order->id], true);
        $qr = (new Builder(
            writer: new PngWriter(),
            data: $adminUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 12,
        ))->build();

        $qrData = 'data:image/png;base64,'.base64_encode($qr->getString());
        $logoData = $this->logoDataUri();
        $shopName = StoreIdentity::name();
        $paymentVerified = $order->payment_method === 'paytr'
            ? $order->paytrAttempts()->where('status', 'success')->exists()
            : $order->paymentNotifications()->where('status', 'verified')->exists();

        $html = view('admin.orders.document', compact(
            'order', 'mode', 'shopName', 'logoData', 'qrData',
            'adminUrl', 'paymentVerified'
        ))->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $output = $dompdf->output();
        if (!str_starts_with($output, '%PDF-')) {
            throw new RuntimeException('Order document could not be rendered.');
        }

        return $output;
    }

    /**
     * Logos are read only from this installation's trusted branding directory.
     * WebP/JPEG/AVIF are locally rasterized to a PDF-compatible PNG.
     */
    private function logoDataUri(): ?string
    {
        $logo = ShopAppearance::current()->desktopLogo();
        // A merchant without an uploaded brand should not print NEWTIC's
        // platform logo on its own customer paperwork.
        if (($logo === ShopAppearance::DEFAULT_DESKTOP_LOGO
                || $logo === ShopAppearance::DEFAULT_MOBILE_LOGO)
            && !in_array(mb_strtolower(StoreIdentity::name()), ['newtic', 'newtic e-ticaret'], true)) {
            return null;
        }
        if (str_starts_with($logo, 'storage/')) {
            $relative = substr($logo, strlen('storage/'));
            if (!preg_match('~^branding/[a-zA-Z0-9_.-]+\.(?:png|jpe?g|webp|avif)$~Di', $relative)) {
                return null;
            }
            if (!Storage::disk('public')->exists($relative)) {
                return null;
            }
            $file = Storage::disk('public')->path($relative);
        } elseif ($logo === ShopAppearance::DEFAULT_DESKTOP_LOGO
            || $logo === ShopAppearance::DEFAULT_MOBILE_LOGO) {
            $file = public_path($logo);
        } else {
            return null;
        }

        if (!is_file($file) || filesize($file) > 5 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesize($file);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $source = @imagecreatefromstring($raw);
        if (!$source) {
            return null;
        }

        try {
            $ratio = min(440 / imagesx($source), 140 / imagesy($source), 1);
            $width = max(1, (int) round(imagesx($source) * $ratio));
            $height = max(1, (int) round(imagesy($source) * $ratio));
            $target = imagecreatetruecolor($width, $height);
            try {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
                imagefilledrectangle($target, 0, 0, $width, $height, $transparent);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
                ob_start();
                imagepng($target, null, 6);
                $png = ob_get_clean();
                return is_string($png) && $png !== ''
                    ? 'data:image/png;base64,'.base64_encode($png)
                    : null;
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
