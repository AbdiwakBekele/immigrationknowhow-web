<?php

namespace App\Http\Controllers;

use App\Models\UsZip;
use App\Support\CountryOptions;
use App\Support\UsStateOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LocationLookupController extends Controller
{
    public function states(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', Rule::in(CountryOptions::codes())],
        ]);

        $country = strtoupper((string) ($validated['country'] ?? 'US'));

        return response()->json([
            'states' => $this->stateOptions($country),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', Rule::in(CountryOptions::codes())],
            'state_id' => ['required', 'string', 'size:2'],
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $country = strtoupper((string) ($validated['country'] ?? 'US'));
        if ($country !== 'US' || ! Schema::hasTable('uszips')) {
            return response()->json([
                'results' => [],
            ]);
        }

        $columns = Schema::getColumnListing('uszips');
        $stateColumn = in_array('state_id', $columns, true) ? 'state_id' : 'state';
        $countyColumn = in_array('county_name', $columns, true)
            ? 'county_name'
            : (in_array('county', $columns, true) ? 'county' : null);

        if (! in_array($stateColumn, $columns, true) || ! $countyColumn) {
            return response()->json([
                'results' => [],
            ]);
        }

        $query = trim($validated['q']);
        $stateId = strtoupper($validated['state_id']);
        $results = collect();

        if (ctype_digit($query)) {
            $zipMatches = UsZip::query()
                ->where($stateColumn, $stateId)
                ->where('zip', 'like', "{$query}%")
                ->select('zip', 'city', DB::raw("{$countyColumn} as county_name"), DB::raw("{$stateColumn} as state_id"))
                ->orderBy('zip')
                ->limit(12)
                ->get()
                ->map(fn ($row) => [
                    'type' => 'zip',
                    'label' => "{$row->zip} - {$row->city}, {$row->state_id}",
                    'value' => $row->zip,
                    'zip' => $row->zip,
                    'city' => $row->city,
                    'county' => $row->county_name,
                    'state_id' => $row->state_id,
                ]);

            $results = $results->merge($zipMatches);
        } else {
            $countyMatches = UsZip::query()
                ->where($stateColumn, $stateId)
                ->where($countyColumn, 'like', "{$query}%")
                ->select(DB::raw("{$countyColumn} as county_name"), DB::raw("{$stateColumn} as state_id"))
                ->distinct()
                ->orderBy($countyColumn)
                ->limit(12)
                ->get()
                ->map(fn ($row) => [
                    'type' => 'county',
                    'label' => "{$row->county_name} County, {$row->state_id}",
                    'value' => $row->county_name,
                    'zip' => null,
                    'city' => null,
                    'county' => $row->county_name,
                    'state_id' => $row->state_id,
                ]);

            $cityMatches = UsZip::query()
                ->where($stateColumn, $stateId)
                ->where('city', 'like', "{$query}%")
                ->select('city', DB::raw("{$countyColumn} as county_name"), DB::raw("{$stateColumn} as state_id"))
                ->distinct()
                ->orderBy('city')
                ->limit(12)
                ->get()
                ->map(fn ($row) => [
                    'type' => 'city',
                    'label' => "{$row->city}, {$row->state_id}",
                    'value' => $row->city,
                    'zip' => null,
                    'city' => $row->city,
                    'county' => $row->county_name,
                    'state_id' => $row->state_id,
                ]);

            $results = $results->merge($countyMatches)->merge($cityMatches);
        }

        return response()->json([
            'results' => $results->unique('label')->values()->take(15)->all(),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function stateOptions(string $country): array
    {
        if ($country !== 'US') {
            return [];
        }

        $fallback = UsStateOptions::selectOptions($country);

        if (! Schema::hasTable('uszips')) {
            return $fallback;
        }

        $columns = Schema::getColumnListing('uszips');
        $stateColumn = in_array('state_id', $columns, true) ? 'state_id' : (in_array('state', $columns, true) ? 'state' : null);

        if (! $stateColumn) {
            return $fallback;
        }

        $stateNameColumn = in_array('state_name', $columns, true) ? 'state_name' : null;
        $stateLabels = UsStateOptions::labels();

        $rows = DB::table('uszips')
            ->select($stateColumn, ...($stateNameColumn ? [$stateNameColumn] : []))
            ->whereNotNull($stateColumn)
            ->where($stateColumn, '<>', '')
            ->distinct()
            ->orderBy($stateColumn)
            ->get();

        if ($rows->isEmpty()) {
            return $fallback;
        }

        return $rows
            ->map(function ($row) use ($stateColumn, $stateNameColumn, $stateLabels) {
                $code = strtoupper((string) $row->{$stateColumn});
                $label = $stateNameColumn && filled($row->{$stateNameColumn})
                    ? (string) $row->{$stateNameColumn}
                    : ($stateLabels[$code] ?? $code);

                return ['value' => $code, 'label' => $label];
            })
            ->filter(fn ($option) => $option['value'] !== '')
            ->unique('value')
            ->values()
            ->all();
    }
}
