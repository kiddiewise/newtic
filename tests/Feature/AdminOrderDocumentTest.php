<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminOrderDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function order(bool $corporate = false): Order
    {
        $order = Order::create([
            'order_number' => 'PK-DOC-'.Str::upper(Str::random(8)),
            'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.com',
            'phone' => '05321234567',
            'address_label' => 'Ev',
            'city' => 'İzmir',
            'district' => 'Karşıyaka',
            'street_address' => 'Atatürk Mahallesi 5. Sokak No 12 Daire 3',
            'invoice_type' => $corporate ? 'corporate' : 'individual',
            'company_title' => $corporate ? 'Deneme Ticaret Limited' : null,
            'tax_office' => $corporate ? 'Karşıyaka' : null,
            'tax_number' => $corporate ? '1234567890' : null,
            'order_note' => 'Kırılabilir ürün, lütfen dikkatli paketleyin.',
            'payment_method' => 'bank_transfer',
            'status' => 'preparing',
            'subtotal_cents' => 95000,
            'shipping_cents' => 7900,
            'total_cents' => 102900,
            'currency' => 'TRY',
            'is_test' => true,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'sku' => 'SURF-125',
            'product_name' => 'Deneme Surf Kurşun 125 gr',
            'quantity' => 3,
            'unit_price_cents' => 25000,
            'line_total_cents' => 75000,
            'option_label' => 'İğne Kalınlığı',
            'option_value' => '0.25',
        ]);
        $order->items()->create([
            'sku' => 'SURF-75',
            'product_name' => 'Deneme Surf Kurşun 75 gr',
            'quantity' => 1,
            'unit_price_cents' => 20000,
            'line_total_cents' => 20000,
        ]);

        return $order;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_proforma_is_real_a4_pdf_only_for_admin_and_has_safe_download_headers(): void
    {
        $order = $this->order(true);
        $uri = route('admin.orders.document', [$order, 'proforma']);

        $this->get($uri)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();

        $this->actingAs($this->admin());
        $response = $this->get($uri)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Promofatura.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(7000, strlen($response->getContent()));
        $this->assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
    }

    public function test_print_preview_is_inline_and_packing_sheet_is_separate_pdf(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin());
        $detail = $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Promofatura PDF')
            ->assertSee('Hazırlama Formu')
            ->assertSee('Önizle / Yazdır');

        $this->assertStringContainsString(
            route('admin.orders.document', [$order, 'proforma']),
            $detail->getContent()
        );

        $preview = $this->get(route('admin.orders.document', [$order, 'proforma', 'preview' => 1]))
            ->assertOk();
        $this->assertStringContainsString('inline;', $preview->headers->get('Content-Disposition'));

        $packing = $this->get(route('admin.orders.document', [$order, 'packing']))->assertOk();
        $this->assertStringContainsString('Hazirlama-Formu.pdf', $packing->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $packing->getContent());
    }

    public function test_invalid_mode_and_missing_order_are_unavailable(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin());
        $this->get("/admin/orders/{$order->id}/documents/not-a-mode")->assertNotFound();
        $this->get('/admin/orders/999999999/documents/proforma')->assertNotFound();
    }

    public function test_document_template_never_shows_prices_in_picking_mode_and_preserves_corporate_address(): void
    {
        $order = $this->order(true)->load('items');
        $params = [
            'order' => $order,
            'shopName' => 'Örnek Mağaza',
            'logoData' => null,
            'qrData' => 'data:image/png;base64,'.base64_encode('stub'),
            'adminUrl' => route('admin.orders.show', $order),
            'paymentVerified' => false,
        ];

        $packing = view('admin.orders.document', $params + ['mode' => 'packing'])->render();
        $this->assertStringContainsString('Deneme Ticaret Limited', $packing);
        $this->assertStringContainsString('1234567890', $packing);
        $this->assertStringContainsString('Atatürk Mahallesi', $packing);
        $this->assertStringContainsString('Kırılabilir ürün', $packing);
        $this->assertStringContainsString('0.25', $packing);
        $this->assertStringNotContainsString('1.029,00', $packing);
        $this->assertStringNotContainsString('950,00', $packing);
        $this->assertStringNotContainsString('GENEL TOPLAM', $packing);

        $proforma = view('admin.orders.document', $params + ['mode' => 'proforma'])->render();
        $this->assertStringContainsString('1.029,00', $proforma);
        $this->assertStringContainsString('PROMOFATURA', $proforma);
        $this->assertStringContainsString('MALİ BELGE DEĞİLDİR', $proforma);
    }

    public function test_custom_brand_logo_is_loaded_from_private_local_store_files_without_external_requests(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(220, 70);
        $color = imagecolorallocate($image, 9, 32, 55);
        imagefilledrectangle($image, 0, 0, 219, 69, $color);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('branding/shop.png', $png);

        ShopAppearance::current()->update(['desktop_logo_path' => 'storage/branding/shop.png']);
        $this->actingAs($this->admin());

        $pdf = $this->get(route('admin.orders.document', [$this->order(), 'proforma']))
            ->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(7000, strlen($pdf));
    }
}
