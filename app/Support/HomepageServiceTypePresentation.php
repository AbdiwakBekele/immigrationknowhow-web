<?php

namespace App\Support;

use App\Models\ServiceTypeOption;

class HomepageServiceTypePresentation
{
    /**
     * @return array<string, array{description: string, image_url: string}>
     */
    private static function presets(): array
    {
        return [
            'immigration_attorney' => [
                'description' => 'Immigration guidance, document review, and referrals to qualified professionals.',
                'image_url' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=900&q=80',
            ],
            'tax_accountant' => [
                'description' => 'Find help with taxes, budgeting, credit, insurance, and financial decisions.',
                'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=900&q=80',
            ],
            'real_estate_agent' => [
                'description' => 'Get connected with rental, moving, home services, and local housing help.',
                'image_url' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=80',
            ],
            'realtors' => [
                'description' => 'Get connected with rental, moving, home services, and local housing help.',
                'image_url' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=80',
            ],
            'tutor' => [
                'description' => 'Find tutors, language classes, school resources, and training options.',
                'image_url' => 'https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=900&q=80',
            ],
            'education_consultant' => [
                'description' => 'Find tutors, language classes, school resources, and training options.',
                'image_url' => 'https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=900&q=80',
            ],
            'healthcare_navigator' => [
                'description' => 'Discover wellness, medical navigation, mental health, and family care support.',
                'image_url' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=900&q=80',
            ],
            'financial_advisor' => [
                'description' => 'Find help with taxes, budgeting, credit, insurance, and financial decisions.',
                'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=900&q=80',
            ],
            'insurance_agent' => [
                'description' => 'Find help with taxes, budgeting, credit, insurance, and financial decisions.',
                'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=900&q=80',
            ],
            'relocation_specialist' => [
                'description' => 'Get connected with rental, moving, home services, and local housing help.',
                'image_url' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=80',
            ],
            'business_consultant' => [
                'description' => 'Connect with professionals who help immigrants start and grow businesses.',
                'image_url' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=900&q=80',
            ],
            'job_recruiter' => [
                'description' => 'Explore career support, job search help, and workplace guidance.',
                'image_url' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=900&q=80',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(ServiceTypeOption $option): array
    {
        $preset = self::presets()[$option->value] ?? null;
        $label = $option->label;

        return [
            'value' => $option->value,
            'label' => $label,
            'icon' => $option->icon,
            'description' => $preset['description'] ?? "Find trusted providers for {$label} and get the support you need.",
            'image_url' => $preset['image_url'] ?? 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=80',
        ];
    }
}
