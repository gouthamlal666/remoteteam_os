@php
    $P = \App\Models\EmployeeProfile::class;
    $row = fn ($label, $value) => ['label' => $label, 'value' => $value];
    $sections = [
        'Employment' => [
            $row('Employee ID', $profile?->employee_code),
            $row('Employment type', $P::EMPLOYMENT_TYPES[$profile?->employment_type] ?? null),
            $row('Date of joining', $profile?->date_of_joining?->format('d M Y')),
            $row('Department', $user->department?->name),
        ],
        'Personal' => [
            $row('Date of birth', $profile?->date_of_birth ? $profile->date_of_birth->format('d M Y').' ('.$profile->date_of_birth->age.')' : null),
            $row('Gender', $P::GENDERS[$profile?->gender] ?? null),
            $row('Blood group', $profile?->blood_group),
            $row('Address', $profile?->fullAddress()),
        ],
        'Contact' => [
            $row('Mobile', $user->phone),
            $row('Alternate phone', $profile?->alternate_phone),
            $row('Work email', $user->email),
            $row('Personal email', $profile?->personal_email),
        ],
        'Guardian / emergency contact' => [
            $row('Name', $profile?->guardian_name),
            $row('Relation', $profile?->guardian_relation),
            $row('Phone', $profile?->guardian_phone),
        ],
    ];
@endphp
<section id="employee" class="scroll-mt-20 overflow-hidden rounded-xl border border-slate-200 bg-white target:ring-2 target:ring-indigo-400">
    @php $completeness = $user->recordCompleteness(); @endphp
    <header class="flex flex-wrap items-center gap-4 border-b border-slate-100 bg-gradient-to-r from-indigo-50/70 via-white to-white px-5 py-4">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-xs ring-1 ring-indigo-100"><x-icon name="id-card" class="size-6" /></span>
        <div class="min-w-0 flex-1">
            <h2 class="font-semibold text-slate-900">Employee record</h2>
            <p class="flex items-center gap-1.5 text-xs text-slate-500"><x-icon name="shield" class="size-3.5" /> Private: only super admins and {{ $user->is(auth()->user()) ? 'you' : \Illuminate\Support\Str::before($user->name, ' ') }} can see this</p>
        </div>
        @if ($canManage)
            <div class="w-40" title="{{ $completeness['missing'] ? 'Missing: '.implode(', ', $completeness['missing']) : 'Complete' }}">
                <div class="flex justify-between text-xs"><span class="text-slate-500">Completed</span><span class="font-semibold text-slate-900">{{ $completeness['percent'] }}%</span></div>
                <x-progress :value="$completeness['percent']" :color="$completeness['percent'] === 100 ? 'bg-emerald-500' : 'bg-indigo-500'" class="mt-1 h-1.5" />
            </div>
            <button wire:click="edit" class="btn-primary"><x-icon name="pencil" class="size-4" /> {{ $profile ? 'Edit details' : 'Add details' }}</button>
        @endif
    </header>

    @if ($canManage && $completeness['missing'])
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-amber-50/60 px-5 py-2.5 text-xs text-amber-900">
            <span class="font-medium">Still needed:</span>
            @foreach ($completeness['missing'] as $item)
                <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-amber-200">{{ $item }}</span>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 p-5 lg:grid-cols-[12rem_1fr]">
        {{-- Photo --}}
        <div class="flex flex-col items-center gap-3">
            <div class="relative size-40 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-100 to-indigo-200 shadow-inner ring-4 ring-white">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="Photo of {{ $user->name }}" class="size-full object-cover">
                @else
                    <span class="flex size-full items-center justify-center text-4xl font-semibold text-indigo-700">{{ $user->initials() }}</span>
                @endif
                <div wire:loading.flex wire:target="photo" class="absolute inset-0 items-center justify-center bg-white/70 text-xs text-slate-600">Uploading…</div>
            </div>
            @if ($canChangePhoto)
                <div class="flex items-center gap-2 text-sm">
                    <label class="btn-secondary cursor-pointer py-1.5">
                        <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        {{ $user->avatar_path ? 'Change photo' : 'Upload photo' }}
                    </label>
                    @if ($user->avatar_path)
                        <span class="text-slate-300">·</span>
                        <button wire:click="removePhoto" wire:confirm="Remove this photo?" class="text-slate-500 hover:text-red-600">Remove</button>
                    @endif
                </div>
                @error('photo') <p class="text-center text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="text-center text-[11px] text-slate-400">JPG, PNG or WebP · max 3 MB</p>
            @endif
        </div>

        {{-- Details --}}
        @php $icons = ['Employment' => 'briefcase', 'Personal' => 'user', 'Contact' => 'phone', 'Guardian / emergency contact' => 'heart']; @endphp
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($sections as $heading => $rows)
                <div @class(['rounded-xl border p-4', 'border-rose-100 bg-rose-50/40' => str_starts_with($heading, 'Guardian'), 'border-slate-100 bg-slate-50/50' => ! str_starts_with($heading, 'Guardian')])>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <x-icon :name="$icons[$heading]" @class(['size-4', 'text-rose-500' => str_starts_with($heading, 'Guardian'), 'text-indigo-500' => ! str_starts_with($heading, 'Guardian')]) />
                        {{ $heading }}
                    </h3>
                    <dl class="space-y-2 text-sm">
                        @foreach ($rows as $r)
                            <div class="grid grid-cols-[7.5rem_1fr] gap-2">
                                <dt class="text-slate-500">{{ $r['label'] }}</dt>
                                <dd class="min-w-0 font-medium break-words text-slate-900">
                                    @if (filled($r['value']))
                                        @if (in_array($r['label'], ['Mobile', 'Alternate phone', 'Phone']))
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $r['value']) }}" class="inline-flex items-center gap-1 text-indigo-700 hover:underline"><x-icon name="phone" class="size-3.5" />{{ $r['value'] }}</a>
                                        @else
                                            {{ $r['value'] }}
                                        @endif
                                    @elseif ($canManage)
                                        <button wire:click="edit" class="font-normal text-slate-400 hover:text-indigo-600">+ Add</button>
                                    @else
                                        <span class="font-normal text-slate-300">Not set</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
            @if ($profile?->notes && $canManage)
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 sm:col-span-2">
                    <h3 class="mb-1 text-sm font-semibold text-slate-800">HR notes</h3>
                    <p class="text-sm whitespace-pre-line text-slate-700">{{ $profile->notes }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Documents --}}
    <div class="border-t border-slate-100">
        <div class="flex items-center justify-between px-5 py-3">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800"><x-icon name="document" class="size-4 text-indigo-500" /> Documents <span class="rounded-full bg-slate-100 px-2 text-xs font-medium text-slate-600">{{ $documents->count() }}</span></h3>
            @if ($canManage && ! $addingDocument)
                <button wire:click="$set('addingDocument', true)" class="btn-secondary py-1.5"><x-icon name="plus" class="size-4" /> Add document</button>
            @endif
        </div>

        @if ($addingDocument)
            <form wire:submit="addDocument" class="mx-4 mb-4 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                <x-field label="Document type" for="doc-type" error="documentType">
                    <select wire:model="documentType" id="doc-type" class="input">
                        @foreach (\App\Models\EmployeeDocument::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Title (optional)" for="doc-title" error="documentTitle">
                    <input wire:model="documentTitle" id="doc-title" class="input" placeholder="e.g. Passport, Aadhaar card, Degree certificate">
                </x-field>
                <x-field label="File" for="doc-file" error="documentFile">
                    <input wire:model="documentFile" id="doc-file" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:ring-1 file:ring-slate-300 hover:file:bg-slate-100">
                    <p wire:loading wire:target="documentFile" class="text-xs text-slate-500">Uploading…</p>
                    <p class="text-[11px] text-slate-400">PDF, Word or image · max 10 MB</p>
                </x-field>
                <x-field label="Expiry date (optional)" for="doc-exp" error="documentExpires">
                    <input wire:model="documentExpires" id="doc-exp" type="date" class="input">
                </x-field>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('addingDocument', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="documentFile,addDocument">Upload</button>
                </div>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 border-t border-slate-100">
            @forelse ($documents as $doc)
                <li wire:key="doc-{{ $doc->id }}" class="flex items-center gap-3 px-4 py-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg">{{ str_contains((string) $doc->mime_type, 'image') ? '🖼️' : (str_contains((string) $doc->mime_type, 'pdf') ? '📕' : '📄') }}</span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('employee-documents.show', $doc) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-700">{{ $doc->title }}</a>
                        <p class="truncate text-xs text-slate-500">{{ $doc->typeLabel() }} · {{ $doc->humanSize() }} · added {{ $doc->created_at->format('d M Y') }} by {{ $doc->uploader->name }}</p>
                    </div>
                    @if ($doc->isExpired())
                        <x-badge color="bg-red-100 text-red-700">Expired {{ $doc->expires_at->format('d M Y') }}</x-badge>
                    @elseif ($doc->expiresSoon())
                        <x-badge color="bg-amber-100 text-amber-800">Expires {{ $doc->expires_at->format('d M Y') }}</x-badge>
                    @elseif ($doc->expires_at)
                        <span class="hidden text-xs text-slate-400 sm:inline">Valid till {{ $doc->expires_at->format('d M Y') }}</span>
                    @endif
                    <a href="{{ route('employee-documents.show', $doc) }}" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Download {{ $doc->title }}"><x-icon name="download" class="size-4" /></a>
                    @if ($canManage)
                        <button wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Delete “{{ $doc->title }}”? The file will be permanently removed." class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete {{ $doc->title }}"><x-icon name="trash" class="size-4" /></button>
                    @endif
                </li>
            @empty
                <li><x-empty class="py-6">No documents yet.{{ $canManage ? ' Add ID proof, resume, offer letter, contracts or certificates.' : '' }}</x-empty></li>
            @endforelse
        </ul>
    </div>

    {{-- Edit details --}}
    @if ($canManage)
        <x-modal wire:model="editing" :title="'Employee details · '.$user->name" max-width="max-w-3xl">
            <form wire:submit="save" class="space-y-6">
                <fieldset class="grid gap-4 sm:grid-cols-3">
                    <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Employment</legend>
                    <x-field label="Employee ID" for="e-code" error="details.employee_code">
                        <input wire:model="details.employee_code" id="e-code" class="input" placeholder="RT-0012">
                    </x-field>
                    <x-field label="Employment type" for="e-type" error="details.employment_type">
                        <select wire:model="details.employment_type" id="e-type" class="input">
                            <option value="">—</option>
                            @foreach ($P::EMPLOYMENT_TYPES as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Date of joining" for="e-doj" error="details.date_of_joining">
                        <input wire:model="details.date_of_joining" id="e-doj" type="date" class="input">
                    </x-field>
                </fieldset>

                <fieldset class="grid gap-4 sm:grid-cols-3">
                    <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Personal</legend>
                    <x-field label="Date of birth" for="e-dob" error="details.date_of_birth">
                        <input wire:model="details.date_of_birth" id="e-dob" type="date" max="{{ today()->subDay()->toDateString() }}" class="input">
                    </x-field>
                    <x-field label="Gender" for="e-gender" error="details.gender">
                        <select wire:model="details.gender" id="e-gender" class="input">
                            <option value="">—</option>
                            @foreach ($P::GENDERS as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Blood group" for="e-blood" error="details.blood_group">
                        <select wire:model="details.blood_group" id="e-blood" class="input">
                            <option value="">—</option>
                            @foreach ($P::BLOOD_GROUPS as $b) <option value="{{ $b }}">{{ $b }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Address" for="e-addr" error="details.address_line" class="sm:col-span-3">
                        <input wire:model="details.address_line" id="e-addr" class="input" placeholder="House / street">
                    </x-field>
                    <x-field label="City" for="e-city" error="details.city">
                        <input wire:model="details.city" id="e-city" class="input">
                    </x-field>
                    <x-field label="State" for="e-state" error="details.state">
                        <input wire:model="details.state" id="e-state" class="input">
                    </x-field>
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="Postcode" for="e-post" error="details.postal_code">
                            <input wire:model="details.postal_code" id="e-post" class="input">
                        </x-field>
                        <x-field label="Country" for="e-country" error="details.country">
                            <input wire:model="details.country" id="e-country" class="input">
                        </x-field>
                    </div>
                </fieldset>

                <fieldset class="grid gap-4 sm:grid-cols-3">
                    <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Contact</legend>
                    <x-field label="Mobile number" for="e-phone" error="phone">
                        <input wire:model="phone" id="e-phone" type="tel" class="input" placeholder="+91 98765 43210">
                    </x-field>
                    <x-field label="Alternate phone" for="e-alt" error="details.alternate_phone">
                        <input wire:model="details.alternate_phone" id="e-alt" type="tel" class="input">
                    </x-field>
                    <x-field label="Personal email" for="e-pemail" error="details.personal_email">
                        <input wire:model="details.personal_email" id="e-pemail" type="email" class="input">
                    </x-field>
                </fieldset>

                <fieldset class="grid gap-4 sm:grid-cols-3">
                    <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Guardian / emergency contact</legend>
                    <x-field label="Guardian name" for="e-gname" error="details.guardian_name">
                        <input wire:model="details.guardian_name" id="e-gname" class="input">
                    </x-field>
                    <x-field label="Relation" for="e-grel" error="details.guardian_relation">
                        <input wire:model="details.guardian_relation" id="e-grel" list="relations" class="input" placeholder="Father, Mother, Spouse…">
                        <datalist id="relations">
                            @foreach ($P::RELATIONS as $r) <option value="{{ $r }}"></option> @endforeach
                        </datalist>
                    </x-field>
                    <x-field label="Guardian phone" for="e-gphone" error="details.guardian_phone">
                        <input wire:model="details.guardian_phone" id="e-gphone" type="tel" class="input">
                    </x-field>
                </fieldset>

                <x-field label="HR notes (super admins only)" for="e-notes" error="details.notes">
                    <textarea wire:model="details.notes" id="e-notes" rows="3" class="input"></textarea>
                </x-field>

                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save details</button>
                </div>
            </form>
        </x-modal>
    @endif
</section>
