@props(['title', 'description', 'draftNotice', 'sections'])

{{-- Shared layout for Terms and Privacy: hero, draft notice, sticky table of contents, prose. --}}
<x-layouts.public :title="$title" :description="$description">
    <x-public.page-hero eyebrow="Legal" :title="$title" :lead="'Version '.config('orbit.terms_version')" />

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-12">
            <aside class="lg:col-span-3">
                <nav aria-label="Document sections" class="rounded-2xl border border-edge bg-surface p-5 shadow-float lg:sticky lg:top-28">
                    <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">Contents</p>
                    <ol class="mt-4 space-y-1 text-[0.9375rem]">
                        @foreach ($sections as $id => $label)
                            <li><a href="#{{ $id }}" class="block rounded-lg px-3 py-1.5 text-fg-muted transition-colors hover:bg-surface-muted hover:text-fg">{{ $label }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </aside>

            <div class="lg:col-span-9">
                <x-alert type="warning" title="Draft for review">{{ $draftNotice }}</x-alert>
                <article class="prose-orbit mt-8 rounded-3xl border border-edge bg-surface p-7 shadow-float sm:p-10">
                    {{ $slot }}
                </article>
            </div>
        </div>
    </div>
</x-layouts.public>
