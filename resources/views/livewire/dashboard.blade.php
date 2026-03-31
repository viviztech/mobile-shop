<?php

use App\Models\Sale;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        $userId = Auth::id();
        $today = today();
        
        // Today's Stats
        $todaySales = Sale::where('user_id', $userId)
            ->whereDate('sale_date', $today)
            ->get();
            
        $todayTotal = $todaySales->sum('total_amount');
        $todayCount = $todaySales->count();

        // Weekly Trends (Last 7 Days)
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $weeklySales = Sale::where('user_id', $userId)
            ->where('sale_date', '>=', $sevenDaysAgo)
            ->get();

        $dailyData = $weeklySales->groupBy(fn($sale) => \Carbon\Carbon::parse($sale->sale_date)->format('M d'))
            ->map(fn($group) => [
                'date' => \Carbon\Carbon::parse($group->first()->sale_date)->format('M d'),
                'revenue' => $group->sum('total_amount'),
                'orders' => $group->count(),
            ])
            ->sortBy(fn($val, $key) => \Carbon\Carbon::parse($key))
            ->values();

        // Active Session
        $activeSession = \App\Models\CashSession::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        // Recent Activity (Last 5 Sales)
        $recentSales = Sale::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return [
            'todayTotal' => $todayTotal,
            'todayCount' => $todayCount,
            'dailyData' => $dailyData,
            'activeSession' => $activeSession,
            'recentSales' => $recentSales,
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
    <!-- Header -->
    <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-900/80 border-b border-white/10 pt-safe">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center shadow-lg shadow-violet-500/25">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Dashboard</h1>
                        <p class="text-xs text-slate-400">Welcome back, {{ Auth::user()->name }}</p>
                    </div>
                </div>
                <livewire:layout.header-navigation />
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 pb-24">
        <!-- Quick Stats -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Today's Revenue</p>
                    <p class="text-xl font-bold text-white">₹{{ number_format($todayTotal, 0) }}</p>
                </div>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-violet-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Today's Orders</p>
                    <p class="text-xl font-bold text-white">{{ $todayCount }}</p>
                </div>
            </div>
        </div>

        <!-- Session Status card -->
        <div class="mb-6">
            @if($activeSession)
                <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        <div>
                            <p class="text-sm font-bold text-white uppercase tracking-wider">Active Session</p>
                            <p class="text-xs text-emerald-400">Started at {{ $activeSession->opened_at->format('H:i A') }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Opening Balance</p>
                        <p class="text-white font-mono font-bold">₹{{ number_format($activeSession->opening_balance, 0) }}</p>
                    </div>
                </div>
            @else
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                        <div>
                            <p class="text-sm font-bold text-white uppercase tracking-wider">No Active Session</p>
                            <p class="text-xs text-amber-400">Please start a session to record sales.</p>
                        </div>
                    </div>
                    <a href="{{ route('sales') }}" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-900 rounded-xl text-xs font-bold transition-all">
                        START NOW
                    </a>
                </div>
            @endif
        </div>

        <!-- Revenue Trend Chart -->
        @if($dailyData->isNotEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                    Weekly Revenue Trend
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
                            <span class="text-xs text-slate-500 w-12 text-right">{{ $day['orders'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Recent Activity -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Recent Sales
                </h2>
                <a href="{{ route('sales') }}" class="text-xs text-violet-400 hover:text-violet-300 font-bold tracking-wider underline">VIEW ALL</a>
            </div>
            <div class="space-y-4">
                @foreach($recentSales as $sale)
                    <div class="flex items-center gap-4 p-3 bg-white/5 rounded-xl border border-white/5">
                        <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center font-bold text-slate-400 text-xs">
                            {{ Carbon::parse($sale->sale_date)->format('d') }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-white truncate text-sm">{{ $sale->product_name }}</h3>
                            <p class="text-[10px] text-slate-500">{{ $sale->category }} • {{ Carbon::parse($sale->created_at)->diffForHumans() }}</p>
                        </div>
                        <p class="text-emerald-400 font-bold text-sm">₹{{ number_format($sale->total_amount, 0) }}</p>
                    </div>
                @endforeach
                @if($recentSales->isEmpty())
                    <p class="text-center text-slate-500 text-sm py-4 italic">No recent sales recorded yet.</p>
                @endif
            </div>
        </div>
    </main>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur-xl border-t border-white/10 px-4 py-3 pb-safe md:hidden">
        <div class="flex items-center justify-around">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 text-violet-400">
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
            <a href="{{ route('reports') }}" class="flex flex-col items-center gap-1 text-slate-500">
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
