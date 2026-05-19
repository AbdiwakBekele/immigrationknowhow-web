<?php

namespace App\Support;

use App\Models\ServiceProvider;
use App\Models\ServiceTypeOption;
use Illuminate\Support\Facades\Schema;

/**
 * Shared provider verification flags used by web and mobile dashboards.
 */
class ProviderVerification
{
    public static function requiresBackgroundCheck(ServiceProvider $provider): bool
    {
        $providerTypes = self::canonicalServiceTypes($provider->service_types ?? []);

        if ($providerTypes === []) {
            return false;
        }

        if (
            Schema::hasTable('service_type_options')
            && Schema::hasColumn('service_type_options', 'requires_background_check')
        ) {
            $requiredTypes = ServiceTypeOption::query()
                ->where('requires_background_check', true)
                ->pluck('value')
                ->map(fn ($value) => self::canonicalServiceTypeValue((string) $value))
                ->filter()
                ->unique();

            return collect($providerTypes)->intersect($requiredTypes)->isNotEmpty();
        }

        return collect($providerTypes)
            ->intersect(['pet_sitter', 'petsitter', 'babysitter', 'baby_sitter', 'tutor'])
            ->isNotEmpty();
    }

    public static function requiresCertificateUpload(ServiceProvider $provider): bool
    {
        $providerTypes = self::canonicalServiceTypes($provider->service_types ?? []);

        if ($providerTypes === []) {
            return false;
        }

        $defaultRequiredTypes = collect(['pet_sitter', 'babysitter', 'health_navigator']);

        if (collect($providerTypes)->intersect($defaultRequiredTypes)->isNotEmpty()) {
            return true;
        }

        if (
            Schema::hasTable('service_type_options')
            && Schema::hasColumn('service_type_options', 'include_certificate')
        ) {
            $requiredTypes = ServiceTypeOption::query()
                ->where('include_certificate', true)
                ->pluck('value')
                ->map(fn ($value) => self::canonicalServiceTypeValue((string) $value))
                ->filter()
                ->unique();

            return collect($providerTypes)->intersect($requiredTypes)->isNotEmpty();
        }

        return collect($providerTypes)
            ->intersect(['pet_sitter', 'petsitter', 'babysitter', 'baby_sitter', 'health_navigator', 'healthcare_navigator', 'healthnavigator'])
            ->isNotEmpty();
    }

    public static function needsCertificateUpload(ServiceProvider $provider): bool
    {
        if (! self::requiresCertificateUpload($provider)) {
            return false;
        }

        $uploadedCertificates = collect($provider->health_certificates ?? [])
            ->contains(fn ($certificate) => is_array($certificate)
                && is_string($certificate['file_path'] ?? null)
                && trim((string) $certificate['file_path']) !== '');

        return ! $uploadedCertificates;
    }

    /**
     * @param  array<int, mixed>  $serviceTypes
     * @return array<int, string>
     */
    private static function canonicalServiceTypes(array $serviceTypes): array
    {
        return collect($serviceTypes)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => self::canonicalServiceTypeValue((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function canonicalServiceTypeValue(string $value): string
    {
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');

        if (($normalized === 'pet_sitter' || str_contains($normalized, 'pet')) && str_contains($normalized, 'sitter')) {
            return 'pet_sitter';
        }

        if (str_contains($normalized, 'babysitter') || (str_contains($normalized, 'baby') && str_contains($normalized, 'sitter'))) {
            return 'babysitter';
        }

        if (str_contains($normalized, 'tutor')) {
            return 'tutor';
        }

        if ((str_contains($normalized, 'health') || str_contains($normalized, 'healthcare')) && str_contains($normalized, 'navigator')) {
            return 'health_navigator';
        }

        return $normalized;
    }
}
