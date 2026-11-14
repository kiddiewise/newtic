<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $mailData['subject'] }}</title></head>
<body style="margin:0;background:#f3f5f8;font-family:Arial,Helvetica,sans-serif;color:#092037;padding:24px 10px">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;margin:auto;border-collapse:collapse;background:white;border-radius:16px;overflow:hidden">
    <tr><td style="padding:25px 30px;background:#092037;color:white;font-weight:700;font-size:21px;letter-spacing:.1px">{{ $mailData['brand'] }}</td></tr>
    <tr><td style="padding:32px 30px">
      <h1 style="margin:0 0 16px;font-size:23px;color:#092037">{{ $mailData['heading'] }}</h1>
      <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#424769">{{ $mailData['body'] }}</p>
      @if(count($mailData['lines']))
      <div style="background:#f5f7fa;border-radius:10px;padding:16px 18px;margin-bottom:20px">
        @foreach($mailData['lines'] as $line)
          <p style="margin:6px 0;font-size:14px;line-height:1.6;color:#092037">{{ $line }}</p>
        @endforeach
      </div>
      @endif
      @if($mailData['action'])
        <a href="{{ $mailData['action']['url'] }}" style="display:inline-block;background:#092037;color:white;padding:13px 22px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600">{{ $mailData['action']['title'] }}</a>
      @endif
      <p style="font-size:12px;color:#7b8492;line-height:1.55;margin-top:28px">{{ $mailData['note'] }}</p>
    </td></tr>
  </table>
</body></html>
