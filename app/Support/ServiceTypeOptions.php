<?php

namespace App\Support;

use App\Enums\ServiceType;
use App\Models\ServiceProvider;
use App\Models\ServiceTypeOption;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ServiceTypeOptions
{
    public static function selectOptions(?string $role = null): array
    {
        if (! Schema::hasTable('service_type_options')) {
            return ServiceType::options();
        }

        try {
            $query = ServiceTypeOption::query()->active()->orderBy('sort_order')->orderBy('label');

            if ($role === 'user') {
                $query->where('for_user', true);
            }

            if ($role === 'provider') {
                $query->where('for_provider', true);
            }

            $rows = $query->get(['value', 'label', 'icon', 'for_user', 'for_provider', 'include_certificate']);
            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($row) => [
                    'value' => $row->value,
                    'label' => $row->label,
                    'icon' => $row->icon,
                    'for_user' => (bool) $row->for_user,
                    'for_provider' => (bool) $row->for_provider,
                    'include_certificate' => (bool) $row->include_certificate,
                ])->toArray();
            }
        } catch (Throwable) {
            return ServiceType::options();
        }

        return ServiceType::options();
    }

    public static function values(?string $role = null): array
    {
        return collect(self::selectOptions($role))->pluck('value')->values()->toArray();
    }

    /**
     * User intake options should include:
     * - all admin-created active service types
     * - any service types providers already use in their profiles
     *
     * @return list<array{value: string, label: string, icon?: string|null, for_user?: bool, for_provider?: bool}>
     */
    public static function userIntakeOptions(): array
    {
        $index = [];

        // Start from all active admin-defined service types.
        if (Schema::hasTable('service_type_options')) {
            try {
                $rows = ServiceTypeOption::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['value', 'label', 'icon', 'for_user', 'for_provider', 'include_certificate']);

                foreach ($rows as $row) {
                    $index[$row->value] = [
                        'value' => $row->value,
                        'label' => $row->label,
                        'icon' => $row->icon,
                        'for_user' => (bool) $row->for_user,
                        'for_provider' => (bool) $row->for_provider,
                        'include_certificate' => (bool) $row->include_certificate,
                    ];
                }
            } catch (Throwable) {
                // Fall through to enum-based defaults below.
            }
        }

        // Fallback to enum options when admin table has no data yet.
        if ($index === []) {
            foreach (ServiceType::options() as $opt) {
                $index[$opt['value']] = $opt;
            }
        }

        // Merge in any service types that providers have already filled.
        if (Schema::hasTable('service_providers')) {
            try {
                $providerValues = ServiceProvider::query()
                    ->pluck('service_types')
                    ->flatMap(fn ($types) => is_array($types) ? $types : [])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->map(fn ($value) => trim($value))
                    ->unique()
                    ->values();

                foreach ($providerValues as $value) {
                    if (! isset($index[$value])) {
                        $index[$value] = [
                            'value' => $value,
                            'label' => (string) Str::of($value)->replace(['_', '-'], ' ')->title(),
                            'icon' => null,
                            'for_user' => true,
                            'for_provider' => true,
                            'include_certificate' => false,
                        ];
                    }
                }
            } catch (Throwable) {
                // Keep currently collected options if provider-read fails.
            }
        }

        $options = array_values($index);
        usort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
