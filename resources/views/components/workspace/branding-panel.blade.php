@props(['organization', 'record', 'draft', 'preview', 'themeModes', 'fonts', 'published'])

@php
    $prefix = request()->routeIs('admin.*') ? 'admin' : 'org';
    $value = function (string $field, $fallback = null) use ($draft, $record) {
        $raw = old($field, $draft[$field] ?? $record->{$field} ?? $fallback);

        return $raw instanceof \BackedEnum ? $raw->value : $raw;
    };
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route($prefix.'.organizations.branding.update', $organization) }}" enctype="multipart/form-data" class="space-y-6 lg:col-span-2">
        @csrf
        @method('PUT')

        <x-card title="Identity">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-form.input name="display_name" label="Display name" :value="$value('display_name', $organization->name)" hint="Shown in the sidebar, browser tab, and login page." /></div>
                <x-form.input name="support_email" type="email" label="Support e-mail" :value="$value('support_email', $organization->contact_email)" />
                <x-form.input name="support_phone" type="tel" label="Support phone" :value="$value('support_phone', $organization->contact_phone)" />
                <div class="sm:col-span-2"><x-form.input name="footer_text" label="Footer text" :value="$value('footer_text')" hint="Plain text only. HTML is not allowed." /></div>
            </div>
        </x-card>

        <x-card title="Colours" description="Six-digit hex values. Contrast is calculated so button text stays readable.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.color name="primary_color" label="Primary" :value="$value('primary_color', $published->primary)" />
                <x-form.color name="secondary_color" label="Secondary" :value="$value('secondary_color', $published->secondary)" />
                <x-form.color name="accent_color" label="Accent" :value="$value('accent_color', $published->accent)" />
            </div>
        </x-card>

        <x-card title="Theme and type">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="theme_mode" label="Theme" :options="$themeModes" :value="$value('theme_mode', $record->theme_mode?->value)" />
                <x-form.select name="font_family" label="Font" :options="$fonts" :value="$value('font_family', $record->font_family?->value)" />
            </div>
        </x-card>

        <x-card title="Login copy">
            <div class="space-y-5">
                <x-form.input name="login_heading" label="Login heading" :value="$value('login_heading', 'Sign in')" />
                <x-form.textarea name="login_message" label="Welcome message" rows="3" :value="$value('login_message')" />
            </div>
        </x-card>

        <x-card title="Logos" description="PNG, JPG, WEBP, or ICO. SVG is not accepted. Files are stored privately and served only on this organisation's hostnames.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="logo" type="file" label="Main logo" accept="image/png,image/jpeg,image/webp,image/x-icon" />
                <x-form.input name="logo_light" type="file" label="Logo for light backgrounds" accept="image/png,image/jpeg,image/webp" />
                <x-form.input name="logo_dark" type="file" label="Logo for dark backgrounds" accept="image/png,image/jpeg,image/webp" />
                <x-form.input name="favicon" type="file" label="Favicon" accept="image/png,image/x-icon,image/webp" />
                <x-form.input name="login_image" type="file" label="Login panel image" accept="image/png,image/jpeg,image/webp" />
            </div>
        </x-card>

        <div class="flex flex-wrap justify-end gap-3">
            <x-button type="submit" variant="secondary">Save draft</x-button>
        </div>
    </form>

    <div class="space-y-6">
        <x-card title="Preview &amp; publish">
            <p class="text-sm text-fg-muted">Draft colours and copy apply on the preview screen. Visitors see published values only.</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-fg-muted">Published</dt><dd>{{ $record->published_at?->diffForHumans() ?? 'Not yet' }}</dd></div>
                <div class="flex items-center justify-between gap-2"><dt class="text-fg-muted">Primary swatch</dt><dd class="size-6 rounded-md border border-edge" style="background: {{ $preview->primary }}"></dd></div>
            </dl>
            <div class="mt-5 flex flex-col gap-2">
                <x-button :href="route($prefix.'.organizations.branding.preview', $organization)" variant="secondary" class="w-full">Preview login</x-button>
                <form method="POST" action="{{ route($prefix.'.organizations.branding.publish', $organization) }}">
                    @csrf
                    <x-button type="submit" class="w-full">Publish branding</x-button>
                </form>
            </div>
            <p class="mt-3 text-xs text-fg-subtle">ORBIT remains attributed in the product chrome. Arbitrary HTML, CSS, and JavaScript are not accepted.</p>
        </x-card>
    </div>
</div>
