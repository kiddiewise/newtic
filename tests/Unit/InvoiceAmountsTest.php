<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Invoices\InvoiceAmounts;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

final class InvoiceAmountsTest extends TestCase
{
    private function order(array $items, int $subtotal, int $shipping=0, ?int $total=null): Order
    {
        $order = new Order(['currency'=>'TRY','subtotal_cents'=>$subtotal,'shipping_cents'=>$shipping,'total_cents'=>$total??($subtotal+$shipping)]);
        $order->setRelation('items',new Collection(array_map(fn($item)=>new OrderItem($item),$items)));
        return $order;
    }

    private function item(int $unit,int $quantity,?int $rate): array
    {
        return ['product_name'=>'Ürün','unit_price_cents'=>$unit,'quantity'=>$quantity,'line_total_cents'=>$unit*$quantity,'vat_rate'=>$rate];
    }

    public function test_rounding_and_mixed_tax_groups_balance_exactly(): void
    {
        $this->assertSame(['gross_cents'=>12000,'net_cents'=>10000,'tax_cents'=>2000,'rate'=>20],InvoiceAmounts::split(12000,20));
        $result=(new InvoiceAmounts)->preview($this->order([$this->item(9990,3,20),$this->item(1101,1,10),$this->item(1,1,1)],31072,7990),20);
        $this->assertTrue($result['complete']);
        $this->assertSame(39062,$result['gross_cents']);
        $this->assertSame($result['gross_cents'],$result['net_cents']+$result['tax_cents']);
        $this->assertSame([1,10,20],array_column($result['groups'],'rate'));
        $this->assertTrue($result['shipping_assumption']);
    }

    public function test_unknown_historical_tax_or_shipping_is_never_inferred(): void
    {
        $result=(new InvoiceAmounts)->preview($this->order([$this->item(12000,1,20),$this->item(2000,1,null)],14000,7990));
        $this->assertFalse($result['complete']);
        $this->assertSame(9990,$result['unclassified_cents']);
        $this->assertNull($result['net_cents']);
        $this->assertNull($result['tax_cents']);
        $this->assertSame(10000,$result['known_net_cents']);
        $this->assertSame(2000,$result['known_tax_cents']);
    }

    public function test_inconsistent_order_total_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        (new InvoiceAmounts)->preview($this->order([$this->item(12000,1,20)],12000,0,12001));
    }

    public function test_inconsistent_line_total_is_rejected(): void
    {
        $line=$this->item(12000,2,20);
        $line['line_total_cents']=12000;
        $this->expectException(DomainException::class);
        (new InvoiceAmounts)->preview($this->order([$line],12000));
    }

    public function test_unsupported_rate_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        InvoiceAmounts::split(12000,18);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        InvoiceAmounts::split(-1,20);
    }
}
