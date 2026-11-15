{{ $mailData['brand'] }}

{{ $mailData['title'] }}

{{ $mailData['content'] }}

@if($mailData['button_text'] && $mailData['button_url'])
{{ $mailData['button_text'] }}: {{ $mailData['button_url'] }}
@endif

{{ $mailData['business_name'] }}
{{ $mailData['business_identifier'] }}
{{ $mailData['business_address'] }}
{{ $mailData['contact_email'] }}

@if($mailData['unsubscribe_url'])
Kampanya aboneliğini ücretsiz sonlandır: {{ $mailData['unsubscribe_url'] }}
@else
Yalnızca yönetici testi.
@endif
