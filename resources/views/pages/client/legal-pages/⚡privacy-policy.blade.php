<?php

use App\Models\SiteSetting;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Privacy Policy')] class extends Component {
    public string $privacy_policy = '';

    public function mount(): void
    {
        $this->privacy_policy = SiteSetting::query()->where('id', 1)->value('privacy_policy') ?? '';
    }
};
?>

<div>
    <section class="relative overflow-hidden py-24 sm:py-28">
        <div class="absolute inset-0 -z-10">
            <div class="absolute left-1/2 top-0 h-72 w-72 -translate-x-1/2 rounded-full bg-blue-500/20 blur-3xl"></div>
            <div class="absolute bottom-0 right-10 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"></div>
        </div>

        <div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10 text-center">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-blue-100 backdrop-blur">
                    <span class="material-symbols-outlined text-[18px]">shield_lock</span>
                    Privacy & Data Protection
                </span>

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Privacy Policy
                </h1>

                <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-blue-100/75">
                    Learn how we collect, use, protect, and manage your information when you use our services.
                </p>
            </div>

            <div
                class="rounded-3xl border border-white/10 bg-white/[0.08] p-6 shadow-2xl shadow-blue-950/20 backdrop-blur-xl sm:p-10">
                @if (!empty($privacy_policy))
                    <article
                        class="prose prose-invert prose-blue max-w-none 
                        prose-headings:text-white 
                        prose-p:text-blue-50/80 
                        prose-a:text-cyan-300 
                        prose-strong:text-white 
                        prose-li:text-blue-50/80 
                        prose-blockquote:border-cyan-300 
                        prose-blockquote:text-blue-100">
                        {!! $privacy_policy !!}
                    </article>
                @else
                    <div class="rounded-2xl border border-dashed border-white/15 bg-white/5 p-10 text-center">
                        <span class="material-symbols-outlined text-5xl text-blue-200/70">lock</span>

                        <h2 class="mt-4 text-xl font-bold text-white">
                            Privacy Policy Not Added Yet
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-blue-100/70">
                            Please add your privacy policy from the admin site settings panel.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
