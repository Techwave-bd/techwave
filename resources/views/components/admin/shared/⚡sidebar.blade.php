<?php

use App\Models\SiteSetting;
use Livewire\Component;

new class extends Component {
    public function logout(): void
    {
        auth()->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirectRoute('admin.login', navigate: true);
    }

    public function getSiteSettingProperty()
    {
        return SiteSetting::current();
    }
};
?>

<!-- Sidebar -->
<aside
    :class="{
        'translate-x-0': sidebarOpen,
        '-translate-x-full': !sidebarOpen,
        'lg:w-20': sidebarCollapsed,
        'lg:w-64': !sidebarCollapsed
    }"
    class="fixed left-0 top-0 z-50 flex h-screen w-64 flex-col border-r border-slate-200 bg-slate-50 transition-all duration-300 lg:translate-x-0">
    <!-- Logo -->
    <div class="h-16 shrink-0 border-b border-slate-200 px-4 flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
            <div class="h-12 w-12 rounded-xl text-white flex items-center justify-center shrink-0">

                @php
                    $logo = $this->siteSetting->logo
                        ? asset('storage/' . $this->siteSetting->logo)
                        : asset('assets/images/logo/logo.png');
                @endphp
                <img src="{{ $logo }}" alt="Logo" class="">
            </div>

            <div x-show="!sidebarCollapsed" class="min-w-0">
                <h1 class="text-lg font-extrabold tracking-tight text-blue-700 font-manrope truncate">
                    Techwave
                </h1>

                <p class="text-slate-500 font-manrope text-xs font-medium truncate">
                    Infrastructure Management
                </p>
            </div>
        </div>

        {{-- <div class="h-16 shrink-0 border-b border-slate-200 px-4 flex items-center justify-between">
            <div class="flex items-center gap-3 overflow-hidden">
                <img src="{{ asset('assets/images/logo/logo.png') }}" alt="Logo"
                    class="p-1 w-full object-contain lg:h-14">
            </div>

            <button @click="sidebarOpen = false" class="lg:hidden p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div> --}}

        <button @click="sidebarOpen = false" class="lg:hidden p-2 rounded-lg hover:bg-slate-100 text-slate-500">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>



    <!-- Scrollable Nav -->
    <nav class="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden px-2 pb-4">

        <!-- Main -->
        <div class="space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                Main
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.dashboard') }}" wire:navigate
                wire:current.exact="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">dashboard</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Dashboard
                </span>
            </a>
        </div>

        <!-- Order Management -->
        <div class="space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                Order Management
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.orders.index') }}" wire:navigate
                wire:current.exact="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">shopping_cart</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Orders
                </span>
            </a>

            {{-- <a href="{{ route('admin.pricing-plan-bookings.index') }}" wire:navigate
                wire:current.exact="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">event_note</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    IT Plan Bookings
                </span>
            </a> --}}

            <a href="{{ route('admin.bookings.index') }}" wire:navigate
                wire:current.exact="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">developer_board</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Bookings
                </span>
            </a>

            {{-- <a href="{{ route('admin.assigned-services.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">assignment_ind</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Assigned Services
                </span>
            </a> --}}
        </div>

        <!-- Website Content -->
        <div class="mt-4 space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-2 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                Website Content
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.categories.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">category</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Categories
                </span>
            </a>

            <a href="{{ route('admin.company-logos.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">handshake</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Our Clients
                </span>
            </a>

            <a href="{{ route('admin.services.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">handyman</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Services
                </span>
            </a>

            <a href="{{ route('admin.service-plans.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">inventory_2</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Service Plans
                </span>
            </a>

            <a href="{{ route('admin.pricing.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">payments</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Pricing
                </span>
            </a>
        </div>

        <!-- Portfolio & Blog -->
        <div class="mt-4 space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-2 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                Portfolio & Blog
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.projects.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">account_tree</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Projects
                </span>
            </a>

            <a href="{{ route('admin.blogs.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">article</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Blogs
                </span>
            </a>
        </div>

        <!-- Support -->
        <div class="mt-4 space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-2 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                Support
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.proposals.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">receipt_long</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Proposals
                </span>
            </a>

            <a href="{{ route('admin.contact-messages.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">mail</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Contact Messages
                </span>
            </a>

            <a href="{{ route('admin.tickets.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">confirmation_number</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Tickets
                </span>
            </a>
        </div>

        <!-- System Management -->
        <div class="mt-4 space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-2 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                System Management
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.users.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">group</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Users
                </span>
            </a>

            <a href="{{ route('admin.departments.index') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">business</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Departments
                </span>
            </a>
        </div>

        <!-- Site Management -->
        <div class="mt-4 space-y-1">
            <p x-show="!sidebarCollapsed"
                class="px-4 pb-2 pt-2 font-manrope text-xs font-semibold uppercase tracking-wider text-slate-500">
                Site Management
            </p>
            <p x-show="sidebarCollapsed"
                class="px-4 pb-2 pt-4 font-manrope text-xl font-semibold uppercase tracking-wider text-slate-500 text-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </p>

            <a href="{{ route('admin.settings.site-setting') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">settings</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Site Settings
                </span>
            </a>
            <a href="{{ route('admin.icons.material-icons') }}" wire:navigate
                wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">
                <span class="material-symbols-outlined shrink-0">insert_emoticon</span>
                <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                    Icons
                </span>
            </a>

            {{-- Mail Templates Dropdown --}}
            <div x-data="{ open: {{ request()->routeIs('admin.settings.invoice-templates') ? 'true' : 'false' }} }" class="space-y-1">
                {{-- Dropdown Button --}}
                <button type="button" @click="open = !open"
                    class="flex w-full items-center justify-between rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">

                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined shrink-0">mail</span>

                        <span x-show="!sidebarCollapsed" class="font-manrope text-sm font-medium">
                            Mail Templates
                        </span>
                    </div>

                    <span x-show="!sidebarCollapsed"
                        class="material-symbols-outlined text-lg transition-transform duration-200"
                        :class="open ? 'rotate-180' : ''">
                        expand_more
                    </span>
                </button>

                {{-- Dropdown Items --}}
                <div x-show="open && !sidebarCollapsed" x-collapse
                    class="ml-4 space-y-1 border-l border-slate-200 pl-3">

                    <a href="{{ route('admin.settings.proposal-templates') }}" wire:navigate
                        wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                        class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">

                        <span class="material-symbols-outlined shrink-0 text-[20px]">
                            receipt_long
                        </span>

                        <span class="font-manrope text-sm font-medium">
                            Proposal Template
                        </span>
                    </a>

                    {{-- Future link example --}}

                    <a href="{{ route('admin.settings.invoice-templates') }}" wire:navigate
                        wire:current="bg-white text-blue-700 border-l-4 border-blue-700 font-semibold shadow-sm"
                        class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900">

                        <span class="material-symbols-outlined shrink-0 text-[20px]">
                            receipt
                        </span>

                        <span class="font-manrope text-sm font-medium">
                            Invoice Template
                        </span>
                    </a>

                </div>
            </div>
        </div>
    </nav>

    <!-- Fixed Bottom Logout -->
    <div class="shrink-0 border-t border-slate-200 bg-slate-50 px-3 py-4">
        <button type="button" wire:click="logout"
            class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-container py-2.5 font-manrope text-sm font-semibold text-white transition-all hover:opacity-90 active:opacity-80">
            <span class="material-symbols-outlined shrink-0">logout</span>
            <span x-show="!sidebarCollapsed">Logout</span>
        </button>
    </div>
</aside>
