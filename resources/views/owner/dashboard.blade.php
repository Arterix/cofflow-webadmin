@extends('owner.layouts.app')
@section('title', 'Owner Dashboard · Konco Space')
@section('page_title', 'Owner Dashboard')
@section('page_sub', 'Ringkasan bisnis read-only')
@section('content')
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm"><div class="text-xs text-gray-500">Order Hari Ini</div><div class="text-2xl font-semibold text-primary">{{ $dashboard['totalOrderToday'] }}</div></div>
    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm"><div class="text-xs text-gray-500">Pendapatan Hari Ini</div><div class="text-2xl font-semibold text-primary">Rp {{ number_format($dashboard['revenueToday'], 0, ',', '.') }}</div></div>
    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm"><div class="text-xs text-gray-500">Stok Kritis</div><div class="text-2xl font-semibold text-primary">{{ $dashboard['criticalIngredients'] }}</div></div>
</div>
<div class="mt-5 bg-white rounded-2xl border border-primary-100 p-5 shadow-sm"><h2 class="font-display font-semibold text-primary mb-3">Menu Terlaris</h2><ol class="space-y-2 text-sm">@foreach ($dashboard['topMenus'] as $menu)<li class="flex justify-between"><span>{{ $loop->iteration }}. {{ $menu->menu?->name }}</span><b>{{ $menu->total_qty }}×</b></li>@endforeach</ol></div>
@endsection
