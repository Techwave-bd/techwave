<?php

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

            <a href="{{ route('account.tool-subscriptions') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-crown-icon lucide-crown">
                        <path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.54l-3.362 9.98a1 1 0 0 1-.95.68H4.332a1 1 0 0 1-.95-.68L.02 6.04a.5.5 0 0 1 .798-.54l4.276 3.664a1 1 0 0 0 1.516-.294z" />
                        <path d="M5 21h14" />
                    </svg>
                </span>
                <span>Subscriptions</span>
            </a>

            <a href="{{ route('account.compressed-images') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-hard-drive-icon lucide-hard-drive">
                        <line x1="22" x2="2" y1="12" y2="12" />
                        <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                        <line x1="6" x2="6.01" y1="16" y2="16" />
                        <line x1="10" x2="10.01" y1="16" y2="16" />
                    </svg>
                </span>
                <span>Compressed Images</span>
            </a>

            <a href="{{ route('account.bg-removed-images') }}" wire:navigate wire:current.exact="client-dash-link-active"
                class="client-dash-link">
                <span class="client-dash-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-eraser-icon lucide-eraser">
                        <path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21" />
                        <path d="M22 21H7" />
                        <path d="m5 11 9 9" />
                    </svg>
                </span>
                <span>BG Removed Images</span>
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
