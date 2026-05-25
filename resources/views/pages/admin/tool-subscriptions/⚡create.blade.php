<?php

use App\Models\ToolCategory;
use App\Models\ToolPlan;
use App\Models\ToolSubscription;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin-app')] #[Title('Create Subscription')] class extends Component {
    public string $user_id = '';
    public string $tool_category_id = '';
    public string $tool_plan_id = '';
    public string $billing_cycle = 'monthly';
    public ?float $amount = null;
    public string $status = 'active';
    public ?string $starts_at = null;
    public ?string $expires_at = null;

    public array $availablePlans = [];

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'tool_category_id' => ['required', 'exists:tool_categories,id'],
            'tool_plan_id' => ['required', 'exists:tool_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,expired,cancelled,pending'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function updatedToolCategoryId(): void
    {
        $this->tool_plan_id = '';
        $this->availablePlans = ToolPlan::query()
            ->where('tool_category_id', $this->tool_category_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function categories()
    {
        return ToolCategory::query()->orderBy('name')->get();
    }

    public function users()
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function save(): void
    {
        $validated = $this->validate();

        ToolSubscription::create([
            'user_id' => $validated['user_id'],
            'tool_category_id' => $validated['tool_category_id'],
            'tool_plan_id' => $validated['tool_plan_id'],
            'billing_cycle' => $validated['billing_cycle'],
            'amount' => $validated['amount'],
            'status' => $validated['status'],
            'starts_at' => $validated['starts_at'] ?? now(),
            'expires_at' => $validated['expires_at'],
        ]);

        $this->dispatch('toast', message: 'Subscription created successfully.', type: 'success');

        $this->redirect(route('admin.tool-subscriptions.index'), navigate: true);
    }
};
?>

<div class="mx-auto max-w-2xl space-y-stack-lg">
    <div>
        <a href="{{ route('admin.tool-subscriptions.index') }}" wire:navigate
            class="mb-4 inline-flex items-center gap-1 text-sm text-on-surface-variant hover:text-primary transition-colors">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to subscriptions
        </a>
        <h2 class="font-h1 text-h1 text-on-surface">Create Subscription</h2>
        <p class="mt-1 text-body-md text-on-surface-variant">
            Create a new tool subscription for a user.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-stack-lg shadow-sm">
        <form wire:submit.prevent="save" class="space-y-stack-md">
            <div class="grid grid-cols-2 gap-stack-md">
                <div class="col-span-2">
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">User</label>
                    <select wire:model="user_id"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">Select User</option>
                        @foreach ($this->users() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="col-span-2">
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Category</label>
                    <select wire:model.live="tool_category_id"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">Select Category</option>
                        @foreach ($this->categories() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('tool_category_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="col-span-2">
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Plan</label>
                    <select wire:model="tool_plan_id"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">Select Plan</option>
                        @foreach ($availablePlans as $plan)
                            <option value="{{ $plan['id'] }}">{{ $plan['name'] }} (৳{{ number_format((float) ($plan['monthly_price'] ?: $plan['yearly_price']), 2) }})</option>
                        @endforeach
                    </select>
                    @error('tool_plan_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Billing Cycle</label>
                    <select wire:model="billing_cycle"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    @error('billing_cycle')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Amount</label>
                    <input wire:model="amount"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        placeholder="5.00" type="number" step="0.01" min="0" />
                    @error('amount')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Status</label>
                    <select wire:model="status"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="pending">Pending</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Starts At</label>
                    <input wire:model="starts_at" type="datetime-local"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    @error('starts_at')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-stack-xs block text-label-md text-on-surface-variant">Expires At</label>
                    <input wire:model="expires_at" type="datetime-local"
                        class="w-full rounded border border-outline bg-white px-3 py-2 text-body-md outline-none focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    @error('expires_at')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex gap-3 pt-stack-md">
                <a href="{{ route('admin.tool-subscriptions.index') }}" wire:navigate
                    class="w-1/3 rounded-lg border border-outline-variant bg-white py-3 text-center font-label-md text-on-surface transition-all hover:bg-slate-50 cursor-pointer">
                    Cancel
                </a>
                <button
                    class="w-2/3 rounded-lg bg-primary-container py-3 font-label-md text-white shadow-sm transition-all hover:opacity-90 active:scale-[0.98] cursor-pointer"
                    type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">
                        Save Subscription
                    </span>
                    <span wire:loading wire:target="save">
                        Saving...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
