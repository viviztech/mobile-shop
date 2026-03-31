<?php

use App\Models\Sale;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public string $period = 'today';
    public string $categoryFilter = 'all';

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    public function setCategoryFilter(string $category): void
    {
        $this->categoryFilter = $category;
    }

    public function with(): array
    {
        $userId = Auth::id();
        $query = Sale::where('user_id', $userId);

        $startDate = match ($this->period) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => today(),
        };

        $baseQuery = $query->where('sale_date', '>=', $startDate);

        // Apply category filter
        if ($this->categoryFilter !== 'all') {
            $baseQuery = $baseQuery->where('category', $this->categoryFilter);
        }

        $sales = $baseQuery->clone()
            ->orderBy('sale_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalRevenue = $sales->sum('total_amount');
        $totalOrders = $sales->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Category breakdown
        $categoryData = $sales->groupBy('category')
            ->map(fn($group) => [
                'category' => $group->first()->category ?? 'Other',
                'count' => $group->count(),
                'revenue' => $group->sum('total_amount'),
            ])
            ->sortByDesc('revenue')
            ->values();

        // Brand breakdown (for MOBILE category)
        $brandData = $sales->where('category', 'MOBILE')
            ->groupBy('brand')
            ->map(fn($group) => [
                'brand' => $group->first()->brand ?? 'Unknown',
                'count' => $group->count(),
                'revenue' => $group->sum('total_amount'),
            ])
            ->sortByDesc('revenue')
            ->take(10)
            ->values();

        // Network type breakdown
        $networkData = $sales->where('category', 'MOBILE')
            ->whereNotNull('network_type')
            ->groupBy('network_type')
            ->map(fn($group) => [
                'type' => $group->first()->network_type,
                'count' => $group->count(),
                'revenue' => $group->sum('total_amount'),
            ])
            ->values();

        // Gift breakdown
        $giftData = $sales->whereNotNull('gift')
            ->where('gift', '!=', 'NO GIFT')
            ->groupBy('gift')
            ->map(fn($group) => [
                'gift' => $group->first()->gift,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values();

        // Top products
        $topProducts = $sales->groupBy('product_name')
            ->map(fn($group) => [
                'name' => $group->first()->product_name,
                'brand' => $group->first()->brand,
                'category' => $group->first()->category,
                'variant' => $group->first()->variant,
                'count' => $group->count(),
                'revenue' => $group->sum('total_amount'),
            ])
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        // Daily breakdown for chart
        $dailyData = $sales->groupBy(fn($sale) => \Carbon\Carbon::parse($sale->sale_date)->format('M d'))
            ->map(fn($group) => [
                'date' => \Carbon\Carbon::parse($group->first()->sale_date)->format('M d'),
                'revenue' => $group->sum('total_amount'),
                'orders' => $group->count(),
            ])
            ->take(7)
            ->values();

        // Service breakdown
        $serviceData = $sales->where('category', 'SERVICE')
            ->whereNotNull('service_product')
            ->groupBy('service_product')
            ->map(fn($group) => [
                'service' => $group->first()->service_product,
                'count' => $group->count(),
                'revenue' => $group->sum('total_amount'),
            ])
            ->sortByDesc('revenue')
            ->values();

        // Cash Sessions
        $sessions = \App\Models\CashSession::where('user_id', $userId)
            ->where('opened_at', '>=', $startDate)
            ->orderBy('opened_at', 'desc')
            ->get();

        return [
            'sales' => $sales,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'avgOrderValue' => $avgOrderValue,
            'categoryData' => $categoryData,
            'brandData' => $brandData,
            'networkData' => $networkData,
            'giftData' => $giftData,
            'topProducts' => $topProducts,
            'dailyData' => $dailyData,
            'serviceData' => $serviceData,
            'categories' => Sale::categories(),
            'sessions' => $sessions,
        ];

    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
    <!-- Header -->
    <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-900/80 border-b border-white/10 pt-safe">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard') }}"
                        class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Reports</h1>
                        <p class="text-xs text-slate-400">Business Insight</p>
                    </div>
                </div>
                <livewire:layout.header-navigation />
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 pb-24">
        <!-- Period Selector -->
        <div class="flex gap-2 mb-4 overflow-x-auto pb-2">
            @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                    class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all {{ $period === $key ? 'bg-violet-600 text-white shadow-lg shadow-violet-500/25' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <!-- Category Filter -->
        <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
            <button wire:click="setCategoryFilter('all')"
                class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ $categoryFilter === 'all' ? 'bg-white/20 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                All
            </button>
            @foreach($categories as $cat)
                <button wire:click="setCategoryFilter('{{ $cat }}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ $categoryFilter === $cat ? 'bg-white/20 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400 mb-1">Revenue</p>
                <p class="text-lg font-bold text-white">₹{{ number_format($totalRevenue, 0) }}</p>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-violet-500/20 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400 mb-1">Orders</p>
                <p class="text-lg font-bold text-white">{{ $totalOrders }}</p>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400 mb-1">Avg. Order</p>
                <p class="text-lg font-bold text-white">₹{{ number_format($avgOrderValue, 0) }}</p>
            </div>
        </div>

        <!-- Cash Session History (New) -->
        @if($sessions->isNotEmpty() && $categoryFilter === 'all')
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Cash Sessions
                    </h2>
                </div>
                <div class="space-y-4">
                    @foreach($sessions as $session)
                        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4 transition-all hover:bg-white/10">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $session->status === 'open' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-500' }}"></span>
                                    <p class="text-sm font-bold text-white uppercase tracking-wider">
                                        {{ $session->status === 'open' ? 'Current Session' : 'Ended Session' }}
                                    </p>
                                </div>
                                <p class="text-xs text-slate-400 font-mono italic">
                                    {{ $session->opened_at->format('M d, H:i') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Opening</p>
                                    <p class="text-white font-mono text-sm">₹{{ number_format($session->opening_balance, 0) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Sales</p>
                                    <p class="text-emerald-400 font-mono text-sm">+ ₹{{ number_format($session->sales->sum('total_amount'), 0) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Expected</p>
                                    <p class="text-violet-400 font-mono font-bold text-sm">₹{{ number_format($session->expected_balance ?: $session->calculateExpectedBalance(), 0) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Actual</p>
                                    @if($session->status === 'closed')
                                        <p class="text-amber-400 font-mono font-bold text-sm">₹{{ number_format($session->closing_balance, 0) }}</p>
                                    @else
                                        <p class="text-slate-600 font-mono italic text-[10px]">Pending</p>
                                    @endif
                                </div>
                            </div>
                            @if($session->status === 'closed' && ($session->closing_balance != $session->expected_balance))
                                @php $diff = $session->closing_balance - $session->expected_balance; @endphp
                                <div class="mt-3 pt-3 border-t border-white/5 flex items-center gap-2">
                                    <svg class="w-3 h-3 {{ $diff > 0 ? 'text-emerald-400' : 'text-red-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <p class="text-[10px] {{ $diff > 0 ? 'text-emerald-400' : 'text-red-400' }} font-bold">
                                        Discrepancy: ₹{{ number_format($diff, 2) }} ({{ $diff > 0 ? 'Surplus' : 'Shortage' }})
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif


        <!-- Category Breakdown -->
        @if($categoryData->isNotEmpty() && $categoryFilter === 'all')
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                    Sales by Category
                </h2>
                <div class="grid grid-cols-3 gap-3">
                    @foreach($categoryData as $cat)
                        @php
                            $categoryColors = [
                                'MOBILE' => ['bg' => 'bg-violet-500/20', 'text' => 'text-violet-400', 'border' => 'border-violet-500/30'],
                                'ACCESSORY' => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-400', 'border' => 'border-amber-500/30'],
                                'SERVICE' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'border' => 'border-emerald-500/30'],
                            ];
                            $colors = $categoryColors[$cat['category']] ?? ['bg' => 'bg-slate-500/20', 'text' => 'text-slate-400', 'border' => 'border-slate-500/30'];
                        @endphp
                        <div class="p-4 rounded-xl {{ $colors['bg'] }} border {{ $colors['border'] }}">
                            <p class="text-xs {{ $colors['text'] }} mb-1">{{ $cat['category'] }}</p>
                            <p class="text-lg font-bold text-white">{{ $cat['count'] }}</p>
                            <p class="text-xs text-slate-400">₹{{ number_format($cat['revenue'], 0) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Brand Analytics (for MOBILE) -->
        @if($brandData->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Sales by Brand
                </h2>
                <div class="space-y-3">
                    @php $maxBrandRevenue = $brandData->max('revenue') ?: 1; @endphp
                    @foreach($brandData as $brand)
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-300 w-20 truncate">{{ $brand['brand'] }}</span>
                            <div class="flex-1 h-6 bg-white/5 rounded-lg overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-violet-600 to-fuchsia-600 rounded-lg flex items-center justify-end pr-2 transition-all"
                                    style="width: {{ max(($brand['revenue'] / $maxBrandRevenue) * 100, 10) }}%">
                                    <span class="text-xs font-medium text-white">{{ $brand['count'] }}</span>
                                </div>
                            </div>
                            <span
                                class="text-xs text-emerald-400 w-20 text-right">₹{{ number_format($brand['revenue'], 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Network Type Analytics -->
        @if($networkData->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                    Network Type (4G vs 5G)
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($networkData as $network)
                        <div
                            class="p-4 rounded-xl text-center {{ $network['type'] === '5G' ? 'bg-emerald-500/20 border border-emerald-500/30' : 'bg-blue-500/20 border border-blue-500/30' }}">
                            <p
                                class="text-2xl font-bold {{ $network['type'] === '5G' ? 'text-emerald-400' : 'text-blue-400' }}">
                                {{ $network['type'] }}</p>
                            <p class="text-xl font-bold text-white mt-1">{{ $network['count'] }} units</p>
                            <p class="text-sm text-slate-400">₹{{ number_format($network['revenue'], 0) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Service Analytics -->
        @if($serviceData->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Service Breakdown
                </h2>
                <div class="space-y-2">
                    @foreach($serviceData as $service)
                        <div class="flex items-center justify-between p-3 bg-white/5 rounded-xl">
                            <div>
                                <p class="text-sm font-medium text-white">{{ $service['service'] }}</p>
                                <p class="text-xs text-slate-400">{{ $service['count'] }} services</p>
                            </div>
                            <p class="text-emerald-400 font-bold">₹{{ number_format($service['revenue'], 0) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Gift Distribution -->
        @if($giftData->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <span class="text-xl">🎁</span>
                    Gift Distribution
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($giftData as $gift)
                        <div class="px-3 py-2 bg-fuchsia-500/20 border border-fuchsia-500/30 rounded-lg">
                            <span class="text-sm text-fuchsia-300">{{ $gift['gift'] }}</span>
                            <span
                                class="ml-2 px-2 py-0.5 bg-white/10 rounded-full text-xs text-white">{{ $gift['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Daily Breakdown Chart -->
        @if($dailyData->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                    Revenue Trend
                </h2>
                <div class="space-y-3">
                    @php $maxRevenue = $dailyData->max('revenue') ?: 1; @endphp
                    @foreach($dailyData as $day)
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-slate-400 w-16">{{ $day['date'] }}</span>
                            <div class="flex-1 h-8 bg-white/5 rounded-lg overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-violet-600 to-fuchsia-600 rounded-lg flex items-center justify-end pr-3 transition-all duration-500"
                                    style="width: {{ max(($day['revenue'] / $maxRevenue) * 100, 5) }}%">
                                    <span class="text-xs font-medium text-white">₹{{ number_format($day['revenue'], 0) }}</span>
                                </div>
                            </div>
                            <span class="text-xs text-slate-500 w-12 text-right">{{ $day['orders'] }} orders</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Top Products -->
        @if($topProducts->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Top Products
                </h2>
                <div class="space-y-3">
                    @foreach($topProducts as $index => $product)
                        <div class="flex items-center gap-4 p-3 bg-white/5 rounded-xl">
                            <span
                                class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center text-white font-bold text-sm">
                                {{ $index + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-medium text-white truncate">{{ $product['name'] }}</h3>
                                <div class="flex items-center gap-2 text-xs text-slate-400">
                                    @if($product['brand'])
                                        <span
                                            class="px-1.5 py-0.5 bg-violet-500/20 text-violet-300 rounded">{{ $product['brand'] }}</span>
                                    @endif
                                    @if($product['variant'])
                                        <span>{{ $product['variant'] }}</span>
                                    @endif
                                    <span>• {{ $product['count'] }} sold</span>
                                </div>
                            </div>
                            <p class="text-emerald-400 font-bold">₹{{ number_format($product['revenue'], 0) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Empty State -->
        @if($sales->isEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-12 text-center">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center">
                    <svg class="w-10 h-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <p class="text-slate-400 text-lg mb-2">No data for this period</p>
                <p class="text-sm text-slate-500">Try selecting a different time range or add some sales first</p>
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center gap-2 mt-6 px-6 py-3 bg-violet-600 hover:bg-violet-500 text-white rounded-xl font-medium transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Your First Sale
                </a>
            </div>
        @endif
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur-xl border-t border-white/10 px-4 py-3 pb-safe md:hidden">
        <div class="flex items-center justify-around">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="{{ route('sales') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-xs">Sales</span>
            </a>
            <a href="{{ route('products') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-xs">Products</span>
            </a>
            <a href="{{ route('reports') }}" class="flex flex-col items-center gap-1 text-violet-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-xs">Reports</span>
            </a>
            <a href="{{ route('settings') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="text-xs">Settings</span>
            </a>
        </div>
    </nav>
</div>