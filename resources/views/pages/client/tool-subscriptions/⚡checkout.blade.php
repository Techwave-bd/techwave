<?php

use App\Models\SiteSetting;
use App\Models\ToolPlan;
use App\Models\ToolSubscription;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscribe')] class extends Component {
    public ToolPlan $plan;

    public string $billing = 'monthly';
    public float $amount = 0;

    public string $sender_bkash = '';
    public string $transaction_id = '';

    public bool $submitted = false;

    public function mount(ToolPlan $plan): void
    {
        abort_if(!$plan->is_active, 404);

        $this->plan = $plan;

        $billing = request()->query('billing', 'monthly');

        $this->billing = in_array($billing, ['monthly', 'yearly'], true) ? $billing : 'monthly';

        $this->recalculateAmount();

        abort_if($this->amount <= 0, 404);
    }

    public function updatedBilling(): void
    {
        $this->recalculateAmount();
    }

    private function recalculateAmount(): void
    {
        $this->amount = $this->billing === 'monthly' ? (float) ($this->plan->monthly_price ?? 0) : (float) ($this->plan->yearly_price ?? 0);
    }

    public function siteSetting()
    {
        return SiteSetting::current();
    }

    protected function rules(): array
    {
        return [
            'sender_bkash' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],
            'transaction_id' => ['required', 'string', 'max:50'],
        ];
    }

    protected function messages(): array
    {
        return [
            'sender_bkash.required' => 'Please enter your bKash number.',
            'sender_bkash.regex' => 'Please enter a valid Bangladeshi bKash number.',
            'transaction_id.required' => 'Please enter the transaction ID.',
        ];
    }

    public function submitPayment(): void
    {
        $validated = $this->validate();

        $existingSubscription = auth()
            ->user()
            ->toolSubscriptions()
            ->where('tool_category_id', $this->plan->tool_category_id)
            ->whereIn('status', ['pending', 'active'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($existingSubscription) {
            $this->dispatch('toast', message: 'You already have an active or pending subscription for this tool. Please cancel it or wait until it expires before booking again.', type: 'error');

            return;
        }

        $now = now();
        $expiresAt = $this->billing === 'monthly' ? $now->copy()->addMonth() : $now->copy()->addYear();

        ToolSubscription::create([
            'user_id' => auth()->id(),
            'tool_category_id' => $this->plan->tool_category_id,
            'tool_plan_id' => $this->plan->id,
            'billing_cycle' => $this->billing,
            'amount' => $this->amount,
            'status' => 'pending',
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'sender_bkash' => $validated['sender_bkash'],
            'transaction_id' => $validated['transaction_id'],
        ]);

        $this->submitted = true;

        $this->dispatch('toast', message: 'Payment submitted successfully! We will verify and activate your subscription shortly.', type: 'success');
    }
};
?>

<div class="min-h-screen text-white bg">
    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-10 text-center">
            <a href="{{ route('client.tools.index') }}" wire:navigate
                class="inline-flex items-center gap-1 text-sm text-blue-100/50 hover:text-cyan-300 transition-colors mb-4">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Back to tools
            </a>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">
                Complete your
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">subscription</span>
            </h1>
        </div>

        @if ($submitted)
            {{-- Success State --}}
            <div
                class="relative mx-auto max-w-3xl overflow-hidden rounded-4xl border border-emerald-300/20 bg-white/[0.07] p-8 text-center shadow-[0_30px_90px_rgba(16,185,129,0.18)] backdrop-blur-2xl sm:p-10">

                {{-- Background Effects --}}
                <div class="pointer-events-none absolute inset-0">
                    <div
                        class="absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-emerald-400/20 blur-3xl">
                    </div>
                    <div class="absolute -bottom-28 -left-20 h-72 w-72 rounded-full bg-cyan-400/15 blur-3xl"></div>
                    <div class="absolute -right-24 top-20 h-64 w-64 rounded-full bg-blue-500/15 blur-3xl"></div>

                    <div
                        class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.12),transparent_35%)]">
                    </div>
                    <div
                        class="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.08),transparent_45%,rgba(59,130,246,0.08))]">
                    </div>
                </div>

                <div class="relative z-10">
                    {{-- <div
                        class="mx-auto flex h-24 w-24 items-center justify-center rounded-full border border-emerald-300/25 bg-emerald-500/15 shadow-lg shadow-emerald-500/20">
                        <span class="material-symbols-outlined text-5xl text-emerald-300">check_circle</span>
                    </div> --}}

                    <div
                        class="mt-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-4 py-2 text-xs font-bold uppercase tracking-wider text-emerald-300">
                        <span class="material-symbols-outlined text-sm">verified</span>
                        Verification Pending
                    </div>

                    <h2 class="mt-5 text-3xl font-extrabold text-white sm:text-4xl">
                        Payment Submitted!
                    </h2>

                    <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-blue-100/65 sm:text-base">
                        Your payment request has been received. We will verify your transaction and activate your
                        subscription shortly.
                    </p>

                    <div class="mx-auto mt-6 max-w-md rounded-2xl border border-white/10 bg-black/15 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-100/40">
                            Transaction ID
                        </p>
                        <p class="mt-1 break-all font-mono text-sm font-bold text-cyan-300">
                            {{ $transaction_id }}
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('account.tool-subscriptions') }}" wire:navigate
                            class="group inline-flex items-center gap-2 rounded-full bg-linear-to-r from-cyan-500 to-blue-500 px-6 py-3 font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5">
                            <span class="material-symbols-outlined text-base">subscriptions</span>
                            View My Subscriptions
                        </a>

                        <a href="{{ route('client.tools.index') }}" wire:navigate
                            class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-6 py-3 font-semibold text-white transition hover:bg-white/12">
                            <span class="material-symbols-outlined text-base">explore</span>
                            Browse Tools
                        </a>
                    </div>
                </div>
            </div>
        @else
            {{-- Billing Toggle --}}
            <div class="mb-8">
                <div
                    class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-8">
                    <div class="mb-6 flex items-start gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-blue-300/20 bg-blue-300/10 text-blue-100">
                            <span class="material-symbols-outlined">calendar_month</span>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">Billing Cycle</h2>
                            <p class="mt-1 text-sm text-blue-100/55">
                                Choose your preferred billing cycle. Save more with yearly.
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
                                    <p class="mt-1 text-sm text-blue-100/55">Pay per month</p>
                                </div>
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full border
                                    {{ $billing === 'monthly' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-white/20 text-transparent' }}">
                                    <span class="material-symbols-outlined text-base">check</span>
                                </div>
                            </div>

                            <p class="mt-4 text-2xl font-bold text-white">
                                ৳{{ number_format((float) $plan->monthly_price, 2) }}
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
                                        @if ($plan->yearly_price && $plan->monthly_price)
                                            @php $savings = (float) $plan->monthly_price * 12 - (float) $plan->yearly_price; @endphp
                                            @if ($savings > 0)
                                                <span
                                                    class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Save
                                                    ৳{{ number_format($savings, 2) }}</span>
                                            @endif
                                        @endif
                                    </p>
                                </div>
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full border
                                    {{ $billing === 'yearly' ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-white/20 text-transparent' }}">
                                    <span class="material-symbols-outlined text-base">check</span>
                                </div>
                            </div>

                            <p class="mt-4 text-2xl font-bold text-white">
                                ৳{{ number_format((float) $plan->yearly_price, 2) }}
                            </p>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-[1fr_420px]">
                {{-- Left: Payment Form --}}
                <div
                    class="rounded-4xl border border-white/15 bg-white/[0.07] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-2xl sm:p-8">
                    <h2 class="text-xl font-bold text-white">Pay with bKash</h2>
                    <p class="mt-1 text-sm text-blue-100/50">
                        Send the exact amount to our bKash number and submit your transaction details.
                    </p>

                    {{-- bKash Payment Info --}}
                    <div class="mt-6 overflow-hidden rounded-2xl border border-cyan-400/15 bg-cyan-400/5">
                        <div class="bg-cyan-400/10 px-5 py-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-cyan-300">Send Money To</p>
                        </div>
                        <div class="p-5">
                            <p class="text-2xl font-extrabold text-white tracking-wider">
                                {{ $this->siteSetting()->bkash_number ?? 'Not set' }}</p>
                            <p class="mt-1 text-sm text-blue-100/50">bKash Merchant Number</p>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span
                                    class="text-3xl font-extrabold text-white">৳{{ number_format($amount, 2) }}</span>
                                <span class="text-blue-100/45 text-sm">BDT</span>
                            </div>
                        </div>
                    </div>

                    {{-- How to pay tutorial (click to reveal) --}}
                    @if ($this->siteSetting()->bkash_instructions)
                        <div x-data="{ show: false }"
                            class="mt-4 overflow-hidden rounded-xl border border-white/10 bg-black/10">
                            <button type="button" @click="show = !show"
                                class="flex w-full items-center gap-2 px-4 py-3 text-left transition hover:bg-white/5 cursor-pointer">
                                <span class="material-symbols-outlined text-base text-cyan-300">info</span>
                                <span class="text-xs font-semibold uppercase tracking-wider text-cyan-300/70">How to
                                    pay</span>
                                <span class="ml-auto text-blue-100/40 transition" :class="{ 'rotate-180': show }">
                                    <span class="material-symbols-outlined text-base">expand_more</span>
                                </span>
                            </button>
                            <div x-show="show" x-transition class="border-t border-white/10 px-4 py-3 space-y-1">
                                @foreach (explode("\n", $this->siteSetting()->bkash_instructions) as $line)
                                    @if (trim($line))
                                        <p class="text-xs text-blue-100/60">{{ $line }}</p>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Payment Form --}}
                    <form wire:submit.prevent="submitPayment" class="mt-6 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-blue-100/80">Your bKash Number</label>
                            <input type="text" wire:model="sender_bkash"
                                class="w-full rounded-xl border border-white/15 bg-white/8 px-4 py-3 text-white placeholder-blue-100/30 outline-none transition focus:border-cyan-400/50 focus:ring-2 focus:ring-cyan-400/10"
                                placeholder="01XXXXXXXXX" />
                            @error('sender_bkash')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-blue-100/80">bKash Transaction ID
                                (TrxID)</label>
                            <input type="text" wire:model="transaction_id"
                                class="w-full rounded-xl border border-white/15 bg-white/8 px-4 py-3 text-white placeholder-blue-100/30 outline-none transition focus:border-cyan-400/50 focus:ring-2 focus:ring-cyan-400/10"
                                placeholder="Enter the TrxID from your bKash app" />
                            @error('transaction_id')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" wire:loading.attr="disabled"
                            class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-500 px-6 py-4 font-bold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 disabled:opacity-60 cursor-pointer">
                            <span
                                class="absolute inset-y-0 -left-1/2 w-1/2 skew-x-[-20deg] bg-white/20 transition-all duration-700 group-hover:left-full"></span>
                            <span wire:loading.remove wire:target="submitPayment"
                                class="relative flex items-center gap-2">
                                <span class="material-symbols-outlined">lock</span>
                                Submit Payment
                            </span>
                            <span wire:loading wire:target="submitPayment" class="relative flex items-center gap-2">
                                <span
                                    class="h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Submitting...
                            </span>
                        </button>
                    </form>
                </div>

                {{-- Right: Order Summary --}}
                <div
                    class="rounded-4xl border border-white/15 bg-white/[0.07] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)] backdrop-blur-2xl sm:p-8 lg:sticky lg:top-24 lg:self-start">
                    <h2 class="text-lg font-bold text-white">Order Summary</h2>

                    <div class="mt-5 space-y-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-400/10 text-cyan-300">
                                <span class="material-symbols-outlined">{{ $plan->toolCategory?->icon ?: 'build' }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $plan->toolCategory?->name ?? 'Category' }}
                                </p>
                                <p class="text-xs text-blue-100/45">{{ $plan->name }} Plan</p>
                            </div>
                        </div>

                        <div class="border-t border-white/10 pt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-blue-100/60">Plan</span>
                                <span class="text-white font-medium">{{ $plan->name }}</span>
                            </div>
                            <div class="mt-2 flex justify-between text-sm">
                                <span class="text-blue-100/60">Billing</span>
                                <span class="font-medium capitalize text-cyan-300">{{ $billing }}</span>
                            </div>
                            {{-- <div class="mt-2 flex justify-between text-sm">
                                <span class="text-blue-100/60">Max file upload</span>
                                <span
                                    class="text-white font-medium">{{ number_format($plan->max_file_upload) }}</span>
                            </div> --}}
                            <div class="mt-4 border-t border-white/10 pt-4 flex justify-between">
                                <span class="text-base font-bold text-white">Total</span>
                                <span
                                    class="text-xl font-extrabold text-cyan-300">৳{{ number_format($amount, 2) }}</span>
                            </div>
                        </div>

                        @if ($plan->features)
                            <div class="border-t border-white/10 pt-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-blue-100/45 mb-3">What's
                                    included</p>
                                <ul class="space-y-2">
                                    @foreach ($plan->features as $feature)
                                        <li class="flex items-start gap-2 text-sm text-blue-100/70">
                                            <span class="mt-0.5 text-emerald-400 text-xs">✓</span>
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
