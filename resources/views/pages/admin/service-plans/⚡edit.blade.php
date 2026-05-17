<?php

use App\Models\Service;
use App\Models\ServicePlan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin-app')] #[Title('Edit Service Plan')] class extends Component {
    public ServicePlan $servicePlan;

    public ?int $service_id = null;

    public string $name = '';
    public string $slug = '';
    public string $badge = '';
    public string $description = '';

    public string $price = '';
    public string $discount_price = '';

    public string $buy_url = '';

    public int $sort_order = 0;
    public bool $is_active = true;

    public array $features = [];
    public string $feature = '';

    public function mount(ServicePlan $servicePlan): void
    {
        $this->servicePlan = $servicePlan;

        $this->service_id = $servicePlan->service_id;

        $this->name = $servicePlan->name;
        $this->slug = $servicePlan->slug;
        $this->badge = $servicePlan->badge ?? '';
        $this->description = $servicePlan->description ?? '';

        $this->price = $servicePlan->price !== null ? (string) $servicePlan->price : '';
        $this->discount_price = $servicePlan->discount_price !== null ? (string) $servicePlan->discount_price : '';

        $this->buy_url = $servicePlan->buy_url ?? '';

        $this->sort_order = (int) $servicePlan->sort_order;
        $this->is_active = (bool) $servicePlan->is_active;

        $this->features = $servicePlan->features ?: [];
    }

    protected function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],

            'name' => ['required', 'string', 'max:160'],

            'slug' => ['required', 'string', 'max:190', Rule::unique('service_plans', 'slug')->ignore($this->servicePlan->id)],

            'badge' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:800'],

            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],

            'buy_url' => ['required', 'url', 'max:255'],

            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],

            'features' => ['required', 'array'],
            'features.*' => ['required', 'string', 'max:180'],
        ];
    }

    protected function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',
            'buy_url.url' => 'Please enter a valid cart or buy URL.',
            'discount_price.lt' => 'Discount price must be less than regular price.',
        ];
    }

    public function services()
    {
        return Service::query()->where('is_active', true)->orderBy('card_title')->get();
    }

    public function selectedService()
    {
        if (!$this->service_id) {
            return null;
        }

        return $this->services()->firstWhere('id', (int) $this->service_id);
    }

    public function updatedName(): void
    {
        $this->slug = Str::slug($this->name);

        $this->validateOnly('name');

        if (filled($this->slug)) {
            $this->validateOnly('slug');
        }
    }

    public function updated($property): void
    {
        if ($property !== 'name') {
            $this->validateOnly($property);
        }
    }

    private function uniqueSlug(string $value): string
    {
        $slug = Str::slug($value ?: $this->name);
        $originalSlug = $slug;
        $counter = 1;

        while (ServicePlan::query()->where('slug', $slug)->where('id', '!=', $this->servicePlan->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function addFeature(): void
    {
        $feature = trim($this->feature);

        if ($feature === '') {
            $this->dispatch('toast', message: 'Please type a feature first.', type: 'warning');

            return;
        }

        if (!in_array($feature, $this->features, true)) {
            $this->features[] = $feature;
        }

        $this->feature = '';
    }

    public function removeFeature(int $index): void
    {
        unset($this->features[$index]);

        $this->features = array_values($this->features);
    }

    public function update(): void
    {
        $validated = $this->validate();

        $this->servicePlan->update([
            'service_id' => $validated['service_id'],

            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($this->slug ?: $validated['name']),

            'badge' => $validated['badge'] ?: null,
            'description' => $validated['description'] ?: null,

            'price' => $validated['price'] !== '' ? $validated['price'] : null,
            'discount_price' => filled($validated['discount_price'] ?? null) ? $validated['discount_price'] : null,

            'features' => array_values(array_filter($validated['features'] ?? [])),

            'buy_url' => $validated['buy_url'] ?: null,

            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Service plan updated successfully.',
        ]);

        $this->redirectRoute('admin.service-plans.index', navigate: true);
    }

    public function discard(): void
    {
        $this->service_id = $this->servicePlan->service_id;

        $this->name = $this->servicePlan->name;
        $this->slug = $this->servicePlan->slug;
        $this->badge = $this->servicePlan->badge ?? '';
        $this->description = $this->servicePlan->description ?? '';

        $this->price = $this->servicePlan->price !== null ? (string) $this->servicePlan->price : '';
        $this->discount_price = $this->servicePlan->discount_price !== null ? (string) $this->servicePlan->discount_price : '';

        $this->buy_url = $this->servicePlan->buy_url ?? '';

        $this->sort_order = (int) $this->servicePlan->sort_order;
        $this->is_active = (bool) $this->servicePlan->is_active;

        $this->features = $this->servicePlan->features ?: [];
        $this->feature = '';

        $this->resetValidation();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Edit Service Plan</h1>
            <p class="mt-1 text-body-md font-body-md text-secondary">
                Update service plan details, pricing, features, and cart page.
            </p>
        </div>

        <a href="{{ route('admin.service-plans.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Plans
        </a>
    </div>

    <form wire:submit.prevent="update">
        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">inventory_2</span>
                        Plan Information
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Parent Service</label>

                            <div x-data="{
                                open: false,
                                search: '',
                                selectedId: @entangle('service_id').live,
                                services: @js(
    $this->services()
        ->map(
            fn($service) => [
                'id' => $service->id,
                'title' => $service->card_title,
            ],
        )
        ->values(),
),
                            
                                get selectedService() {
                                    return this.services.find(service => service.id == this.selectedId) || null;
                                },
                            
                                get filteredServices() {
                                    if (!this.search.trim()) {
                                        return this.services;
                                    }
                            
                                    return this.services.filter(service =>
                                        service.title.toLowerCase().includes(this.search.toLowerCase())
                                    );
                                },
                            
                                selectService(service) {
                                    this.selectedId = service.id;
                                    this.search = service.title;
                                    this.open = false;
                                },
                            
                                clearService() {
                                    this.selectedId = null;
                                    this.search = '';
                                    this.open = true;
                                },
                            
                                init() {
                                    if (this.selectedService) {
                                        this.search = this.selectedService.title;
                                    }
                            
                                    this.$watch('selectedId', () => {
                                        if (this.selectedService) {
                                            this.search = this.selectedService.title;
                                        }
                                    });
                                }
                            }" class="relative" @click.outside="open = false">
                                <div class="relative">
                                    <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                        @keydown.escape.window="open = false" placeholder="Search and select service..."
                                        class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 pr-20 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                    <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
                                        <button type="button" x-show="selectedId" @click="clearService()"
                                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-red-500">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>

                                        <button type="button" @click="open = !open"
                                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100">
                                            <span class="material-symbols-outlined text-[22px] transition"
                                                :class="open ? 'rotate-180' : ''">
                                                expand_more
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div x-show="open" x-transition
                                    class="absolute z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                                    style="display: none;">
                                    <template x-if="filteredServices.length">
                                        <div class="space-y-1">
                                            <template x-for="service in filteredServices" :key="service.id">
                                                <button type="button" @click="selectService(service)"
                                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition hover:bg-primary/5"
                                                    :class="selectedId == service.id ? 'bg-primary/10 text-primary' :
                                                        'text-slate-700'">
                                                    <span x-text="service.title" class="font-medium"></span>

                                                    <span x-show="selectedId == service.id"
                                                        class="material-symbols-outlined text-[18px] text-primary">
                                                        check_circle
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="!filteredServices.length">
                                        <div class="rounded-lg bg-slate-50 px-4 py-6 text-center">
                                            <span class="material-symbols-outlined text-3xl text-slate-300">
                                                search_off
                                            </span>
                                            <p class="mt-2 text-sm font-medium text-slate-500">No service found</p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            @error('service_id')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Plan Name</label>

                            <input type="text" wire:model.live="name" placeholder="e.g., Business Hosting"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('name')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Slug:
                                <span class="font-mono text-primary">{{ $slug ?: 'plan-slug' }}</span>
                            </p>

                            @error('slug')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Badge</label>

                            <input type="text" wire:model.live="badge" placeholder="Popular, Best Value, Recommended"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('badge')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Regular Price</label>

                            <input type="number" step="0.01" min="0" wire:model.live="price"
                                placeholder="e.g., 20000"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('price')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Discount Price</label>

                            <input type="number" step="0.01" min="0" wire:model.live="discount_price"
                                placeholder="e.g., 15000"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('discount_price')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Leave empty if there is no discount.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Sort Order</label>

                            <input type="number" min="0" wire:model.live="sort_order"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('sort_order')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Buy / Cart URL</label>

                            <input type="url" wire:model.live="buy_url"
                                placeholder="https://gipsyhost.com/index.php?rp=/store/shared-hosting/student"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('buy_url')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Visitor will be redirected to this URL when they click Buy Now.
                            </p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Description</label>

                            <textarea wire:model.live="description" rows="3" placeholder="Short plan description..."
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"></textarea>

                            @error('description')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-6 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">checklist</span>
                        Plan Features
                    </h3>

                    <div class="mb-4 flex gap-3">
                        <input wire:model.live="feature" wire:keydown.enter.prevent="addFeature"
                            class="flex-1 rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                            placeholder="e.g., 10GB SSD Storage" type="text" />

                        <button type="button" wire:click="addFeature"
                            class="flex items-center gap-1 rounded border border-dashed border-[#0F52BA] px-4 py-2.5 text-sm font-semibold text-[#0F52BA] transition-colors hover:bg-primary/5">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Add
                        </button>

                        @error('feature')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex min-h-[60px] flex-wrap gap-2 rounded-lg border border-slate-100 bg-surface p-4">
                        @forelse ($features as $index => $item)
                            <div wire:key="feature-{{ $index }}"
                                class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-3 py-1.5 shadow-sm">
                                <span class="text-sm font-body-md">{{ $item }}</span>

                                <button type="button" wire:click="removeFeature({{ $index }})"
                                    class="material-symbols-outlined text-sm text-outline hover:text-error">
                                    close
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-secondary">No features added yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" wire:click="discard" wire:loading.attr="disabled"
                            class="rounded-lg border border-outline-variant px-5 py-2 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">
                            Discard Changes
                        </button>

                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">
                            <span wire:loading.remove wire:target="update">Update Plan</span>

                            <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Updating...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-span-12 space-y-6 lg:col-span-4">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                        Plan Status
                    </h3>

                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface">
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="text-xs text-secondary">Show or hide this plan publicly.</span>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_active" class="peer sr-only" />
                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                            </div>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-5 text-h3 font-h2">Quick Preview</h3>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            @if ($badge)
                                <span
                                    class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    {{ $badge }}
                                </span>
                            @endif

                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700' => $is_active,
                                'bg-red-100 text-red-700' => !$is_active,
                            ])>
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <h4 class="text-lg font-semibold text-on-surface">
                            {{ $name ?: 'Plan Name' }}
                        </h4>

                        <p class="mt-1 font-mono text-xs text-primary">
                            {{ $slug ?: 'plan-slug' }}
                        </p>

                        @if ($this->selectedService())
                            <p class="mt-2 text-xs font-semibold text-blue-700">
                                {{ $this->selectedService()->card_title }}
                            </p>
                        @endif

                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            {{ $description ?: 'Plan description will appear here.' }}
                        </p>

                        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
                            <p class="text-sm text-slate-500">Price (BDT)</p>

                            @if ($discount_price && $price && (float) $discount_price < (float) $price)
                                <div class="mt-1">
                                    <div class="flex items-end gap-2">
                                        <p class="text-2xl font-bold text-on-surface">
                                            ৳{{ number_format((float) $discount_price, 0) }}
                                        </p>

                                        <p class="pb-1 text-sm font-semibold text-slate-400 line-through">
                                            ৳{{ number_format((float) $price, 0) }}
                                        </p>
                                    </div>

                                    <p class="mt-1 text-xs font-semibold text-emerald-600">
                                        {{ round((1 - (float) $discount_price / (float) $price) * 100) }}% discount
                                        applied
                                    </p>
                                </div>
                            @else
                                <p class="text-2xl font-bold text-on-surface">
                                    {{ $price ? '৳' . number_format((float) $price, 0) : 'Custom' }}
                                </p>
                            @endif
                        </div>

                        <div class="mt-4 space-y-2">
                            @forelse ($features as $previewFeature)
                                <div class="flex items-center gap-2 text-sm text-slate-600">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-500">
                                        check_circle
                                    </span>
                                    {{ $previewFeature }}
                                </div>
                            @empty
                                <p class="text-sm text-slate-400">No features yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
