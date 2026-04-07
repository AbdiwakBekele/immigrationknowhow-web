<?php

namespace App\Enums;

enum ServiceType: string
{
    case IMMIGRATION_ATTORNEY = 'immigration_attorney';
    case TAX_ACCOUNTANT = 'tax_accountant';
    case TUTOR = 'tutor';
    case TRANSLATOR = 'translator';
    case NOTARY = 'notary';
    case REAL_ESTATE_AGENT = 'real_estate_agent';
    case INSURANCE_AGENT = 'insurance_agent';
    case FINANCIAL_ADVISOR = 'financial_advisor';
    case DRIVING_INSTRUCTOR = 'driving_instructor';
    case JOB_RECRUITER = 'job_recruiter';
    case RELOCATION_SPECIALIST = 'relocation_specialist';
    case HEALTHCARE_NAVIGATOR = 'healthcare_navigator';
    case EDUCATION_CONSULTANT = 'education_consultant';
    case BUSINESS_CONSULTANT = 'business_consultant';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IMMIGRATION_ATTORNEY => 'Immigration Attorney',
            self::TAX_ACCOUNTANT => 'Tax Accountant',
            self::TUTOR => 'Tutor / ESL Teacher',
            self::TRANSLATOR => 'Translator / Interpreter',
            self::NOTARY => 'Notary Public',
            self::REAL_ESTATE_AGENT => 'Real Estate Agent',
            self::INSURANCE_AGENT => 'Insurance Agent',
            self::FINANCIAL_ADVISOR => 'Financial Advisor',
            self::DRIVING_INSTRUCTOR => 'Driving Instructor',
            self::JOB_RECRUITER => 'Job Recruiter',
            self::RELOCATION_SPECIALIST => 'Relocation Specialist',
            self::HEALTHCARE_NAVIGATOR => 'Healthcare Navigator',
            self::EDUCATION_CONSULTANT => 'Education Consultant',
            self::BUSINESS_CONSULTANT => 'Business Consultant',
            self::OTHER => 'Other Services',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::IMMIGRATION_ATTORNEY => 'scale',
            self::TAX_ACCOUNTANT => 'calculator',
            self::TUTOR => 'academic-cap',
            self::TRANSLATOR => 'language',
            self::NOTARY => 'document-check',
            self::REAL_ESTATE_AGENT => 'home',
            self::INSURANCE_AGENT => 'shield-check',
            self::FINANCIAL_ADVISOR => 'banknotes',
            self::DRIVING_INSTRUCTOR => 'truck',
            self::JOB_RECRUITER => 'briefcase',
            self::RELOCATION_SPECIALIST => 'map',
            self::HEALTHCARE_NAVIGATOR => 'heart',
            self::EDUCATION_CONSULTANT => 'book-open',
            self::BUSINESS_CONSULTANT => 'building-office',
            self::OTHER => 'squares-plus',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'icon' => $case->icon(),
        ])->toArray();
    }
}
