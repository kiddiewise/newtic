@extends('admin.layout')
@section('title','Belge türü | NEWTIC')
@section('content')
<section class="admin-panel"><h1>Alıcı belge türü</h1><p>{{ $type }}</p><p>Bu kontrol fatura düzenlemez veya göndermez.</p><a class="admin-secondary" href="{{ route('admin.invoices.index') }}">Fatura yönetimine dön</a></section>
@endsection
