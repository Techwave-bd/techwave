<?php

use App\Models\ToolCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function logout()
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return $this->redirectRoute('home', navigate: true);
    }

    public function getIsToolsPremiumProperty(): bool
    {
        return ToolCategory::query()
            ->where('slug', 'image-tools')
            ->whereHas('toolSubscriptions', fn($q) => $q->where('user_id', auth()->id())->active())
            ->exists();
    }
};
?>

<!-- sidebar -->
<aside
    :class="sidebarOpen ? 'translate-x-0 opacity-100' : '-translate-x-full opacity-0 lg:translate-x-0 lg:opacity-100'"
    class="fixed left-4 top-4 bottom-4 z-50 w-71.25 rounded-[28px] border border-white/10 bg-slate-950/35 p-5 backdrop-blur-2xl transition-all duration-300 lg:static lg:w-70 lg:translate-x-0 lg:rounded-none lg:border-0 lg:border-r ">

    <div class="flex h-full flex-col">
        <!-- brand -->
        <div class="flex items-center justify-between">
            <button @click="sidebarOpen = false"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/8 text-white lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- profile card -->
        {{-- <div class="mt-8 rounded-[24px] border border-white/10 bg-white/[0.05] p-4 backdrop-blur-xl">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full border border-white/10 bg-blue-500/15 text-lg font-bold text-cyan-200">
                                    {{ strtoupper(substr(auth()->user()->name ?? 'C', 0, 1)) }}
                                </div>

                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-semibold text-white">
                                        {{ auth()->user()->name ?? 'Client User' }}
                                    </h3>
                                    <p class="truncate text-sm text-blue-100/55">
                                        {{ auth()->user()->email ?? 'client@email.com' }}
                                    </p>
                                </div>
                            </div>
                        </div> --}}

        <!-- nav -->
        <nav class="mt-8 flex-1 space-y-2">
            <a href="{{ route('account.dashboard') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-layout-panel-left-icon lucide-layout-panel-left">
                        <rect width="7" height="18" x="3" y="3" rx="1" />
                        <rect width="7" height="7" x="14" y="3" rx="1" />
                        <rect width="7" height="7" x="14" y="14" rx="1" />
                    </svg>
                </span>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('account.profile') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-user-icon lucide-user">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                </span>
                <span>Profile</span>
            </a>

            <a href="{{ route('account.services') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-server-cog-icon lucide-server-cog">
                        <path d="m10.852 14.772-.383.923" />
                        <path d="M13.148 14.772a3 3 0 1 0-2.296-5.544l-.383-.923" />
                        <path d="m13.148 9.228.383-.923" />
                        <path d="m13.53 15.696-.382-.924a3 3 0 1 1-2.296-5.544" />
                        <path d="m14.772 10.852.923-.383" />
                        <path d="m14.772 13.148.923.383" />
                        <path d="M4.5 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-.5" />
                        <path d="M4.5 14H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-.5" />
                        <path d="M6 18h.01" />
                        <path d="M6 6h.01" />
                        <path d="m9.228 10.852-.923-.383" />
                        <path d="m9.228 13.148-.923.383" />
                    </svg>
                </span>
                <span>Services / Plans</span>
            </a>
            <a href="{{ route('account.tool-subscriptions') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-toolbox-icon lucide-toolbox"><path d="M16 12v4"/><path d="M16 6a2 2 0 0 1 1.414.586l4 4A2 2 0 0 1 22 12v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 .586-1.414l4-4A2 2 0 0 1 8 6z"/><path d="M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 14h20"/><path d="M8 12v4"/></svg>
                </span>
                <span>Tool Subscriptions</span>
            </a>

            <a href="{{ route('client.tickets.index') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-ticket-icon lucide-ticket">
                        <path
                            d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
                        <path d="M13 5v2" />
                        <path d="M13 17v2" />
                        <path d="M13 11v2" />
                    </svg>
                </span>
                <span>Tickets</span>
            </a>

            <a href="{{ route('client.proposals.index') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-receipt-text-icon lucide-receipt-text">
                        <path d="M13 16H8" />
                        <path d="M14 8H8" />
                        <path d="M16 12H8" />
                        <path
                            d="M4 3a1 1 0 0 1 1-1 1.3 1.3 0 0 1 .7.2l.933.6a1.3 1.3 0 0 0 1.4 0l.934-.6a1.3 1.3 0 0 1 1.4 0l.933.6a1.3 1.3 0 0 0 1.4 0l.933-.6a1.3 1.3 0 0 1 1.4 0l.934.6a1.3 1.3 0 0 0 1.4 0l.933-.6A1.3 1.3 0 0 1 19 2a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1 1.3 1.3 0 0 1-.7-.2l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.934.6a1.3 1.3 0 0 1-1.4 0l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-1.4 0l-.934-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-.7.2 1 1 0 0 1-1-1z" />
                    </svg>
                </span>
                <span>Proposal</span>
            </a>

            @if ($this->is_tools_premium)
                <div x-data="{ toolsBackupOpen: false }" class="space-y-1">
                    <button @click="toolsBackupOpen = !toolsBackupOpen" type="button"
                        class="client-dash-link w-full">
                        <span class="client-dash-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                <line x1="12" y1="22.08" x2="12" y2="12" />
                            </svg>
                        </span>
                        <span>Tools Backup</span>
                        <svg :class="toolsBackupOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg"
                            class="ml-auto h-4 w-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </button>

                    <div x-show="toolsBackupOpen" @click.outside="toolsBackupOpen = false" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1" class="space-y-1 pl-4">
                        <a href="{{ route('account.compressed-images') }}" wire:navigate wire:current.exact="client-dash-link-active"
                            class="client-dash-link">
                            <span class="client-dash-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                                </svg>
                            </span>
                            <span>Compressed Images</span>
                        </a>

                        <a href="{{ route('account.bg-removed-images') }}" wire:navigate wire:current.exact="client-dash-link-active"
                            class="client-dash-link">
                            <span class="client-dash-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                </svg>
                            </span>
                            <span>BG Removed Images</span>
                        </a>

                        <a href="{{ route('account.resized-images') }}" wire:navigate wire:current.exact="client-dash-link-active"
                            class="client-dash-link">
                            <span class="client-dash-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                </svg>
                            </span>
                            <span>Resized Images</span>
                        </a>
                    </div>
                </div>
            @endif

            <a href="{{ route('account.change-password') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-lock-icon lucide-lock">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                </span>
                <span>Change Password</span>
            </a>
        </nav>

        <!-- logout -->
        <div class="mt-6 border-t border-white/10 pt-4">
            <button type="button" wire:click="logout" wire:loading.attr="disabled"
                class="client-dash-link w-full text-left text-red-200 hover:bg-red-500/10 hover:text-white">
                <span class="client-dash-icon bg-red-500/10 text-red-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H9m0 0 3-3m-3 3 3 3" />
                    </svg>
                </span>

                <span wire:loading.remove wire:target="logout">Logout</span>
                <span wire:loading wire:target="logout">Signing out...</span>
            </button>
        </div>
    </div>
</aside>
