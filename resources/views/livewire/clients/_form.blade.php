{{-- Shared client form. Expects $form (ClientForm). --}}
<form wire:submit="saveClient" class="space-y-6">
    <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Business</legend>
        <x-field label="Client name" for="c-name" error="form.name">
            <input wire:model="form.name" id="c-name" class="input" placeholder="ShopNest">
        </x-field>
        <x-field label="Legal / registered name" for="c-legal" error="form.legal_name">
            <input wire:model="form.legal_name" id="c-legal" class="input" placeholder="ShopNest Retail Pvt Ltd">
        </x-field>
        <x-field label="Status" for="c-status" error="form.status">
            <select wire:model="form.status" id="c-status" class="input">
                @foreach (\App\Models\Client::STATUSES as $value => [$label]) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
        </x-field>
        <x-field label="Industry" for="c-industry" error="form.industry">
            <input wire:model="form.industry" id="c-industry" class="input" list="c-industries" placeholder="E-commerce">
            <datalist id="c-industries">
                @foreach (['E-commerce', 'Education', 'Healthcare', 'Real estate', 'Restaurant & food', 'SaaS / software', 'Finance', 'Travel', 'Non-profit', 'Manufacturing'] as $i) <option value="{{ $i }}"></option> @endforeach
            </datalist>
        </x-field>
        <x-field label="Client since" for="c-since" error="form.client_since">
            <input wire:model="form.client_since" id="c-since" type="date" class="input">
        </x-field>
        <x-field label="GST / tax number" for="c-tax" error="form.tax_id">
            <input wire:model="form.tax_id" id="c-tax" class="input font-mono">
        </x-field>
    </fieldset>

    <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Contact</legend>
        <x-field label="Website" for="c-web" error="form.website" class="sm:col-span-2">
            <input wire:model="form.website" id="c-web" type="url" class="input" placeholder="https://shopnest.com">
        </x-field>
        <x-field label="Email" for="c-email" error="form.email">
            <input wire:model="form.email" id="c-email" type="email" class="input" placeholder="hello@shopnest.com">
        </x-field>
        <x-field label="Phone" for="c-phone" error="form.phone">
            <input wire:model="form.phone" id="c-phone" type="tel" class="input" placeholder="+91 98765 43210">
        </x-field>
        <x-field label="WhatsApp (if different)" for="c-wa" error="form.whatsapp">
            <input wire:model="form.whatsapp" id="c-wa" type="tel" class="input">
        </x-field>
    </fieldset>

    <fieldset class="grid gap-4 sm:grid-cols-4">
        <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Address</legend>
        <x-field label="Street" for="c-addr" error="form.address_line" class="sm:col-span-4">
            <input wire:model="form.address_line" id="c-addr" class="input">
        </x-field>
        <x-field label="City" for="c-city" error="form.city"><input wire:model="form.city" id="c-city" class="input"></x-field>
        <x-field label="State" for="c-state" error="form.state"><input wire:model="form.state" id="c-state" class="input"></x-field>
        <x-field label="Postcode" for="c-post" error="form.postal_code"><input wire:model="form.postal_code" id="c-post" class="input"></x-field>
        <x-field label="Country" for="c-country" error="form.country"><input wire:model="form.country" id="c-country" class="input"></x-field>
    </fieldset>

    <x-field label="Notes" for="c-notes" error="form.notes">
        <textarea wire:model="form.notes" id="c-notes" rows="3" class="input" placeholder="Preferences, how they like to communicate, billing terms…"></textarea>
    </x-field>

    <div class="flex justify-end gap-2">
        <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
        <button type="submit" class="btn-primary">Save client</button>
    </div>
</form>
