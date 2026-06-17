<?php

use App\Models\Customer;
use App\Models\InvoiceTheme;
use App\Models\SavedInvoiceProduct;
use App\Models\ToolCategory;
use App\Services\InvoiceThemeRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Invoice Generator')] class extends Component {
    use WithFileUploads;

    public string $invoice_number = '';
    public string $issue_date = '';
    public string $due_date = '';
    public string $currency = 'BDT';
    public string $seller_name = '';
    public string $seller_email = '';
    public string $seller_phone = '';
    public string $seller_address = '';
    public string $seller_company_name = '';
    public string $seller_website = '';
    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $customer_address = '';
    public string $customer_shipping_name = '';
    public string $customer_shipping_email = '';
    public string $customer_shipping_phone = '';
    public string $customer_shipping_address = '';
    public ?int $selected_customer_id = null;
    public string $notes = 'Thanks for your business.';
    public string $terms = 'Payment is due by the due date shown above.';
    public string $discount_type = 'none';
    public float $discount_value = 0;
    public float $shipping_charge = 0;
    public ?int $selectedThemeId = null;
    public bool $themeChosen = false;
    public array $items = [];

    public $logo_upload = null;
    public ?string $invoice_logo = null;
    public bool $using_company_logo = false;

    public string $payment_method = '';
    public string $sender_number = '';
    public string $transaction_id = '';

    // Customer modal
    public string $customer_modal_name = '';
    public string $customer_modal_email = '';
    public string $customer_modal_phone = '';
    public string $customer_modal_shipping_address = '';
    public string $customer_modal_billing_address = '';

    // Product modal
    public string $product_modal_name = '';
    public string $product_modal_description = '';
    public string $product_modal_type = 'goods';
    public string $product_modal_unit = 'pcs';
    public float $product_modal_price = 0;
    public float $product_modal_discount_price = 0;
    public int $product_modal_stock_count = 0;
    public float $product_modal_purchase_price = 0;

    public function mount(): void
    {
         $this->invoice_number = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        $this->issue_date = now()->toDateString();
        $this->due_date = '';

        $company = auth()->user()?->company;
        $this->seller_name = $company?->company_name ?? auth()->user()?->name ?? '';
        $this->seller_email = $company?->email ?? auth()->user()?->email ?? '';
        $this->seller_phone = $company?->phone ?? '';
        $this->seller_address = $company?->address ?? '';
        $this->seller_company_name = $company?->company_name ?? '';
        $this->seller_website = $company?->website ?? '';

        $this->loadInvoiceLogo();
        $this->addItem();
    }

    public function getThemesProperty()
    {
        return InvoiceTheme::query()->active()->get();
    }

    public function getIsPremiumProperty(): bool
    {
        $category = ToolCategory::where('slug', 'business-tools')->first();

    return auth()->user()?->hasActiveToolSubscription($category) ?? false;
    }

    public function getSavedProductsProperty()
    {
        if (! $this->is_premium) {
            return collect();
        }

        return auth()->user()->savedInvoiceProducts()->latest()->get();
    }

    public function chooseTheme(int $themeId): void
    {
        $theme = InvoiceTheme::query()->active()->findOrFail($themeId);

        if (! $theme->isAccessibleBy(auth()->user())) {
            $this->addError('selectedThemeId', 'This Pro theme requires an active Business Tools subscription.');

            return;
        }

        $this->selectedThemeId = $theme->id;
        $this->themeChosen = true;
        $this->resetValidation('selectedThemeId');
    }

    public function changeTheme(): void
    {
        $this->themeChosen = false;
        $this->resetValidation('selectedThemeId');
    }

    public function saveInvoiceLogo(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'logo_upload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth()->user();

        if ($user->invoice_logo && Storage::disk('public')->exists($user->invoice_logo)) {
            Storage::disk('public')->delete($user->invoice_logo);
        }

        $user->invoice_logo = $this->logo_upload->store('users/invoice-logos', 'public');
        $user->save();

        $this->logo_upload = null;
        $this->using_company_logo = false;
        $this->loadInvoiceLogo();
        $this->dispatch('toast', message: 'Invoice logo saved successfully.', type: 'success');
    }

    public function useCompanyLogo(): void
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();

        if ($this->logo_upload) {
            $this->logo_upload = null;
        }

        if ($user->invoice_logo && Storage::disk('public')->exists($user->invoice_logo)) {
            Storage::disk('public')->delete($user->invoice_logo);
        }

        $user->invoice_logo = null;
        $user->save();

        $this->loadInvoiceLogo();
        $this->dispatch('toast', message: 'Using company logo.', type: 'success');
    }

    public function resetInvoiceLogo(): void
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();

        if ($user->invoice_logo && Storage::disk('public')->exists($user->invoice_logo)) {
            Storage::disk('public')->delete($user->invoice_logo);
        }

        $user->invoice_logo = null;
        $user->save();

        $this->loadInvoiceLogo();
        $this->dispatch('toast', message: 'Reverted to company logo.', type: 'success');
    }

    public function addItem(): void
    {
        $this->items[] = [
            'name' => '',
            'description' => '',
            'quantity' => 1,
            'purchase_price' => 0,
            'unit_price' => 0,
            'stock_qty' => 0,
            'image' => null,
            'tax' => 0,
        ];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) === 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function useSavedProduct(int $productId, ?int $index = null): void
    {
        $this->ensurePremium();
        $product = auth()->user()->savedInvoiceProducts()->findOrFail($productId);

        $item = [
            'name' => $product->name,
            'description' => $product->description ?? '',
            'quantity' => 1,
            'purchase_price' => (float) ($product->purchase_price ?? 0),
            'unit_price' => (float) $product->unit_price,
            'stock_qty' => $product->stock_count ?? 0,
            'image' => null,
            'tax' => (float) ($product->tax_rate ?? 0),
        ];

        if ($index !== null && isset($this->items[$index])) {
            $this->items[$index] = $item;

            return;
        }

        if (count($this->items) === 1 && blank($this->items[0]['name'])) {
            $this->items[0] = $item;

            return;
        }

        $this->items[] = $item;
    }

    public function saveItem(int $index): void
    {
        $this->ensurePremium();
        $item = $this->items[$index] ?? null;

        if (! $item || blank($item['name'])) {
            $this->addError("items.$index.name", 'Please enter an item name before saving.');

            return;
        }

        if ((float) ($item['unit_price'] ?? 0) <= 0) {
            $this->addError("items.$index.unit_price", 'Please enter a valid price before saving.');

            return;
        }

        auth()->user()->savedInvoiceProducts()->create([
            'name' => $item['name'],
            'description' => $item['description'] ?? '',
            'type' => $item['type'] ?? 'goods',
            'unit' => $item['unit'] ?? 'pcs',
            'unit_price' => $item['unit_price'],
            'purchase_price' => $item['purchase_price'] ?: null,
            'stock_count' => $item['stock_qty'] ?: null,
            'tax_rate' => $item['tax'] ?: null,
        ]);

        $this->dispatch('toast', message: '"'.$item['name'].'" saved for future invoices.', type: 'success');
    }

    public function getCustomersProperty()
    {
        if (! $this->is_premium) {
            return collect();
        }

        return auth()->user()->customers()->latest()->get();
    }

    public function selectCustomer(int $id): void
    {
        $this->ensurePremium();
        $customer = auth()->user()->customers()->findOrFail($id);

        $this->customer_name = $customer->name;
        $this->customer_email = $customer->email;
        $this->customer_phone = $customer->phone;
        $this->customer_address = $customer->billing_address;
        $this->customer_shipping_name = $customer->name;
        $this->customer_shipping_email = $customer->email;
        $this->customer_shipping_phone = $customer->phone;
        $this->customer_shipping_address = $customer->shipping_address;
    }

    public function updatedSelectedCustomerId(?int $id): void
    {
        if ($id) {
            $this->selectCustomer($id);
        } else {
            $this->customer_name = '';
            $this->customer_email = '';
            $this->customer_phone = '';
            $this->customer_address = '';
            $this->customer_shipping_name = '';
            $this->customer_shipping_email = '';
            $this->customer_shipping_phone = '';
            $this->customer_shipping_address = '';
        }
    }

    public function saveCustomerFromModal(): void
    {
        $this->ensurePremium();

        $validated = $this->validate([
            'customer_modal_name' => ['required', 'string', 'max:150'],
            'customer_modal_email' => ['nullable', 'email', 'max:150'],
            'customer_modal_phone' => ['nullable', 'string', 'max:80'],
            'customer_modal_shipping_address' => ['nullable', 'string', 'max:1000'],
            'customer_modal_billing_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = auth()->user()->customers()->create([
            'name' => $validated['customer_modal_name'],
            'email' => $validated['customer_modal_email'],
            'phone' => $validated['customer_modal_phone'],
            'shipping_address' => $validated['customer_modal_shipping_address'],
            'billing_address' => $validated['customer_modal_billing_address'],
        ]);

        $this->selectCustomer($customer->id);
        $this->selected_customer_id = $customer->id;
        $this->reset(
            'customer_modal_name',
            'customer_modal_email',
            'customer_modal_phone',
            'customer_modal_shipping_address',
            'customer_modal_billing_address',
        );

        $this->dispatch('close-customer-modal');
        $this->dispatch('customer-selected', name: $customer->name);
    }

    public function selectProduct(int $id, ?int $index = null): void
    {
        $this->ensurePremium();
        $product = auth()->user()->savedInvoiceProducts()->findOrFail($id);

        $item = [
            'name' => $product->name,
            'description' => $product->description ?? '',
            'quantity' => 1,
            'purchase_price' => (float) ($product->purchase_price ?? 0),
            'unit_price' => (float) $product->unit_price,
            'stock_qty' => $product->stock_count ?? 0,
            'image' => null,
            'tax' => (float) ($product->tax_rate ?? 0),
        ];

        if ($index !== null && isset($this->items[$index]) && blank($this->items[$index]['name'])) {
            $this->items[$index] = $item;
        } else {
            $this->items[] = $item;
        }
    }

    public function saveProductFromModal(): void
    {
        $this->ensurePremium();

        $validated = $this->validate([
            'product_modal_name' => ['required', 'string', 'max:150'],
            'product_modal_description' => ['nullable', 'string', 'max:1000'],
            'product_modal_type' => ['required', 'string', 'in:goods,service'],
            'product_modal_unit' => ['required', 'string', 'max:10'],
            'product_modal_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'product_modal_discount_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'product_modal_stock_count' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'product_modal_purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $product = auth()->user()->savedInvoiceProducts()->create([
            'name' => $validated['product_modal_name'],
            'description' => $validated['product_modal_description'],
            'type' => $validated['product_modal_type'],
            'unit' => $validated['product_modal_unit'],
            'unit_price' => $validated['product_modal_price'],
            'discount_price' => $validated['product_modal_discount_price'] ?: null,
            'stock_count' => $validated['product_modal_stock_count'] ?: null,
            'purchase_price' => $validated['product_modal_purchase_price'] ?: null,
        ]);

        $this->selectProduct($product->id);

        $this->reset(
            'product_modal_name',
            'product_modal_description',
            'product_modal_type',
            'product_modal_unit',
            'product_modal_price',
            'product_modal_discount_price',
            'product_modal_stock_count',
            'product_modal_purchase_price',
        );

        $this->dispatch('close-product-modal');
        $this->dispatch('toast', message: 'Product "'.$product->name.'" saved.', type: 'success');
    }

    public function download()
    {
        $validated = $this->validate([
            'invoice_number' => ['required', 'string', 'max:80'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'max:10'],
            'seller_name' => ['required', 'string', 'max:150'],
            'seller_email' => ['nullable', 'email', 'max:150'],
            'seller_phone' => ['nullable', 'string', 'max:80'],
            'seller_address' => ['nullable', 'string', 'max:1000'],
            'seller_company_name' => ['nullable', 'string', 'max:150'],
            'seller_website' => ['nullable', 'string', 'max:250'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:80'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'customer_shipping_name' => ['nullable', 'string', 'max:150'],
            'customer_shipping_email' => ['nullable', 'email', 'max:150'],
            'customer_shipping_phone' => ['nullable', 'string', 'max:80'],
            'customer_shipping_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', 'in:none,fixed,percentage'],
            'discount_value' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->discount_type === 'percentage' && (float) $value > 100) {
                        $fail('The discount percentage cannot be greater than 100.');
                    }
                },
            ],
            'selectedThemeId' => ['required', 'exists:invoice_themes,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.tax' => ['required', 'numeric', 'min:0', 'max:100'],
            'shipping_charge' => ['required', 'numeric', 'min:0', 'max:9999999999'],

            'payment_method' => ['nullable', 'string', 'max:30'],
            'sender_number' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);

        $theme = InvoiceTheme::query()->active()->findOrFail($validated['selectedThemeId']);

        if (! $theme->isAccessibleBy(auth()->user())) {
            throw ValidationException::withMessages([
                'selectedThemeId' => 'This paid theme requires an active Business Tools subscription.',
            ]);
        }

        $items = collect($validated['items'])->map(function (array $item) {
            $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
            $itemTax = $lineTotal * ((float) ($item['tax'] ?? 0) / 100);

            return $item + ['line_total' => $lineTotal, 'item_tax' => $itemTax];
        });
        $subtotal = $items->sum('line_total');
        $taxTotal = $items->sum('item_tax');
        $shippingCharge = (float) $validated['shipping_charge'];
        $beforeDiscount = $subtotal + $taxTotal + $shippingCharge;
        $discountAmount = match ($validated['discount_type']) {
            'percentage' => $beforeDiscount * (min((float) $validated['discount_value'], 100) / 100),
            'fixed' => (float) $validated['discount_value'],
            default => 0,
        };
        $discountAmount = min($beforeDiscount, $discountAmount);
        $validated['discount'] = $discountAmount;
        $validated['discount_label'] = match ($validated['discount_type']) {
            'percentage' => number_format((float) $validated['discount_value'], 2).'%',
            'fixed' => 'Fixed discount',
            default => 'No discount',
        };
        $validated['payment_method'] = $this->payment_method;
        $validated['sender_number'] = $this->sender_number;
        $validated['transaction_id'] = $this->transaction_id;

        $total = max(0, $beforeDiscount - $discountAmount);
        $logoDataUri = $this->logoDataUri();

        if ($theme->usesCustomTemplate()) {
            $html = app(InvoiceThemeRenderer::class)->render(
                $theme,
                $validated,
                $items,
                $subtotal,
                $taxTotal,
                $total,
                $logoDataUri,
            );
            $pdf = Pdf::loadHTML($html)->setPaper('a4');
        } else {
            $pdf = Pdf::loadView('pdf.generated-invoice', [
                'invoice' => $validated,
                'theme' => $theme,
                'items' => $items,
                'subtotal' => $subtotal,
                'taxTotal' => $taxTotal,
                'discountAmount' => $discountAmount,
                'total' => $total,
                'logoDataUri' => $logoDataUri,
            ])->setPaper('a4');
        }

        return response()->streamDownload(
            fn () => print($pdf->output()),
            Str::slug($validated['invoice_number']) . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function ensurePremium(): void
    {
        abort_unless(auth()->check() && $this->is_premium, 403, 'An active Business Tools subscription is required.');
    }

    private function loadInvoiceLogo(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        if ($user->invoice_logo) {
            $this->using_company_logo = false;
            $this->invoice_logo = $user->invoice_logo;
        } else {
            $companyLogo = $user->company?->logo;
            $this->using_company_logo = filled($companyLogo);
            $this->invoice_logo = $companyLogo;
        }
    }

    private function logoDataUri(): ?string
    {
        if ($this->logo_upload) {
            return 'data:' . $this->logo_upload->getMimeType() . ';base64,' . base64_encode((string) $this->logo_upload->getContent());
        }

        if (! $this->invoice_logo || ! Storage::disk('public')->exists($this->invoice_logo)) {
            return null;
        }

        $path = Storage::disk('public')->path($this->invoice_logo);
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
};
?>

<div class="min-h-screen text-white">
    <section class="mx-auto max-w-350 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-10 text-center">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-cyan-300">Business Tools</p>
            <h1 class="mt-3 text-4xl font-extrabold sm:text-6xl">Invoice Generator</h1>
            <p class="mx-auto mt-4 max-w-2xl text-blue-100/60">
                {{ $themeChosen ? 'Add your business details and create a ready-to-send invoice.' : 'Choose an invoice design to get started.' }}
            </p>
        </div>

        @if (! $themeChosen)
            <section>
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-300">Step 1</p>
                        <h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">Choose your invoice theme</h2>
                    </div>
                    <span class="hidden text-sm text-blue-100/50 sm:block">{{ $this->themes->count() }} designs available</span>
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    @forelse ($this->themes as $theme)
                        @php($locked = $theme->is_paid && ! $this->is_premium)
                        <article @class([
                            'group relative overflow-hidden rounded-3xl border bg-white/6 shadow-[0_24px_70px_rgba(0,0,0,0.22)] backdrop-blur-xl transition',
                            'border-white/10 hover:-translate-y-1 hover:border-cyan-300/35' => ! $locked,
                            'border-white/10 opacity-65' => $locked,
                        ])>
                            <div class="h-72 overflow-hidden bg-slate-100">
                                @if ($theme->preview_image)
                                    <img src="{{ asset('storage/' . $theme->preview_image) }}" alt="{{ $theme->name }} template preview"
                                        class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.025]">
                                @else
                                    <div class="flex h-full items-center justify-center">
                                        <span class="material-symbols-outlined text-7xl text-slate-300">receipt_long</span>
                                    </div>
                                @endif
                            </div>

                            <div class="p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-xl font-bold">{{ $theme->name }}</h3>
                                        <p class="mt-1 line-clamp-2 text-sm leading-6 text-blue-100/55">{{ $theme->description ?: 'Professional invoice template' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black {{ $theme->is_paid ? 'bg-amber-400/15 text-amber-300' : 'bg-emerald-400/15 text-emerald-300' }}">
                                        {{ $theme->is_paid ? 'PRO' : 'FREE' }}
                                    </span>
                                </div>

                                @if ($locked)
                                    <div class="mt-5 rounded-xl border border-amber-300/20 bg-amber-400/10 px-4 py-3 text-center text-sm font-bold text-amber-200">
                                        Business Tools subscription required
                                    </div>
                                @else
                                    <button type="button" wire:click="chooseTheme({{ $theme->id }})"
                                        class="mt-5 w-full cursor-pointer rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-5 py-3 text-sm font-black tracking-wider text-white shadow-lg shadow-cyan-500/20 transition hover:-translate-y-0.5">
                                        Use this theme
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-white/15 bg-white/[0.04] p-12 text-center">
                            <span class="material-symbols-outlined text-6xl text-blue-100/25">receipt_long</span>
                            <h3 class="mt-4 text-xl font-bold">No invoice themes available</h3>
                            <p class="mt-2 text-blue-100/50">Please check back after an administrator publishes a theme.</p>
                        </div>
                    @endforelse
                </div>
                @error('selectedThemeId') <p class="mt-4 text-center text-sm text-red-300">{{ $message }}</p> @enderror
            </section>
        @else
        @php($chosenTheme = $this->themes->firstWhere('id', $selectedThemeId))
        <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-cyan-300/20 bg-cyan-400/[0.07] p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                @if ($chosenTheme?->preview_image)
                    <img src="{{ asset('storage/' . $chosenTheme->preview_image) }}" alt="" class="h-16 w-20 rounded-lg object-cover object-top">
                @endif
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">Selected theme</p>
                    <p class="mt-1 font-bold">{{ $chosenTheme?->name }}</p>
                </div>
            </div>
            <button type="button" wire:click="changeTheme" class="rounded-lg border border-white/15 px-4 py-2 text-sm font-bold text-blue-100 transition hover:bg-white/10">
                Change theme
            </button>
        </div>

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

        <form wire:submit="download" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-white/10 bg-white/6 p-6 backdrop-blur-xl">
                    <h2 class="mb-5 text-xl font-bold">Invoice number</h2>
                    <div>
                        <input wire:model="invoice_number" placeholder="e.g. INV-20260616-ABC1"
                            class="w-full rounded-xl border border-white/10 bg-black/20 px-5 py-4 text-lg font-bold tracking-wider text-center">
                        @error('invoice_number') <p class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/6 p-6 backdrop-blur-xl">
                    <h2 class="mb-5 text-xl font-bold">Dates</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="text-sm text-blue-100/70">Issue date</label><input wire:model="issue_date" type="date" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">@error('issue_date') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror</div>
                        <div><label class="text-sm text-blue-100/70">Due date <span class="text-blue-100/40">(optional)</span></label><input wire:model="due_date" type="date" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">@error('due_date') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror</div>
                    </div>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/6 p-6 backdrop-blur-xl">
                    @if ($this->is_premium)
                    <div class="mb-5">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-300">Customer</p>
                    </div>
                    <div class="mb-6" x-data="{
                        open: false,
                        search: '',
                        customers: {{ Js::from($this->customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'email' => $c->email])->values()->toArray()) }},
                        get filtered() {
                            if (!this.search) return this.customers;
                            const q = this.search.toLowerCase();
                            return this.customers.filter(c => c.name.toLowerCase().includes(q));
                        },
                        select(id, name) {
                            $wire.set('selected_customer_id', id);
                            this.search = name;
                            this.open = false;
                        },
                        clear() {
                            $wire.set('selected_customer_id', null);
                            this.search = '';
                            this.open = false;
                        }
                    }" x-on:click.away="open = false"
                    x-on:customer-selected.window="search = $event.detail.name">
                        <div class="relative">
                            <div class="flex items-center rounded-xl border border-white/10 bg-black/20">
                                <input x-model="search"
                                    x-on:focus="open = true"
                                    x-on:input="open = true"
                                    placeholder="Search customers..."
                                    autocomplete="off"
                                    class="flex-1 bg-transparent px-4 py-3 outline-none">
                                <button type="button" x-on:click="clear()" x-show="search"
                                    class="mr-2 flex size-6 items-center justify-center rounded-full text-blue-100/50 transition hover:bg-white/10 hover:text-blue-100/80">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                            <div x-show="open && (filtered.length || search)"
                                x-cloak
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-auto rounded-xl border border-white/10 bg-slate-900 shadow-xl">
                                <template x-for="customer in filtered" :key="customer.id">
                                    <button type="button"
                                        x-on:click="select(customer.id, customer.name)"
                                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/10">
                                        <span class="material-symbols-outlined text-blue-100/40">person</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-medium" x-text="customer.name"></p>
                                            <p class="truncate text-xs text-blue-100/50" x-text="customer.email"></p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" x-on:click="$dispatch('open-customer-modal')"
                                class="inline-flex items-center gap-1 text-sm font-semibold text-cyan-200 transition hover:text-cyan-100">
                                <span class="material-symbols-outlined text-base">add_circle</span>
                                Add new customer
                            </button>
                        </div>
                    </div>
                    @else
                        <div class="mb-5">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-300">Customer</p>
                        </div>
                        <div class="mb-6 grid gap-4 md:grid-cols-2">
                            <div class="rounded-xl border border-white/10 bg-white/3 p-4">
                                <p class="mb-3 text-sm font-bold text-blue-100/70">Billing contact</p>
                                <div class="space-y-3">
                                    <div><input wire:model="customer_name" placeholder="Full name" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">@error('customer_name') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror</div>
                                    <input wire:model="customer_email" type="email" placeholder="Email" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                                    <input wire:model="customer_phone" placeholder="Phone" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                                    <textarea wire:model="customer_address" rows="3" placeholder="Address" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3"></textarea>
                                </div>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/3 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-sm font-bold text-blue-100/70">Shipping contact</p>
                                    <button type="button" x-on:click="$wire.set('customer_shipping_name', $wire.customer_name); $wire.set('customer_shipping_email', $wire.customer_email); $wire.set('customer_shipping_phone', $wire.customer_phone); $wire.set('customer_shipping_address', $wire.customer_address)"
                                        class="text-xs font-semibold text-cyan-300 hover:text-cyan-200 transition cursor-pointer">
                                        Copy from billing
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <input wire:model="customer_shipping_name" placeholder="Full name" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                                    <input wire:model="customer_shipping_email" type="email" placeholder="Email" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                                    <input wire:model="customer_shipping_phone" placeholder="Phone" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                                    <textarea wire:model="customer_shipping_address" rows="3" placeholder="Address" class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3"></textarea>
                                </div>
                            </div>
                        </div>
                    @endif

                    <hr class="mb-6 border-white/10">

                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-xl font-bold">Products and Services</h2>
                        <div class="flex items-center gap-2">
                            @if ($this->is_premium)
                            <button type="button" x-on:click="$dispatch('open-product-modal')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-cyan-300/30 px-3 py-1.5 text-xs font-bold text-cyan-200 transition hover:bg-cyan-400/10">
                                <span class="material-symbols-outlined text-base">add</span>
                                Add Product
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-visible rounded-xl border border-white/10">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-white/10 text-xs font-bold uppercase tracking-wider text-blue-100/70">
                                    <th class="px-3 py-3 min-w-35">Item</th>
                                    <th class="px-3 py-3 text-center w-20">QTY</th>
                                    <th class="px-3 py-3 text-right w-28">Price</th>
                                    <th class="px-3 py-3 text-right w-20">Tax</th>
                                    <th class="px-3 py-3 text-right w-28">Amount</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @foreach ($items as $index => $item)
                                    <tr wire:key="invoice-item-{{ $index }}" class="bg-black/10">
                                        <td class="px-3 py-2">
                                            @if ($this->is_premium)
                                            <div class="relative" x-data="{
                                                open: false,
                                                query: @entangle('items.' . $index . '.name').live,
                                                products: {{ Js::from($this->saved_products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description ?? '', 'unit_price' => (float) $p->unit_price, 'tax' => (float) ($p->tax_rate ?? 0)])->values()->toArray()) }},
                                                get filtered() {
                                                    if (!this.query) return this.products;
                                                    const q = this.query.toLowerCase();
                                                    return this.products.filter(p => p.name.toLowerCase().includes(q));
                                                },
                                                select(product) {
                                                    $wire.set('items.{{ $index }}.name', product.name);
                                                    $wire.set('items.{{ $index }}.description', product.description ?? '');
                                                    $wire.set('items.{{ $index }}.unit_price', product.unit_price);
                                                    $wire.set('items.{{ $index }}.tax', product.tax);
                                                    this.open = false;
                                                }
                    }" x-on:click.away="open = false"
                    x-on:customer-selected.window="search = $event.detail.name">
                                                <input placeholder="Item name"
                                                    x-model="query"
                                                    x-on:focus="open = true"
                                                    x-on:input="open = true"
                                                    autocomplete="off"
                                                    class="w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2 text-sm">
                                                <div x-show="open && filtered.length > 0"
                                                    x-cloak
                                                    class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-auto rounded-lg border border-white/10 bg-slate-900 shadow-xl">
                                                    <template x-for="product in filtered" :key="product.id">
                                                    <button type="button"
                                                        x-on:click="select(product)"
                                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-white/10">
                                                        <span x-text="product.name" class="font-medium truncate"></span>
                                                        <span x-text="product.unit_price.toFixed(2)" class="shrink-0 ml-2 text-blue-100/50"></span>
                                                    </button>
                                                    </template>
                                                </div>
                                            </div>
                                            @else
                                            <input wire:model="items.{{ $index }}.name" placeholder="Item name"
                                                class="w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2 text-sm">
                                            @endif
                                            @error('items.{{ $index }}.name') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                                            <textarea wire:model="items.{{ $index }}.description" rows="2" placeholder="Description (optional)"
                                                class="mt-1 w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2 text-sm" style="field-sizing: content"></textarea>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input wire:model.live="items.{{ $index }}.quantity" type="number" min="0" step="0.1" placeholder="1"
                                                class="w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-center">
                                            @error('items.{{ $index }}.quantity') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input wire:model.live="items.{{ $index }}.unit_price" type="number" min="0" step="0.1" placeholder="0.00"
                                                class="w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-right">
                                            @error('items.{{ $index }}.unit_price') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input wire:model.live="items.{{ $index }}.tax" type="number" min="0" max="100" step="0.01" placeholder="0"
                                                class="w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-right">
                                        </td>
                                        <td class="px-3 py-2 text-right font-bold text-white text-sm">
                                            {{ number_format((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" wire:click="removeItem({{ $index }})"
                                                @disabled(count($items) === 1)
                                                wire:confirm="Remove this item?"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-red-400/25 bg-red-500/10 text-red-200 transition hover:border-red-400/40 hover:bg-red-500/20 disabled:cursor-not-allowed disabled:opacity-40">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" wire:click="addItem"
                        class="mt-4 inline-flex items-center justify-center gap-1.5 rounded-lg bg-cyan-500/15 px-3 py-1.5 text-xs font-bold text-cyan-200 transition hover:bg-cyan-500/25">
                        <span class="material-symbols-outlined text-base">add</span>
                        Add Item
                    </button>

                    @error('items.*.name') <p class="mt-3 text-sm text-red-300">{{ $message }}</p> @enderror
                    @error('shipping_charge') <p class="mt-3 text-sm text-red-300">{{ $message }}</p> @enderror
                </section>

                <section class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-bold text-blue-100/70">Notes</p>
                        <textarea wire:model="notes" rows="4" placeholder="Notes" class="w-full rounded-2xl border border-white/10 bg-white/6 p-5"></textarea>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-bold text-blue-100/70">Terms and conditions</p>
                        <textarea wire:model="terms" rows="4" placeholder="Terms and conditions" class="w-full rounded-2xl border border-white/10 bg-white/6 p-5"></textarea>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-white/10 bg-white/6 p-5 backdrop-blur-xl">
                    <h2 class="text-lg font-bold">Invoice logo</h2>

                    <div class="mt-4 flex min-h-28 items-center justify-center rounded-xl border border-white/10 bg-white p-4">
                        @if ($logo_upload)
                            <img src="{{ $logo_upload->temporaryUrl() }}" alt="Invoice logo preview" class="max-h-20 max-w-full object-contain">
                        @elseif ($invoice_logo)
                            <img src="{{ asset('storage/' . $invoice_logo) }}" alt="Invoice logo" class="max-h-20 max-w-full object-contain">
                        @else
                            <span class="material-symbols-outlined text-5xl text-slate-300">image</span>
                        @endif
                    </div>

                    <div class="mt-4 space-y-3">
                        <label for="invoice-logo-upload"
                            class="flex cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-cyan-300/30 bg-cyan-400/6 px-4 py-5 text-center transition hover:border-cyan-300/60 hover:bg-cyan-400/10">
                            <span class="material-symbols-outlined text-2xl text-cyan-300">upload_file</span>
                            <span>
                                <span class="block text-sm font-bold text-cyan-100">
                                    {{ $logo_upload ? $logo_upload->getClientOriginalName() : 'Choose invoice logo' }}
                                </span>
                                <span class="mt-1 block text-xs text-blue-100/45">PNG, JPG, or WebP up to 2 MB</span>
                            </span>
                        </label>
                        <input id="invoice-logo-upload" wire:model="logo_upload" type="file"
                            accept="image/png,image/jpeg,image/webp" class="sr-only">

                        <p wire:loading wire:target="logo_upload" class="text-center text-xs font-semibold text-cyan-200">
                            Loading logo preview...
                        </p>
                        @error('logo_upload') <p class="text-sm text-red-300">{{ $message }}</p> @enderror

                            @if ($logo_upload)
                                <p class="text-xs text-blue-100/50">Logo will be used in the PDF.</p>
                            @endif
                            @if (auth()->check() && auth()->user()->company?->logo && ($logo_upload || ! $this->using_company_logo))
                                <button type="button" wire:click="useCompanyLogo" wire:loading.attr="disabled"
                                    class="mt-2 w-full rounded-lg border border-white/10 py-2 text-xs font-semibold text-blue-100/70 transition hover:bg-white/10">
                                    Use company logo
                                </button>
                            @endif
                        </div>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/6 p-5 backdrop-blur-xl">
                    <h2 class="mb-4 text-lg font-bold">Totals and discount</h2>
                    <div class="space-y-4">
                        <div><label class="text-sm text-blue-100/70">Currency</label><input wire:model="currency" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">@error('currency') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror</div>
                        <div>
                            <label class="text-sm text-blue-100/70">Discount type</label>
                            <select wire:model.live="discount_type" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                                <option value="none">No discount</option>
                                <option value="fixed">Fixed amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </div>
                        @if ($discount_type !== 'none')
                            <div>
                                <label class="text-sm text-blue-100/70">
                                    {{ $discount_type === 'percentage' ? 'Discount percentage' : 'Discount amount' }}
                                </label>
                                <div class="relative mt-2">
                                    <input wire:model="discount_value" type="number" min="0"
                                        max="{{ $discount_type === 'percentage' ? 100 : 9999999999 }}" step="0.1"
                                        class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 pr-12">
                                    <span class="absolute inset-y-0 right-4 flex items-center text-sm font-bold text-blue-100/45">
                                        {{ $discount_type === 'percentage' ? '%' : $currency }}
                                    </span>
                                </div>
                            </div>
                        @endif
                        @error('discount_type') <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                        @error('discount_value') <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                        <div x-data="{ enabled: {{ $shipping_charge > 0 ? 'true' : 'false' }} }">
                            <div class="flex items-center justify-between">
                                <label class="text-sm text-blue-100/70">Shipping charge</label>
                                <button type="button" x-on:click="enabled = ! enabled; if (!enabled) $wire.set('shipping_charge', 0)"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border border-white/10 transition"
                                    x-bind:class="enabled ? 'bg-cyan-500' : 'bg-white/10'">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition"
                                        x-bind:class="enabled ? 'translate-x-[18px]' : 'translate-x-[3px]'"></span>
                                </button>
                            </div>
                            <template x-if="enabled">
                                <input wire:model.live="shipping_charge" type="number" min="0" step="0.1" placeholder="0.00"
                                    class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-right">
                            </template>
                            <template x-if="!enabled">
                                <p class="mt-2 text-sm text-emerald-300/80 font-semibold">Free shipping</p>
                            </template>
                            @error('shipping_charge') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/6 p-5 backdrop-blur-xl">
                    <h2 class="mb-4 text-lg font-bold">Payment method</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-blue-100/70">Payment method</label>
                            <select wire:model.live="payment_method" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                                <option value="">Select method</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="bkash">bKash</option>
                                <option value="nagad">Nagad</option>
                                <option value="rocket">Rocket</option>
                            </select>
                        </div>
                        @if ($payment_method && $payment_method !== 'cash')
                            <div>
                                <label class="text-sm text-blue-100/70">Sender number</label>
                                <input wire:model="sender_number" placeholder="01XXXXXXXXX"
                                    class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                            </div>
                            <div>
                                <label class="text-sm text-blue-100/70">Transaction ID</label>
                                <input wire:model="transaction_id" placeholder="TrxID or reference"
                                    class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                            </div>
                        @endif
                        @error('payment_method') <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                        @error('sender_number') <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                        @error('transaction_id') <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                </section>

                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 px-6 py-4 font-black uppercase tracking-wider shadow-xl shadow-cyan-500/20 disabled:opacity-60 cursor-pointer">
                    <span wire:loading.remove wire:target="download">Download PDF</span>
                    <span wire:loading wire:target="download">Generating...</span>
                </button>
            </aside>
        </form>
        @endif
    </section>

    @if ($this->is_premium)
    {{-- Customer modal --}}
    <div x-data="{ open: false, copyBilling: false }"
        x-on:open-customer-modal.window="open = true"
        x-on:close-customer-modal.window="open = false; copyBilling = false"
        x-on:keydown.escape.window="open = false"
        x-cloak
        x-show="open"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/70" x-on:click="open = false"></div>
        <div x-show="open" x-transition
            class="relative w-full max-w-xl rounded-2xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-xl font-bold">Add customer</h3>
                <button type="button" x-on:click="open = false" class="text-blue-100/50 hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Customer name <span class="text-red-300">*</span></label>
                        <input wire:model="customer_modal_name" placeholder="Enter customer name"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                        @error('customer_modal_name') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Email</label>
                        <input wire:model="customer_modal_email" type="email" placeholder="Enter email address"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                    </div>
                </div>

                <div>
                    <label class="text-sm font-bold text-blue-100/70">Phone</label>
                    <input wire:model="customer_modal_phone" placeholder="Enter phone number"
                        class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                </div>

                <div class="rounded-xl border border-white/10 bg-white/3 p-4">
                    <label class="text-sm font-bold text-blue-100/70">Billing address</label>
                    <textarea wire:model="customer_modal_billing_address" rows="3" placeholder="Street, city, zip code, country"
                        class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3"></textarea>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/3 p-4">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-bold text-blue-100/70">Shipping address</label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-blue-100/50 hover:text-cyan-200">
                            <input type="checkbox" x-model="copyBilling" class="rounded border-white/20 bg-black/20 text-cyan-500">
                            Same as billing
                        </label>
                    </div>
                    <textarea wire:model="customer_modal_shipping_address" rows="3" placeholder="Street, city, zip code, country"
                        x-bind:disabled="copyBilling"
                        x-bind:class="copyBilling ? 'opacity-40' : ''"
                        class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3"></textarea>
                </div>

                <button type="button" wire:click="saveCustomerFromModal" wire:loading.attr="disabled"
                    class="w-full rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 py-3 font-bold text-white disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveCustomerFromModal">Save customer</span>
                    <span wire:loading wire:target="saveCustomerFromModal">Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Product modal --}}
    <div x-data="{ open: false }"
        x-on:open-product-modal.window="open = true"
        x-on:close-product-modal.window="open = false"
        x-on:keydown.escape.window="open = false"
        x-cloak
        x-show="open"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/70" x-on:click="open = false"></div>
        <div x-show="open" x-transition
            class="relative w-full max-w-lg rounded-2xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-xl font-bold">Add new product</h3>
                <button type="button" x-on:click="open = false" class="text-blue-100/50 hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-bold text-blue-100/70">Name <span class="text-red-300">*</span></label>
                    <input wire:model="product_modal_name" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                    @error('product_modal_name') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-bold text-blue-100/70">Description</label>
                    <textarea wire:model="product_modal_description" rows="3" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3"></textarea>
                    @error('product_modal_description') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Type <span class="text-red-300">*</span></label>
                        <select wire:model="product_modal_type" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                            <option value="goods">Goods</option>
                            <option value="service">Service</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Unit <span class="text-red-300">*</span></label>
                        <select wire:model="product_modal_unit" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                            @foreach (['pcs','box','cm','dz','ft','g','in','kg','km','lb','mg','ml','m'] as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Selling price <span class="text-red-300">*</span></label>
                        <input wire:model="product_modal_price" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                        @error('product_modal_price') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Discount price</label>
                        <input wire:model="product_modal_discount_price" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Purchase price</label>
                        <input wire:model="product_modal_purchase_price" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-blue-100/70">Stock count</label>
                        <input wire:model="product_modal_stock_count" type="number" min="0" step="1" class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                    </div>
                </div>
                <button type="button" wire:click="saveProductFromModal" wire:loading.attr="disabled"
                    class="w-full rounded-xl bg-linear-to-r from-cyan-500 to-blue-500 py-3 font-bold text-white disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveProductFromModal">Save & add to invoice</span>
                    <span wire:loading wire:target="saveProductFromModal">Saving...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type="number"] {
        -moz-appearance: textfield;
    }
    select option {
        background: #0f172a;
        color: #fff;
    }
</style>
