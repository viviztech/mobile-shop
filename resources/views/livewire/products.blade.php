<?php

use App\Models\Product;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    // Form Fields
    #[Validate('required|string|in:MOBILE,ACCESSORY,SERVICE')]
    public string $category = 'MOBILE';

    #[Validate('nullable|string')]
    public string $brand = '';

    #[Validate('nullable|string|in:4G,5G')]
    public string $network_type = '';

    #[Validate('required|string|min:2|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $variant = '';

    #[Validate('nullable|string')]
    public string $service_type = '';

    #[Validate('required|numeric|min:0')]
    public float $purchase_price = 0;

    #[Validate('required|numeric|min:0')]
    public float $selling_price = 0;

    #[Validate('required|integer|min:0')]
    public int $stock_quantity = 0;

    #[Validate('required|integer|min:0')]
    public int $min_stock_alert = 5;

    #[Validate('nullable|string|max:100')]
    public string $sku = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    // UI States
    public bool $showModal = false;
    public bool $showSuccess = false;
    public ?int $editingId = null;
    public string $search = '';
    public string $filterCategory = 'all';
    public string $filterStock = 'all';
    public string $successMessage = '';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset([
            'category', 'brand', 'network_type', 'name', 'variant',
            'service_type', 'purchase_price', 'selling_price', 'stock_quantity',
            'min_stock_alert', 'sku', 'description', 'editingId'
        ]);
        $this->category = 'MOBILE';
        $this->min_stock_alert = 5;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'user_id' => Auth::id(),
            'category' => $this->category,
            'brand' => $this->category === 'MOBILE' ? ($this->brand ?: null) : null,
            'network_type' => $this->category === 'MOBILE' ? ($this->network_type ?: null) : null,
            'name' => $this->name,
            'variant' => $this->category === 'MOBILE' ? ($this->variant ?: null) : null,
            'service_type' => $this->category === 'SERVICE' ? ($this->service_type ?: null) : null,
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'stock_quantity' => $this->stock_quantity,
            'min_stock_alert' => $this->min_stock_alert,
            'sku' => $this->sku ?: Product::generateSku($this->category, $this->brand, $this->name),
            'description' => $this->description ?: null,
        ];

        if ($this->editingId) {
            $product = Product::find($this->editingId);
            $product->update($data);
            $this->successMessage = 'Product updated successfully!';
        } else {
            Product::create($data);
            $this->successMessage = 'Product added successfully!';
        }

        $this->showSuccess = true;
        $this->closeModal();
    }

    public function edit(int $id): void
    {
        $product = Product::where('user_id', Auth::id())->find($id);
        if ($product) {
            $this->editingId = $product->id;
            $this->category = $product->category;
            $this->brand = $product->brand ?? '';
            $this->network_type = $product->network_type ?? '';
            $this->name = $product->name;
            $this->variant = $product->variant ?? '';
            $this->service_type = $product->service_type ?? '';
            $this->purchase_price = (float) $product->purchase_price;
            $this->selling_price = (float) $product->selling_price;
            $this->stock_quantity = $product->stock_quantity;
            $this->min_stock_alert = $product->min_stock_alert;
            $this->sku = $product->sku ?? '';
            $this->description = $product->description ?? '';
            $this->showModal = true;
        }
    }

    public function delete(int $id): void
    {
        Product::where('id', $id)->where('user_id', Auth::id())->delete();
        $this->successMessage = 'Product deleted successfully!';
        $this->showSuccess = true;
    }

    public function toggleActive(int $id): void
    {
        $product = Product::where('user_id', Auth::id())->find($id);
        if ($product) {
            $product->is_active = !$product->is_active;
            $product->save();
        }
    }

    public function updateStock(int $id, int $quantity): void
    {
        $product = Product::where('user_id', Auth::id())->find($id);
        if ($product) {
            $product->stock_quantity = max(0, $product->stock_quantity + $quantity);
            $product->save();
        }
    }

    public function dismissSuccess(): void
    {
        $this->showSuccess = false;
    }

    public function with(): array
    {
        $query = Product::where('user_id', Auth::id());

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('brand', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        // Apply category filter
        if ($this->filterCategory !== 'all') {
            $query->where('category', $this->filterCategory);
        }

        // Apply stock filter
        if ($this->filterStock === 'low') {
            $query->whereColumn('stock_quantity', '<=', 'min_stock_alert');
        } elseif ($this->filterStock === 'out') {
            $query->where('stock_quantity', 0);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        // Stats
        $allProducts = Product::where('user_id', Auth::id());
        $totalProducts = $allProducts->count();
        $lowStockCount = Product::where('user_id', Auth::id())
            ->whereColumn('stock_quantity', '<=', 'min_stock_alert')
            ->where('stock_quantity', '>', 0)
            ->count();
        $outOfStockCount = Product::where('user_id', Auth::id())
            ->where('stock_quantity', 0)
            ->count();
        $totalValue = Product::where('user_id', Auth::id())
            ->selectRaw('SUM(selling_price * stock_quantity) as total')
            ->value('total') ?? 0;

        return [
            'products' => $products,
            'totalProducts' => $totalProducts,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'totalValue' => $totalValue,
            'categories' => Product::categories(),
            'brands' => Product::brands(),
            'networkTypes' => Product::networkTypes(),
            'variants' => Product::variants(),
            'serviceTypes' => Product::serviceTypes(),
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
    <!-- Header -->
    <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-900/80 border-b border-white/10">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard') }}"
                        class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Product Catalog</h1>
                        <p class="text-xs text-slate-400">Manage your inventory</p>
                    </div>
                </div>
                <button wire:click="openModal"
                    class="px-4 py-2 bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-500 hover:to-fuchsia-500 text-white font-medium rounded-xl shadow-lg shadow-violet-500/25 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <span class="hidden sm:inline">Add Product</span>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 pb-24">
        <!-- Success Toast -->
        @if($showSuccess)
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => { show = false; $wire.dismissSuccess() }, 3000)" x-transition
                class="fixed top-20 right-4 left-4 md:left-auto md:w-80 z-50">
                <div class="bg-emerald-500/90 backdrop-blur-xl text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="font-medium">{{ $successMessage }}</p>
                </div>
            </div>
        @endif

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-violet-500/20 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400">Total Products</p>
                <p class="text-xl font-bold text-white">{{ $totalProducts }}</p>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400">Stock Value</p>
                <p class="text-xl font-bold text-white">₹{{ number_format($totalValue, 0) }}</p>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400">Low Stock</p>
                <p class="text-xl font-bold text-white">{{ $lowStockCount }}</p>
            </div>
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4">
                <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
                <p class="text-xs text-slate-400">Out of Stock</p>
                <p class="text-xl font-bold text-white">{{ $outOfStockCount }}</p>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search products..."
                            class="w-full pl-10 pr-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                    </div>
                </div>
                <!-- Category Filter -->
                <div class="flex gap-2 overflow-x-auto">
                    <button wire:click="$set('filterCategory', 'all')"
                        class="px-3 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all {{ $filterCategory === 'all' ? 'bg-violet-600 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                        All
                    </button>
                    @foreach($categories as $cat)
                        <button wire:click="$set('filterCategory', '{{ $cat }}')"
                            class="px-3 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all {{ $filterCategory === $cat ? 'bg-violet-600 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                            {{ $cat }}
                        </button>
                    @endforeach
                </div>
                <!-- Stock Filter -->
                <div class="flex gap-2">
                    <button wire:click="$set('filterStock', 'all')"
                        class="px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition-all {{ $filterStock === 'all' ? 'bg-emerald-600 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                        All Stock
                    </button>
                    <button wire:click="$set('filterStock', 'low')"
                        class="px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition-all {{ $filterStock === 'low' ? 'bg-amber-600 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                        Low
                    </button>
                    <button wire:click="$set('filterStock', 'out')"
                        class="px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition-all {{ $filterStock === 'out' ? 'bg-red-600 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' }}">
                        Out
                    </button>
                </div>
            </div>
        </div>

        <!-- Products List -->
        @if($products->isEmpty())
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-12 text-center">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center">
                    <svg class="w-10 h-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <p class="text-slate-400 text-lg mb-2">No products found</p>
                <p class="text-sm text-slate-500 mb-6">Start by adding your first product to the catalog</p>
                <button wire:click="openModal"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-violet-600 hover:bg-violet-500 text-white rounded-xl font-medium transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add First Product
                </button>
            </div>
        @else
            <div class="grid gap-4">
                @foreach($products as $product)
                    <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-4 hover:bg-white/10 transition-all">
                        <div class="flex items-start gap-4">
                            <!-- Product Icon -->
                            <div class="w-14 h-14 rounded-xl flex-shrink-0 flex items-center justify-center
                                {{ $product->category === 'MOBILE' ? 'bg-violet-500/20' : ($product->category === 'ACCESSORY' ? 'bg-amber-500/20' : 'bg-emerald-500/20') }}">
                                @if($product->category === 'MOBILE')
                                    <svg class="w-7 h-7 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                @elseif($product->category === 'ACCESSORY')
                                    <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                    </svg>
                                @else
                                    <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                @endif
                            </div>

                            <!-- Product Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full
                                        {{ $product->category === 'MOBILE' ? 'bg-violet-500/20 text-violet-400' : 
                                           ($product->category === 'ACCESSORY' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400') }}">
                                        {{ $product->category }}
                                    </span>
                                    @if($product->brand)
                                        <span class="text-xs text-slate-500">{{ $product->brand }}</span>
                                    @endif
                                    @if($product->network_type)
                                        <span class="px-1.5 py-0.5 text-xs rounded bg-slate-700 text-slate-300">{{ $product->network_type }}</span>
                                    @endif
                                    @if(!$product->is_active)
                                        <span class="px-1.5 py-0.5 text-xs rounded bg-red-500/20 text-red-400">Inactive</span>
                                    @endif
                                </div>
                                <h3 class="font-semibold text-white truncate">{{ $product->name }}</h3>
                                <div class="flex items-center gap-3 mt-1 text-xs text-slate-400">
                                    @if($product->variant)
                                        <span>{{ $product->variant }}</span>
                                    @endif
                                    <span>SKU: {{ $product->sku }}</span>
                                </div>
                            </div>

                            <!-- Stock & Price -->
                            <div class="text-right flex-shrink-0">
                                <p class="text-lg font-bold text-emerald-400">₹{{ number_format($product->selling_price, 0) }}</p>
                                <p class="text-xs text-slate-500">Cost: ₹{{ number_format($product->purchase_price, 0) }}</p>
                                <div class="mt-2 flex items-center justify-end gap-2">
                                    <div class="flex items-center gap-1 px-2 py-1 rounded-lg 
                                        {{ $product->stock_quantity === 0 ? 'bg-red-500/20 text-red-400' : 
                                           ($product->is_low_stock ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400') }}">
                                        <span class="text-xs font-medium">{{ $product->stock_quantity }} in stock</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-1">
                                <button wire:click="edit({{ $product->id }})" class="p-2 text-slate-400 hover:text-violet-400 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $product->id }})" wire:confirm="Delete this product?" class="p-2 text-slate-400 hover:text-red-400 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $products->links() }}
            </div>
        @endif
    </main>

    <!-- Add/Edit Product Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-init="document.body.style.overflow = 'hidden'" x-on:remove="document.body.style.overflow = ''">
            <div wire:click="closeModal" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-slate-800 rounded-3xl shadow-2xl">
                <!-- Modal Header -->
                <div class="sticky top-0 bg-slate-800 px-6 py-4 border-b border-white/10 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white">{{ $editingId ? 'Edit Product' : 'Add New Product' }}</h2>
                    <button wire:click="closeModal" class="p-2 text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form wire:submit="save" class="p-6 space-y-5">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Category *</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($categories as $cat)
                                <label class="relative cursor-pointer">
                                    <input wire:model.live="category" type="radio" name="category" value="{{ $cat }}" class="sr-only peer">
                                    <div class="px-3 py-2 text-center rounded-xl border transition-all text-sm
                                        peer-checked:bg-violet-600 peer-checked:border-violet-500 peer-checked:text-white
                                        bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                        {{ $cat }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Brand (for MOBILE) -->
                    @if($category === 'MOBILE')
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Brand</label>
                            <select wire:model="brand" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">Select Brand</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b }}">{{ $b }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Network Type -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Network</label>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($networkTypes as $type)
                                        <label class="relative cursor-pointer">
                                            <input wire:model="network_type" type="radio" name="network_type" value="{{ $type }}" class="sr-only peer">
                                            <div class="px-3 py-2 text-center rounded-xl border transition-all text-sm
                                                peer-checked:bg-emerald-600 peer-checked:border-emerald-500 peer-checked:text-white
                                                bg-white/5 border-white/10 text-slate-400 hover:bg-white/10">
                                                {{ $type }}
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Variant -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Variant</label>
                                <select wire:model="variant" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                                    <option value="">Select</option>
                                    @foreach($variants as $v)
                                        <option value="{{ $v }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <!-- Service Type (for SERVICE) -->
                    @if($category === 'SERVICE')
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Service Type</label>
                            <select wire:model="service_type" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">Select Service</option>
                                @foreach($serviceTypes as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Product Name -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            {{ $category === 'SERVICE' ? 'Service Name' : 'Product Name' }} *
                        </label>
                        <input wire:model="name" type="text" 
                            placeholder="{{ $category === 'MOBILE' ? 'e.g. Vivo V30 Pro' : ($category === 'ACCESSORY' ? 'e.g. Wireless Charger' : 'e.g. Display Change') }}"
                            class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Pricing -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Purchase Price (₹) *</label>
                            <input wire:model="purchase_price" type="number" min="0" step="0.01" placeholder="0.00"
                                class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Selling Price (₹) *</label>
                            <input wire:model="selling_price" type="number" min="0" step="0.01" placeholder="0.00"
                                class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                    </div>

                    <!-- Stock -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Stock Quantity *</label>
                            <input wire:model="stock_quantity" type="number" min="0" placeholder="0"
                                class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Low Stock Alert</label>
                            <input wire:model="min_stock_alert" type="number" min="0" placeholder="5"
                                class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                    </div>

                    <!-- SKU -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">SKU (Auto-generated if empty)</label>
                        <input wire:model="sku" type="text" placeholder="Leave empty for auto-generated"
                            class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Description (Optional)</label>
                        <textarea wire:model="description" rows="2" placeholder="Additional notes..."
                            class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-500 hover:to-fuchsia-500 text-white font-semibold rounded-xl shadow-lg shadow-violet-500/25 transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" wire:loading.remove wire:target="save" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg class="w-5 h-5 animate-spin" wire:loading wire:target="save" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Product' : 'Add Product' }}</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur-xl border-t border-white/10 px-6 py-3 md:hidden">
        <div class="flex items-center justify-around">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="{{ route('products') }}" class="flex flex-col items-center gap-1 text-violet-400">
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
