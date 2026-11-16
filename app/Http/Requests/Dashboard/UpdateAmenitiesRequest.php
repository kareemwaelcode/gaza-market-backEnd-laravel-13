<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardAmenity;
use Closure;

class UpdateAmenitiesRequest extends DashboardRequest
{
    protected function prepareForValidation(): void
    {
        $services = $this->input('services');

        if (! is_array($services)) {
            return;
        }

        foreach ($services as $key => $entry) {
            if (is_array($entry) && isset($entry['details']) && is_string($entry['details'])) {
                $details = trim($entry['details']);
                $services[$key]['details'] = $details === '' ? null : $details;
            }
        }

        $this->merge(['services' => $services]);
    }

    public function rules(): array
    {
        return [
            'services' => [
                'present',
                'array',
                'max:'.DashboardAmenity::MAX_PER_PLACE,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    foreach (array_keys($value) as $key) {
                        if (! is_string($key) || preg_match(DashboardAmenity::KEY_PATTERN, $key) !== 1) {
                            $fail('The services field contains an invalid service key.');

                            return;
                        }
                    }
                },
            ],
            'services.*' => ['required', 'array'],
            'services.*.enabled' => ['required', 'boolean'],
            'services.*.details' => ['nullable', 'string', 'max:'.DashboardAmenity::DETAILS_MAX_LENGTH],
        ];
    }

    /** @return array<string, string|null> */
    public function enabledServices(): array
    {
        $enabled = [];

        foreach ($this->validated('services') as $key => $entry) {
            if (filter_var($entry['enabled'], FILTER_VALIDATE_BOOLEAN)) {
                $enabled[(string) $key] = $entry['details'] ?? null;
            }
        }

        return $enabled;
    }
}