<x-layouts.app :title="'Edit '.$type->name">
    <x-page-header title="Edit {{ $type->name }}" description="Changes apply to new requests. Past requests keep their recorded dates and decision." eyebrow="Leave">
        <x-button :href="route('org.leave.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> Back to leave</x-button>
    </x-page-header>

    <form method="POST" action="{{ route('org.leave.types.update', $type) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card title="Leave type">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Name" :value="$type->name" required />
                <x-form.input name="code" label="Code" :value="$type->code" required class="font-mono uppercase" />
                <x-form.input type="number" name="default_days" label="Default days" :value="$type->default_days" step="1" min="0" hint="Whole days. Used when this type tracks a balance and the person has no balance row yet." />
            </div>
            <div class="mt-5 space-y-3">
                <input type="hidden" name="paid" value="0">
                <label class="flex items-center gap-3 text-sm text-fg">
                    <input type="checkbox" name="paid" value="1" @checked(old('paid', $type->paid)) class="size-4 rounded border-edge-strong text-primary">
                    Paid
                </label>
                <input type="hidden" name="requires_balance" value="0">
                <label class="flex items-center gap-3 text-sm text-fg">
                    <input type="checkbox" name="requires_balance" value="1" @checked(old('requires_balance', $type->requires_balance)) class="size-4 rounded border-edge-strong text-primary">
                    Use a balance
                </label>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-3 text-sm text-fg">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active)) class="size-4 rounded border-edge-strong text-primary">
                    Active (staff can request this type)
                </label>
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button :href="route('org.leave.index')" variant="secondary" size="sm">Cancel</x-button>
            <x-button type="submit" size="sm">Save changes</x-button>
        </div>
    </form>
</x-layouts.app>
