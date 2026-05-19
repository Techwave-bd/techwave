<?php

use App\Events\SupportTicketUpdated;
use App\Models\Order;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    use WithFileUploads;

    public string $search = '';
    public string $status = 'all';

    public bool $showCreateModal = false;

    public string $subject = '';
    public string $department = 'General Support';
    public string $priority = 'medium';
    public string $message = '';

    public array $images = [];

    public int $refreshKey = 0;

    public function getListeners(): array
    {
        return [
            'echo-private:user.' . Auth::id() . '.tickets,.ticket.updated' => 'refreshTicketsFromBroadcast',
        ];
    }

    public function refreshTicketsFromBroadcast(): void
    {
        $this->refreshKey++;
    }

    public function activeOrder(): ?Order
    {
        return Order::query()
            ->with(['pricingPlan', 'service', 'servicePlan'])
            ->where('user_id', Auth::id())
            ->whereIn('status', ['paid', 'active'])
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->latest('end_date')
            ->latest()
            ->first();
    }

    public function latestOrder(): ?Order
    {
        return Order::query()
            ->with(['pricingPlan', 'service', 'servicePlan'])
            ->where('user_id', Auth::id())
            ->latest()
            ->first();
    }

    public function canOpenTicket(): bool
    {
        return (bool) $this->activeOrder();
    }

    public function activeSupportTitle(): string
    {
        $order = $this->activeOrder();

        if (!$order) {
            return 'No active order';
        }

        if ($order->order_type === 'pricing_plan') {
            return $order->pricingPlan?->title ?? ($order->plan_name ?? 'Active IT Plan');
        }

        return $order->service?->card_title ?? ($order->servicePlan?->name ?? ($order->plan_name ?? 'Active Service'));
    }

    public function activeSupportSubtitle(): string
    {
        $order = $this->activeOrder();

        if (!$order) {
            $latestOrder = $this->latestOrder();

            if (!$latestOrder) {
                return 'You need a paid or active order to open a support ticket.';
            }

            if ($latestOrder->status === 'awaiting_payment') {
                return 'Your latest order is waiting for payment. Support will unlock after payment confirmation.';
            }

            if ($latestOrder->status === 'cancelled') {
                return 'Your latest order was cancelled. Please purchase or renew a service to open support tickets.';
            }

            if ($latestOrder->end_date && $latestOrder->end_date->lt(now())) {
                return 'Your support period expired on ' . $latestOrder->end_date->format('M d, Y') . '. Please renew your plan or service.';
            }

            return 'You need an active order within a valid service period to open support tickets.';
        }

        $periodText = '';

        if ($order->start_date || $order->end_date) {
            $periodText = ' Valid from ' . ($order->start_date?->format('M d, Y') ?? 'N/A') . ' to ' . ($order->end_date?->format('M d, Y') ?? 'N/A') . '.';
        }

        return match ($order->status) {
            'paid' => 'Your order is paid and eligible for support.' . $periodText,
            'active' => 'Your order is active and eligible for support.' . $periodText,
            default => 'Your support access is active.' . $periodText,
        };
    }

    public function supportStatusLabel(): string
    {
        $order = $this->activeOrder();

        if ($order) {
            return 'Eligible for Support';
        }

        $latestOrder = $this->latestOrder();

        if ($latestOrder?->end_date && $latestOrder->end_date->lt(now())) {
            return 'Support Expired';
        }

        if ($latestOrder?->status === 'awaiting_payment') {
            return 'Payment Pending';
        }

        return 'Support Locked';
    }

    public function tickets()
    {
        $search = trim($this->search);

        return SupportTicket::query()
            ->withCount(['replies', 'attachments'])
            ->where('user_id', Auth::id())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ticket_no', 'like', '%' . $search . '%')
                        ->orWhere('subject', 'like', '%' . $search . '%')
                        ->orWhere('department', 'like', '%' . $search . '%');
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('status', $this->status);
            })
            ->latest('last_reply_at')
            ->latest()
            ->paginate(10);
    }

    public function openCreateModal(): void
    {
        if (!$this->canOpenTicket()) {
            $this->dispatch('toast', message: $this->activeSupportSubtitle(), type: 'error');

            return;
        }

        $this->resetValidation();

        $this->subject = '';
        $this->department = 'General Support';
        $this->priority = 'medium';
        $this->message = '';
        $this->images = [];

        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    protected function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'images.*' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function removePreviewImage(int $index): void
    {
        if (isset($this->images[$index])) {
            unset($this->images[$index]);

            $this->images = array_values($this->images);
        }
    }

    public function createTicket()
    {
        $activeOrder = $this->activeOrder();

        if (!$activeOrder) {
            $this->dispatch('toast', message: $this->activeSupportSubtitle(), type: 'error');

            return;
        }

        $validated = $this->validate();

        $user = Auth::user();

        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'ticket_no' => SupportTicket::generateTicketNo(),
            'subject' => $validated['subject'],
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? null,
            'department' => $validated['department'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'message' => $validated['message'],
            'last_reply_at' => now(),
            'client_read_at' => now(),
            'admin_read_at' => null,
        ]);

        foreach ($this->images as $image) {
            $path = $image->store('support-tickets/' . $ticket->id, 'public');

            $ticket->attachments()->create([
                'file_name' => $image->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $image->getMimeType(),
                'file_size' => $image->getSize(),
            ]);
        }

        $this->showCreateModal = false;

        SupportTicketUpdated::dispatch($ticket->fresh(), 'created');

        $this->dispatch('toast', message: 'Support ticket opened successfully.', type: 'success');

        return $this->redirectRoute('client.tickets.show', $ticket, navigate: true);
    }
};
?>

<div x-data="{ sidebarOpen: false }" class="relative min-h-screen text-white">

    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">
        <div
            class="rounded-[34px] border border-white/10 bg-white/6 shadow-[0_20px_80px_rgba(0,0,0,0.22)] backdrop-blur-2xl">
            <div class="flex min-h-[calc(100vh-3rem)]">
                <div x-show="sidebarOpen" x-transition.opacity
                    class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"
                    style="display:none;"></div>

                <livewire:shared.user-sidebar />

                <div class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">

                    <div class="space-y-6" wire:key="client-ticket-index-{{ $refreshKey }}">
                        @php
                            $activeOrder = $this->activeOrder();
                            $latestOrder = $this->latestOrder();
                        @endphp

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">
                                    Support Center
                                </p>

                                <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">
                                    My Tickets
                                </h1>

                                <p class="mt-2 text-sm text-blue-100/60">
                                    Open and manage your support requests.
                                </p>
                            </div>

                            <button type="button" wire:click="openCreateModal" @class([
                                'inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-bold text-white shadow-lg transition',
                                'bg-gradient-to-r from-blue-500 to-sky-400 shadow-blue-500/25 hover:-translate-y-0.5' => $this->canOpenTicket(),
                                'cursor-not-allowed bg-white/10 text-blue-100/40' => !$this->canOpenTicket(),
                            ])>
                                <span class="material-symbols-outlined text-lg">add</span>
                                Open New Ticket
                            </button>
                        </div>

                        @if ($activeOrder)
                            <div class="client-card p-5">
                                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-emerald-300/80">
                                            Support Access Active
                                        </p>

                                        <h2 class="mt-1 text-xl font-bold text-white">
                                            {{ $this->activeSupportTitle() }}
                                        </h2>

                                        <p class="mt-1 text-sm text-blue-100/60">
                                            {{ $this->activeSupportSubtitle() }}
                                        </p>
                                    </div>

                                    <span
                                        class="inline-flex w-fit rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-bold uppercase tracking-wider text-emerald-300">
                                        {{ $this->supportStatusLabel() }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="rounded-3xl border border-red-400/20 bg-red-500/10 p-5 backdrop-blur-xl">
                                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-red-200/80">
                                            {{ $this->supportStatusLabel() }}
                                        </p>

                                        <h2 class="mt-1 text-xl font-bold text-white">
                                            No active support access found
                                        </h2>

                                        <p class="mt-1 text-sm text-red-100/70">
                                            {{ $this->activeSupportSubtitle() }}
                                        </p>

                                        @if ($latestOrder)
                                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-red-100/50">Latest Order</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $latestOrder->order_no }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-red-100/50">Status</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ ucfirst(str_replace('_', ' ', $latestOrder->status)) }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/6 p-4">
                                                    <p class="text-xs text-red-100/50">End Date</p>
                                                    <p class="mt-1 text-sm font-bold text-white">
                                                        {{ $latestOrder->end_date?->format('M d, Y') ?? 'N/A' }}
                                                    </p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <a href="{{ route('home') }}" wire:navigate
                                        class="inline-flex w-fit rounded-full bg-white px-5 py-2.5 text-sm font-bold text-slate-900 transition hover:opacity-90">
                                        View Services
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="client-card p-5">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="relative">
                                    <span
                                        class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-blue-100/40">
                                        search
                                    </span>

                                    <input type="search" wire:model.live.debounce.400ms="search"
                                        placeholder="Search tickets..."
                                        class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 pl-12 pr-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl">
                                </div>

                                <div class="relative">
                                    <select wire:model.live="status"
                                        class="h-12 w-full appearance-none rounded-2xl border border-white/10 bg-slate-900/40 px-4 pr-10 text-sm text-white outline-none backdrop-blur-xl">
                                        <option value="all">All Status</option>
                                        <option value="open">Open</option>
                                        <option value="pending">Pending</option>
                                        <option value="answered">Answered</option>
                                        <option value="closed">Closed</option>
                                    </select>

                                    <span
                                        class="material-symbols-outlined pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-blue-100/40">
                                        expand_more
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="client-card overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left">
                                    <thead>
                                        <tr class="border-b border-white/10 text-sm text-blue-100/45">
                                            <th class="px-5 py-4 font-medium">Ticket</th>
                                            <th class="px-5 py-4 font-medium">Department</th>
                                            <th class="px-5 py-4 font-medium">Priority</th>
                                            <th class="px-5 py-4 font-medium">Status</th>
                                            <th class="px-5 py-4 font-medium">Activity</th>
                                            <th class="px-5 py-4 text-right font-medium">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-sm text-blue-50/90">
                                        @forelse ($this->tickets() as $ticket)
                                            <tr wire:key="client-ticket-{{ $ticket->id }}-{{ $ticket->status }}-{{ $ticket->replies_count }}-{{ $refreshKey }}"
                                                class="border-b border-white/10 transition hover:bg-white/4">
                                                <td class="px-5 py-4">
                                                    <p class="font-semibold text-white">
                                                        {{ $ticket->subject }}
                                                    </p>

                                                    <p class="mt-1 font-mono text-xs text-blue-100/45">
                                                        {{ $ticket->ticket_no }}
                                                    </p>

                                                    <p class="mt-1 text-xs text-blue-100/45">
                                                        {{ $ticket->created_at?->format('M d, Y h:i A') }}
                                                    </p>
                                                </td>

                                                <td class="px-5 py-4 text-blue-100/70">
                                                    {{ $ticket->department }}
                                                </td>

                                                <td class="px-5 py-4">
                                                    <span @class([
                                                        'inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider',
                                                        'bg-slate-400/15 text-slate-200' => $ticket->priority === 'low',
                                                        'bg-blue-400/15 text-blue-200' => $ticket->priority === 'medium',
                                                        'bg-orange-400/15 text-orange-200' => $ticket->priority === 'high',
                                                        'bg-red-400/15 text-red-200' => $ticket->priority === 'urgent',
                                                    ])>
                                                        {{ ucfirst($ticket->priority) }}
                                                    </span>
                                                </td>

                                                <td class="px-5 py-4">
                                                    <span @class([
                                                        'inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider',
                                                        'bg-blue-400/15 text-blue-200' => $ticket->status === 'open',
                                                        'bg-amber-400/15 text-amber-200' => $ticket->status === 'pending',
                                                        'bg-emerald-400/15 text-emerald-200' => $ticket->status === 'answered',
                                                        'bg-slate-400/15 text-slate-200' => $ticket->status === 'closed',
                                                    ])>
                                                        {{ ucfirst($ticket->status) }}
                                                    </span>
                                                </td>

                                                <td class="px-5 py-4 text-blue-100/60">
                                                    <div class="space-y-1">
                                                        <p>{{ $ticket->replies_count }} replies</p>
                                                        <p>{{ $ticket->attachments_count }} images</p>

                                                        @if ($ticket->last_reply_at)
                                                            <p class="text-xs text-blue-100/40">
                                                                {{ $ticket->last_reply_at->diffForHumans() }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                </td>

                                                <td class="px-5 py-4 text-right">
                                                    <a href="{{ route('client.tickets.show', $ticket) }}" wire:navigate
                                                        class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/8 px-4 py-2 text-xs font-bold text-white transition hover:bg-white/12">
                                                        View Chat
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-5 py-14 text-center">
                                                    <div class="mx-auto max-w-sm">
                                                        <div
                                                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/8 text-blue-100/60">
                                                            <span class="material-symbols-outlined">support_agent</span>
                                                        </div>

                                                        <h3 class="mt-4 text-base font-semibold text-white">
                                                            No tickets found
                                                        </h3>

                                                        <p class="mt-1 text-sm text-blue-100/55">
                                                            Your support tickets will appear here.
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="border-t border-white/10 px-5 py-4">
                                {{ $this->tickets()->links() }}
                            </div>
                        </div>
                    </div>

                    @if ($showCreateModal)
                        <div
                            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 backdrop-blur-sm">
                            <div
                                class="w-full max-w-2xl rounded-[28px] border border-white/10 bg-slate-950/95 p-6 shadow-2xl">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">
                                            New Support Ticket
                                        </p>

                                        <h2 class="mt-1 text-2xl font-bold text-white">
                                            Open Ticket
                                        </h2>
                                    </div>

                                    <button type="button" wire:click="closeCreateModal"
                                        class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/8 text-white">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>

                                <div class="mt-6 space-y-4">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-blue-100/80">
                                            Subject
                                        </label>

                                        <input type="text" wire:model="subject"
                                            class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none"
                                            placeholder="Example: Website issue or email problem">

                                        @error('subject')
                                            <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-blue-100/80">
                                                Department
                                            </label>

                                            <select wire:model="department"
                                                class="h-12 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 text-sm text-white outline-none">
                                                <option value="General Support">General Support</option>
                                                <option value="Technical Support">Technical Support</option>
                                                <option value="Billing Support">Billing Support</option>
                                                <option value="Website Support">Website Support</option>
                                                <option value="Hosting Support">Hosting Support</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-blue-100/80">
                                                Priority
                                            </label>

                                            <select wire:model="priority"
                                                class="h-12 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 text-sm text-white outline-none">
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-blue-100/80">
                                            Message
                                        </label>

                                        <textarea wire:model="message" rows="5"
                                            class="w-full rounded-2xl border border-white/10 bg-white/8 px-4 py-3 text-sm text-white placeholder:text-blue-100/35 outline-none"
                                            placeholder="Describe your issue..."></textarea>

                                        @error('message')
                                            <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-white/15 bg-white/8 px-4 py-6 text-center transition hover:bg-white/12">
                                            <span class="material-symbols-outlined text-3xl text-blue-100/50">
                                                add_photo_alternate
                                            </span>

                                            <span class="mt-2 text-sm font-semibold text-white">
                                                Attach Images
                                            </span>

                                            <span class="mt-1 text-xs text-blue-100/45">
                                                JPG, PNG, WEBP up to 4MB each
                                            </span>

                                            <input type="file" wire:model="images" multiple accept="image/*"
                                                class="hidden">
                                        </label>

                                        @error('images.*')
                                            <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                        @enderror

                                        @if ($images)
                                            <div class="mt-3 grid grid-cols-3 gap-3">
                                                @foreach ($images as $index => $image)
                                                    <div wire:key="ticket-preview-image-{{ $index }}"
                                                        class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/8">

                                                        <img src="{{ $image->temporaryUrl() }}"
                                                            class="h-24 w-full object-cover">

                                                        <button type="button"
                                                            wire:click="removePreviewImage({{ $index }})"
                                                            class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-red-500 text-white shadow-lg transition hover:bg-red-600 p-3">
                                                            <span
                                                                class="material-symbols-outlined text-[10px]">delete</span>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex justify-end gap-3 pt-2">
                                        <button type="button" wire:click="closeCreateModal"
                                            class="rounded-full border border-white/10 bg-white/8 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-white/12">
                                            Cancel
                                        </button>

                                        <button type="button" wire:click="createTicket" wire:loading.attr="disabled"
                                            class="inline-flex items-center justify-center gap-2 rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/25 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">
                                            <span wire:loading.remove wire:target="createTicket,images">
                                                Submit Ticket
                                            </span>

                                            <span wire:loading wire:target="createTicket,images"
                                                class="inline-flex items-center gap-2">
                                                <span
                                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                                Submitting...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- <div wire:poll.8s>

</div> --}}
