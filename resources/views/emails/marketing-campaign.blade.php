<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $mailData['subject'] }}</title></head>
<body style="background:#f3f5f8;margin:0;padding:22px 8px;font-family:Arial,sans-serif;color:#092037">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:auto;border-collapse:collapse;background:#fff;border-radius:16px;overflow:hidden">
<tr><td style="background:#092037;color:#fff;padding:22px 30px;font-size:21px;font-weight:700">{{ $mailData['brand'] }}</td></tr>
@if($mailData['banner'])
<tr><td><img src="{{ $mailData['banner'] }}" alt="Kampanya görseli" style="display:block;width:100%;max-width:600px;height:auto" width="600"></td></tr>
@endif
<tr><td style="padding:30px">
  <h1 style="font-size:24px;line-height:1.25;margin:0 0 18px">{{ $mailData['title'] }}</h1>
  <div style="font-size:15px;color:#424769;line-height:1.75;white-space:pre-line">{{ $mailData['content'] }}</div>
  @if($mailData['button_text'] && $mailData['button_url'])
  <div style="margin:26px 0"><a href="{{ $mailData['button_url'] }}" style="display:inline-block;padding:14px 25px;background:#092037;color:#fff;font-size:14px;font-weight:600;text-decoration:none;border-radius:9px">{{ $mailData['button_text'] }}</a></div>
  @endif
  <hr style="border:0;border-top:1px solid #e7ebef;margin:25px 0">
  <div style="font-size:11px;line-height:1.6;color:#737d8e">
    <strong>{{ $mailData['business_name'] }}</strong>
    @if($mailData['business_identifier']) · {{ $mailData['business_identifier'] }} @endif
    @if($mailData['business_address'])<p style="margin:3px 0">{{ $mailData['business_address'] }}</p>@endif
    @if($mailData['contact_email'])<p style="margin:3px 0">İletişim: {{ $mailData['contact_email'] }}</p>@endif
    @if($mailData['unsubscribe_url'])
    <p style="margin:14px 0 0">Bu kampanya e-postalarını almak istemiyorsanız <a style="color:#092037;text-decoration:underline" href="{{ $mailData['unsubscribe_url'] }}">e-posta aboneliğinizi ücretsiz sonlandırın</a>.</p>
    @else
    <p>Bu ileti yalnızca yönetici önizlemesidir. Gerçek abonelere gönderilmedi.</p>
    @endif
  </div>
</td></tr>
</table>
</body></html>
