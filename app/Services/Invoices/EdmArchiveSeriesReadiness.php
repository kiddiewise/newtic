<?php

namespace App\Services\Invoices;

/** Read-only evidence from GetCompany, not permission to issue an archive invoice. */
final class EdmArchiveSeriesReadiness
{
    public static function inspect(array $result,int $year): array
    {
        $out=['state'=>'unverified','series'=>[],'submission_allowed'=>false];
        if (($result['status']??null)!=='ok' || !is_array($result['data']??null)) return $out;
        $data=$result['data'];
        if (!is_int($data['unreadable_series']??null) || $data['unreadable_series']<0 || !is_array($data['series']??null)) return $out;
        $seen=[];$eligible=[];
        foreach ($data['series'] as $row) {
            if (!is_array($row) || !is_int($row['year']??null) || !is_string($row['code']??null)
                || !preg_match('/^[A-Z0-9]{3}$/D',$row['code'])
                || !is_bool($row['active']??null) || !is_bool($row['archive']??null) || !is_bool($row['internet']??null)) return $out;
            if ($row['year']!==$year) continue;
            if (isset($seen[$row['code']])) return $out;
            $seen[$row['code']]=true;
            if ($row['active'] && $row['archive'] && $row['internet'] && !in_array($row['code'],['ABC','GIB'],true)) $eligible[]=$row['code'];
        }
        sort($eligible);
        $out['state']=$data['unreadable_series']>0?'partial':($eligible?'available':'not_found');
        $out['series']=$eligible;
        return $out;
    }
}
