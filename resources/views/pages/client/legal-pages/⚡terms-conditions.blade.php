<?php

use App\Models\SiteSetting;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Terms & Conditions')] class extends Component {
    public string $terms_conditions = '';

    public function mount(): void
    {
        $this->terms_conditions = SiteSetting::query()->where('id', 1)->value('terms_conditions') ?? '';
    }
};
?>

<div>
    <section class="relative overflow-hidden pt-10">

        <div class="mx-auto w-full max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="mb-10 text-center">

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Terms & Conditions
                </h1>

                <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-blue-100/75">
                    Please read our terms carefully before using our website, services, or digital solutions.
                </p>
            </div>

            <div
                class="rounded-3xl border border-white/10 bg-white/[0.08] p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-10">
                @if (!empty($terms_conditions))
                    <article
                        class="prose prose-invert prose-blue max-w-none 
                        prose-headings:text-white 
                        prose-p:text-blue-50/80 
                        prose-a:text-cyan-300 
                        prose-strong:text-white 
                        prose-li:text-blue-50/80 
                        prose-blockquote:border-cyan-300 
                        prose-blockquote:text-blue-100 legal-content">
                        {!! $terms_conditions !!}
                    </article>
                @else
                    <div class="rounded-2xl border border-dashed border-white/15 bg-white/5 p-10 text-center">
                        <span class="material-symbols-outlined text-5xl text-blue-200/70">description</span>

                        <h2 class="mt-4 text-xl font-bold text-white">
                            Terms & Conditions Not Added Yet
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-blue-100/70">
                            Please add your terms and conditions from the admin site settings panel.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
