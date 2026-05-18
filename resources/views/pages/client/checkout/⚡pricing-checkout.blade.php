<?php

use App\Models\PricingPlan;
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
    public string $company_email = '';

    public string $user_note = '';
    public ?float $requested_price = null;

    /*
    |--------------------------------------------------------------------------
    | Future Direct Payment Switch
    |--------------------------------------------------------------------------
    | Keep this false for now because all pricing plan requests will go through
    | booking/negotiation. Later, when you want SSLCommerz direct payment again,
    | change this to true and update the form action logic if needed.
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

    public function updatedBilling(): void
    {
        $this->billing = in_array($this->billing, ['monthly', 'yearly'], true) ? $this->billing : 'monthly';

        $this->amount = $this->getAmount();
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

        if ($user->isCompanyAccount() && $user->company) {
            $this->company_name = (string) old('company_name', $user->company->company_name ?? '');
            $this->company_phone = (string) old('company_phone', $user->company->phone ?? '');
            $this->company_email = (string) old('company_email', $user->company->email ?? '');
            $this->customer_address = (string) old('customer_address', $user->company->address ?? '');

            return;
        }

        $this->company_name = (string) old('company_name', '');
        $this->company_phone = (string) old('company_phone', '');
        $this->company_email = (string) old('company_email', '');
        $this->customer_address = (string) old('customer_address', '');
    }

    public function getAmount(): float
    {
        return (float) ($this->billing === 'yearly' ? $this->pricingPlan->yearly_price : $this->pricingPlan->monthly_price);
    }

    public function billingLabel(): string
    {
        return $this->billing === 'yearly' ? 'Yearly' : 'Monthly';
    }

    public function bookingAction(): string
    {
        return route('client.checkout.pricing.booking', $this->pricingPlan->id);
    }

    public function paymentAction(): string
    {
        return route('client.checkout.pricing.pay', $this->pricingPlan->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Future Payment Helpers
    |--------------------------------------------------------------------------
    | These are kept for your future SSLCommerz direct-payment flow.
    */
    public function getTaxAmount(): float
    {
        return $this->getAmount() * 0.15;
    }

    public function getTotalAmount(): float
    {
        return $this->getAmount() + $this->getTaxAmount();
    }
};
?>

<div>
    <section class="min-h-screen py-10 text-white">
        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-10 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-cyan-300">
                    Booking Request
                </p>

                <h1 class="mt-3 text-3xl font-bold sm:text-4xl lg:text-5xl">
                    Submit Your Plan Booking
                </h1>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-blue-100/60 sm:text-base">
                    All plans are negotiable. Submit your details and our team will review your request before final
                    confirmation.
                </p>
            </div>

            <form method="POST" novalidate
                @guest
x-data
                    @submit.prevent="$dispatch('toast', {
                        type: 'error',
                        message: 'Please login first to book a plan.'
                    })" @endguest
                action="{{ $this->bookingAction() }}">
                @csrf

                @error('pricing_plan')
                    <div class="mb-6 rounded-2xl border border-red-300/20 bg-red-400/10 p-4 text-sm text-red-100">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-red-200">error</span>
                            <p>{{ $message }}</p>
                        </div>
                    </div>
                @enderror

                <input type="hidden" name="billing" value="{{ $billing }}">
                <input type="hidden" name="plan_price" value="{{ $this->getAmount() }}">

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
                                                Negotiable booking
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
                                                Negotiable booking
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
                                <input type="hidden" name="customer_email"
                                    value="{{ old('customer_email', $customer_email) }}">

                                <div>
                                    <label for="customer_name"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Full Name <span class="text-red-300">*</span>
                                    </label>

                                    <input id="customer_name" type="text" name="customer_name"
                                        value="{{ old('customer_name', $customer_name) }}" placeholder="Your full name"
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

                                    <input id="customer_phone" type="text" name="customer_phone"
                                        value="{{ old('customer_phone', $customer_phone) }}"
                                        placeholder="Enter phone number"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('customer_phone')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                @error('customer_email')
                                    <div class="sm:col-span-2">
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    </div>
                                @enderror
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

                                    <input id="company_name" type="text" name="company_name"
                                        value="{{ old('company_name', $company_name) }}"
                                        placeholder="Your company name"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('company_name')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="company_email"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Email <span class="text-red-300">*</span>
                                    </label>

                                    <input id="company_email" type="email" name="company_email"
                                        value="{{ old('company_email', $company_email) }}"
                                        placeholder="company@example.com"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('company_email')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="company_phone"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Company Phone <span class="text-red-300">*</span>
                                    </label>

                                    <input id="company_phone" type="text" name="company_phone"
                                        value="{{ old('company_phone', $company_phone) }}"
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

                                    <textarea id="customer_address" name="customer_address" rows="3" placeholder="House / Road / Area"
                                        class="w-full resize-none rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">{{ old('customer_address', $customer_address) }}</textarea>

                                    @error('customer_address')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="requested_price"
                                        class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Requested Price
                                    </label>

                                    <input id="requested_price" type="number" step="0.01" min="0"
                                        name="requested_price" wire:model.live="requested_price"
                                        placeholder="Your expected {{ $billing }} price"
                                        class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">

                                    @error('requested_price')
                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="user_note" class="mb-2 block text-sm font-semibold text-blue-100/80">
                                        Requirement / Message
                                    </label>

                                    <textarea id="user_note" name="user_note" rows="4"
                                        placeholder="Write your requirement, expected service details, or negotiation message..."
                                        class="w-full resize-none rounded-2xl border border-white/10 bg-white/10 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-blue-100/35 focus:border-cyan-300/70 focus:bg-white/15">{{ old('user_note', $user_note) }}</textarea>

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
                                            contract
                                        </span>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-bold">
                                            Booking Summary
                                        </h2>
                                        <p class="mt-1 text-sm text-blue-100/55">
                                            Review your booking request before submission.
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
                                        <span class="text-blue-100/60">Listed {{ $this->billingLabel() }} Price</span>

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
                                    <button type="submit"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 font-bold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:shadow-blue-500/40">
                                        <span class="material-symbols-outlined text-xl">
                                            send
                                        </span>

                                        SUBMIT BOOKING REQUEST
                                    </button>
                                @else
                                    <button type="button" x-data
                                        @click="$dispatch('toast', {
                                            type: 'error',
                                            message: 'Please login first to book a plan.'
                                        })"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-4 font-bold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:shadow-blue-500/40">
                                        <span class="material-symbols-outlined text-xl">lock</span>

                                        SUBMIT BOOKING REQUEST
                                    </button>
                                @endauth

                                <div
                                    class="flex items-center justify-center gap-2 text-center text-xs text-blue-100/50">
                                    <span class="material-symbols-outlined text-base">support_agent</span>
                                    Our team will contact you after review.
                                </div>

                                @if ($directPaymentEnabled)
                                    <div class="hidden">
                                        Future SSLCommerz payment route:
                                        {{ $this->paymentAction() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>
</div>
