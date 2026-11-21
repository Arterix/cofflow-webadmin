@extends('owner.layouts.app')
@section('title', 'Owner Report · Konco Space')
@section('page_title', 'Laporan Owner')
@section('page_sub', 'Laporan penjualan dan arus stok read-only')
@section('content')
<div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm"><div class="text-xs text-gray-500">Total Order</div><div class="text-2xl font-semibold text-primary">{{ $report['totalOrder'] }}</div><div class="mt-2 text-xs text-gray-500">Total Revenue</div><div class="text-2xl font-semibold text-primary">Rp {{ number_format($report['totalRevenue'], 0, ',', '.') }}</div></div>
<div class="mt-5 bg-white rounded-2xl border border-primary-100 p-5 shadow-sm"><h2 class="font-display font-semibold text-primary mb-3">Arus Stok</h2><p class="text-sm">Masuk: {{ number_format($report['stockIn'], 3, ',', '.') }} · Keluar: {{ number_format($report['stockOut'], 3, ',', '.') }}</p></div>
@endsection
