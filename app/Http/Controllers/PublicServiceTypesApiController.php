<?php

namespace App\Http\Controllers;

use App\Models\ServiceTypeOption;
use App\Support\HomepageServiceTypePresentation;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicServiceTypesApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 6), 1), 100);

        if (! Schema::hasTable('service_type_options')) {
            $fallback = collect(ServiceTypeOptions::userIntakeOptions())
                ->take($limit)
                ->map(fn (array $option) => [
                    'value' => $option['value'],
                    'label' => $option['label'],
                    'icon' => $option['icon'] ?? null,
                    'description' => "Find trusted providers for {$option['label']} and get the support you need.",
                    'image_url' => 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=80',
                ])
                ->values()
                ->all();

            return response()->json(['service_types' => $fallback]);
        }

        $options = collect(ServiceTypeOptions::userIntakeOptions())->take($limit);
        $models = ServiceTypeOption::query()
            ->whereIn('value', $options->pluck('value')->all())
            ->get()
            ->keyBy('value');

        $serviceTypes = $options
            ->map(function (array $option) use ($models) {
                $model = $models->get($option['value']);

                if ($model) {
                    return HomepageServiceTypePresentation::payload($model);
                }

                return [
                    'value' => $option['value'],
                    'label' => $option['label'],
                    'icon' => $option['icon'] ?? null,
                    'description' => "Find trusted providers for {$option['label']} and get the support you need.",
                    'image_url' => 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=80',
                ];
            })
            ->values()
            ->all();

        return response()->json(['service_types' => $serviceTypes]);
    }
}
