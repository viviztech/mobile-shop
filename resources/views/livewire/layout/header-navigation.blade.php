<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav class="hidden md:flex items-center gap-6">
    <a href="{{ route('dashboard') }}" wire:navigate
        class="text-sm font-medium transition-colors hover:text-white {{ request()->routeIs('dashboard') ? 'text-white' : 'text-slate-400' }}">
        Home
    </a>
    <a href="{{ route('products') }}" wire:navigate
        class="text-sm font-medium transition-colors hover:text-white {{ request()->routeIs('products') ? 'text-white' : 'text-slate-400' }}">
        Products
    </a>
    <a href="{{ route('reports') }}" wire:navigate
        class="text-sm font-medium transition-colors hover:text-white {{ request()->routeIs('reports') ? 'text-white' : 'text-slate-400' }}">
        Reports
    </a>
    <a href="{{ route('settings') }}" wire:navigate
        class="text-sm font-medium transition-colors hover:text-white {{ request()->routeIs('settings') ? 'text-white' : 'text-slate-400' }}">
        Settings
    </a>
    <a href="{{ route('profile') }}" wire:navigate
        class="text-sm font-medium transition-colors hover:text-white {{ request()->routeIs('profile') ? 'text-white' : 'text-slate-400' }}">
        Profile
    </a>

    <div class="h-4 w-px bg-white/10 mx-2"></div>

    <button wire:click="logout" class="text-sm font-medium text-slate-400 hover:text-red-400 transition-colors">
        Log Out
    </button>
</nav>