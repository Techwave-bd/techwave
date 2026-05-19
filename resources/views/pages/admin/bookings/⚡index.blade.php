<?php

use App\Mail\OrderInvoiceMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin-app')] #[Title('Bookings')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public string $bookingType = 'all';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedBookingType(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function bookings()
    {
        $search = trim($this->search);

        return Booking::query()
            ->with(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('booking_no', 'like', '%' . $search . '%')
                        ->orWhere('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%')
                        ->orWhere('company_phone', 'like', '%' . $search . '%')
                        ->orWhere('company_email', 'like', '%' . $search . '%')
                        ->orWhere('plan_name', 'like', '%' . $search . '%')
                        ->orWhere('message', 'like', '%' . $search . '%')
                        ->orWhere('user_note', 'like', '%' . $search . '%')
                        ->orWhereHas('service', function ($serviceQuery) use ($search) {
                            $serviceQuery->where('card_title', 'like', '%' . $search . '%')->orWhere('detail_title', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('servicePlan', function ($servicePlanQuery) use ($search) {
                            $servicePlanQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('pricingPlan', function ($pricingPlanQuery) use ($search) {
                            $pricingPlanQuery->where('title', 'like', '%' . $search . '%')->orWhere('plan_type', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->bookingType !== 'all', function ($query) {
                $query->where('booking_type', $this->bookingType);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function confirmToOrder(int $bookingId): void
    {
        $booking = Booking::query()
            ->with(['order', 'user'])
            ->findOrFail($bookingId);

        if ($booking->order) {
            $this->dispatch('toast', message: 'Order already created for this booking.', type: 'warning');

            return;
        }

        if (in_array($booking->status, ['rejected', 'cancelled'], true)) {
            $this->dispatch('toast', message: 'Rejected or cancelled bookings cannot be converted to order.', type: 'error');

            return;
        }

        $amount = $this->orderAmount($booking);

        if ($amount <= 0) {
            $this->dispatch('toast', message: 'Please add a valid quoted, requested, or plan price before creating order.', type: 'error');

            return;
        }

        $orderUserId = $this->resolveOrderUserId($booking);

        [$startDate, $endDate] = $this->orderDates($booking->billing_cycle);

        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $orderUserId,

            'order_no' => $this->makeOrderNo(),
            'order_type' => $booking->booking_type,

            'service_id' => $booking->service_id,
            'service_plan_id' => $booking->service_plan_id,
            'pricing_plan_id' => $booking->pricing_plan_id,

            'billing_cycle' => $booking->billing_cycle,

            'full_name' => $booking->full_name,
            'phone' => $booking->phone,
            'email' => $booking->email,

            'company_name' => $booking->company_name,
            'company_phone' => $booking->company_phone,
            'company_email' => $booking->company_email,

            'plan_name' => $booking->plan_name,
            'plan_price' => $booking->plan_price,
            'requested_price' => $booking->requested_price,
            'quoted_price' => $booking->quoted_price,
            'final_price' => $amount,
            'amount' => $amount,
            'currency' => $booking->currency ?: 'BDT',

            'message' => $booking->message,
            'user_note' => $booking->user_note,
            'admin_note' => $booking->admin_note,

            'status' => 'awaiting_payment',

            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $booking->update([
            'user_id' => $orderUserId,
            'status' => 'converted',
            'final_price' => $amount,
            'admin_read_at' => now(),
        ]);

        $email = $order->email ?: $order->user?->email;

        if ($email) {
            Mail::to($email)->send(new OrderInvoiceMail($order));
        }

        $this->dispatch('toast', message: 'Booking confirmed, order created, and invoice sent successfully.', type: 'success');
    }

    public function markAsQuoted(int $bookingId): void
    {
        Booking::query()
            ->findOrFail($bookingId)
            ->update([
                'status' => 'quoted',
            ]);

        $this->dispatch('toast', message: 'Booking marked as quoted.', type: 'success');
    }

    public function markAsRejected(int $bookingId): void
    {
        Booking::query()
            ->findOrFail($bookingId)
            ->update([
                'status' => 'rejected',
            ]);

        $this->dispatch('toast', message: 'Booking rejected.', type: 'success');
    }

    public function markAsCancelled(int $bookingId): void
    {
        Booking::query()
            ->findOrFail($bookingId)
            ->update([
                'status' => 'cancelled',
            ]);

        $this->dispatch('toast', message: 'Booking cancelled.', type: 'success');
    }

    public function delete(int $bookingId): void
    {
        $booking = Booking::query()->with('order')->findOrFail($bookingId);

        if ($booking->order) {
            $this->dispatch('toast', message: 'This booking already has an order. Delete the order first if needed.', type: 'error');

            return;
        }

        $booking->delete();

        $this->dispatch('toast', message: 'Booking deleted successfully.', type: 'success');
    }

    private function orderDates(?string $billingCycle): array
    {
        $startDate = now()->toDateString();

        $endDate = match ($billingCycle) {
            'monthly' => now()->addMonth()->toDateString(),
            'yearly' => now()->addYear()->toDateString(),
            default => null,
        };

        return [$startDate, $endDate];
    }

    private function resolveOrderUserId($booking): ?int
    {
        if ($booking->user_id) {
            return (int) $booking->user_id;
        }

        if (!$booking->email) {
            return null;
        }

        return User::query()->where('email', $booking->email)->value('id');
    }

    private function makeOrderNo(): string
    {
        do {
            $orderNo = 'ORD-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::query()->where('order_no', $orderNo)->exists());

        return $orderNo;
    }

    private function orderAmount($booking): float
    {
        return (float) ($booking->final_price ?? ($booking->quoted_price ?? ($booking->requested_price ?? ($booking->plan_price ?? 0))));
    }

    public function bookingTitle($booking): string
    {
        if ($booking->booking_type === 'pricing_plan') {
            return $booking->pricingPlan?->title ?? ($booking->plan_name ?? 'Pricing Plan Booking');
        }

        return $booking->service?->card_title ?? ($booking->plan_name ?? 'Service Booking');
    }

    public function bookingSubtitle($booking): ?string
    {
        if ($booking->booking_type === 'pricing_plan') {
            return $booking->pricingPlan?->plan_type ? ucfirst($booking->pricingPlan->plan_type) : 'Pricing Plan';
        }

        if ($booking->servicePlan?->name) {
            return $booking->servicePlan->name;
        }

        return $booking->service?->category?->name;
    }

    public function displayPrice($booking): string
    {
        $price = $booking->final_price ?? ($booking->quoted_price ?? ($booking->requested_price ?? $booking->plan_price));

        if (!$price || (float) $price <= 0) {
            return 'Negotiable';
        }

        return '৳' . number_format((float) $price, 2);
    }

    public function displayBilling($booking): string
    {
        return match ($booking->billing_cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'one_time' => 'One-time',
            'custom' => 'Custom',
            default => 'Negotiable',
        };
    }
};
?>

<div>
    <div class="mx-auto w-full max-w-7xl space-y-stack-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">
                    Bookings
                </h2>

                <p class="text-xs font-body-md text-secondary md:text-body-md">
                    View booking requests and confirm approved bookings into orders.
                </p>
            </div>

            <div class="flex w-full flex-col gap-4 lg:w-auto lg:flex-row lg:items-center">
                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 lg:max-w-3xl">
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            search
                        </span>

                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search booking..."
                            class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pl-10 pr-4 text-label-md font-label-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    </div>

                    <div class="relative">
                        <select wire:model.live="bookingType"
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="all">All Types</option>
                            <option value="service">Service Booking</option>
                            <option value="pricing_plan">Pricing Plan</option>
                        </select>

                        <span
                            class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            expand_more
                        </span>
                    </div>

                    <div class="relative">
                        <select wire:model.live="status"
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-white px-4 py-2.5 pr-10 text-label-md font-label-md text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="quoted">Quoted</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                            <option value="converted">Converted</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        <span
                            class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">
                            expand_more
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/50">
                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Customer
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Booking
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Company
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Price
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Message
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Status
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Order
                            </th>

                            <th class="px-6 py-4 text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Submitted At
                            </th>

                            <th
                                class="px-6 py-4 text-right text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->bookings() as $booking)
                            <tr wire:key="booking-{{ $booking->id }}" class="transition-colors hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <div>
                                        <span class="block text-label-md font-label-md text-on-surface">
                                            {{ $booking->full_name ?: $booking->user?->name ?: 'N/A' }}
                                        </span>

                                        @if ($booking->booking_no)
                                            <span class="block font-mono text-[11px] text-slate-400">
                                                {{ $booking->booking_no }}
                                            </span>
                                        @endif

                                        @if ($booking->phone)
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}"
                                                class="block text-xs text-slate-400 transition hover:text-primary">
                                                {{ $booking->phone }}
                                            </a>
                                        @endif

                                        @if ($booking->email)
                                            <a href="mailto:{{ $booking->email }}"
                                                class="mt-1 block text-xs text-secondary transition hover:text-primary">
                                                {{ $booking->email }}
                                            </a>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="block text-body-sm text-on-surface">
                                        {{ $this->bookingTitle($booking) }}
                                    </span>

                                    @if ($this->bookingSubtitle($booking))
                                        <span class="block text-xs capitalize text-secondary">
                                            {{ $this->bookingSubtitle($booking) }}
                                        </span>
                                    @endif

                                    {{-- <span @class([
                                        'mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider',
                                        'bg-cyan-100 text-cyan-700' => $booking->booking_type === 'service',
                                        'bg-purple-100 text-purple-700' =>
                                            $booking->booking_type === 'pricing_plan',
                                    ])>
                                        {{ $booking->booking_type === 'pricing_plan' ? 'Pricing Plan' : 'Service' }}
                                    </span> --}}
                                </td>

                                <td class="px-6 py-4">
                                    <span class="block text-body-sm text-on-surface">
                                        {{ $booking->company_name ?: 'N/A' }}
                                    </span>

                                    @if ($booking->company_phone)
                                        <span class="block text-xs text-secondary">
                                            {{ $booking->company_phone }}
                                        </span>
                                    @endif

                                    @if ($booking->company_email)
                                        <span class="block text-xs text-secondary">
                                            {{ $booking->company_email }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    {{-- <span class="block font-mono text-body-sm text-on-surface">
                                        {{ $this->displayPrice($booking) }}
                                    </span> --}}

                                    {{-- <span class="block text-xs text-secondary">
                                        {{ $this->displayBilling($booking) }}
                                    </span> --}}

                                    @if ($booking->plan_price)
                                        <span class="block text-xs text-slate-400">
                                            Listed: ৳{{ number_format((float) $booking->plan_price, 2) }}
                                        </span>
                                    @endif

                                    @if ($booking->requested_price)
                                        <span class="block text-xs text-cyan-600">
                                            Requested: ৳{{ number_format((float) $booking->requested_price, 2) }}
                                        </span>
                                    @endif

                                    @if ($booking->quoted_price)
                                        <span class="block text-xs text-emerald-600">
                                            Quoted: ৳{{ number_format((float) $booking->quoted_price, 2) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <p class="max-w-xs text-body-sm leading-6 text-secondary">
                                        {{ $booking->message || $booking->user_note ? Str::limit($booking->message ?: $booking->user_note, 90) : 'No message provided' }}
                                    </p>
                                </td>

                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider',
                                        'bg-slate-100 text-slate-600' => $booking->status === 'pending',
                                        'bg-blue-100 text-blue-700' => $booking->status === 'quoted',
                                        'bg-cyan-100 text-cyan-700' => $booking->status === 'accepted',
                                        'bg-red-100 text-red-700' => in_array($booking->status, [
                                            'rejected',
                                            'cancelled',
                                        ]),
                                        'bg-emerald-100 text-emerald-700' => $booking->status === 'converted',
                                    ])>
                                        {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    @if ($booking->order)
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-700">
                                            <span class="material-symbols-outlined text-[14px]">verified</span>
                                            {{ $booking->order->order_no }}
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-700">
                                            <span class="material-symbols-outlined text-[14px]">hourglass_empty</span>
                                            Not Created
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-body-sm text-secondary">
                                    {{ $booking->created_at?->format('M d, Y h:i A') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" @click="open = !open"
                                            class="text-slate-400 transition-colors hover:text-primary">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>

                                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                                            class="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">

                                            <a href="{{ route('admin.bookings.quote', $booking) }}" wire:navigate
                                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                <span class="material-symbols-outlined text-[18px]">request_quote</span>
                                                Edit / Send Quote
                                            </a>

                                            @if (!$booking->order && !in_array($booking->status, ['rejected', 'cancelled']))
                                                <button type="button" wire:click="confirmToOrder({{ $booking->id }})"
                                                    wire:confirm="Confirm this booking and create an order?"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-emerald-700 transition hover:bg-emerald-50">
                                                    <span
                                                        class="material-symbols-outlined text-[18px]">check_circle</span>
                                                    Confirm & Create Order
                                                </button>

                                                <div class="my-1 border-t border-slate-100"></div>
                                            @endif

                                            @if ($booking->status !== 'quoted' && !$booking->order)
                                                <button type="button" wire:click="markAsQuoted({{ $booking->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span
                                                        class="material-symbols-outlined text-[18px]">request_quote</span>
                                                    Mark Quoted
                                                </button>
                                            @endif

                                            @if ($booking->status !== 'rejected' && !$booking->order)
                                                <button type="button" wire:click="markAsRejected({{ $booking->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">block</span>
                                                    Mark Rejected
                                                </button>
                                            @endif

                                            @if ($booking->status !== 'cancelled' && !$booking->order)
                                                <button type="button"
                                                    wire:click="markAsCancelled({{ $booking->id }})"
                                                    @click="open = false"
                                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                                                    Mark Cancelled
                                                </button>
                                            @endif

                                            @if ($booking->phone || $booking->email)
                                                <div class="my-1 border-t border-slate-100"></div>
                                            @endif

                                            @if ($booking->phone)
                                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}"
                                                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span class="material-symbols-outlined text-[18px]">phone</span>
                                                    Call Customer
                                                </a>
                                            @endif

                                            @if ($booking->email)
                                                <a href="mailto:{{ $booking->email }}"
                                                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <span
                                                        class="material-symbols-outlined text-[18px]">outgoing_mail</span>
                                                    Send Email
                                                </a>
                                            @endif

                                            @if (!$booking->order)
                                                <div class="my-1 border-t border-slate-100"></div>

                                                <button type="button" wire:click="delete({{ $booking->id }})"
                                                    wire:confirm="Are you sure you want to delete this booking?"
                                                    @click="open = false"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 cursor-pointer">
                                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                                    Delete
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div
                                            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <span class="material-symbols-outlined">support_agent</span>
                                        </div>

                                        <h3 class="text-base font-semibold text-on-surface">
                                            No bookings found
                                        </h3>

                                        <p class="mt-1 text-sm text-secondary">
                                            Customer booking requests will appear here.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/30 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-body-sm font-body-sm text-secondary">Per page</span>

                    <select wire:model.live="perPage"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 focus:border-primary focus:ring-primary/10">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div>
                    {{ $this->bookings()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
