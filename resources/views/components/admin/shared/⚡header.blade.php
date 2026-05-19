<?php

use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $notificationRefreshKey = 0;

    #[On('echo-private:admin.tickets,.ticket.updated')]
    public function refreshAdminTicketNotifications(array $event = []): void
    {
        $action = $event['action'] ?? null;

        $this->notificationRefreshKey++;

        if ($action !== 'client_replied' && $action !== 'user_replied' && $action !== 'created') {
            return;
        }

        $this->dispatch('admin-ticket-notification-received');
        $this->dispatch('toast', message: 'New support ticket update received.', type: 'info');
    }

    #[On('echo-private:admin.contact-messages,.contact.message.created')]
    public function refreshContactMessageNotifications(array $event = []): void
    {
        $this->notificationRefreshKey++;

        $this->dispatch('admin-contact-notification-received');
        $this->dispatch('toast', message: 'New contact message received.', type: 'info');
    }

    #[On('echo-private:admin.bookings,.booking.created')]
    public function refreshBookingNotifications(array $event = []): void
    {
        $this->notificationRefreshKey++;

        $this->dispatch('admin-booking-notification-received');
        $this->dispatch('toast', message: 'New booking request received.', type: 'info');
    }

    public function unreadTicketCount(): int
    {
        return SupportTicket::query()->whereNull('admin_read_at')->count();
    }

    public function unreadContactMessageCount(): int
    {
        return ContactMessage::query()->whereNull('admin_read_at')->count();
    }

    public function unreadBookingCount(): int
    {
        return Booking::query()->whereNull('admin_read_at')->count();
    }

    public function totalUnreadCount(): int
    {
        return $this->unreadTicketCount() + $this->unreadContactMessageCount() + $this->unreadBookingCount();
    }

    public function bookingTitle($booking): string
    {
        if ($booking->booking_type === 'pricing_plan') {
            return $booking->pricingPlan?->title ?? ($booking->plan_name ?? 'Pricing Plan');
        }

        return $booking->service?->card_title ?? ($booking->servicePlan?->name ?? ($booking->plan_name ?? 'Service'));
    }

    public function latestNotifications()
    {
        $tickets = SupportTicket::query()
            ->with('user')
            ->whereNull('admin_read_at')
            ->latest('last_reply_at')
            ->latest()
            ->limit(5)
            ->get()
            ->toBase()
            ->map(function ($ticket) {
                return [
                    'type' => 'ticket',
                    'id' => $ticket->id,
                    'title' => 'New ticket update',
                    'subject' => $ticket->subject,
                    'from' => $ticket->customer_name ?? ($ticket->user?->name ?? 'Customer'),
                    'priority' => $ticket->priority,
                    'time' => $ticket->last_reply_at ?? $ticket->created_at,
                    'url' => Route::has('admin.tickets.show') ? route('admin.tickets.show', $ticket) : '#',
                ];
            });

        $contacts = ContactMessage::query()
            ->whereNull('admin_read_at')
            ->latest()
            ->limit(5)
            ->get()
            ->toBase()
            ->map(function ($message) {
                return [
                    'type' => 'contact',
                    'id' => $message->id,
                    'title' => 'New contact message',
                    'subject' => $message->subject,
                    'from' => $message->name,
                    'priority' => 'new',
                    'time' => $message->created_at,
                    'url' => Route::has('admin.contact-messages.index') ? route('admin.contact-messages.index') : '#',
                ];
            });

        $bookings = Booking::query()
            ->with(['user', 'service', 'servicePlan', 'pricingPlan'])
            ->whereNull('admin_read_at')
            ->latest()
            ->limit(5)
            ->get()
            ->toBase()
            ->map(function ($booking) {
                return [
                    'type' => 'booking',
                    'id' => $booking->id,
                    'title' => $booking->booking_type === 'pricing_plan' ? 'New plan booking' : 'New service booking',
                    'subject' => ($booking->booking_no ?? 'Booking') . ' · ' . $this->bookingTitle($booking),
                    'from' => $booking->full_name ?? ($booking->user?->name ?? 'Customer'),
                    'priority' => $booking->status ?? 'pending',
                    'time' => $booking->created_at,
                    'url' => Route::has('admin.bookings.quote') ? route('admin.bookings.quote', $booking) : (Route::has('admin.bookings.index') ? route('admin.bookings.index') : '#'),
                ];
            });

        return $tickets->merge($contacts)->merge($bookings)->sortByDesc('time')->take(10)->values();
    }

    public function markAllNotificationsRead(): void
    {
        SupportTicket::query()
            ->whereNull('admin_read_at')
            ->update([
                'admin_read_at' => now(),
            ]);

        ContactMessage::query()
            ->whereNull('admin_read_at')
            ->update([
                'admin_read_at' => now(),
            ]);

        Booking::query()
            ->whereNull('admin_read_at')
            ->update([
                'admin_read_at' => now(),
            ]);

        $this->notificationRefreshKey++;

        $this->dispatch('toast', message: 'All notifications marked as read.', type: 'success');
    }

    public function notificationIcon(string $type): string
    {
        return match ($type) {
            'ticket' => 'confirmation_number',
            'contact' => 'mail',
            'booking' => 'event_note',
            default => 'notifications',
        };
    }

    public function notificationColor(string $type): string
    {
        return match ($type) {
            'ticket' => 'bg-blue-100 text-blue-700',
            'contact' => 'bg-emerald-100 text-emerald-700',
            'booking' => 'bg-amber-100 text-amber-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function notificationBadgeColor(string $type): string
    {
        return match ($type) {
            'ticket' => 'bg-blue-50 text-blue-700',
            'contact' => 'bg-emerald-50 text-emerald-700',
            'booking' => 'bg-amber-50 text-amber-700',
            default => 'bg-slate-50 text-slate-700',
        };
    }
};
?>

<header
    class="flex items-center justify-between gap-3 h-16 px-4 sm:px-6 sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200 font-manrope text-sm">

    <!-- Left -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
        <button type="button" @click="sidebarOpen = true"
            class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100">
            <span class="material-symbols-outlined">menu</span>
        </button>

        <!-- Desktop Collapse Button -->
        <div class="hidden shrink-0 lg:flex">
            <button @click="sidebarCollapsed = !sidebarCollapsed"
                class="flex cursor-pointer items-center justify-center px-2 rounded-lg border border-slate-200 bg-white py-1.5 text-slate-600 transition hover:bg-slate-100">
                <span class="material-symbols-outlined text-[20px]"
                    x-text="sidebarCollapsed ? 'chevron_right' : 'chevron_left'"></span>
            </button>
        </div>

        <div class="relative hidden sm:block w-full max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                search
            </span>

            <input
                class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-body-md focus:ring-2 focus:ring-primary-container/10 focus:border-primary-container transition-all"
                placeholder="Search resources..." type="text" />
        </div>

        <button type="button" class="sm:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100">
            <span class="material-symbols-outlined">search</span>
        </button>
    </div>

    <!-- Right -->
    <div class="flex items-center gap-1 sm:gap-3 shrink-0">

        <!-- Notification -->
        <div x-data="{ notificationOpen: false }" class="relative"
            x-on:admin-ticket-notification-received.window="$nextTick(() => {})"
            x-on:admin-contact-notification-received.window="$nextTick(() => {})"
            x-on:admin-booking-notification-received.window="$nextTick(() => {})">

            @php
                $unreadTicketCount = $this->unreadTicketCount();
                $unreadContactCount = $this->unreadContactMessageCount();
                $unreadBookingCount = $this->unreadBookingCount();
                $totalUnreadCount = $this->totalUnreadCount();
                $notifications = $this->latestNotifications();
            @endphp

            <button type="button" @click.stop="notificationOpen = !notificationOpen"
                class="relative cursor-pointer rounded-full p-2 text-slate-500 transition-colors hover:bg-slate-100">
                <span class="material-symbols-outlined">notifications</span>

                @if ($totalUnreadCount > 0)
                    <span wire:key="admin-notification-badge-{{ $notificationRefreshKey }}-{{ $totalUnreadCount }}"
                        class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">
                        {{ $totalUnreadCount > 99 ? '99+' : $totalUnreadCount }}
                    </span>
                @endif
            </button>

            <div x-cloak x-show="notificationOpen" @click.outside="notificationOpen = false"
                x-transition.origin.top.right
                class="absolute right-0 top-full z-9999 mt-3 w-105 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">

                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Notifications</h3>

                        <p class="text-xs text-slate-500">
                            {{ $totalUnreadCount }} new notification{{ $totalUnreadCount === 1 ? '' : 's' }}
                        </p>

                        @if ($totalUnreadCount > 0)
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ $unreadTicketCount }} ticket{{ $unreadTicketCount === 1 ? '' : 's' }},
                                {{ $unreadContactCount }} contact{{ $unreadContactCount === 1 ? '' : 's' }},
                                {{ $unreadBookingCount }} booking{{ $unreadBookingCount === 1 ? '' : 's' }}
                            </p>
                        @endif
                    </div>

                    <button type="button" @click="notificationOpen = false"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <div wire:key="admin-notification-list-{{ $notificationRefreshKey }}"
                    class="max-h-88 divide-y divide-slate-100 overflow-y-auto">
                    @forelse ($notifications as $notification)
                        <a href="{{ $notification['url'] }}" wire:navigate @click="notificationOpen = false"
                            class="flex gap-3 px-4 py-3 transition hover:bg-slate-50">

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $this->notificationColor($notification['type']) }}">
                                <span class="material-symbols-outlined text-[20px]">
                                    {{ $this->notificationIcon($notification['type']) }}
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="truncate text-sm font-semibold text-slate-800">
                                        {{ $notification['title'] }}
                                    </p>

                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $this->notificationBadgeColor($notification['type']) }}">
                                        {{ $notification['type'] }}
                                    </span>
                                </div>

                                <p class="mt-0.5 truncate text-xs text-slate-500">
                                    {{ $notification['subject'] }}
                                </p>

                                <p class="mt-0.5 truncate text-xs text-slate-400">
                                    By {{ $notification['from'] }}
                                </p>

                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ $notification['time']?->diffForHumans() }}
                                </p>
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-10 text-center">
                            <div
                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <span class="material-symbols-outlined">notifications_off</span>
                            </div>

                            <h4 class="mt-3 text-sm font-semibold text-slate-900">
                                No new notifications
                            </h4>

                            <p class="mt-1 text-xs text-slate-500">
                                New tickets, contacts, and bookings will appear here.
                            </p>
                        </div>
                    @endforelse
                </div>

                <div class="grid grid-cols-2 gap-2 border-t border-slate-100 bg-slate-50 p-3">
                    <a href="{{ Route::has('admin.tickets.index') ? route('admin.tickets.index') : '#' }}"
                        wire:navigate @click="notificationOpen = false"
                        class="flex items-center justify-center gap-2 rounded-xl bg-white px-3 py-2.5 text-xs font-semibold text-slate-700 border border-slate-200 transition hover:bg-slate-100">
                        <span class="material-symbols-outlined text-[17px]">confirmation_number</span>
                        Tickets
                    </a>

                    <a href="{{ Route::has('admin.contact-messages.index') ? route('admin.contact-messages.index') : '#' }}"
                        wire:navigate @click="notificationOpen = false"
                        class="flex items-center justify-center gap-2 rounded-xl bg-white px-3 py-2.5 text-xs font-semibold text-slate-700 border border-slate-200 transition hover:bg-slate-100">
                        <span class="material-symbols-outlined text-[17px]">mail</span>
                        Contacts
                    </a>

                    <a href="{{ Route::has('admin.bookings.index') ? route('admin.bookings.index') : '#' }}"
                        wire:navigate @click="notificationOpen = false"
                        class="flex items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-xs font-semibold text-white transition hover:opacity-90">
                        <span class="material-symbols-outlined text-[17px]">event_note</span>
                        Bookings
                    </a>

                    <a href="{{ Route::has('admin.orders.index') ? route('admin.orders.index') : '#' }}" wire:navigate
                        @click="notificationOpen = false"
                        class="flex items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-xs font-semibold text-white transition hover:opacity-90">
                        <span class="material-symbols-outlined text-[17px]">shopping_cart</span>
                        Orders
                    </a>

                    @if ($totalUnreadCount > 0)
                        <button type="button" wire:click="markAllNotificationsRead"
                            class="col-span-2 flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                            <span class="material-symbols-outlined text-[17px]">done_all</span>
                            Mark all as read
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Settings -->
        <button type="button"
            class="hidden sm:flex p-2 text-slate-500 hover:bg-slate-100 transition-colors rounded-full">
            <span class="material-symbols-outlined">settings</span>
        </button>

        <div class="hidden sm:block h-8 w-px bg-slate-200 mx-1"></div>

        <!-- User -->
        <div class="flex items-center gap-2 cursor-pointer">
            @if (auth()->user()->avatar)
                <img src="{{ Storage::url(auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}"
                    class="h-10 w-10 object-cover rounded-full" />
            @else
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-linear-to-r from-primary to-sky-600 text-sm font-bold text-white">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
            @endif

            <div class="hidden md:block text-left">
                <p class="text-slate-900 font-semibold text-sm leading-tight capitalize">
                    {{ auth()->user()->name }}
                </p>
                <p class="text-slate-500 text-xs capitalize">
                    {{ auth()->user()->role }}
                </p>
            </div>
        </div>
    </div>
</header>
