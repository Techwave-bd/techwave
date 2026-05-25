<?php

use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin-app')] #[Title('Create Service')] class extends Component {
    use WithFileUploads;

    public ?int $category_id = null;

    public string $card_title = '';
    public string $detail_title = '';
    public string $icon = '';

    public string $short_description = '';
    public string $overview = '';

    public string $audience_title = '';
    public string $audience_detail = '';

    public string $meta_title = '';
    public string $meta_description = '';
    public string $meta_keywords = '';

    public bool $is_active = true;
    public bool $is_featured = false;

    public $image = null;

    public array $benefits = [
        [
            'title' => '',
            'description' => '',
        ],
    ];

    public array $included_items = [];
    public string $included_item = '';

    public array $tags = [];
    public string $tag = '';

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],

            'card_title' => ['required', 'string', 'max:160'],
            'detail_title' => ['required', 'string', 'max:180'],
            'icon' => ['nullable', 'string', 'max:80'],

            'short_description' => ['required', 'string', 'max:500'],
            'overview' => ['nullable', 'string'],

            'audience_title' => ['nullable', 'string', 'max:160'],
            'audience_detail' => ['nullable', 'string', 'max:1000'],

            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],

            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],

            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],

            'benefits' => ['nullable', 'array'],
            'benefits.*.title' => ['nullable', 'string', 'max:160'],
            'benefits.*.description' => ['nullable', 'string', 'max:500'],

            'included_items' => ['nullable', 'array'],
            'included_items.*' => ['nullable', 'string', 'max:120'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function messages(): array
    {
        return [
            'category_id.required' => 'Please select a service category.',

            'benefits.min' => 'Please add at least one key benefit.',
            'benefits.*.title.required' => 'Benefit title is required.',
            'benefits.*.description.required' => 'Benefit description is required.',

            'included_items.min' => 'Please add at least one included item.',
        ];
    }

    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function selectedCategory()
    {
        if (!$this->category_id) {
            return null;
        }

        return $this->categories()->firstWhere('id', (int) $this->category_id);
    }

    public function updated($property): void
    {
        $this->validateOnly($property);
    }

    public function addBenefit(): void
    {
        $this->benefits[] = [
            'title' => '',
            'description' => '',
        ];
    }

    public function removeBenefit(int $index): void
    {
        unset($this->benefits[$index]);

        $this->benefits = array_values($this->benefits);
    }

    public function addIncludedItem(): void
    {
        $item = trim($this->included_item);

        if ($item === '') {
            $this->dispatch('toast', message: 'Please type an included item first.', type: 'warning');

            return;
        }

        if (!in_array($item, $this->included_items, true)) {
            $this->included_items[] = $item;
        }

        $this->included_item = '';

        $this->resetValidation('included_items');
    }

    public function removeIncludedItem(int $index): void
    {
        unset($this->included_items[$index]);

        $this->included_items = array_values($this->included_items);
    }

    public function addTag(): void
    {
        $tag = trim($this->tag);

        if ($tag === '') {
            return;
        }

        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }

        $this->tag = '';
    }

    public function removeTag(int $index): void
    {
        unset($this->tags[$index]);

        $this->tags = array_values($this->tags);
    }

    private function uniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (Service::query()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function cleanQuillValue(?string $value): ?string
    {
        $value = trim((string) $value);

        $emptyValues = ['', '<p><br></p>', '<p></p>', '<p>&nbsp;</p>', '<div><br></div>'];

        if (in_array($value, $emptyValues, true)) {
            return null;
        }

        if (blank(trim(strip_tags($value)))) {
            return null;
        }

        return $value;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['overview'] = $this->cleanQuillValue($validated['overview'] ?? null);

        $imagePath = null;

        if ($this->image) {
           $imagePath = $this->storeServiceImage($this->image, $validated['card_title']);
        }

        $benefits = collect($validated['benefits'])
            ->map(
                fn($benefit) => [
                    'title' => trim($benefit['title']),
                    'description' => trim($benefit['description']),
                ],
            )
            ->values()
            ->toArray();

        Service::create([
            'category_id' => $validated['category_id'],

            'card_title' => $validated['card_title'],
            'detail_title' => $validated['detail_title'],
            'slug' => $this->uniqueSlug($validated['card_title']),

            'icon' => $validated['icon'] ?: null,
            'image' => $imagePath,

            'short_description' => $validated['short_description'],
            'overview' => $validated['overview'],

            'benefits' => $benefits,
            'included_items' => array_values(array_filter($validated['included_items'])),
            'tags' => array_values(array_filter($validated['tags'] ?? [])),

            'audience_title' => $validated['audience_title'],
            'audience_detail' => $validated['audience_detail'],

            'meta_title' => $validated['meta_title'] ?: null,
            'meta_description' => $validated['meta_description'] ?: null,
            'meta_keywords' => $validated['meta_keywords'] ?: null,

            'is_active' => $validated['is_active'],
            'is_featured' => $validated['is_featured'],
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Service created successfully.',
        ]);

        $this->redirectRoute('admin.services.index', navigate: true);
    }

    public function discard(): void
    {
        $this->reset(['category_id', 'card_title', 'detail_title', 'icon', 'short_description', 'overview', 'audience_title', 'audience_detail', 'meta_title', 'meta_description', 'meta_keywords', 'image', 'included_items', 'included_item', 'tags', 'tag']);

        $this->benefits = [
            [
                'title' => '',
                'description' => '',
            ],
        ];

        $this->is_active = true;
        $this->is_featured = false;

        $this->resetValidation();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Create Service</h1>
            <p class="mt-1 text-body-md font-body-md text-secondary">
                Create service content for website cards, detail pages, and service recommendations.
            </p>
        </div>

        <a href="{{ route('admin.services.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Services
        </a>
    </div>

    <form wire:submit.prevent="save">
        <div class="grid grid-cols-12 gap-6">
            <!-- Main Form -->
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <!-- Service Information -->
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">design_services</span>
                        Service Information
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Category -->
                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Service Category</label>

                            <div class="relative">
                                <select wire:model.live="category_id"
                                    class="w-full appearance-none rounded border border-outline-variant bg-white px-4 py-2.5 pr-10 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10">
                                    <option value="">Select a category</option>

                                    @foreach ($this->categories() as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <span
                                    class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    expand_more
                                </span>
                            </div>

                            @error('category_id')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Service Card Title</label>

                            <input wire:model.live="card_title"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                                placeholder="e.g., Managed Kubernetes Node" type="text" />

                            @error('card_title')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Detail Page Title</label>

                            <input wire:model.live="detail_title"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                                placeholder="e.g., Enterprise Kubernetes Solutions" type="text" />

                            @error('detail_title')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <label class="block font-label-md text-on-surface">Service Icon</label>

                        <div class="flex gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <span class="material-symbols-outlined text-[24px]">
                                    {{ $icon ?: 'design_services' }}
                                </span>
                            </div>

                            <input wire:model.live="icon"
                                class="flex-1 rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                                placeholder="e.g., cloud, settings, lock" type="text" />
                        </div>

                        <p class="text-[10px] font-bold uppercase tracking-tight text-on-surface-variant">
                            Use Material Symbol name such as cloud, settings, lock, security, dns, storage.
                        </p>

                        @error('icon')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-6 space-y-2">
                        <label class="block font-label-md text-on-surface">Short Description</label>

                        <textarea wire:model.live="short_description"
                            class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                            placeholder="Enter a brief summary for the catalog card..." rows="2"></textarea>

                        @error('short_description')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Quill Rich Text Editor -->
                    <div class="mt-6 space-y-2">
                        <label class="block font-label-md text-on-surface">Service Overview</label>

                        <div wire:ignore x-data="{
                            quill: null,
                            value: @entangle('overview'),
                        
                            cleanEditorValue() {
                                const text = this.quill.getText().trim();
                        
                                if (!text.length) {
                                    return '';
                                }
                        
                                return this.quill.root.innerHTML;
                            },
                        
                            init() {
                                this.quill = new Quill(this.$refs.editor, {
                                    theme: 'snow',
                                    placeholder: 'Describe the service technical architecture and business value...',
                                    modules: {
                                        toolbar: [
                                            [{ header: [2, 3, false] }],
                                            [{ 'font': [] }],
                                            ['bold', 'italic', 'underline', 'strike'],
                                            [{ 'color': [] }, { 'background': [] }],
                                            [{ list: 'ordered' }, { list: 'bullet' }],
                                            [{ 'align': [] }],
                                            ['blockquote', 'code-block'],
                                            ['link'],
                                            ['clean']
                                        ]
                                    }
                                });
                        
                                if (this.value && this.value !== '<p><br></p>') {
                                    this.quill.clipboard.dangerouslyPasteHTML(this.value);
                                }
                        
                                this.quill.on('text-change', () => {
                                    this.value = this.cleanEditorValue();
                                });
                        
                                this.$watch('value', (newValue) => {
                                    const cleanValue = newValue === '<p><br></p>' ? '' : newValue;
                        
                                    if (this.quill.root.innerHTML !== cleanValue) {
                                        this.quill.clipboard.dangerouslyPasteHTML(cleanValue || '');
                                    }
                                });
                            }
                        }"
                            class="overflow-hidden rounded-lg border border-outline-variant bg-white">
                            <div x-ref="editor"></div>
                        </div>

                        @error('overview')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Key Benefits -->
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex items-center justify-between gap-4">
                        <h3 class="flex items-center gap-2 text-h3 font-h2 text-on-surface">
                            Key Benefits
                        </h3>

                        <button type="button" wire:click="addBenefit"
                            class="flex items-center gap-1 text-sm font-semibold text-[#0F52BA] hover:underline">
                            <span class="material-symbols-outlined text-lg">add_circle</span>
                            Add Benefit
                        </button>
                    </div>

                    <div class="space-y-4">
                        @foreach ($benefits as $index => $benefit)
                            <div wire:key="benefit-{{ $index }}"
                                class="rounded-lg border border-slate-100 bg-surface p-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                    <div class="md:col-span-4">
                                        <input wire:model.live="benefits.{{ $index }}.title"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2 text-sm"
                                            placeholder="Benefit Title" type="text" />

                                        @error("benefits.$index.title")
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="md:col-span-7">
                                        <input wire:model.live="benefits.{{ $index }}.description"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2 text-sm"
                                            placeholder="Brief description of the value proposition" type="text" />

                                        @error("benefits.$index.description")
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex justify-end md:col-span-1">
                                        <button type="button" wire:click="removeBenefit({{ $index }})"
                                            class="text-outline transition hover:text-error">
                                            <span class="material-symbols-outlined">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @error('benefits')
                        <p class="mt-3 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Included Items -->
                    <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                        <label class="mb-4 flex items-center gap-2 font-label-md text-on-surface">
                            What's Included
                        </label>

                        <div class="mb-4 flex gap-3">
                            <input wire:model.live="included_item" wire:keydown.enter.prevent="addIncludedItem"
                                class="flex-1 rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                                placeholder="e.g., 24/7 Monitoring" type="text" />

                            <button type="button" wire:click="addIncludedItem"
                                class="flex items-center gap-1 rounded border border-dashed border-[#0F52BA] px-4 py-2.5 text-sm font-semibold text-[#0F52BA] transition-colors hover:bg-primary/5">
                                <span class="material-symbols-outlined text-sm">add</span>
                                Item
                            </button>
                        </div>

                        <div class="flex min-h-15 flex-wrap gap-2 rounded-lg border border-slate-100 bg-surface p-4">
                            @forelse ($included_items as $index => $item)
                                <div wire:key="included-item-{{ $index }}"
                                    class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-3 py-1.5 shadow-sm">
                                    <span class="text-sm font-body-md">{{ $item }}</span>

                                    <button type="button" wire:click="removeIncludedItem({{ $index }})"
                                        class="material-symbols-outlined text-sm text-outline hover:text-error">
                                        close
                                    </button>
                                </div>
                            @empty
                                <p class="text-sm text-secondary">No included items added yet.</p>
                            @endforelse
                        </div>

                        @error('included_items')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror

                        @error('included_items.*')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Service Tags -->
                    <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                        <label class="mb-4 block font-label-md text-on-surface">Service Tags</label>

                        <div class="mb-4 flex gap-3">
                            <input wire:model.live="tag" wire:keydown.enter.prevent="addTag"
                                class="flex-1 rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                                placeholder="e.g., Infrastructure" type="text" />

                            <button type="button" wire:click="addTag"
                                class="flex items-center gap-1 rounded border border-dashed border-[#0F52BA] px-4 py-2.5 text-sm font-semibold text-[#0F52BA] transition-colors hover:bg-primary/5">
                                <span class="material-symbols-outlined text-sm">add</span>
                                Tag
                            </button>
                        </div>

                        <div
                            class="flex min-h-[60px] flex-wrap gap-2 rounded-lg border border-slate-100 bg-surface p-4">
                            @forelse ($tags as $index => $serviceTag)
                                <div wire:key="service-tag-{{ $index }}"
                                    class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-3 py-1.5 shadow-sm">
                                    <span class="text-sm font-body-md">{{ $serviceTag }}</span>

                                    <button type="button" wire:click="removeTag({{ $index }})"
                                        class="material-symbols-outlined text-sm text-outline hover:text-error">
                                        close
                                    </button>
                                </div>
                            @empty
                                <p class="text-sm text-secondary">No service tags added yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Target Audience -->
                <div class="grid grid-cols-1 gap-6 rounded-xl border border-slate-200 bg-white p-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <h4 class="flex flex-wrap items-center gap-2 font-label-md text-[#0F52BA]">
                            <span class="material-symbols-outlined text-lg">person_search</span>
                            Who This Service Is For
                        </h4>

                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-label-sm text-on-surface-variant">Profile Title</label>

                                <input wire:model.live="audience_title"
                                    class="w-full rounded border border-outline-variant bg-white px-3 py-2 text-sm"
                                    placeholder="e.g., System Architects" type="text" />

                                @error('audience_title')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-label-sm text-on-surface-variant">Requirement Detail</label>

                                <textarea wire:model.live="audience_detail"
                                    class="w-full rounded border border-outline-variant bg-white px-3 py-2 text-sm"
                                    placeholder="Describe the ideal user profile for this specific service..." rows="2"></textarea>

                                @error('audience_detail')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-center justify-center rounded-lg bg-blue-50/50 p-5 text-center">
                        <span class="material-symbols-outlined mb-2 text-4xl text-primary-container">info</span>

                        <p class="max-w-55 text-xs font-body-sm text-on-secondary-container">
                            Audience data helps your service page show clearer value to specific user groups.
                        </p>
                    </div>
                </div>

                <!-- SEO Meta Section -->
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div class="mb-8 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="flex items-center gap-2 text-h3 font-h2 text-on-surface">
                                <span class="material-symbols-outlined text-primary">travel_explore</span>
                                SEO Meta Information
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Optimize how this service appears in search engines and social previews.
                            </p>
                        </div>

                        <span
                            class="rounded-full border border-outline-variant px-2 py-0.5 text-xs font-normal uppercase tracking-widest text-outline-variant">
                            Optional
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <label class="block font-label-md text-on-surface">Meta Title</label>

                                <span class="text-xs text-secondary">
                                    {{ strlen($meta_title) }}/180
                                </span>
                            </div>

                            <input wire:model.live="meta_title"
                                class="w-full rounded-lg border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"
                                placeholder="e.g., Managed IT Services in Bangladesh | TechWave" type="text" />

                            @error('meta_title')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <label class="block font-label-md text-on-surface">Meta Description</label>

                                <span class="text-xs text-secondary">
                                    {{ strlen($meta_description) }}/500
                                </span>
                            </div>

                            <textarea wire:model.live="meta_description"
                                class="w-full rounded-lg border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"
                                placeholder="Write a short SEO-friendly description for this service..." rows="3"></textarea>

                            @error('meta_description')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <label class="block font-label-md text-on-surface">Meta Keywords</label>

                                <span class="text-xs text-secondary">
                                    {{ strlen($meta_keywords) }}/500
                                </span>
                            </div>

                            <textarea wire:model.live="meta_keywords"
                                class="w-full rounded-lg border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"
                                placeholder="e.g., managed IT service, cyber security, cloud server, business email" rows="2"></textarea>

                            <p class="text-xs text-secondary">Separate keywords with commas.</p>

                            @error('meta_keywords')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Buttons -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" wire:click="discard" wire:loading.attr="disabled"
                            class="rounded-lg border border-outline-variant px-5 py-2 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                            Discard Changes
                        </button>

                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save Service</span>

                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-span-12 space-y-6 lg:col-span-4">
                <!-- Media Upload -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-6 text-h3 font-h2">Service Media</h3>

                    <div class="space-y-3">
                        <label for="image"
                            class="flex h-64 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface transition-colors hover:bg-surface-container">
                            @if ($image)
                                <img src="{{ $image->temporaryUrl() }}" alt="Service preview"
                                    class="h-full w-full object-cover" />
                            @else
                                <span class="material-symbols-outlined mb-2 text-5xl text-outline">
                                    add_photo_alternate
                                </span>

                                <p class="text-sm font-body-sm text-outline">
                                    Click to upload main service image
                                </p>

                                <p class="mt-1 text-xs font-bold uppercase tracking-widest text-outline-variant">
                                    PNG, JPG, WEBP up to 10MB
                                </p>
                            @endif
                        </label>

                        <input id="image" type="file" wire:model="image"
                            accept="image/png,image/jpeg,image/jpg,image/webp" class="hidden" />

                        <div wire:loading wire:target="image" class="text-sm text-primary">
                            Uploading image...
                        </div>

                        @error('image')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Service Settings -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                        Service Settings
                    </h3>

                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center gap-3">
                            <div @class([
                                'h-2.5 w-2.5 rounded-full',
                                'bg-emerald-500' => $is_active,
                                'bg-red-500' => !$is_active,
                            ])></div>

                            <div>
                                <span class="block text-label-md font-label-md text-on-surface">
                                    {{ $is_active ? 'Active' : 'Inactive' }}
                                </span>

                                <span class="text-xs text-secondary">
                                    Show or hide this service publicly.
                                </span>
                            </div>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_active" class="peer sr-only" />

                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100">
                            </div>
                        </label>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50/50 p-3">
                        <div class="flex items-center gap-3">
                            <div @class([
                                'flex h-8 w-8 items-center justify-center rounded-full',
                                'bg-amber-100 text-amber-600' => $is_featured,
                                'bg-slate-100 text-slate-400' => !$is_featured,
                            ])>
                                <span class="material-symbols-outlined text-[18px]">stars</span>
                            </div>

                            <div>
                                <span class="block text-label-md font-label-md text-on-surface">
                                    Featured Service
                                </span>

                                <span class="text-xs text-secondary">
                                    Highlight this service on homepage sections.
                                </span>
                            </div>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_featured" class="peer sr-only" />

                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-amber-500 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-100">
                            </div>
                        </label>
                    </div>

                    <p class="mt-3 text-body-sm font-body-sm leading-relaxed text-secondary">
                        Active services are visible publicly. Featured services can appear in premium website sections.
                    </p>
                </div>

                <!-- Quick Preview -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-5 text-h3 font-h2">Quick Preview</h3>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">
                                {{ $icon ?: 'design_services' }}
                            </span>
                        </div>

                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            @if ($this->selectedCategory())
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    <span class="material-symbols-outlined text-[14px]">
                                        {{ $this->selectedCategory()->icon ?: 'category' }}
                                    </span>
                                    {{ $this->selectedCategory()->name }}
                                </span>
                            @endif

                            @if ($is_featured)
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    <span class="material-symbols-outlined text-[14px]">stars</span>
                                    Featured
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
                            {{ $card_title ?: 'Service Card Title' }}
                        </h4>

                        <p class="mt-2 text-sm leading-relaxed text-secondary">
                            {{ $short_description ?: 'Short service description will appear here.' }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse ($tags as $previewTag)
                                <span
                                    class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm">
                                    {{ $previewTag }}
                                </span>
                            @empty
                                <span
                                    class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-400 shadow-sm">
                                    No tags yet
                                </span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
