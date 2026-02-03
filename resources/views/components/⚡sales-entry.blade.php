<?php

use App\Models\Sale;
use Livewire\Volt\Component;
use Livewire\Attributes\Validate;

new class extends Component {
    #[Validate('required|date')]
    public string $sale_date = '';

    #[Validate('required|string|min:2|max:255')]
    public string $customer_name = '';

    #[Validate('required|string|regex:/^[0-9]{10}$/')]
    public string $customer_mobile = '';

    #[Validate('required|string|min:2|max:255')]
    public string $product_name = '';

    #[Validate('required|integer|min:1')]
    public int $quantity = 1;

    #[Validate('required|numeric|min:0.01')]
    public float $total_amount = 0;

    #[Validate('nullable|string|max:500')]
    public string $remarks = '';

    public bool $showSuccess = false;
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->sale_date = now()->format('Y-m-d');
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingId) {
            $sale = Sale::find($this->editingId);
            $sale->update($validated);
            $this->editingId = null;
        } else {
            Sale::create($validated);
        }

        $this->reset(['customer_name', 'customer_mobile', 'product_name', 'quantity', 'total_amount', 'remarks']);
        $this->sale_date = now()->format('Y-m-d');
        $this->showSuccess = true;

        $this->dispatch('sale-saved');
    }

    public function edit(int $id): void
    {
        $sale = Sale::find($id);
        if ($sale) {
            $this->editingId = $sale->id;
            $this->sale_date = $sale->sale_date->format('Y-m-d');
            $this->customer_name = $sale->customer_name;
            $this->customer_mobile = $sale->customer_mobile;
            $this->product_name = $sale->product_name;
            $this->quantity = $sale->quantity;
            $this->total_amount = (float) $sale->total_amount;
            $this->remarks = $sale->remarks ?? '';
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['customer_name', 'customer_mobile', 'product_name', 'quantity', 'total_amount', 'remarks']);
        $this->sale_date = now()->format('Y-m-d');
    }

    public function delete(int $id): void
    {
        Sale::destroy($id);
        $this->dispatch('sale-saved');
    }

    public function dismissSuccess(): void
    {
        $this->showSuccess = false;
    }

    public function with(): array
    {
        return [
            'sales' => Sale::orderBy('sale_date', 'desc')->orderBy('created_at', 'desc')->take(50)->get(),
            'todayTotal' => Sale::whereDate('sale_date', today())->sum('total_amount'),
            'todayCount' => Sale::whereDate('sale_date', today())->count(),
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
    <!-- Header -->
    <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-900/80 border-b border-white/10">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center shadow-lg shadow-violet-500/25">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Cell Ulagam</h1>
                        <p class="text-xs text-slate-400">Sales Manager</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Today's Sales</p>
                    <p class="text-lg font-bold text-emerald-400">₹{{ number_format($todayTotal, 2) }}</p>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 pb-24">
        <!-- Success Toast -->
        @if($showSuccess)
            <div x-data="{ show: true }" x-show="show"
                x-init="setTimeout(() => { show = false; $wire.dismissSuccess() }, 3000)"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" class="fixed top-20 right-4 left-4 md:left-auto md:w-80 z-50">
                <div
                    class="bg-emerald-500/90 backdrop-blur-xl text-white px-4 py-3 rounded-xl shadow-2xl shadow-emerald-500/25 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="font-medium">Sale saved successfully!</p>
                </div>
            </div>
        @endif

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Today's Revenue</p>
                        <p class="text-lg font-bold text-white">₹{{ number_format($todayTotal, 0) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Today's Orders</p>
                        <p class="text-lg font-bold text-white">{{ $todayCount }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Form Card -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ $editingId ? 'Edit Sale' : 'New Sale' }}
                </h2>
                @if($editingId)
                    <button wire:click="cancelEdit" class="text-sm text-slate-400 hover:text-white transition-colors">
                        Cancel Edit
                    </button>
                @endif
            </div>

            <form wire:submit="save" class="space-y-4">
                <!-- Date -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Sale Date</label>
                    <input type="date" wire:model="sale_date"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    @error('sale_date') <span class="text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Customer Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Customer Name</label>
                        <input type="text" wire:model="customer_name" placeholder="Enter customer name"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                        @error('customer_name') <span class="text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Mobile Number</label>
                        <input type="tel" wire:model="customer_mobile" placeholder="10-digit mobile" maxlength="10"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                        @error('customer_mobile') <span class="text-red-400 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Product Info -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Product Name</label>
                    <input type="text" wire:model="product_name" placeholder="e.g., iPhone 15 Pro Max"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    @error('product_name') <span class="text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Quantity & Amount -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Quantity</label>
                        <input type="number" wire:model="quantity" min="1"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                        @error('quantity') <span class="text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Total Amount (₹)</label>
                        <input type="number" wire:model="total_amount" step="0.01" min="0" placeholder="0.00"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                        @error('total_amount') <span class="text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Remarks -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Remarks (Optional)</label>
                    <textarea wire:model="remarks" rows="2" placeholder="Any additional notes..."
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all resize-none"></textarea>
                    @error('remarks') <span class="text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-500 hover:to-fuchsia-500 text-white font-semibold rounded-xl shadow-lg shadow-violet-500/25 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading.remove
                        wire:target="save">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading
                        wire:target="save">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Sale' : 'Save Sale' }}</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </form>
        </div>

        <!-- Recent Sales -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Recent Sales
            </h2>

            @if($sales->isEmpty())
                <div class="text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center">
                        <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <p class="text-slate-400">No sales recorded yet</p>
                    <p class="text-sm text-slate-500 mt-1">Start by adding your first sale above</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($sales as $sale)
                        <div class="bg-white/5 border border-white/10 rounded-2xl p-4 hover:bg-white/10 transition-all group"
                            wire:key="sale-{{ $sale->id }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="font-medium text-white truncate">{{ $sale->product_name }}</h3>
                                        @if($sale->quantity > 1)
                                            <span class="px-2 py-0.5 bg-violet-500/20 text-violet-300 text-xs rounded-full">
                                                x{{ $sale->quantity }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-slate-400">{{ $sale->customer_name }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span class="text-xs text-slate-500">{{ $sale->customer_mobile }}</span>
                                        <span class="text-slate-600">•</span>
                                        <span class="text-xs text-slate-500">{{ $sale->sale_date->format('M d, Y') }}</span>
                                    </div>
                                    @if($sale->remarks)
                                        <p class="text-xs text-slate-500 mt-2 italic">{{ $sale->remarks }}</p>
                                    @endif
                                </div>
                                <div class="text-right flex flex-col items-end gap-2">
                                    <p class="text-lg font-bold text-emerald-400">₹{{ number_format($sale->total_amount, 0) }}
                                    </p>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button wire:click="edit({{ $sale->id }})"
                                            class="p-2 rounded-lg bg-white/5 hover:bg-violet-500/20 text-slate-400 hover:text-violet-400 transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $sale->id }})"
                                            wire:confirm="Are you sure you want to delete this sale?"
                                            class="p-2 rounded-lg bg-white/5 hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>

    <!-- Bottom Navigation (Mobile) -->
    <nav
        class="fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur-xl border-t border-white/10 px-6 py-3 md:hidden">
        <div class="flex items-center justify-around">
            <button class="flex flex-col items-center gap-1 text-violet-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs">Home</span>
            </button>
            <button class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-xs">Reports</span>
            </button>
            <button class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="text-xs">Settings</span>
            </button>
        </div>
    </nav>
</div>