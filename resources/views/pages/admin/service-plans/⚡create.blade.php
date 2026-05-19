<?php

use App\Models\Service;
use App\Models\ServicePlan;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin-app')] #[Title('Create Service Plan')] class extends Component {
    public ?int $service_id = null;

    public string $name = '';
    public string $slug = '';
    public string $badge = '';
    public string $description = '';

    public string $price = '';
    public string $discount_price = '';

    public bool $has_monthly_price = false;
    public string $monthly_price = '';
    public string $monthly_discount_price = '';
    public string $monthly_buy_url = '';

    public bool $has_yearly_price = false;
    public string $yearly_price = '';
    public string $yearly_discount_price = '';
    public string $yearly_buy_url = '';

    public string $buy_url = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public array $features = [];
    public string $feature = '';

    protected function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:service_plans,slug'],
            'badge' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:800'],

            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],

            'has_monthly_price' => ['boolean'],
            'monthly_price' => [$this->has_monthly_price ? 'required' : 'nullable', 'numeric', 'min:0'],
            'monthly_discount_price' => ['nullable', 'numeric', 'min:0', 'lt:monthly_price'],
            'monthly_buy_url' => [$this->has_monthly_price ? 'required' : 'nullable', 'url', 'max:255'],

            'has_yearly_price' => ['boolean'],
            'yearly_price' => [$this->has_yearly_price ? 'required' : 'nullable', 'numeric', 'min:0'],
            'yearly_discount_price' => ['nullable', 'numeric', 'min:0', 'lt:yearly_price'],
            'yearly_buy_url' => [$this->has_yearly_price ? 'required' : 'nullable', 'url', 'max:255'],

            'buy_url' => ['nullable', 'url', 'max:255'],
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
            'buy_url.url' => 'Please enter a valid default or one-time buy URL.',

            'discount_price.lt' => 'Discount price must be less than regular price.',

            'monthly_price.required' => 'Monthly price is required when monthly pricing is enabled.',
            'monthly_discount_price.lt' => 'Monthly discount price must be less than monthly price.',
            'monthly_buy_url.required' => 'Monthly buy URL is required when monthly pricing is enabled.',
            'monthly_buy_url.url' => 'Please enter a valid monthly buy URL.',

            'yearly_price.required' => 'Yearly price is required when yearly pricing is enabled.',
            'yearly_discount_price.lt' => 'Yearly discount price must be less than yearly price.',
            'yearly_buy_url.required' => 'Yearly buy URL is required when yearly pricing is enabled.',
            'yearly_buy_url.url' => 'Please enter a valid yearly buy URL.',
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

    public function updatedHasMonthlyPrice(): void
    {
        if (!$this->has_monthly_price) {
            $this->monthly_price = '';
            $this->monthly_discount_price = '';
            $this->monthly_buy_url = '';

            $this->resetValidation(['monthly_price', 'monthly_discount_price', 'monthly_buy_url']);
        }
    }

    public function updatedHasYearlyPrice(): void
    {
        if (!$this->has_yearly_price) {
            $this->yearly_price = '';
            $this->yearly_discount_price = '';
            $this->yearly_buy_url = '';

            $this->resetValidation(['yearly_price', 'yearly_discount_price', 'yearly_buy_url']);
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

        while (ServicePlan::query()->where('slug', $slug)->exists()) {
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

    public function save(): void
    {
        $validated = $this->validate();

        ServicePlan::create([
            'service_id' => $validated['service_id'],

            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($this->slug ?: $validated['name']),

            'badge' => $validated['badge'] ?: null,
            'description' => $validated['description'] ?: null,

            'price' => filled($validated['price'] ?? null) ? $validated['price'] : null,
            'discount_price' => filled($validated['discount_price'] ?? null) ? $validated['discount_price'] : null,

            'has_monthly_price' => $validated['has_monthly_price'] ?? false,
            'monthly_price' => $validated['has_monthly_price'] ?? false ? $validated['monthly_price'] : null,
            'monthly_discount_price' => ($validated['has_monthly_price'] ?? false) && filled($validated['monthly_discount_price'] ?? null) ? $validated['monthly_discount_price'] : null,
            'monthly_buy_url' => $validated['has_monthly_price'] ?? false ? ($validated['monthly_buy_url'] ?: null) : null,

            'has_yearly_price' => $validated['has_yearly_price'] ?? false,
            'yearly_price' => $validated['has_yearly_price'] ?? false ? $validated['yearly_price'] : null,
            'yearly_discount_price' => ($validated['has_yearly_price'] ?? false) && filled($validated['yearly_discount_price'] ?? null) ? $validated['yearly_discount_price'] : null,
            'yearly_buy_url' => $validated['has_yearly_price'] ?? false ? ($validated['yearly_buy_url'] ?: null) : null,

            'features' => array_values(array_filter($validated['features'] ?? [])),

            'buy_url' => $validated['buy_url'] ?: null,

            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Service plan created successfully.',
        ]);

        $this->redirectRoute('admin.service-plans.index', navigate: true);
    }

    public function discard(): void
    {
        $this->reset(['service_id', 'name', 'slug', 'badge', 'description', 'price', 'discount_price', 'has_monthly_price', 'monthly_price', 'monthly_discount_price', 'monthly_buy_url', 'has_yearly_price', 'yearly_price', 'yearly_discount_price', 'yearly_buy_url', 'buy_url', 'sort_order', 'features', 'feature']);

        $this->is_active = true;

        $this->resetValidation();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Create Service Plan</h1>
            <p class="mt-1 text-body-md font-body-md text-secondary">
                Create one-time, monthly, yearly, or flexible service plans.
            </p>
        </div>

        <a href="{{ route('admin.service-plans.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Plans
        </a>
    </div>

    <form wire:submit.prevent="save">
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
                            <label class="block font-label-md text-on-surface">One-time Price</label>

                            <input type="number" step="0.01" min="0" wire:model.live="price"
                                placeholder="e.g., 20000"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('price')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Leave empty if this plan only has monthly or yearly pricing.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">One-time Discount Price</label>

                            <input type="number" step="0.01" min="0" wire:model.live="discount_price"
                                placeholder="e.g., 15000"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('discount_price')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Leave empty if there is no one-time discount.
                            </p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">One-time Buy URL</label>

                            <input type="url" wire:model.live="buy_url"
                                placeholder="https://gipsyhost.com/index.php?rp=/store/shared-hosting/student"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('buy_url')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Used for one-time purchase. Monthly and yearly plans can use separate URLs.
                            </p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-semibold text-on-surface">Monthly Pricing</h4>
                                    <p class="mt-1 text-xs text-secondary">
                                        Enable this if this plan has a monthly price and monthly cart URL.
                                    </p>
                                </div>

                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="has_monthly_price"
                                        class="peer sr-only" />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            @if ($has_monthly_price)
                                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">Monthly Price</label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model.live="monthly_price" placeholder="e.g., 2500"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('monthly_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">
                                            Monthly Discount Price
                                        </label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model.live="monthly_discount_price" placeholder="e.g., 2000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('monthly_discount_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label class="block font-label-md text-on-surface">
                                            Monthly Buy / Cart URL
                                        </label>

                                        <input type="url" wire:model.live="monthly_buy_url"
                                            placeholder="https://gipsyhost.com/index.php?rp=/store/service/monthly"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('monthly_buy_url')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            User will go to this URL when monthly billing is selected.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-semibold text-on-surface">Yearly Pricing</h4>
                                    <p class="mt-1 text-xs text-secondary">
                                        Enable this if this plan has a yearly price and yearly cart URL.
                                    </p>
                                </div>

                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="has_yearly_price" class="peer sr-only" />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            @if ($has_yearly_price)
                                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">Yearly Price</label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model.live="yearly_price" placeholder="e.g., 24000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('yearly_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">
                                            Yearly Discount Price
                                        </label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model.live="yearly_discount_price" placeholder="e.g., 20000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('yearly_discount_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label class="block font-label-md text-on-surface">
                                            Yearly Buy / Cart URL
                                        </label>

                                        <input type="url" wire:model.live="yearly_buy_url"
                                            placeholder="https://gipsyhost.com/index.php?rp=/store/service/yearly"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('yearly_buy_url')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            User will go to this URL when yearly billing is selected.
                                        </p>
                                    </div>
                                </div>
                            @endif
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
                            class="flex cursor-pointer items-center gap-1 rounded border border-dashed border-[#0F52BA] px-4 py-2.5 text-sm font-semibold text-[#0F52BA] transition-colors hover:bg-primary/5">
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
                                    class="material-symbols-outlined cursor-pointer text-sm text-outline hover:text-error">
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
                            class="cursor-pointer rounded-lg border border-outline-variant px-5 py-2 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                            Discard Changes
                        </button>

                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save Plan</span>

                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Saving...
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

                        <div class="mt-4 space-y-3 rounded-xl bg-white p-4 shadow-sm">
                            <p class="text-sm font-semibold text-slate-500">Pricing Preview</p>

                            @if ($price)
                                <div class="rounded-lg border border-slate-100 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        One-time
                                    </p>

                                    @if ($discount_price && (float) $discount_price < (float) $price)
                                        <div class="mt-1 flex items-end gap-2">
                                            <p class="text-2xl font-bold text-on-surface">
                                                ৳{{ number_format((float) $discount_price, 0) }}
                                            </p>

                                            <p class="pb-1 text-sm font-semibold text-slate-400 line-through">
                                                ৳{{ number_format((float) $price, 0) }}
                                            </p>
                                        </div>
                                    @else
                                        <p class="mt-1 text-2xl font-bold text-on-surface">
                                            ৳{{ number_format((float) $price, 0) }}
                                        </p>
                                    @endif

                                    @if ($buy_url)
                                        <p class="mt-2 truncate text-xs text-slate-400">
                                            URL: {{ $buy_url }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            @if ($has_monthly_price && $monthly_price)
                                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-500">
                                        Monthly
                                    </p>

                                    @if ($monthly_discount_price && (float) $monthly_discount_price < (float) $monthly_price)
                                        <div class="mt-1 flex items-end gap-2">
                                            <p class="text-2xl font-bold text-on-surface">
                                                ৳{{ number_format((float) $monthly_discount_price, 0) }}
                                            </p>

                                            <p class="pb-1 text-sm font-semibold text-slate-400 line-through">
                                                ৳{{ number_format((float) $monthly_price, 0) }}
                                            </p>
                                        </div>
                                    @else
                                        <p class="mt-1 text-2xl font-bold text-on-surface">
                                            ৳{{ number_format((float) $monthly_price, 0) }}
                                        </p>
                                    @endif

                                    <p class="mt-1 text-xs text-blue-500">per month</p>

                                    @if ($monthly_buy_url)
                                        <p class="mt-2 truncate text-xs text-blue-500">
                                            URL: {{ $monthly_buy_url }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            @if ($has_yearly_price && $yearly_price)
                                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500">
                                        Yearly
                                    </p>

                                    @if ($yearly_discount_price && (float) $yearly_discount_price < (float) $yearly_price)
                                        <div class="mt-1 flex items-end gap-2">
                                            <p class="text-2xl font-bold text-on-surface">
                                                ৳{{ number_format((float) $yearly_discount_price, 0) }}
                                            </p>

                                            <p class="pb-1 text-sm font-semibold text-slate-400 line-through">
                                                ৳{{ number_format((float) $yearly_price, 0) }}
                                            </p>
                                        </div>
                                    @else
                                        <p class="mt-1 text-2xl font-bold text-on-surface">
                                            ৳{{ number_format((float) $yearly_price, 0) }}
                                        </p>
                                    @endif

                                    <p class="mt-1 text-xs text-emerald-500">per year</p>

                                    @if ($yearly_buy_url)
                                        <p class="mt-2 truncate text-xs text-emerald-500">
                                            URL: {{ $yearly_buy_url }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            @if (!$price && !($has_monthly_price && $monthly_price) && !($has_yearly_price && $yearly_price))
                                <p class="text-2xl font-bold text-on-surface">
                                    Custom
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
