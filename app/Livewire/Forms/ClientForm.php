<?php

namespace App\Livewire\Forms;

use App\Models\Client;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ClientForm extends Form
{
    public ?Client $client = null;

    public string $name = '';

    public string $legal_name = '';

    public string $status = 'active';

    public string $industry = '';

    public string $website = '';

    public string $email = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $address_line = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public string $postal_code = '';

    public string $tax_id = '';

    public string $client_since = '';

    public string $notes = '';

    public function setClient(Client $client): void
    {
        $this->client = $client;

        foreach (array_keys($this->fields()) as $field) {
            $value = $client->{$field};
            $this->{$field} = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
    }

    /** @return array<string, array<int, mixed>> */
    private function fields(): array
    {
        $phone = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'];

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('clients', 'name')->ignore($this->client?->id)],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'status' => ['required', Rule::in(array_keys(Client::STATUSES))],
            'industry' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:500'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => $phone,
            'whatsapp' => $phone,
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'client_since' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function rules(): array
    {
        return $this->fields();
    }

    protected function messages(): array
    {
        return [
            'name.unique' => 'A client with this name already exists.',
            'phone.regex' => 'Enter a valid phone number.',
            'whatsapp.regex' => 'Enter a valid WhatsApp number.',
        ];
    }

    /** @return array<string, mixed> validated values with blanks stored as null */
    public function payload(): array
    {
        return collect($this->validate())->map(fn ($value) => $value === '' ? null : $value)->all();
    }
}
