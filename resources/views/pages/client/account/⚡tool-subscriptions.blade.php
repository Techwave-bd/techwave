<?php

use App\Models\ToolSubscription;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My Subscriptions')] class extends Component {
    use WithPagination;

    public function subscriptions()
    {
        return ToolSubscription::query()
            ->with(['category', 'plan'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);
    }
};
?>

<div class="min-h-screen text-white">
    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                My
                <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">Subscriptions</span>
            </h1>
            <p class="mt-2 text-blue-100/60">Manage your active and past tool subscriptions.</p>
        </div>

        <div class="space-y-4">
            @forelse ($this->subscriptions() as $sub)
                <div class="rounded-2xl border border-white/15 bg-white/[0.07] p-5 shadow-[0_10px_30px_rgba(0,0,0,0.12)] backdrop-blur-2xl">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-400/10 text-cyan-300">
                                <span class="material-symbols-outlined">{{ $sub->category?->icon ?: 'build' }}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-bold text-white">{{ $sub->category?->name ?? 'Unknown' }}</h3>
                                    <span @class([
                                        'text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full',
                                        'bg-emerald-500/15 text-emerald-300' => $sub->status === 'active',
                                        'bg-amber-500/15 text-amber-300' => $sub->status === 'pending',
                                        'bg-red-500/15 text-red-300' => $sub->status === 'expired' || $sub->status === 'cancelled',
                                    ])>
                                        {{ $sub->status }}
                                    </span>
                                </div>
                                <p class="text-sm text-blue-100/50">{{ $sub->plan?->name ?? 'No plan' }} · {{ ucfirst($sub->billing_cycle) }}</p>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="text-xl font-bold text-white">৳{{ number_format((float) $sub->amount, 2) }}</p>
                            @if ($sub->expires_at)
                                <p class="text-xs text-blue-100/45">
                                    @if ($sub->status === 'active')
                                        Expires {{ $sub->expires_at->format('M d, Y') }}
                                    @elseif ($sub->status === 'pending')
                                        Pending verification
                                    @else
                                        {{ $sub->expires_at->format('M d, Y') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    @if ($sub->transaction_id)
                        <div class="mt-3 flex flex-wrap gap-4 text-xs text-blue-100/45">
                            <span>TrxID: <span class="font-mono text-cyan-300/70">{{ $sub->transaction_id }}</span></span>
                            @if ($sub->verified_at)
                                <span>Verified: {{ $sub->verified_at->format('M d, Y h:i A') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-white/15 bg-white/[0.07] p-12 text-center backdrop-blur-2xl">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/8">
                        <span class="material-symbols-outlined text-3xl text-blue-100/40">subscriptions</span>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-white">No subscriptions yet</h3>
                    <p class="mt-2 text-sm text-blue-100/50">Subscribe to a plan to unlock premium features.</p>
                    <a href="{{ route('client.tools.index') }}" wire:navigate
                        class="mt-6 inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 px-6 py-3 font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-base">workspace_premium</span>
                        Browse Tools
                    </a>
                </div>
            @endforelse

            <div class="mt-4">
                {{ $this->subscriptions()->links() }}
            </div>
        </div>
    </div>
</div>
