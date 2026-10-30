@php $editing = $staff->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$staff->name : 'Add staff member'">
    <x-page-header :title="$editing ? $staff->name : 'Add staff member'" :description="$editing ? 'Staff no. '.$staff->staff_number : 'Create a people record. Linking a login account is optional and can be done later.'" eyebrow="Staff register">
        <x-button :href="$editing ? route('org.staff.show', $staff) : route('org.staff.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> Back</x-button>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('org.staff.update', $staff) : route('org.staff.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Identity">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" :value="$staff->first_name" required autocomplete="off" />
                <x-form.input name="last_name" label="Last name" :value="$staff->last_name" required autocomplete="off" />
                <x-form.input name="staff_number" label="Staff number" :value="$staff->staff_number" required hint="Unique within your organisation. Letters, numbers, dashes." class="font-mono uppercase" />
                <x-form.input name="job_title" label="Job title" :value="$staff->job_title" />
                <x-form.input name="email" type="email" label="Work e-mail" :value="$staff->email" hint="Used for notifications when no account is linked." />
                <x-form.input name="phone" type="tel" label="Phone" :value="$staff->phone" placeholder="+233201234567" />
            </div>
        </x-card>

        <x-card title="Placement">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="department" label="Department" :options="$departments" :value="$staff->department?->uuid" placeholder="— None —" />
                <x-form.select name="primary_location" label="Primary location" :options="$locations" :value="$staff->primaryLocation?->uuid" placeholder="— None —" />
                <x-form.select name="work_type" label="Work type" :options="$workTypes" :value="$staff->work_type?->value" required />
                <x-form.select name="status" label="Status" :options="$statuses" :value="$staff->status?->value" required />
                <x-form.input name="started_on" type="date" label="Start date" :value="$staff->started_on?->format('Y-m-d')" />
                <x-form.input name="ended_on" type="date" label="End date" :value="$staff->ended_on?->format('Y-m-d')" hint="Only for exited staff." />
            </div>
        </x-card>

        <x-card title="Login account" description="Link this record to an existing user account in your organisation. One account can be linked to one staff record.">
            <x-form.select name="user" label="Linked account" :options="$linkableUsers" :value="$staff->user?->uuid" placeholder="— Not linked —" hint="Only accounts without a staff record are listed. Invite new users from Users &amp; access." />
        </x-card>

        <x-card title="Notes">
            <x-form.textarea name="notes" label="Internal notes" :value="$staff->notes" rows="3" hint="Visible to anyone who can view staff records." />
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-button :href="$editing ? route('org.staff.show', $staff) : route('org.staff.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit">{{ $editing ? 'Save changes' : 'Create staff record' }}</x-button>
        </div>
    </form>
</x-layouts.app>
