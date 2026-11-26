<?php

namespace App\Services\Invoices;

use DomainException;

/** NEWTIC local monetary integrity rules. Not GIB Schematron or fiscal approval. */
final class InvoiceSnapshotRules
{
    private const MAX = 1_000_000_000_000;
    private const KEYS = ['gross_cents','net_cents','tax_cents'];

    private static function require(bool $valid): void
    {
        if (!$valid) throw new DomainException('Fatura taslağının satır, KDV veya toplam bilgileri tutarsız.');
    }

    private static function money(mixed $value): int
    {
        self::require(is_int($value) && $value>=0 && $value<=self::MAX);
        return $value;
    }

    public static function validate(array $snapshot): void
    {
        self::require(($snapshot['complete']??null)===true);
        $lines=$snapshot['lines']??null;
        $groups=$snapshot['groups']??null;
        self::require(is_array($lines) && array_is_list($lines) && count($lines)>0 && count($lines)<=10000);
        self::require(is_array($groups) && array_is_list($groups) && count($groups)>0 && count($groups)<=3);
        $totals=array_fill_keys(self::KEYS,0);
        $expected=[];
        foreach ($lines as $line) {
            self::require(is_array($line));
            $qty=$line['quantity']??null;
            $rate=$line['rate']??null;
            $unit=self::money($line['unit_gross_cents']??null);
            $label=$line['label']??null;
            self::require(is_string($label) && trim($label)!=='' && mb_strlen($label)<=2000
                && preg_match('/^[\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}\t\r\n]+$/uD',$label)===1);
            self::require(in_array($line['kind']??null,['product','shipping'],true));
            self::require(is_int($qty) && $qty>=1 && $qty<=1_000_000 && $unit<=intdiv(self::MAX,$qty));
            self::require(in_array($rate,[1,10,20],true));
            $split=InvoiceAmounts::split($unit*$qty,$rate);
            $expected[$rate]??=array_fill_keys(self::KEYS,0);
            foreach (self::KEYS as $key) {
                $value=self::money($line[$key]??null);
                self::require($value===$split[$key]);
                $totals[$key]=self::money($totals[$key]+$value);
                $expected[$rate][$key]=self::money($expected[$rate][$key]+$value);
            }
        }
        $seen=[];
        foreach ($groups as $group) {
            self::require(is_array($group));
            $rate=$group['rate']??null;
            self::require(is_int($rate) && isset($expected[$rate]) && !isset($seen[$rate]));
            $seen[$rate]=true;
            foreach (self::KEYS as $key) self::require(self::money($group[$key]??null)===$expected[$rate][$key]);
        }
        self::require(count($seen)===count($expected));
        foreach (self::KEYS as $key) self::require(self::money($snapshot[$key]??null)===$totals[$key]);
        self::require($totals['net_cents']+$totals['tax_cents']===$totals['gross_cents']);
    }
}
