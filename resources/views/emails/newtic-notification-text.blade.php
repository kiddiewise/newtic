{{ $mailData['brand'] }}

{{ $mailData['heading'] }}

{{ $mailData['body'] }}

@foreach($mailData['lines'] as $line)
{{ $line }}
@endforeach

@if($mailData['action'])
{{ $mailData['action']['title'] }}: {{ $mailData['action']['url'] }}
@endif

{{ $mailData['note'] }}
