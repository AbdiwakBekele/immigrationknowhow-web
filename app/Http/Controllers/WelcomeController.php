<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Enums\VerificationStatus;
use App\Models\Review;
use App\Models\ServiceProvider;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        // Get featured providers (verified, high-rated, with reviews)
        $featuredProviders = ServiceProvider::query()
            ->where('verification_status', VerificationStatus::APPROVED)
            ->where('is_active', true)
            ->where('total_reviews', '>', 0)
            ->with('user:id,first_name,last_name,avatar')
            ->orderByDesc('average_rating')
            ->orderByDesc('total_reviews')
            ->limit(6)
            ->get()
            ->map(fn($provider) => [
                'id' => $provider->id,
                'slug' => $provider->slug,
                'business_name' => $provider->business_name,
                'bio' => $provider->bio,
                'primary_service_type' => $provider->primary_service_type,
                'average_rating' => $provider->average_rating,
                'reviews_count' => $provider->total_reviews,
                'is_verified' => $provider->isVerified(),
                'offers_free_consultation' => $provider->free_consultation,
                'user' => $provider->user,
            ]);

        // Get service types with icons
        $serviceTypes = collect(ServiceType::cases())->map(fn($type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'icon' => $this->getServiceIcon($type),
        ]);

        // Get platform stats
        $stats = [
            'providers' => ServiceProvider::where('is_active', true)->count(),
            'services' => count(ServiceType::cases()),
            'connections' => '10K+',
            'rating' => number_format(ServiceProvider::whereNotNull('average_rating')->avg('average_rating') ?? 4.8, 1),
        ];

        // Get testimonials (featured reviews)
        $testimonials = Review::query()
            ->where('is_featured', true)
            ->where('rating', '>=', 4)
            ->with(['user:id,first_name,last_name', 'serviceProvider:id,service_types'])
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn($review) => [
                'id' => $review->id,
                'comment' => $review->comment,
                'rating' => $review->rating,
                'user_name' => $review->user?->full_name ?? 'Anonymous',
                'service_type' => $review->serviceProvider?->primary_service_type ?? 'Service',
            ]);

        return Inertia::render('Welcome', [
            'featuredProviders' => $featuredProviders,
            'serviceTypes' => $serviceTypes,
            'stats' => $stats,
            'testimonials' => $testimonials,
        ]);
    }

    private function getServiceIcon(ServiceType $type): string
    {
        return match ($type) {
            ServiceType::IMMIGRATION_ATTORNEY => '⚖️',
            ServiceType::TAX_ACCOUNTANT => '📊',
            ServiceType::TUTOR => '📚',
            ServiceType::TRANSLATOR => '🌐',
            ServiceType::REAL_ESTATE_AGENT => '🏠',
            ServiceType::INSURANCE_AGENT => '🛡️',
            ServiceType::FINANCIAL_ADVISOR => '💰',
            ServiceType::JOB_RECRUITER => '💼',
            ServiceType::HEALTHCARE_NAVIGATOR => '🏥',
            ServiceType::EDUCATION_CONSULTANT => '🎓',
            ServiceType::NOTARY => '📝',
            ServiceType::BUSINESS_CONSULTANT => '🏢',
            ServiceType::DRIVING_INSTRUCTOR => '🚗',
            ServiceType::RELOCATION_SPECIALIST => '📦',
            ServiceType::OTHER => '✨',
        };
    }
}
