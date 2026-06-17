<?php

use App\Models\InvoiceTheme;
use App\Services\InvoiceThemeRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin-app')] #[Title('Edit Invoice Theme')] class extends Component {
    use WithFileUploads;

    public InvoiceTheme $invoiceTheme;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $brand_color = '#2563eb';
    public bool $is_paid = false;
    public bool $is_active = true;
    public int $sort_order = 0;
    public string $html_template = '';
    public string $css_styles = '';
    public $preview;

    public function mount(InvoiceTheme $invoiceTheme): void
    {
        $this->invoiceTheme = $invoiceTheme;
        $this->fillFromTheme();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('invoice_themes', 'slug')->ignore($this->invoiceTheme->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'html_template' => ['required', 'string', 'max:200000', 'not_regex:/<\s*(script|iframe|object|embed|form|input|button|textarea|select|meta|base)\b/i', 'not_regex:/\bon\w+\s*=/i', 'not_regex:/javascript\s*:/i'],
            'css_styles' => ['nullable', 'string', 'max:50000', 'not_regex:/<\/?style\b/i', 'not_regex:/@import/i', 'not_regex:/javascript\s*:/i', 'not_regex:/expression\s*\(/i'],
            'preview' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function resetTemplateCode(): void
    {
        $this->html_template = InvoiceThemeRenderer::starterHtml();
        $this->css_styles = InvoiceThemeRenderer::starterCss();
        $this->resetErrorBag(['html_template', 'css_styles']);
    }

    public function discardChanges(): void
    {
        $this->invoiceTheme->refresh();
        $this->preview = null;
        $this->fillFromTheme();
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate();
        $previewPath = $this->invoiceTheme->preview_image;

        if ($this->preview) {
            if ($previewPath) {
                Storage::disk('public')->delete($previewPath);
            }

            $previewPath = $this->preview->store('invoice-themes/previews', 'public');
        }

        $this->invoiceTheme->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'layout' => 'modern',
            'brand_color' => $validated['brand_color'],
            'is_paid' => $validated['is_paid'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
            'html_template' => $validated['html_template'],
            'css_styles' => $validated['css_styles'],
            'preview_image' => $previewPath,
        ]);

        session()->flash('toast', ['type' => 'success', 'message' => 'Invoice theme updated.']);
        $this->redirectRoute('admin.invoice-themes.index', navigate: true);
    }

    private function fillFromTheme(): void
    {
        $this->name = $this->invoiceTheme->name;
        $this->slug = $this->invoiceTheme->slug;
        $this->description = $this->invoiceTheme->description ?? '';
        $this->brand_color = $this->invoiceTheme->brand_color;
        $this->is_paid = $this->invoiceTheme->is_paid;
        $this->is_active = $this->invoiceTheme->is_active;
        $this->sort_order = $this->invoiceTheme->sort_order;
        $this->html_template = $this->invoiceTheme->html_template;
        $this->css_styles = $this->invoiceTheme->css_styles ?? '';
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface md:text-h1 md:font-h1">Edit {{ $invoiceTheme->name }}</h1>
            <p class="mt-1 text-xs text-secondary md:text-body-md">
                Update the HTML and CSS design separately.
            </p>
        </div>

        <a href="{{ route('admin.invoice-themes.index') }}" wire:navigate
            class="inline-flex items-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Invoice Themes
        </a>
    </div>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-[340px_1fr]">
        <aside class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-6 text-h3 font-h2">Theme settings</h3>

                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                            Card preview image
                        </label>
                        <label class="flex cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface transition-colors hover:bg-surface-container">
                            @if ($preview)
                                <img src="{{ $preview->temporaryUrl() }}" class="aspect-[4/3] w-full rounded-lg object-cover" alt="New theme preview">
                            @elseif ($invoiceTheme->preview_image)
                                <img src="{{ Storage::url($invoiceTheme->preview_image) }}" class="aspect-[4/3] w-full rounded-lg object-cover" alt="{{ $invoiceTheme->name }} preview">
                            @else
                                <div class="flex aspect-[4/3] flex-col items-center justify-center">
                                    <span class="material-symbols-outlined mb-2 text-5xl text-outline">
                                        add_photo_alternate
                                    </span>
                                    <p class="text-sm font-body-sm text-outline">Choose preview image</p>
                                    <p class="mt-1 text-xs text-secondary">Click the image to replace it.</p>
                                </div>
                            @endif
                            <input type="file" wire:model="preview" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                        </label>
                        <div wire:loading wire:target="preview" class="text-sm text-primary">Uploading image...</div>
                        @error('preview') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                            Access
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer rounded-lg border p-3 text-center text-sm font-semibold {{ ! $is_paid ? 'border-primary bg-blue-50 text-primary' : 'border-slate-200 text-slate-600' }}">
                                <input type="radio" wire:model.live="is_paid" value="0" class="sr-only"> Free
                            </label>
                            <label class="cursor-pointer rounded-lg border p-3 text-center text-sm font-semibold {{ $is_paid ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-slate-200 text-slate-600' }}">
                                <input type="radio" wire:model.live="is_paid" value="1" class="sr-only"> Pro
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Brand color
                            </label>
                            <input type="color" wire:model="brand_color"
                                class="h-11 w-full rounded-lg border border-slate-200 p-1 transition-all focus:border-primary focus:ring-2 focus:ring-primary/10">
                            @error('brand_color') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Sort order
                            </label>
                            <input type="number" min="0" wire:model="sort_order" placeholder="0"
                                class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10">
                            @error('sort_order') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center gap-3">
                            <div @class([
                                'h-2.5 w-2.5 rounded-full',
                                'bg-emerald-500' => $is_active,
                                'bg-red-500' => ! $is_active,
                            ])></div>
                            <div>
                                <span class="block text-label-md font-label-md text-on-surface">
                                    {{ $is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-xs text-secondary">Visible in the theme chooser</span>
                            </div>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_active" class="peer sr-only">
                            <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
                        </label>
                    </div>
                </div>
            </section>
        </aside>

        <section class="space-y-6 rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                <span class="material-symbols-outlined text-primary">receipt_long</span>
                Theme Code &amp; Details
            </h3>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                        Theme name
                    </label>
                    <input type="text" wire:model="name"
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10">
                    @error('name') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-2">
                    <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                        Slug
                    </label>
                    <input type="text" wire:model="slug"
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10">
                    @error('slug') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                    Description
                </label>
                <textarea wire:model="description" rows="2"
                    class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>
                @error('description') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ copied: null }" class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-bold text-slate-900">Template placeholders</h2>
                    <div class="flex gap-2">
                        <button type="button" wire:click="resetTemplateCode"
                            wire:confirm="Replace both the HTML and CSS with the starter template?"
                            class="text-xs font-semibold text-primary">Reset starter code</button>
                    </div>
                </div>
                <p class="mt-1 text-xs leading-5 text-slate-600">Use placeholders in your HTML or CSS. Click a tag to copy it. <code class="font-mono">@{{item_rows}}</code> generates the product rows.</p>
                <div class="mt-3 space-y-3">
                    @php
                        $groups = [
                            'Invoice' => ['brand_color', 'logo', 'invoice_number', 'issue_date', 'due_date', 'currency', 'notes', 'terms', 'payment_section'],
                            'Seller' => ['seller_name', 'seller_email', 'seller_phone', 'seller_address'],
                            'Customer' => ['customer_name', 'customer_email', 'customer_phone', 'customer_address'],
                            'Financial' => ['subtotal', 'tax_percent', 'tax_total', 'discount_type', 'discount_value', 'discount_label', 'discount', 'total'],
                            'Items' => ['item_rows'],
                            'Payment' => ['payment_method', 'sender_number', 'transaction_id'],
                        ];
                    @endphp
                    @foreach ($groups as $group => $placeholders)
                        <div>
                            <p class="mb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ $group }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($placeholders as $placeholder)
                                    <button type="button"
                                        x-on:click="
                                            navigator.clipboard.writeText('@{{' + '{{ $placeholder }}' + '@}}');
                                            copied = '{{ $placeholder }}';
                                            setTimeout(() => copied = null, 1500);
                                        "
                                        class="relative rounded bg-white px-2 py-1 text-[11px] text-blue-700 transition-colors hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                        title="Click to copy &#123;&#123;{{ $placeholder }}&#125;&#125;">
                                        <span x-show="copied !== '{{ $placeholder }}'" x-cloak>&#123;&#123;{{ $placeholder }}&#125;&#125;</span>
                                        <span x-show="copied === '{{ $placeholder }}'" x-cloak class="text-emerald-600">Copied!</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="space-y-2">
                    <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                        HTML Template
                    </label>
                    <p class="text-xs text-secondary">Edit the HTML div that contains your theme markup with placeholders.</p>
                    <textarea wire:model="html_template" rows="24" spellcheck="false"
                        class="w-full rounded-lg border border-slate-200 bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100 transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>
                    @error('html_template') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                        CSS Styles
                    </label>
                    <p class="text-xs text-secondary">Edit the CSS styles for your theme. Do not include <code>&lt;style&gt;</code> tags.</p>
                    <textarea wire:model="css_styles" rows="24" spellcheck="false"
                        class="w-full rounded-lg border border-slate-200 bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100 transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>
                    @error('css_styles') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <button type="button" wire:click="discardChanges"
                        class="rounded-lg border border-outline-variant px-5 py-2 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
                        Discard Changes
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save Changes</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Saving...
                        </span>
                    </button>
                </div>
            </div>
        </section>
    </form>
</div>
