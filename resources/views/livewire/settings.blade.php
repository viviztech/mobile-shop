<?php

use App\Models\Sale;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public bool $showDeleteConfirm = false;
    public bool $showExportSuccess = false;

    public function exportCsv()
    {
        $sales = Sale::orderBy('sale_date', 'desc')->get();

        $csv = "Date,Customer Name,Mobile,Product,Quantity,Amount,Remarks\n";
        foreach ($sales as $sale) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%d,%.2f,%s\n",
                $sale->sale_date->format('Y-m-d'),
                str_replace(',', ' ', $sale->customer_name),
                $sale->customer_mobile,
                str_replace(',', ' ', $sale->product_name),
                $sale->quantity,
                $sale->total_amount,
                str_replace(',', ' ', $sale->remarks ?? '')
            );
        }

        $filename = 'cell_ulagam_sales_' . now()->format('Y-m-d_H-i') . '.csv';
        $path = storage_path('app/public/' . $filename);

        if (!file_exists(storage_path('app/public'))) {
            mkdir(storage_path('app/public'), 0755, true);
        }

        file_put_contents($path, $csv);

        $this->showExportSuccess = true;

        return response()->download($path, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }

    public function clearAllData(): void
    {
        Sale::truncate();
        $this->showDeleteConfirm = false;
        $this->dispatch('data-cleared');
    }

    public function with(): array
    {
        return [
            'totalSales' => Sale::count(),
            'totalRevenue' => Sale::sum('total_amount'),
            'dbSize' => $this->getDatabaseSize(),
        ];
    }

    private function getDatabaseSize(): string
    {
        $path = database_path('database.sqlite');
        if (file_exists($path)) {
            $bytes = filesize($path);
            if ($bytes >= 1048576) {
                return round($bytes / 1048576, 2) . ' MB';
            }
            return round($bytes / 1024, 2) . ' KB';
        }
        return '0 KB';
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
                        <h1 class="text-xl font-bold text-white">Settings</h1>
                        <p class="text-xs text-slate-400">App Configuration</p>
                    </div>
                </div>
                <livewire:layout.header-navigation />
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 pb-24">
        <!-- Export Success Toast -->
        @if($showExportSuccess)
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => { show = false }, 3000)" x-transition
                class="fixed top-20 right-4 left-4 md:left-auto md:w-80 z-50">
                <div
                    class="bg-emerald-500/90 backdrop-blur-xl text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="font-medium">Data exported successfully!</p>
                </div>
            </div>
        @endif

        <!-- App Info -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
            <div class="flex items-center gap-4 mb-6">
                <div
                    class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center shadow-lg shadow-violet-500/25">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">Cell Ulagam</h2>
                    <p class="text-slate-400">Sales Manager v1.0.0</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-4 bg-white/5 rounded-xl">
                    <p class="text-2xl font-bold text-white">{{ $totalSales }}</p>
                    <p class="text-xs text-slate-400">Total Sales</p>
                </div>
                <div class="text-center p-4 bg-white/5 rounded-xl">
                    <p class="text-2xl font-bold text-emerald-400">₹{{ number_format($totalRevenue / 1000, 1) }}K</p>
                    <p class="text-xs text-slate-400">Total Revenue</p>
                </div>
                <div class="text-center p-4 bg-white/5 rounded-xl">
                    <p class="text-2xl font-bold text-violet-400">{{ $dbSize }}</p>
                    <p class="text-xs text-slate-400">Database</p>
                </div>
            </div>
        </div>

        <!-- Data Management -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden mb-6">
            <div class="p-4 border-b border-white/10">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                    Data Management
                </h2>
            </div>

            <div class="divide-y divide-white/10">
                <!-- Export Data -->
                <button wire:click="exportCsv"
                    class="w-full flex items-center justify-between p-4 hover:bg-white/5 transition-all group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-white">Export to CSV</p>
                            <p class="text-xs text-slate-400">Download all sales data</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-slate-500 group-hover:text-white transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Clear Data -->
                <div x-data="{ showConfirm: false }">
                    <button @click="showConfirm = true"
                        class="w-full flex items-center justify-between p-4 hover:bg-white/5 transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="font-medium text-red-400">Clear All Data</p>
                                <p class="text-xs text-slate-400">Delete all sales records permanently</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-500 group-hover:text-white transition-colors" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <!-- Confirm Modal -->
                    <div x-show="showConfirm" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                        @click.self="showConfirm = false">
                        <div x-show="showConfirm" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            class="bg-slate-800 border border-white/10 rounded-2xl p-6 max-w-sm w-full">
                            <div
                                class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/20 flex items-center justify-center">
                                <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white text-center mb-2">Delete All Data?</h3>
                            <p class="text-slate-400 text-center mb-6">This will permanently delete all
                                {{ $totalSales }} sales records. This action cannot be undone.
                            </p>
                            <div class="flex gap-3">
                                <button @click="showConfirm = false"
                                    class="flex-1 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl font-medium transition-all">
                                    Cancel
                                </button>
                                <button wire:click="clearAllData" @click="showConfirm = false"
                                    class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-xl font-medium transition-all">
                                    Delete All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- About -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden">
            <div class="p-4 border-b border-white/10">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    About
                </h2>
            </div>

            <div class="p-4 space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-slate-400">Version</span>
                    <span class="text-white">1.0.0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400">Framework</span>
                    <span class="text-white">Laravel 12 + Livewire</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400">Database</span>
                    <span class="text-white">SQLite</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400">Mobile Ready</span>
                    <span class="text-emerald-400">NativePHP Enabled</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-slate-500 text-sm">Made with ❤️ for Cell Ulagam</p>
            <p class="text-slate-600 text-xs mt-1">© {{ date('Y') }} All rights reserved</p>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav
        class="fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur-xl border-t border-white/10 px-6 py-3 pb-safe md:hidden">
        <div class="flex items-center justify-around">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="{{ route('reports') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-xs">Reports</span>
            </a>
            <a href="{{ route('settings') }}" class="flex flex-col items-center gap-1 text-violet-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="text-xs">Settings</span>
            </a>
        </div>
    </nav>
</div>