import { mergeContinuedLegalSections } from '@/utils/legalDocument';

/** @typedef {{ heading: string, paragraphs?: string[], list?: string[], subsections?: { title: string, list?: string[], paragraphs?: string[] }[] }} LegalSection */

const privacyPolicySections = [
        {
            heading: '1. Educational Purpose Only',
            paragraphs: [
                'Immigrant KnowHow is an educational and informational platform only.',
                'We do not provide legal advice, immigration advice, tax advice, financial advice, accounting advice, investment advice, or any other regulated professional services. Content available on the website, through AI tools, articles, guides, community features, or service providers is intended solely for general informational and educational purposes.',
                'You should always consult a qualified immigration attorney, licensed tax professional, accountant, financial advisor, or other qualified professional before making any legal, immigration, financial, tax, business, or investment decisions.',
                'Your use of the Services does not create an attorney-client relationship, fiduciary relationship, advisor-client relationship, or any other professional relationship between you and Immigrant KnowHow.',
            ],
        },
        {
            heading: '2. Information We Collect',
            paragraphs: ['We may collect the following types of information:'],
            subsections: [
                {
                    title: 'A. Personal Information',
                    list: [
                        'Name',
                        'Email address',
                        'Phone number',
                        'Account login information',
                        'Profile information',
                        'Payment or billing details (processed through third-party providers)',
                    ],
                },
                {
                    title: 'B. Usage Information',
                    list: [
                        'Browser type',
                        'Device information',
                        'IP address',
                        'Pages visited',
                        'Session duration',
                        'Clickstream and interaction data',
                    ],
                },
                {
                    title: 'C. User Content',
                    list: [
                        'Messages',
                        'Uploaded files',
                        'Reviews',
                        'Listings',
                        'Communication with service providers',
                        'AI chat interactions',
                    ],
                },
                {
                    title: 'D. Cookies & Tracking Technologies',
                    list: [],
                    paragraphs: [
                        'We may use cookies, analytics tools, pixels, and similar technologies to improve user experience, analyze traffic, and personalize content.',
                        'You may disable cookies through your browser settings, though some features may not function properly.',
                    ],
                },
            ],
        },
        {
            heading: '3. How We Use Information',
            paragraphs: ['We may use collected information to:'],
            list: [
                'Operate and improve the Services',
                'Create and manage user accounts',
                'Process subscriptions and payments',
                'Match users with service providers',
                'Provide customer support',
                'Analyze usage and platform performance',
                'Detect fraud, abuse, or security issues',
                'Send platform updates, marketing communications, or notifications',
                'Improve AI-powered experiences and recommendations',
            ],
        },
        {
            heading: '4. Service Providers & Third-Party Relationships',
            paragraphs: [
                'Immigrant KnowHow may allow users to connect with independent third-party service providers, including but not limited to:',
            ],
            list: [
                'Immigration attorneys',
                'Tax professionals',
                'Financial advisors',
                'Tutors',
                'Translators',
                'Consultants',
                'Contractors',
                'Community professionals and businesses',
            ],
        },
        {
            heading: '4. Service Providers & Third-Party Relationships (continued)',
            paragraphs: [
                'These service providers are independent third parties and are not employees, agents, partners, representatives, or affiliates of Immigrant KnowHow unless explicitly stated otherwise.',
                'We do not guarantee:',
            ],
            list: [
                'The accuracy of provider information',
                'Licensing status',
                'Qualifications',
                'Certifications',
                'Availability',
                'Pricing',
                'Results or outcomes',
            ],
        },
        {
            heading: '4. Service Providers & Third-Party Relationships (continued — due diligence)',
            paragraphs: [
                'Users are solely responsible for independently verifying any provider’s credentials, licensing, qualifications, reviews, insurance, and suitability before engaging their services.',
            ],
        },
        {
            heading: '5. No Liability for User or Provider Interactions',
            paragraphs: [
                'Immigrant KnowHow is not a party to any agreement, transaction, communication, dispute, or relationship between users and service providers.',
                'We are not responsible or liable for:',
            ],
            list: [
                'Services provided by third parties',
                'Advice given by providers',
                'Contracts or agreements between parties',
                'Payments or financial losses',
                'Immigration outcomes',
                'Legal outcomes',
                'Tax filings or tax consequences',
                'Business or investment decisions',
                'Damages, disputes, injuries, negligence, fraud, misconduct, or losses arising from user-provider interactions',
            ],
        },
        {
            heading: '5. No Liability for User or Provider Interactions (continued)',
            paragraphs: ['Any engagement between a user and a service provider is done entirely at the user’s own risk.'],
        },
        {
            heading: '6. Sharing of Information',
            paragraphs: ['We may share information:'],
            list: [
                'With service providers necessary to operate the platform',
                'With payment processors',
                'With analytics providers',
                'When required by law',
                'To enforce our legal rights or policies',
                'In connection with a merger, acquisition, or sale of assets',
            ],
        },
        {
            heading: '6. Sharing of Information (continued)',
            paragraphs: ['We do not sell personal information to third parties.'],
        },
        {
            heading: '7. Data Security',
            paragraphs: [
                'We implement reasonable administrative, technical, and organizational safeguards to protect your information. However, no system or method of transmission over the internet is completely secure.',
                'You acknowledge and accept that use of the Services is at your own risk.',
            ],
        },
        {
            heading: '8. Third-Party Services & Links',
            paragraphs: [
                'Our platform may contain links to third-party websites, applications, advertisements, or external services. We are not responsible for the privacy practices, content, or policies of third parties.',
                'Users should review third-party privacy policies independently.',
            ],
        },
        {
            heading: '9. AI-Generated Content Disclaimer',
            paragraphs: [
                'Certain features of the Services may use artificial intelligence (“AI”) technologies to generate educational information, summaries, recommendations, translations, or responses.',
                'AI-generated content may contain inaccuracies, omissions, outdated information, or errors and should not be relied upon as legal, financial, tax, medical, immigration, or professional advice.',
                'Users should independently verify all information with qualified professionals before making decisions.',
            ],
        },
        {
            heading: '10. Children’s Privacy',
            paragraphs: [
                'Our Services are not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13.',
                'If we become aware that information has been collected from a child under 13, we may delete such information.',
            ],
        },
        {
            heading: '11. Your Rights',
            paragraphs: ['Depending on your jurisdiction, you may have rights to:'],
            list: [
                'Access your information',
                'Correct inaccurate information',
                'Request deletion of your information',
                'Opt out of certain communications',
                'Request a copy of your data',
            ],
        },
        {
            heading: '11. Your Rights (continued)',
            paragraphs: ['Requests may be submitted through our contact information below.'],
        },
        {
            heading: '12. Changes to This Privacy Policy',
            paragraphs: [
                'We may update this Privacy Policy periodically. Changes become effective upon posting on the website.',
                'Continued use of the Services after updates constitutes acceptance of the revised policy.',
            ],
        },
        {
            heading: '13. Contact Information',
            paragraphs: [
                'For questions regarding this Privacy Policy, please contact:',
                'Immigrant KnowHow',
            ],
        },
];

/** @type {{ title: string, lastUpdated: string, intro: string[], sections: LegalSection[] }} */
export const privacyPolicyDocument = {
    title: 'Privacy Policy',
    lastUpdated: 'May 21, 2026',
    intro: [
        'Welcome to Immigrant KnowHow (“Immigrant KnowHow,” “we,” “our,” or “us”). This Privacy Policy explains how we collect, use, disclose, and protect your information when you use our website, platform, applications, and related services (collectively, the “Services”).',
        'By accessing or using our Services, you agree to this Privacy Policy.',
    ],
    sections: mergeContinuedLegalSections(privacyPolicySections),
};