import { mergeContinuedLegalSections } from '@/utils/legalDocument';

/** @typedef {{ heading: string, paragraphs?: string[], list?: string[] }} LegalSection */

const termsOfServiceSections = [
        {
            heading: '1. Educational Platform Only',
            paragraphs: [
                'Immigrant KnowHow is an educational and informational platform only.',
                'The Services do not constitute:',
            ],
            list: [
                'Legal advice',
                'Immigration advice',
                'Tax advice',
                'Financial advice',
                'Investment advice',
                'Accounting advice',
                'Professional consulting services',
            ],
        },
        {
            heading: '1. Educational Platform Only (continued)',
            paragraphs: [
                'Nothing on the platform should be interpreted as a substitute for professional advice from licensed attorneys, accountants, tax professionals, financial advisors, or other qualified professionals.',
                'Users are solely responsible for consulting appropriate professionals before making decisions related to immigration, taxes, finance, business, investments, legal matters, or personal matters.',
            ],
        },
        {
            heading: '2. No Attorney-Client or Professional Relationship',
            paragraphs: ['Use of the Services does not create:'],
            list: [
                'An attorney-client relationship',
                'A fiduciary relationship',
                'A consultant-client relationship',
                'An advisor-client relationship',
                'Any professional relationship with Immigrant KnowHow',
            ],
        },
        {
            heading: '2. No Attorney-Client or Professional Relationship (continued)',
            paragraphs: [
                'Communications through the platform, including AI-generated responses, educational content, or provider listings, do not constitute professional advice.',
            ],
        },
        {
            heading: '3. User Accounts',
            paragraphs: [
                'Users may be required to create an account to access certain features.',
                'You agree to:',
            ],
            list: [
                'Provide accurate information',
                'Maintain confidentiality of login credentials',
                'Be responsible for all activity under your account',
            ],
        },
        {
            heading: '3. User Accounts (continued)',
            paragraphs: ['We reserve the right to suspend or terminate accounts at our discretion.'],
        },
        {
            heading: '4. Third-Party Service Providers',
            paragraphs: [
                'The platform may connect users with independent third-party professionals and service providers.',
                'Immigrant KnowHow:',
            ],
            list: [
                'Does not employ or supervise providers',
                'Does not guarantee provider qualifications',
                'Does not guarantee outcomes or results',
                'Does not endorse or warrant any provider unless explicitly stated',
            ],
        },
        {
            heading: '4. Third-Party Service Providers (continued)',
            paragraphs: ['Users are solely responsible for:'],
            list: [
                'Conducting due diligence',
                'Reviewing credentials and licenses',
                'Negotiating agreements',
                'Verifying pricing and qualifications',
                'Evaluating risks',
            ],
        },
        {
            heading: '5. No Liability for Transactions or Services',
            paragraphs: ['Immigrant KnowHow is not responsible or liable for:'],
            list: [
                'Interactions between users and providers',
                'Contracts or agreements between parties',
                'Financial transactions',
                'Disputes',
                'Service quality',
                'Misrepresentations',
                'Professional negligence',
                'Immigration outcomes',
                'Legal outcomes',
                'Tax outcomes',
                'Financial losses',
                'Personal injuries',
                'Damages of any kind',
            ],
        },
        {
            heading: '5. No Liability for Transactions or Services (continued)',
            paragraphs: ['Any relationship or transaction between users and providers is solely between those parties.'],
        },
        {
            heading: '6. AI Disclaimer',
            paragraphs: ['Certain Services may use artificial intelligence technologies.', 'AI-generated responses may:'],
            list: [
                'Be incomplete',
                'Be inaccurate',
                'Contain outdated information',
                'Misinterpret context',
            ],
        },
        {
            heading: '6. AI Disclaimer (continued)',
            paragraphs: [
                'AI content is provided for informational purposes only and should not be relied upon as professional advice.',
                'Users assume all risks associated with reliance on AI-generated content.',
            ],
        },
        {
            heading: '7. User Conduct',
            paragraphs: ['Users agree not to:'],
            list: [
                'Violate laws or regulations',
                'Upload harmful or fraudulent content',
                'Harass or abuse others',
                'Attempt unauthorized access to systems',
                'Interfere with platform operations',
                'Misrepresent credentials or identity',
            ],
        },
        {
            heading: '7. User Conduct (continued)',
            paragraphs: ['We reserve the right to remove content or terminate accounts violating these Terms.'],
        },
        {
            heading: '8. Intellectual Property',
            paragraphs: [
                'All platform content, branding, logos, software, text, graphics, and materials are owned by or licensed to Immigrant KnowHow and protected by applicable intellectual property laws.',
                'Users may not reproduce, distribute, or commercially exploit platform content without written permission.',
            ],
        },
        {
            heading: '9. Disclaimer of Warranties',
            paragraphs: [
                'The Services are provided “AS IS” and “AS AVAILABLE” without warranties of any kind.',
                'We disclaim all warranties, express or implied, including:',
            ],
            list: [
                'Merchantability',
                'Fitness for a particular purpose',
                'Accuracy',
                'Reliability',
                'Availability',
                'Non-infringement',
            ],
        },
        {
            heading: '9. Disclaimer of Warranties (continued)',
            paragraphs: ['We do not guarantee uninterrupted or error-free operation.'],
        },
        {
            heading: '10. Limitation of Liability',
            paragraphs: [
                'To the maximum extent permitted by law, Immigrant KnowHow and its owners, officers, employees, affiliates, contractors, and partners shall not be liable for any indirect, incidental, consequential, special, punitive, or exemplary damages arising from:',
            ],
            list: [
                'Use of the Services',
                'Reliance on content',
                'AI-generated responses',
                'User-provider interactions',
                'Transactions',
                'Professional services obtained through the platform',
                'Loss of data, profits, revenue, or business opportunities',
            ],
        },
        {
            heading: '10. Limitation of Liability (continued)',
            paragraphs: ['Your sole remedy for dissatisfaction with the Services is to stop using the Services.'],
        },
        {
            heading: '11. Indemnification',
            paragraphs: [
                'You agree to defend, indemnify, and hold harmless Immigrant KnowHow and its affiliates from any claims, liabilities, damages, losses, and expenses arising from:',
            ],
            list: [
                'Your use of the Services',
                'Your violation of these Terms',
                'Your interactions with third parties',
                'Your reliance on platform content',
            ],
        },
        {
            heading: '12. Governing Law',
            paragraphs: [
                'These Terms shall be governed by and interpreted under the laws of the State of Michigan, United States, without regard to conflict of law principles.',
            ],
        },
        {
            heading: '13. Changes to Terms',
            paragraphs: [
                'We reserve the right to modify these Terms at any time.',
                'Continued use of the Services after updates constitutes acceptance of the revised Terms.',
            ],
        },
        {
            heading: '14. Contact Information',
            paragraphs: [
                'Questions regarding these Terms may be directed to:',
                'Immigrant KnowHow',
            ],
        },
];

/** @type {{ title: string, lastUpdated: string, intro: string[], sections: LegalSection[] }} */
export const termsOfServiceDocument = {
    title: 'Terms of Service',
    lastUpdated: 'May 21, 2026',
    intro: [
        'These Terms of Service (“Terms”) govern your access to and use of Immigrant KnowHow and all related services, applications, content, and features (collectively, the “Services”).',
        'By using the Services, you agree to these Terms.',
    ],
    sections: mergeContinuedLegalSections(termsOfServiceSections),
};