<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\ShoppingCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CustomerProductPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        BankAccount::create([
            'bank_name' => 'Deneme Bankası',
            'account_holder' => 'Deneme Mağazası',
            'iban' => 'TR330006100519786457841326',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function product(bool $required = true, string $sku = 'PHOTO-001'): Product
    {
        $category = Category::first() ?? Category::create([
            'name' => 'Kişiye özel hediyeler', 'slug' => 'kisiye-ozel',
            'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'sku' => $sku, 'slug' => strtolower($sku),
            'name' => 'Karikatür biblo '.$sku,
            'price' => '250.00', 'stock_quantity' => 10,
            'tracks_stock' => true,
            'is_active' => true,
            'requires_customer_photos' => $required,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function image(string $name = 'referans.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 800);
    }

    private function address(string $token): array
    {
        return [
            'checkout_token' => $token,
            'payment_method' => 'bank_transfer',
            'email' => 'musteri@example.test',
            'address_label' => 'Ev',
            'full_name' => 'Alıcı Müşteri',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi Örnek Sokak No 12',
            'invoice_type' => 'individual',
        ];
    }

    public function test_admin_can_toggle_customer_photo_requirement_and_existing_products_remain_optional(): void
    {
        $product = $this->product(false);
        $this->assertFalse($product->requires_customer_photos);
        $this->actingAs($this->admin());

        $this->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee('Satın alırken fotoğraf yüklenmesini zorunlu tut');

        $form = [
            'name' => $product->name,
            'sku' => $product->sku,
            'primary_category_id' => $product->primary_category_id,
            'regular_price' => '250.00',
            'stock_quantity' => 10,
            'is_active' => 1,
            'requires_customer_photos' => 1,
        ];
        $this->patch(route('admin.products.update', $product), $form)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->requires_customer_photos);

        $this->patch(route('admin.products.update', $product), array_merge($form, [
            'requires_customer_photos' => 0,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->requires_customer_photos);

        $this->get(route('product.show', $product->slug))
            ->assertOk()->assertDontSee('name="customer_photos['.$product->id.'][]"', false);
    }

    public function test_product_card_leads_to_upload_picker_and_missing_files_are_rejected_by_server(): void
    {
        $product = $this->product();
        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertSee('Fotoğraf(lar) yükle')
            ->assertSee('name="customer_photos['.$product->id.'][]"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->get(route('category.show', $product->category->slug))->assertOk()
            ->assertSee('Fotoğraf Yükle')
            ->assertSee(route('product.show', $product->slug));

        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertSessionHasErrors('customer_photos.'.$product->id);

        $this->assertSame([], session('shopping_cart', []));
        $this->assertSame([], session('cart_customer_photos', []));
    }

    public function test_multiple_photos_are_private_and_persist_in_correct_cart_line(): void
    {
        $product = $this->product();

        $this->post(route('cart.add'), [
            'items' => [$product->id => 2],
            'customer_photos' => [$product->id => [
                $this->image('ilk.jpg'), $this->image('ikinci.png'),
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $photoKey = ShoppingCart::key($product->id);
        $photos = session('cart_customer_photos')[$photoKey];
        $this->assertCount(2, $photos);
        foreach ($photos as $photo) {
            $this->assertStringStartsWith('customer-photos/drafts/', $photo['path']);
            Storage::disk('local')->assertExists($photo['path']);
            Storage::disk('public')->assertMissing($photo['path']);
        }
        $this->assertSame(2, session('shopping_cart')[$product->id]);

        $this->get(route('cart.index'))->assertOk()->assertSee('Müşteri fotoğrafı: 2 adet');

        // Re-adding without new files reuses already uploaded private drafts.
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertSessionHasNoErrors();
        $this->assertCount(2, session('cart_customer_photos')[$photoKey]);

        $this->delete(route('cart.remove', $product))->assertRedirect()->assertSessionHasNoErrors();
        foreach ($photos as $photo) Storage::disk('local')->assertMissing($photo['path']);
        $this->assertSame([], session('cart_customer_photos', []));
    }

    public function test_invalid_file_and_oversized_and_unrelated_uploads_fail_before_cart_mutation(): void
    {
        $product = $this->product();

        $this->post(route('cart.add'), [
            'items' => [$product->id => 1],
            'customer_photos' => [$product->id => [
                UploadedFile::fake()->create('hijack.svg', 1, 'image/svg+xml'),
            ]],
        ])->assertSessionHasErrors('customer_photos.'.$product->id.'.0');

        $this->post(route('cart.add'), [
            'items' => [$product->id => 1],
            'customer_photos' => [$product->id => [
                $this->image('buyuk.jpg')->size(5200),
            ]],
        ])->assertSessionHasErrors('customer_photos.'.$product->id.'.0');

        $this->post(route('cart.add'), [
            'items' => [$product->id => 1],
            'customer_photos' => [999999 => [$this->image('baska-urun.jpg')]],
        ])->assertSessionHasErrors('customer_photos');

        $this->assertSame([], session('shopping_cart', []));
        $this->assertSame([], session('cart_customer_photos', []));
    }

    public function test_non_photo_product_cannot_receive_unrequested_customer_files(): void
    {
        $product = $this->product(false);
        $this->post(route('cart.add'), [
            'items' => [$product->id => 1],
            'customer_photos' => [$product->id => [$this->image()]],
        ])->assertSessionHasErrors('customer_photos.'.$product->id);

        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, session('shopping_cart')[$product->id]);
    }

    public function test_photo_uploads_stay_separate_for_two_options_and_copy_to_private_order_records(): void
    {
        $product = $this->product();
        $product->update(['option_label' => 'Boyut']);
        $small = $product->options()->create(['value' => 'Küçük']);
        $large = $product->options()->create(['value' => 'Büyük']);
        foreach ([[$small, 'small.jpg'], [$large, 'large.jpg']] as [$option, $fileName]) {
            $this->post(route('cart.add'), [
                'items' => [$product->id => 1],
                'options' => [$product->id => $option->id],
                'customer_photos' => [$product->id => [$this->image($fileName)]],
            ])->assertSessionHasNoErrors();
        }

        $smallKey = ShoppingCart::key($product->id, $small->id);
        $largeKey = ShoppingCart::key($product->id, $large->id);
        $smallDraft = session('cart_customer_photos')[$smallKey][0]['path'];
        $largeDraft = session('cart_customer_photos')[$largeKey][0]['path'];
        $this->assertNotSame($smallDraft, $largeDraft);

        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.place'), $this->address((string) session('checkout_token')))
            ->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(2, $order->items()->count());
        $items = $order->items()->get()->keyBy('option_value');
        $this->assertSame('small.jpg', $items['Küçük']->customer_photos[0]['name']);
        $this->assertSame('large.jpg', $items['Büyük']->customer_photos[0]['name']);
        foreach ($items as $line) {
            $path = $line->customer_photos[0]['path'];
            $this->assertStringStartsWith('customer-photos/orders/'.$order->id.'/'.$line->id.'/', $path);
            Storage::disk('local')->assertExists($path);
        }
        $this->assertSame([], session('cart_customer_photos', []));
        Storage::disk('local')->assertMissing($smallDraft);
        Storage::disk('local')->assertMissing($largeDraft);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $smallLine = $items['Küçük'];
        $url = route('admin.orders.customer-photo', [$order, $smallLine, 0]);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($this->admin());
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Müşteri tarafından yüklenen fotoğraflar');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('admin.orders.customer-photo', [$order, $smallLine, 999]))->assertNotFound();
        $this->get(route('admin.orders.customer-photo', [$order, $smallLine, 0]))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_checkout_checks_photo_requirement_again_after_admin_changes_product(): void
    {
        $product = $this->product(false);
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk();

        $product->update(['requires_customer_photos' => true]);
        $this->post(route('checkout.place'), $this->address((string) session('checkout_token')))
            ->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }
}
