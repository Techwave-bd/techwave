<?php

use App\Mail\BookingQuoteMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin-app')] #[Title('Booking Quote')] class extends Component {
    public Booking $booking;

    public string $status = 'pending';
    public string $quoted_price = '';
    public string $admin_note = '';

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order']);

        if (is_null($this->booking->admin_read_at)) {
            $this->booking->update([
                'admin_read_at' => now(),
            ]);
        }

        $this->status = $this->booking->status ?: 'pending';
        $this->quoted_price = $this->booking->quoted_price !== null ? (string) $this->booking->quoted_price : '';
        $this->admin_note = $this->booking->admin_note ?: '';
    }

    protected function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,quoted,accepted,rejected,converted,cancelled'],
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'quoted_price.numeric' => 'Quoted price must be a valid number.',
            'quoted_price.min' => 'Quoted price cannot be negative.',
        ];
    }

    public function saveQuote(): void
    {
        $validated = $this->validate();

        $quotedPrice = $validated['quoted_price'] !== '' ? $validated['quoted_price'] : null;

        $this->booking->update([
            'status' => $quotedPrice ? 'quoted' : $validated['status'],
            'quoted_price' => $quotedPrice,
            'final_price' => $quotedPrice ?: $this->booking->final_price,
            'admin_note' => $validated['admin_note'] ?: null,
            'admin_read_at' => now(),
        ]);

        $this->status = $this->booking->status;
        $this->quoted_price = $this->booking->quoted_price !== null ? (string) $this->booking->quoted_price : '';
        $this->admin_note = $this->booking->admin_note ?: '';

        $this->booking = $this->booking->fresh(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order']);

        $this->dispatch('toast', message: 'Quote saved successfully.', type: 'success');
    }

    public function sendQuoteMail(): void
    {
        $this->saveQuote();

        $email = $this->clientEmail();

        if (!$email) {
            $this->dispatch('toast', message: 'No customer email found for this booking.', type: 'error');

            return;
        }

        Mail::to($email)->send(new BookingQuoteMail($this->booking));

        $this->dispatch('toast', message: 'Quotation email sent successfully.', type: 'success');
    }

    public function markAsAccepted(): void
    {
        $this->booking->update([
            'status' => 'accepted',
            'admin_read_at' => now(),
        ]);

        $this->status = 'accepted';

        $this->booking = $this->booking->fresh(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order']);

        $this->dispatch('toast', message: 'Booking marked as accepted.', type: 'success');
    }

    public function markAsRejected(): void
    {
        $this->booking->update([
            'status' => 'rejected',
            'admin_read_at' => now(),
        ]);

        $this->status = 'rejected';

        $this->booking = $this->booking->fresh(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order']);

        $this->dispatch('toast', message: 'Booking rejected successfully.', type: 'success');
    }

    public function markAsCancelled(): void
    {
        $this->booking->update([
            'status' => 'cancelled',
            'admin_read_at' => now(),
        ]);

        $this->status = 'cancelled';

        $this->booking = $this->booking->fresh(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order']);

        $this->dispatch('toast', message: 'Booking cancelled successfully.', type: 'success');
    }

    public function confirmToOrder(): void
    {
        $this->booking = $this->booking->fresh('order');

        if ($this->booking->order) {
            $this->dispatch('toast', message: 'Order already created for this booking.', type: 'warning');

            return;
        }

        if (in_array($this->booking->status, ['rejected', 'cancelled'], true)) {
            $this->dispatch('toast', message: 'Rejected or cancelled bookings cannot be converted to order.', type: 'error');

            return;
        }

        $amount = $this->finalAmount();

        if ($amount <= 0) {
            $this->dispatch('toast', message: 'Please add a valid quoted, requested, or plan price before creating order.', type: 'error');

            return;
        }

        $orderUserId = $this->resolveOrderUserId($this->booking);

        Order::query()->create([
            'booking_id' => $this->booking->id,
            'user_id' => $orderUserId,

            'order_no' => $this->makeOrderNo(),
            'order_type' => $this->booking->booking_type,

            'service_id' => $this->booking->service_id,
            'service_plan_id' => $this->booking->service_plan_id,
            'pricing_plan_id' => $this->booking->pricing_plan_id,

            'billing_cycle' => $this->booking->billing_cycle,

            'full_name' => $this->booking->full_name,
            'phone' => $this->booking->phone,
            'email' => $this->booking->email,

            'company_name' => $this->booking->company_name,
            'company_phone' => $this->booking->company_phone,
            'company_email' => $this->booking->company_email,

            'plan_name' => $this->booking->plan_name,
            'plan_price' => $this->booking->plan_price,
            'requested_price' => $this->booking->requested_price,
            'quoted_price' => $this->booking->quoted_price,
            'final_price' => $amount,
            'amount' => $amount,
            'currency' => $this->booking->currency ?: 'BDT',

            'message' => $this->booking->message,
            'user_note' => $this->booking->user_note,
            'admin_note' => $this->booking->admin_note,

            'status' => 'awaiting_payment',
        ]);

        $this->booking->update([
            'user_id' => $orderUserId,
            'status' => 'converted',
            'final_price' => $amount,
            'admin_read_at' => now(),
        ]);

        $this->status = 'converted';

        $this->booking = $this->booking->fresh(['user', 'service.category', 'servicePlan', 'pricingPlan', 'order']);

        $this->dispatch('toast', message: 'Booking confirmed and order created successfully.', type: 'success');
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
            $nextId = (int) Order::query()->max('id') + 1;
            $orderNo = 'ORD-' . now()->format('ymd') . '-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_no', $orderNo)->exists());

        return $orderNo;
    }

    public function bookingTitle(): string
    {
        if ($this->booking->booking_type === 'pricing_plan') {
            return $this->booking->pricingPlan?->title ?? ($this->booking->plan_name ?? 'Pricing Plan Booking');
        }

        return $this->booking->service?->card_title ?? ($this->booking->servicePlan?->name ?? ($this->booking->plan_name ?? 'Service Booking'));
    }

    public function bookingSubtitle(): ?string
    {
        if ($this->booking->booking_type === 'pricing_plan') {
            return $this->booking->pricingPlan?->plan_type ? ucfirst($this->booking->pricingPlan->plan_type) : 'Pricing Plan';
        }

        if ($this->booking->servicePlan?->name) {
            return $this->booking->servicePlan->name;
        }

        return $this->booking->service?->category?->name;
    }

    public function billingLabel(): string
    {
        return match ($this->booking->billing_cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'one_time' => 'One-time',
            'custom' => 'Custom',
            default => 'Negotiable',
        };
    }

    public function statusColor(): string
    {
        return match ($this->booking->status) {
            'pending' => 'bg-amber-100 text-amber-700',
            'quoted' => 'bg-blue-100 text-blue-700',
            'accepted' => 'bg-cyan-100 text-cyan-700',
            'converted' => 'bg-emerald-100 text-emerald-700',
            'rejected' => 'bg-red-100 text-red-700',
            'cancelled' => 'bg-slate-100 text-slate-600',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function finalAmount(): float
    {
        return (float) ($this->booking->final_price ?? ($this->booking->quoted_price ?? ($this->booking->requested_price ?? ($this->booking->plan_price ?? 0))));
    }

    public function clientEmail(): ?string
    {
        return $this->booking->email ?: $this->booking->user?->email;
    }

    public function clientPhone(): ?string
    {
        return $this->booking->phone ?: $this->booking->user?->phone;
    }

    public function mailSubject(): string
    {
        return 'Quotation for ' . $this->bookingTitle() . ' - ' . $this->booking->booking_no;
    }

    public function mailBody(): string
    {
        $amount = number_format($this->finalAmount(), 2);

        return trim("
Dear {$this->booking->full_name},

Thank you for your booking request.

Booking No: {$this->booking->booking_no}
Service / Plan: {$this->bookingTitle()}
Billing Cycle: {$this->billingLabel()}
Quoted Amount: BDT {$amount}

Admin Note:
{$this->booking->admin_note}

Please review the quotation and let us know if you would like to proceed.

Regards,
Techwave Team
        ");
    }

    public function mailtoLink(): string
    {
        if (!$this->clientEmail()) {
            return '#';
        }

        return 'mailto:' . $this->clientEmail() . '?subject=' . rawurlencode($this->mailSubject()) . '&body=' . rawurlencode($this->mailBody());
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider {{ $this->statusColor() }}">
                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                </span>

                <span
                    class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                    {{ $booking->booking_no }}
                </span>

                @if ($booking->order)
                    <span
                        class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-700">
                        Order: {{ $booking->order->order_no }}
                    </span>
                @endif
            </div>

            <h1 class="text-h1 font-h1 text-on-surface">
                Booking Quote
            </h1>

            <p class="mt-1 text-body-md font-body-md text-secondary">
                Create, review, and send quotation to client before converting booking into order.
            </p>
        </div>

        <a href="{{ route('admin.bookings.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Bookings
        </a>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 space-y-6 lg:col-span-8">

            <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="flex items-center gap-2 text-h3 font-h2 text-on-surface">
                            <span class="material-symbols-outlined text-primary">request_quote</span>
                            Quote Preview
                        </h3>

                        <p class="mt-1 text-sm text-secondary">
                            This is the quote information you can send to the client.
                        </p>
                    </div>

                    <div class="text-left sm:text-right">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Quote Amount
                        </p>

                        <p class="mt-1 text-3xl font-bold text-primary">
                            ৳ {{ number_format($this->finalAmount(), 2) }}
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                    <div
                        class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Quotation For</p>

                            <h2 class="mt-2 text-2xl font-bold text-slate-900">
                                {{ $this->bookingTitle() }}
                            </h2>

                            @if ($this->bookingSubtitle())
                                <p class="mt-1 text-sm text-secondary">
                                    {{ $this->bookingSubtitle() }}
                                </p>
                            @endif
                        </div>

                        <div class="text-left sm:text-right">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Booking No</p>

                            <p class="mt-2 font-mono text-sm font-semibold text-slate-900">
                                {{ $booking->booking_no }}
                            </p>

                            <p class="mt-3 text-xs font-bold uppercase tracking-wider text-slate-400">Date</p>

                            <p class="mt-1 text-sm text-slate-700">
                                {{ now()->format('M d, Y') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-xl bg-white p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Client</p>

                            <p class="mt-2 font-semibold text-slate-900">
                                {{ $booking->full_name ?: $booking->user?->name ?: 'N/A' }}
                            </p>

                            @if ($this->clientEmail())
                                <a href="mailto:{{ $this->clientEmail() }}"
                                    class="mt-1 block break-all text-sm text-primary hover:underline">
                                    {{ $this->clientEmail() }}
                                </a>
                            @endif

                            @if ($this->clientPhone())
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $this->clientPhone()) }}"
                                    class="mt-1 block text-sm text-primary hover:underline">
                                    {{ $this->clientPhone() }}
                                </a>
                            @endif
                        </div>

                        <div class="rounded-xl bg-white p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Company</p>

                            <p class="mt-2 font-semibold text-slate-900">
                                {{ $booking->company_name ?: 'N/A' }}
                            </p>

                            @if ($booking->company_email)
                                <a href="mailto:{{ $booking->company_email }}"
                                    class="mt-1 block break-all text-sm text-primary hover:underline">
                                    {{ $booking->company_email }}
                                </a>
                            @endif

                            @if ($booking->company_phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->company_phone) }}"
                                    class="mt-1 block text-sm text-primary hover:underline">
                                    {{ $booking->company_phone }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Item
                                    </th>

                                    <th class="px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Billing
                                    </th>

                                    <th
                                        class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Amount
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900">
                                            {{ $this->bookingTitle() }}
                                        </p>

                                        <p class="mt-1 text-sm text-secondary">
                                            {{ $this->bookingSubtitle() ?: 'Custom service quotation' }}
                                        </p>
                                    </td>

                                    <td class="px-5 py-4 text-sm font-semibold text-slate-700">
                                        {{ $this->billingLabel() }}
                                    </td>

                                    <td class="px-5 py-4 text-right font-bold text-slate-900">
                                        ৳ {{ number_format($this->finalAmount(), 2) }}
                                    </td>
                                </tr>
                            </tbody>

                            <tfoot>
                                <tr class="border-t border-slate-200 bg-slate-50">
                                    <td colspan="2" class="px-5 py-4 text-right text-sm font-bold text-slate-700">
                                        Total Quoted Amount
                                    </td>

                                    <td class="px-5 py-4 text-right text-xl font-bold text-primary">
                                        ৳ {{ number_format($this->finalAmount(), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($booking->admin_note)
                        <div class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-500">
                                Note / Terms
                            </p>

                            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-blue-900">
                                {{ $booking->admin_note }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="mb-8 flex items-center justify-between gap-4">
                    <h3 class="flex items-center gap-2 text-h3 font-h2 text-on-surface">
                        <span class="material-symbols-outlined text-primary">event_note</span>
                        Booking Summary
                    </h3>

                    <span class="text-xs font-bold uppercase tracking-wider text-secondary">
                        Submitted {{ $booking->created_at?->diffForHumans() }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Plan / Service</p>

                        <p class="mt-2 text-base font-semibold text-slate-900">
                            {{ $this->bookingTitle() }}
                        </p>

                        <p class="mt-1 text-sm text-secondary">
                            {{ $this->bookingSubtitle() ?: 'No additional plan details available.' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Billing Cycle</p>

                        <p class="mt-2 text-base font-semibold text-slate-900">
                            {{ $this->billingLabel() }}
                        </p>

                        <p class="mt-1 text-sm text-secondary">
                            Booking with review and negotiation process.
                        </p>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-blue-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Listed Plan Price</p>

                        <p class="mt-2 text-2xl font-bold text-blue-700">
                            {{ $booking->plan_price ? '৳ ' . number_format((float) $booking->plan_price, 2) : 'N/A' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Customer Requested Price
                        </p>

                        <p class="mt-2 text-2xl font-bold text-amber-700">
                            {{ $booking->requested_price ? '৳ ' . number_format((float) $booking->requested_price, 2) : 'Not requested' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-5 md:col-span-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-500">Final / Quoted Amount</p>

                        <p class="mt-2 text-3xl font-bold text-emerald-700">
                            {{ $booking->quoted_price || $booking->final_price ? '৳ ' . number_format($this->finalAmount(), 2) : 'Not quoted yet' }}
                        </p>

                        @if ($booking->quoted_price)
                            <p class="mt-2 text-sm text-emerald-700/80">
                                This is the amount offered after negotiation.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2 text-on-surface">
                    <span class="material-symbols-outlined text-primary">notes</span>
                    Notes
                </h3>

                <div class="grid grid-cols-1 gap-5">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Customer Message</p>

                        <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">
                            {{ $booking->user_note ?: ($booking->message ?: 'No customer message provided.') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-blue-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Admin Note / Quote Terms</p>

                        <p class="mt-3 whitespace-pre-line text-sm leading-7 text-blue-900">
                            {{ $booking->admin_note ?: 'No admin note added yet.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 space-y-6 lg:col-span-4">

            <form wire:submit.prevent="saveQuote" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-5 flex items-center gap-2 text-h3 font-h2 text-on-surface">
                    <span class="material-symbols-outlined text-primary">payments</span>
                    Quote Settings
                </h3>

                <div class="space-y-5">
                    <div>
                        <label class="mb-2 block font-label-md text-on-surface">Status</label>

                        <select wire:model="status"
                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10">
                            <option value="pending">Pending</option>
                            <option value="quoted">Quoted</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                            <option value="converted">Converted</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        @error('status')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block font-label-md text-on-surface">Quoted Amount</label>

                        <input wire:model="quoted_price" type="number" step="0.01"
                            class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                            placeholder="e.g., 8500" />

                        @error('quoted_price')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block font-label-md text-on-surface">Admin Note / Quote Terms</label>

                        <textarea wire:model="admin_note" rows="6"
                            class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                            placeholder="Write negotiation details, final offer, payment terms, delivery terms, or next step..."></textarea>

                        @error('admin_note')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="saveQuote"
                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveQuote">Save Quote</span>

                        <span wire:loading wire:target="saveQuote" class="inline-flex items-center gap-2">
                            <span
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                    Send Quote
                </h3>

                <div class="space-y-3">
                    @if ($this->clientEmail())
                        <button type="button" wire:click="sendQuoteMail" wire:loading.attr="disabled"
                            wire:target="sendQuoteMail"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="material-symbols-outlined text-[18px]">outgoing_mail</span>

                            <span wire:loading.remove wire:target="sendQuoteMail">
                                Send Quote Email
                            </span>

                            <span wire:loading wire:target="sendQuoteMail">
                                Sending...
                            </span>
                        </button>

                        <a href="{{ $this->mailtoLink() }}"
                            class="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <span class="material-symbols-outlined text-[18px]">mail</span>
                            Open Manual Email
                        </a>
                    @else
                        <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                            No client email found for this booking.
                        </div>
                    @endif

                    <button type="button" onclick="window.print()"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <span class="material-symbols-outlined text-[18px]">print</span>
                        Print Quote
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                    Quick Actions
                </h3>

                <div class="space-y-3">
                    @if ($this->clientPhone())
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $this->clientPhone()) }}"
                            class="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <span class="material-symbols-outlined text-[18px]">call</span>
                            Call Customer
                        </a>
                    @endif

                    <button type="button" wire:click="markAsAccepted"
                        class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-cyan-100 bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-cyan-700 transition hover:bg-cyan-100">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        Mark Accepted
                    </button>

                    @if (!$booking->order && !in_array($booking->status, ['rejected', 'cancelled']))
                        <button type="button" wire:click="confirmToOrder"
                            wire:confirm="Confirm this booking and create an order?"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
                            <span class="material-symbols-outlined text-[18px]">shopping_cart_checkout</span>
                            Confirm & Create Order
                        </button>
                    @endif

                    <button type="button" wire:click="markAsRejected"
                        wire:confirm="Are you sure you want to reject this booking?"
                        class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-red-100 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-100">
                        <span class="material-symbols-outlined text-[18px]">block</span>
                        Reject Booking
                    </button>

                    <button type="button" wire:click="markAsCancelled"
                        wire:confirm="Are you sure you want to cancel this booking?"
                        class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        <span class="material-symbols-outlined text-[18px]">cancel</span>
                        Cancel Booking
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-5 text-h3 font-h2 text-on-surface">Linked Order</h3>

                @if ($booking->order)
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">
                            Converted to Order
                        </p>

                        <p class="mt-2 font-semibold text-emerald-900">
                            {{ $booking->order->order_no }}
                        </p>

                        <p class="mt-1 text-sm text-emerald-700">
                            ৳ {{ number_format((float) $booking->order->amount, 2) }}
                        </p>
                    </div>
                @else
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-center">
                        <span class="material-symbols-outlined text-4xl text-slate-400">shopping_cart_off</span>

                        <p class="mt-2 text-sm font-semibold text-slate-700">
                            No order linked yet
                        </p>

                        <p class="mt-1 text-xs text-secondary">
                            Once confirmed, an order will be created from this booking.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
