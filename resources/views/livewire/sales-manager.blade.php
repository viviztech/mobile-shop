<?php

use App\Models\Sale;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    // Form Fields matching Google Form
    #[Validate('required|date')]
    public string $sale_date = '';

    #[Validate('required|string|in:MOBILE,ACCESSORY,SERVICE')]
    public string $category = 'MOBILE';

    #[Validate('nullable|string')]
    public string $brand = '';

    #[Validate('nullable|string|in:4G,5G')]
    public string $network_type = '';

    #[Validate('required|string|min:2|max:255')]
    public string $product_name = '';

    #[Validate('nullable|string')]
    public string $variant = '';

    #[Validate('nullable|string')]
    public string $service_product = '';

    #[Validate('required|numeric|min:0')]
    public float $total_amount = 0;

    #[Validate('nullable|string')]
    public string $gift = '';

    #[Validate('nullable|string|max:500')]
    public string $remarks = '';

    // Legacy fields (optional, for backward compatibility)
    public string $customer_name = '';
    public string $customer_mobile = '';
    public int $quantity = 1;

    // UI states
    public bool $showSuccess = false;
    public ?int $editingId = null;
    public int $currentStep = 1;

    // Product Search State
    public string $searchQuery = '';
    public array $searchResults = [];
    public ?int $selectedProductId = null;
    public bool $showSearchResults = false;

    public function updatedSearchQuery(): void
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = [];
            $this->showSearchResults = false;
            return;
        }

        $this->searchResults = \App\Models\Product::where('user_id', Auth::id())
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                    ->orWhere('brand', 'like', '%' . $this->searchQuery . '%')
                    ->orWhere('sku', 'like', '%' . $this->searchQuery . '%');
            })
            ->take(5)
            ->get()
            ->toArray();

        $this->showSearchResults = true;
    }

    public function selectProduct(int $id): void
    {
        $product = \App\Models\Product::find($id);
        if ($product) {
            $this->selectedProductId = $product->id;
            $this->category = $product->category;
            $this->brand = $product->brand ?? '';
            $this->network_type = $product->network_type ?? '';
            $this->product_name = $product->name; // Main model name
            $this->variant = $product->variant ?? '';
            $this->service_product = $product->service_type ?? '';
            $this->total_amount = (float) $product->selling_price;

            // Clear search
            $this->searchQuery = '';
            $this->showSearchResults = false;
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'user_id' => Auth::id(),
            'product_id' => $this->selectedProductId,
            'sale_date' => $this->sale_date,
            'category' => $this->category,
            'brand' => $this->brand ?: null,
            'network_type' => $this->network_type ?: null,
            'product_name' => $this->product_name,
            'variant' => $this->variant ?: null,
            'service_product' => $this->service_product ?: null,
            'customer_name' => $this->customer_name ?: Auth::user()->name,
            'customer_mobile' => $this->customer_mobile ?: '0000000000',
            'quantity' => $this->quantity,
            'total_amount' => $this->total_amount,
            'gift' => $this->gift ?: null,
            'remarks' => $this->remarks ?: null,
        ];

        if ($this->editingId) {
            $sale = Sale::find($this->editingId);
            // Handle stock adjustment if product changed? 
            // For simplicity in MVP, we won't auto-adjust stock on edit yet, only on create/delete.
            $sale->update($data);
            $this->editingId = null;
        } else {
            $sale = Sale::create($data);

            // Deduct stock
            if ($this->selectedProductId) {
                $product = \App\Models\Product::find($this->selectedProductId);
                if ($product) {
                    $product->stock_quantity = max(0, $product->stock_quantity - $this->quantity);
                    $product->save();
                }
            }
        }

        $this->resetForm();
        $this->showSuccess = true;
        // Dispatch event with stock check
        $this->dispatch('sale-saved');
    }

    public function resetForm(): void
    {
        $this->reset([
            'category',
            'brand',
            'network_type',
            'product_name',
            'variant',
            'service_product',
            'total_amount',
            'gift',
            'remarks',
            'customer_name',
            'customer_mobile',
            'selectedProductId',
            'searchQuery',
            'searchResults'
        ]);
        $this->category = 'MOBILE';
        $this->sale_date = now()->format('Y-m-d');
        $this->quantity = 1;
        $this->currentStep = 1;
    }

    public function mount(): void
    {
        $this->sale_date = now()->format('Y-m-d');
    }

    public function edit(int $id): void
    {
        $sale = Sale::find($id);
        if ($sale) {
            $this->editingId = $sale->id;
            $this->selectedProductId = $sale->product_id;
            $this->sale_date = \Carbon\Carbon::parse($sale->sale_date)->format('Y-m-d');
            $this->category = $sale->category ?? 'MOBILE';
            $this->brand = $sale->brand ?? '';
            $this->network_type = $sale->network_type ?? '';
            $this->product_name = $sale->product_name;
            $this->variant = $sale->variant ?? '';
            $this->service_product = $sale->service_product ?? '';
            $this->total_amount = (float) $sale->total_amount;
            $this->gift = $sale->gift ?? '';
            $this->remarks = $sale->remarks ?? '';
            $this->customer_name = $sale->customer_name ?? '';
            $this->customer_mobile = $sale->customer_mobile ?? '';
            $this->quantity = $sale->quantity ?? 1;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $sale = Sale::where('id', $id)->where('user_id', Auth::id())->first();
        if ($sale) {
            // Restore stock
            if ($sale->product_id) {
                $product = \App\Models\Product::find($sale->product_id);
                if ($product) {
                    $product->stock_quantity += $sale->quantity;
                    $product->save();
                }
            }
            $sale->delete();
        }
        $this->dispatch('sale-saved');
    }

    public function dismissSuccess(): void
    {
        $this->showSuccess = false;
    }

    public function nextStep(): void
    {
        $this->currentStep = min($this->currentStep + 1, 3);
    }

    public function prevStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
    }

    public function with(): array
    {
        $userId = Auth::id();

        return [
            'sales' => Sale::where('user_id', $userId)
                ->orderBy('sale_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get(),
            'todayTotal' => Sale::where('user_id', $userId)
                ->whereDate('sale_date', today())
                ->sum('total_amount'),
            'todayCount' => Sale::where('user_id', $userId)
                ->whereDate('sale_date', today())
                ->count(),
            'categories' => Sale::categories(),
            'brands' => Sale::brands(),
            'networkTypes' => Sale::networkTypes(),
            'variants' => Sale::variants(),
            'serviceProducts' => Sale::serviceProducts(),
            'gifts' => Sale::gifts(),
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
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                x-init="setTimeout(() => { show = false; $wire.dismissSuccess() }, 3000)" x-transition
                class="fixed top-20 right-4 left-4 md:left-auto md:w-80 z-50">
                <div
                    class="bg-emerald-500/90 backdrop-blur-xl text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="font-medium">Sale recorded successfully!</p>
                </div>
            </div>
        @endif

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Today's Orders</p>
                    <p class="text-xl font-bold text-white">{{ $todayCount }}</p>
                </div>
            </div>
        </div>

        <!-- Sales Form Card -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ $editingId ? 'Edit Sale' : 'New Sale' }}
                </h2>
                @if($editingId)
                    <button wire:click="cancelEdit" class="text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>

            <form wire:submit="save" class="space-y-6">
                <!-- Step 1: Date & Category -->
                <div class="space-y-4">
                    <!-- Date -->
                    <div>
                        <label for="sale_date" class="block text-sm font-medium text-slate-300 mb-2">DATE *</label>
                        <input wire:model="sale_date" type="date" id="sale_date"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                        @error('sale_date') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Search Product (Quick Fill) -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-slate-300 mb-2">QUICK SEARCH (Optional)</label>
                        <div class="relative">
                            <input wire:model.live.debounce.300ms="searchQuery" type="text"
                                placeholder="Search by Name, Brand or SKU..."
                                class="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                        </div>

                        <!-- Search Results Dropdown -->
                        @if($showSearchResults && count($searchResults) > 0)
                            <div
                                class="absolute z-10 w-full mt-1 bg-slate-800 border border-white/10 rounded-xl shadow-2xl max-h-60 overflow-y-auto">
                                @foreach($searchResults as $result)
                                    <button type="button" wire:click="selectProduct({{ $result['id'] }})"
                                        class="w-full text-left px-4 py-3 hover:bg-white/5 border-b border-white/5 last:border-0 transition-colors flex items-center justify-between group">
                                        <div>
                                            <p class="text-white font-medium group-hover:text-violet-400 transition-colors">
                                                {{ $result['name'] }}
                                            </p>
                                            <p class="text-xs text-slate-400">
                                                {{ $result['brand'] }} • {{ $result['variant'] }} •
                                                <span
                                                    class="{{ $result['stock_quantity'] > 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                                    Stock: {{ $result['stock_quantity'] }}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-emerald-400 font-bold">
                                                ₹{{ number_format($result['selling_price'], 0) }}</p>
                                            @if($result['sku'])
                                                <p class="text-[10px] text-slate-500">{{ $result['sku'] }}</p>
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @elseif($showSearchResults && strlen($searchQuery) >= 2)
                            <div
                                class="absolute z-10 w-full mt-1 bg-slate-800 border border-white/10 rounded-xl shadow-2xl p-4 text-center text-slate-400">
                                No products found. <a href="{{ route('products') }}"
                                    class="text-violet-400 hover:underline">Add to catalog?</a>
                            </div>
                        @endif
                    </div>

                    <!-- Category (MOBILE/ACCESSORY/SERVICE) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-3">CATEGORY *</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($categories as $cat)
                                <label class="relative cursor-pointer">
                                    <input wire:model.live="category" type="radio" name="category" value="{{ $cat }}"
                                        class="sr-only peer">
                                    <div
                                        class="px-4 py-3 text-center rounded-xl border transition-all
                                                                    peer-checked:bg-violet-600 peer-checked:border-violet-500 peer-checked:text-white
                                                                    bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                        <span class="text-sm font-medium">{{ $cat }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Brand (shown for MOBILE) -->
                    @if($category === 'MOBILE')
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-3">BRAND *</label>
                            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 max-h-48 overflow-y-auto p-1">
                                @foreach($brands as $b)
                                    <label class="relative cursor-pointer">
                                        <input wire:model.live="brand" type="radio" name="brand" value="{{ $b }}"
                                            class="sr-only peer">
                                        <div
                                            class="px-3 py-2 text-center rounded-lg border transition-all text-xs
                                                                                                    peer-checked:bg-violet-600 peer-checked:border-violet-500 peer-checked:text-white
                                                                                                    bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                            {{ $b }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Network Type -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-3">TYPE *</label>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($networkTypes as $type)
                                    <label class="relative cursor-pointer">
                                        <input wire:model.live="network_type" type="radio" name="network_type" value="{{ $type }}"
                                            class="sr-only peer">
                                        <div
                                            class="px-4 py-3 text-center rounded-xl border transition-all
                                                                                                    peer-checked:bg-emerald-600 peer-checked:border-emerald-500 peer-checked:text-white
                                                                                                    bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                            <span class="text-lg font-bold">{{ $type }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Model/Accessory Name -->
                    <div>
                        <label for="product_name" class="block text-sm font-medium text-slate-300 mb-2">
                            {{ $category === 'SERVICE' ? 'SERVICE DESCRIPTION' : ($category === 'ACCESSORY' ? 'ACCESSORY NAME' : 'MODEL NAME') }}
                            *
                        </label>
                        <input wire:model.live="product_name" type="text" id="product_name"
                            placeholder="{{ $category === 'SERVICE' ? 'Describe the service' : 'e.g. iPhone 15 Pro Max' }}"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                        @error('product_name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Variant (shown for MOBILE) -->
                    @if($category === 'MOBILE')
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-3">VARIANT *</label>
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                @foreach(array_filter($variants, fn($v) => !in_array($v, ['ACCESSORY', 'SERVICE'])) as $v)
                                    <label class="relative cursor-pointer">
                                        <input wire:model.live="variant" type="radio" name="variant" value="{{ $v }}"
                                            class="sr-only peer">
                                        <div
                                            class="px-3 py-2 text-center rounded-lg border transition-all text-xs
                                                                                                    peer-checked:bg-violet-600 peer-checked:border-violet-500 peer-checked:text-white
                                                                                                    bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                            {{ $v }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Service Product (shown for SERVICE) -->
                    @if($category === 'SERVICE')
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-3">SERVICE TYPE</label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($serviceProducts as $sp)
                                    <label class="relative cursor-pointer">
                                        <input wire:model.live="service_product" type="radio" name="service_product"
                                            value="{{ $sp }}" class="sr-only peer">
                                        <div
                                            class="px-3 py-2 text-center rounded-lg border transition-all text-xs
                                                                                                    peer-checked:bg-amber-600 peer-checked:border-amber-500 peer-checked:text-white
                                                                                                    bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                            {{ $sp }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Price -->
                    <div>
                        <label for="total_amount" class="block text-sm font-medium text-slate-300 mb-2">PRICE (₹)
                            *</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400">₹</span>
                            <input wire:model.live="total_amount" type="number" id="total_amount" min="0" step="0.01"
                                placeholder="0.00"
                                class="w-full pl-9 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                        </div>
                        @error('total_amount') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Gift (shown for MOBILE) -->
                    @if($category === 'MOBILE')
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-3">GIFT *</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach($gifts as $g)
                                    <label class="relative cursor-pointer">
                                        <input wire:model="gift" type="radio" name="gift" value="{{ $g }}" class="sr-only peer">
                                        <div
                                            class="px-3 py-2 text-center rounded-lg border transition-all text-xs
                                                                                                    peer-checked:bg-fuchsia-600 peer-checked:border-fuchsia-500 peer-checked:text-white
                                                                                                    bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                            {{ $g }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Remarks (Optional) -->
                    <div>
                        <label for="remarks" class="block text-sm font-medium text-slate-300 mb-2">REMARKS
                            (Optional)</label>
                        <textarea wire:model="remarks" id="remarks" rows="2" placeholder="Any additional notes..."
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 transition-all resize-none"></textarea>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-500 hover:to-fuchsia-500 text-white font-semibold rounded-xl shadow-lg shadow-violet-500/25 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" wire:loading.remove wire:target="save" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg class="w-5 h-5 animate-spin" wire:loading wire:target="save" fill="none" viewBox="0 0 24 24">
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
                <div class="text-center py-8">
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
                            <div class="bg-white/5 rounded-xl p-4 border border-white/5 hover:border-white/10 transition-all">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                class="px-2 py-0.5 text-xs font-medium rounded-full
                                                                                                                                {{ $sale->category === 'MOBILE' ? 'bg-violet-500/20 text-violet-400' :
                        ($sale->category === 'ACCESSORY' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400') }}">
                                                {{ $sale->category }}
                                            </span>
                                            @if($sale->brand)
                                                <span class="text-xs text-slate-500">{{ $sale->brand }}</span>
                                            @endif
                                            @if($sale->network_type)
                                                <span
                                                    class="px-1.5 py-0.5 text-xs rounded bg-slate-700 text-slate-300">{{ $sale->network_type }}</span>
                                            @endif
                                        </div>
                                        <h3 class="font-medium text-white truncate">{{ $sale->product_name }}</h3>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-slate-500">
                                            <span>{{ $sale->sale_date->format('d M Y') }}</span>
                                            @if($sale->variant)
                                                <span>• {{ $sale->variant }}</span>
                                            @endif
                                            @if($sale->gift && $sale->gift !== 'NO GIFT')
                                                <span class="text-fuchsia-400">• 🎁 {{ $sale->gift }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-lg font-bold text-emerald-400">₹{{ number_format($sale->total_amount, 0) }}
                                        </p>
                                        <div class="flex gap-1 mt-1">
                                            <button wire:click="edit({{ $sale->id }})"
                                                class="p-1.5 text-slate-400 hover:text-violet-400 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button wire:click="delete({{ $sale->id }})" wire:confirm="Delete this sale?"
                                                class="p-1.5 text-slate-400 hover:text-red-400 transition-colors">
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

    <!-- Bottom Navigation -->
    <nav
        class="fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur-xl border-t border-white/10 px-4 py-3 md:hidden">
        <div class="flex items-center justify-around">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 text-violet-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="{{ route('products') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-xs">Products</span>
            </a>
            <a href="{{ route('reports') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-xs">Reports</span>
            </a>
            <a href="{{ route('settings') }}" class="flex flex-col items-center gap-1 text-slate-500">
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