<?php

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\PricingPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] class extends Component {
    public PricingPlan $pricingPlan;

    public string $billing = 'monthly';

    public float $amount = 0;

    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';

    public string $customer_address = '';

    public string $company_name = '';
    public string $company_phone = '';

    public string $user_note = '';
    public ?float $requested_price = null;

    /*
    |--------------------------------------------------------------------------
    | Future Direct Payment Switch
    |--------------------------------------------------------------------------
    | Keep this false for now because all pricing plan requests will go through
    | booking/negotiation.
    |
    | Later, when you want SSLCommerz direct payment again, change this to true.
    */
    public bool $directPaymentEnabled = false;

    public function mount(PricingPlan $pricingPlan): void
    {
        abort_if($pricingPlan->status !== 'active', 404);

        $this->pricingPlan = $pricingPlan;

        $billing = request()->query('billing', 'monthly');

        $this->billing = in_array($billing, ['monthly', 'yearly'], true) ? $billing : 'monthly';

        $this->amount = $this->getAmount();

        abort_if($this->amount <= 0, 404);

        $this->fillUserAndCompanyInfo();
    }

    protected function rules(): array
    {
        return [
            'billing' => ['required', 'in:monthly,yearly'],

            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],

            'company_name' => ['required', 'string', 'max:255'],
            'company_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],

            'customer_address' => ['required', 'string', 'max:500'],
            'requested_price' => ['nullable', 'numeric', 'min:0'],
            'user_note' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'customer_name.required' => 'Please enter your full name.',
            'customer_email.required' => 'Please enter your email address.',
            'customer_email.email' => 'Please enter a valid email address.',
            'customer_phone.required' => 'Please enter your phone number.',
            'customer_phone.regex' => 'Please enter a valid Bangladeshi phone number.',

            'company_name.required' => 'Please enter your company name.',
            'company_phone.required' => 'Please enter your company phone number.',
            'company_phone.regex' => 'Please enter a valid Bangladeshi company phone number.',

            'customer_address.required' => 'Please enter your company address.',
            'requested_price.numeric' => 'Requested price must be a valid number.',
            'requested_price.min' => 'Requested price cannot be negative.',
        ];
    }

    public function updatedBilling(): void
    {
        $this->billing = in_array($this->billing, ['monthly', 'yearly'], true) ? $this->billing : 'monthly';

        $this->amount = $this->getAmount();
    }

    public function updated($property): void
    {
        $this->validateOnly($property);
    }

    public function fillUserAndCompanyInfo(): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

        $this->customer_name = (string) old('customer_name', $user->name ?? '');
        $this->customer_email = (string) old('customer_email', $user->email ?? '');
        $this->customer_phone = (string) old('customer_phone', $user->phone ?? '');

        if (method_exists($user, 'isCompanyAccount') && $user->isCompanyAccount() && $user->company) {
            $this->company_name = (string) old('company_name', $user->company->company_name ?? '');
            $this->company_phone = (string) old('company_phone', $user->company->phone ?? '');
            $this->customer_address = (string) old('customer_address', $user->company->address ?? '');

            return;
        }

        $this->company_name = (string) old('company_name', '');
        $this->company_phone = (string) old('company_phone', '');
        $this->customer_address = (string) old('customer_address', '');
    }

    public function submitBooking(): void
    {
        if (!Auth::check()) {
            $this->dispatch('toast', message: 'Please login first to book a plan.', type: 'error');

            return;
        }

        abort_if($this->pricingPlan->status !== 'active', 404);

        if ($this->userHasActiveOrPendingBooking(Auth::id(), $this->pricingPlan->id)) {
            $this->dispatch('toast', message: 'You already have this plan pending or active. You cannot book the same plan again until it is completed or cancelled.', type: 'warning');

            return;
        }

        $validated = $this->validate();

        $planPrice = $this->getAmount();

        abort_if($planPrice <= 0, 404);

        $booking = Booking::query()->create([
            'user_id' => Auth::id(),

            'booking_no' => $this->makeBookingNo(),
            'booking_type' => 'pricing_plan',

            'service_id' => null,
            'service_plan_id' => null,
            'pricing_plan_id' => $this->pricingPlan->id,

            'billing_cycle' => $validated['billing'],

            'full_name' => $validated['customer_name'],
            'phone' => $this->normalizeBdPhone($validated['customer_phone']),
            'email' => $validated['customer_email'],

            'company_name' => $validated['company_name'],
            'company_phone' => $this->normalizeBdPhone($validated['company_phone']),

            'plan_name' => $this->pricingPlan->title,
            'plan_price' => $planPrice,
            'requested_price' => $validated['requested_price'] ?? null,
            'quoted_price' => null,
            'final_price' => null,
            'currency' => 'BDT',

            'message' => $this->buildMessageWithAddress($validated['customer_address'], $validated['user_note'] ?? null),
            'user_note' => $validated['user_note'] ?? null,
            'admin_note' => null,

            'status' => 'pending',

            'pricing_order_id' => null,
            'admin_read_at' => null,
        ]);

        BookingCreated::dispatch($booking);

        $this->dispatch('toast', message: 'Your plan booking request has been submitted successfully. Our team will review it and contact you soon.', type: 'success');

        $this->redirectRoute('account.services', ['tab' => 'plans'], navigate: true);
    }

    public function getAmount(): float
    {
        return (float) ($this->billing === 'yearly' ? $this->pricingPlan->yearly_price : $this->pricingPlan->monthly_price);
    }

    public function billingLabel(): string
    {
        return $this->billing === 'yearly' ? 'Yearly' : 'Monthly';
    }

    /*
    |--------------------------------------------------------------------------
    | Future SSLCommerz Helpers
    |--------------------------------------------------------------------------
    | These are kept for your future SSLCommerz direct-payment flow.
    */
    public function paymentAction(): string
    {
        return route('client.checkout.pricing.pay', $this->pricingPlan->id);
    }

    private function makeBookingNo(): string
    {
        do {
            $bookingNo = 'BK-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (Booking::query()->where('booking_no', $bookingNo)->exists());

        return $bookingNo;
    }

    private function normalizeBdPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[\s\-()]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '88')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }

    private function buildMessageWithAddress(string $address, ?string $userNote = null): string
    {
        $message = "Company Address:\n" . $address;

        if ($userNote) {
            $message .= "\n\nRequirement / Message:\n" . $userNote;
        }

        return $message;
    }

    private function userHasActiveOrPendingBooking(int $userId, int $pricingPlanId): bool
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->where('booking_type', 'pricing_plan')
            ->where('pricing_plan_id', $pricingPlanId)
            ->whereIn('status', ['pending', 'quoted', 'accepted', 'converted'])
            ->exists();
    }
};
?>

<div>
    <section class="min-h-screen py-10 text-white">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-10 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-cyan-300">
                    {{ $directPaymentEnabled ? 'Secure Checkout' : 'Booking Request' }}
                </p>

                <h1 class="mt-3 text-3xl font-bold sm:text-4xl lg:text-5xl">
                    {{ $directPaymentEnabled ? 'Complete Your Order' : 'Submit Your Plan Booking' }}
                </h1>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-blue-100/60 sm:text-base">
                    @if ($directPaymentEnabled)
                        Review your selected plan and continue to secure payment.
                    @else
                        All plans are negotiable. Submit your details and our team will review your request before final
                        confirmation.
                    @endif
                </p>
            </div>

            <form wire:submit.prevent="submitBooking" novalidate>

                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-red-300/20 bg-red-400/10 p-4 text-sm text-red-100">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-red-200">error</span>

                            <div>
                                <p class="font-semibold">Please fix the following errors:</p>

                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @error('pricing_plan')
                    <div class="mb-6 rounded-2xl border border-red-300/20 bg-red-400/10 p-4 text-sm text-red-100">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-red-200">error</span>
                            <p>{{ $message }}</p>
                        </div>
                    </div>
                @enderror

                <div class="grid gap-8 lg:grid-cols-[1fr_420px]">

                    {{-- Left --}}
                    <div class="space-y-6">

                        {{-- Billing Option --}}
                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                            <div class="mb-6 flex items-start gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-blue-300/20 bg-blue-300/10 text-blue-100">
                                    <span class="material-symbols-outlined">calendar_month</span>
                                </div>

                                <div>
                                    <h2 class="text-xl font-bold">Billing Cycle</h2>
                                    <p class="mt-1 text-sm text-blue-100/55">
                                        Choose your preferred billing cycle. Final price will be confirmed after review.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <button type="button" wire:click="$set('billing', 'monthly')"
                                    class="group cursor-pointer rounded-2xl border p-5 text-left transition
                                    {{ $billing === 'monthly'
                                        ? 'border-cyan-300/60 bg-cyan-300/10 shadow-lg shadow-cyan-500/10'
                                        : 'border-white/10 bg-white/5 hover:border-white/20 hover:bg-white/10' }}">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="font-bold text-white">Monthly</p>
                                            <p class="mt-1 text-sm text-blue-100/55">
                                                {{ $directPaymentEnabled ? 'Direct payment' : 'Negotiable booking' }}
                                            </p>
                                        </div>

                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-full border
                                            {{ $billing === 'monthly' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-white/20 text-transparent' }}">
                                            <span class="material-symbols-outlined text-base">check</span>
                                        </div>
                                    </div>

                                    <p class="mt-4 text-2xl font-bold">
                                        ৳{{ number_format((float) $pricingPlan->monthly_price, 2) }}
                                    </p>
                                </button>

                                <button type="button" wire:click="$set('billing', 'yearly')"
                                    class="group cursor-pointer rounded-2xl border p-5 text-left transition
                                    {{ $billing === 'yearly'
                                        ? 'border-cyan-300/60 bg-cyan-300/10 shadow-lg shadow-cyan-500/10'
                                        : 'border-white/10 bg-white/5 hover:border-white/20 hover:bg-white/10' }}">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="font-bold text-white">Yearly</p>
                                            <p class="mt-1 text-sm text-blue-100/55">
                                                {{ $directPaymentEnabled ? 'Direct payment' : 'Negotiable booking' }}
                                            </p>
                                        </div>

                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-full border
                                            {{ $billing === 'yearly' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-white/20 text-transparent' }}">
                                            <span class="material-symbols-outlined text-base">check</span>
                                        </div>
                                    </div>

                                    <p class="mt-4 text-2xl font-bold">
                                        ৳{{ number_format((float) $pricingPlan->yearly_price, 2) }}
                                    </p>
                                </button>
                            </div>

                            @error('billing')
                                <p class="mt-3 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Personal Information --}}
                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                            <div class="mb-6 flex items-start gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/20 bg-cyan-300/10 text-cyan-200">
                                    <span class="material-symbols-outlined">person</span>
                                </div>

                                <div>
                                    <h2 class="text-xl font-bold">Personal Information</h2>
                                    <p class="mt-1 text-sm text-blue-100/55">
                                        This information will be used for booking and communication.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="customer_name"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Full Name <span class="text-red-300">*</span>
                                    </label>

                                    <input id="customer_name" type="text" wire:model.live="customer_name"
                                        placeholder="Your full name"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('customer_name')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="customer_phone"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Phone Number <span class="text-red-300">*</span>
                                    </label>

                                    <input id="customer_phone" type="text" wire:model.live="customer_phone"
                                        placeholder="Enter phone number"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('customer_phone')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="customer_email"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Email Address <span class="text-red-300">*</span>
                                    </label>

                                    <input id="customer_email" type="email" wire:model.live="customer_email"
                                        @auth readonly @endauth placeholder="Enter your email address"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15 {{ auth()->check() ? 'cursor-not-allowed opacity-80' : '' }}">

                                    @error('customer_email')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Company Information --}}
                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                            <div class="mb-6 flex items-start gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-blue-300/20 bg-blue-300/10 text-blue-100">
                                    <span class="material-symbols-outlined">business_center</span>
                                </div>

                                <div>
                                    <h2 class="text-xl font-bold">Company Information</h2>
                                    <p class="mt-1 text-sm text-blue-100/55">
                                        If your account has a company profile, this information will be loaded
                                        automatically.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="company_name" class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Name <span class="text-red-300">*</span>
                                    </label>

                                    <input id="company_name" type="text" wire:model.live="company_name"
                                        placeholder="Your company name"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('company_name')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="">
                                    <label for="company_phone"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Phone <span class="text-red-300">*</span>
                                    </label>

                                    <input id="company_phone" type="text" wire:model.live="company_phone"
                                        placeholder="Company phone number"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('company_phone')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="customer_address"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Address <span class="text-red-300">*</span>
                                    </label>

                                    <textarea id="customer_address" wire:model.live="customer_address" rows="3" placeholder="House / Road / Area"
                                        class="w-full resize-none rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15"></textarea>

                                    @error('customer_address')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                @unless ($directPaymentEnabled)
                                    <div class="sm:col-span-2">
                                        <label for="requested_price"
                                            class="mb-2 block text-sm font-semibold text-blue-100/80">
                                            Requested Price
                                        </label>

                                        <input id="requested_price" type="number" step="0.01" min="0"
                                            wire:model.live.debounce.500ms="requested_price"
                                            placeholder="Your expected {{ $billing }} price"
                                            class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                        @error('requested_price')
                                            <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endunless

                                <div class="sm:col-span-2">
                                    <label for="user_note" class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Requirement / Message
                                    </label>

                                    <textarea id="user_note" wire:model.live="user_note" rows="4"
                                        placeholder="Write your requirement, expected service details, or negotiation message..."
                                        class="w-full resize-none rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15"></textarea>

                                    @error('user_note')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Summary --}}
                    <div class="lg:sticky lg:top-24 lg:self-start">
                        <div
                            class="overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-2xl shadow-blue-950/30 backdrop-blur-xl">
                            <div class="border-b border-white/10 p-6 sm:p-8">
                                <div class="flex items-start gap-4">
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-blue-500 to-cyan-400 text-white shadow-lg shadow-blue-500/20">
                                        <span class="material-symbols-outlined">
                                            {{ $directPaymentEnabled ? 'receipt_long' : 'contract' }}
                                        </span>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-bold">
                                            {{ $directPaymentEnabled ? 'Order Summary' : 'Booking Summary' }}
                                        </h2>
                                        <p class="mt-1 text-sm text-blue-100/55">
                                            {{ $directPaymentEnabled ? 'Review your order before payment.' : 'Review your booking request before submission.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 p-6 sm:p-8">
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-sm text-blue-100/55">Selected Plan</p>
                                    <h3 class="mt-1 text-xl font-bold text-white">
                                        {{ $pricingPlan->title }}
                                    </h3>

                                    @if ($pricingPlan->description)
                                        <p class="mt-2 line-clamp-2 text-sm text-blue-100/50">
                                            {{ $pricingPlan->description }}
                                        </p>
                                    @endif
                                </div>

                                <div class="space-y-3 rounded-2xl border border-white/10 bg-white/5 p-5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <span class="text-blue-100/60">Billing Type</span>
                                        <span class="font-semibold capitalize text-white">{{ $billing }}</span>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <span class="text-blue-100/60">
                                            {{ $directPaymentEnabled ? 'Subtotal' : 'Listed ' . $this->billingLabel() . ' Price' }}
                                        </span>

                                        <span class="font-semibold text-white">
                                            ৳{{ number_format($this->getAmount(), 2) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <span class="text-blue-100/60">Pricing Status</span>
                                        <span class="font-semibold text-amber-300">Negotiable</span>
                                    </div>

                                    @if ($requested_price)
                                        <div class="flex justify-between gap-4">
                                            <span class="text-blue-100/60">Requested Price</span>

                                            <span class="font-semibold text-cyan-300">
                                                ৳{{ number_format((float) $requested_price, 2) }}
                                            </span>
                                        </div>
                                    @endif

                                    <div
                                        class="rounded-2xl border border-amber-300/15 bg-amber-300/10 p-4 text-xs leading-6 text-amber-100/90">
                                        <div class="mb-2 flex items-center gap-2 font-bold text-amber-200">
                                            <span class="material-symbols-outlined text-base">info</span>
                                            No payment required now
                                        </div>

                                        Our team will review your request and send a final quoted price before payment.
                                    </div>
                                </div>

                                @auth
                                    <button type="submit" wire:loading.attr="disabled" wire:target="submitBooking"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 font-bold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:shadow-blue-500/40 disabled:cursor-not-allowed disabled:opacity-60">
                                        <span wire:loading.remove wire:target="submitBooking"
                                            class="inline-flex items-center gap-2">
                                            <span class="material-symbols-outlined text-xl">
                                                {{ $directPaymentEnabled ? 'lock' : 'send' }}
                                            </span>

                                            {{ $directPaymentEnabled ? 'CONTINUE TO PAYMENT' : 'SUBMIT BOOKING REQUEST' }}
                                        </span>

                                        <span wire:loading wire:target="submitBooking"
                                            class="inline-flex items-center gap-2">
                                            <span
                                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                            SUBMITTING...
                                        </span>
                                    </button>
                                @else
                                    <button type="button" x-data
                                        @click="$dispatch('toast', {
                                            type: 'error',
                                            message: 'Please login first to book a plan.'
                                        })"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 font-bold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:shadow-blue-500/40">
                                        <span class="material-symbols-outlined text-xl">lock</span>

                                        {{ $directPaymentEnabled ? 'CONTINUE TO PAYMENT' : 'SUBMIT BOOKING REQUEST' }}
                                    </button>
                                @endauth

                                <div
                                    class="flex items-center justify-center gap-2 text-center text-xs text-blue-100/50">
                                    @if ($directPaymentEnabled)
                                        <span class="material-symbols-outlined text-base">verified_user</span>
                                        Secure payment powered by SSLCommerz
                                    @else
                                        <span class="material-symbols-outlined text-base">support_agent</span>
                                        Our team will contact you after review.
                                    @endif
                                </div>

                                <div class="hidden">
                                    Future SSLCommerz payment route:
                                    {{ $this->paymentAction() }}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>
</div>
