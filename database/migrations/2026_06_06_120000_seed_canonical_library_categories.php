<?php

use App\Support\Library\LibraryCategoryCatalog;
use App\Support\Library\LibraryItemCategoryRegionUpdater;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensure canonical library categories exist, then update category_id and regions
 * on existing ebooks already stored in library_items.
 *
 * Each entry match_title is used only to find an existing book (fuzzy normalized match, not
 * literal word-for-word). No new books are created and no title/author/file fields are modified.
 * On match, category_id and regions are fully replaced — previous region checkboxes are not merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        LibraryCategoryCatalog::ensureCategoriesExist();

        (new LibraryItemCategoryRegionUpdater())->apply($this->entries());
    }

    public function down(): void
    {
        // Data correction migration — no automatic rollback.
    }

    /**
     * 212 lookup rows (array keys 0–211). Duplicate match_title values target the same book.
     *
     * @return list<array{match_title: string, category: string, region: string}>
     */
    private function entries(): array
    {
        return [
              0 => 
              array (
                'match_title' => 'Thriving in Britain: A Guide for Immigrant Success',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'GREAT BRITAIN',
              ),
              1 => 
              array (
                'match_title' => 'Navigating New Shores: Mental Health Challenges for Immigrant Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              2 => 
              array (
                'match_title' => 'Navigating the Credentialing Maze: A Guide for Immigrant Families in America',
                'category' => 'PROFESSIONAL DEVELOPMENT',
                'region' => 'USA',
              ),
              3 => 
              array (
                'match_title' => 'Your Essential Guide to Moving to Australia: What Every Immigrant Should Know',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'EUROPE',
              ),
              4 => 
              array (
                'match_title' => 'Navigating Canada – Your Essential Guide for New Immigrants',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'USA - Canada',
              ),
              5 => 
              array (
                'match_title' => 'Surviving America: Essential Strategies for Immigrants',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'USA - Canada',
              ),
              6 => 
              array (
                'match_title' => 'Welcome to Belgium: A Newcomer\'s Guide to Culture and Etiquette',
                'category' => 'TOURISM - VISITING OTHER COUNTRIES FOR LEISURE & ADVENTURE',
                'region' => 'EUROPE',
              ),
              7 => 
              array (
                'match_title' => 'Welcome to the Netherlands: A Comprehensive Guide for New Arrivals',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'EUROPE',
              ),
              8 => 
              array (
                'match_title' => 'Finland Uncovered: A Comprehensive Guide for New-Comers',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'EUROPE',
              ),
              9 => 
              array (
                'match_title' => 'Welcome to Germany: A Comprehensive Guide for Newcomers',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'EUROPE',
              ),
              10 => 
              array (
                'match_title' => 'The Immigrant’s Guide to Education: Opportunities and Success',
                'category' => 'PROFESSIONAL DEVELOPMENT',
                'region' => 'USA',
              ),
              11 => 
              array (
                'match_title' => 'Success in America: A Guide for Newcomers',
                'category' => 'PROFESSIONAL DEVELOPMENT',
                'region' => 'USA',
              ),
              12 => 
              array (
                'match_title' => 'Discovering Bulgaria: Essential Insights for Newcomers',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'EUROPE',
              ),
              13 => 
              array (
                'match_title' => 'Your Essential Guide to Moving to Poland: Visa, Costs, and Culture',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'EUROPE',
              ),
              14 => 
              array (
                'match_title' => 'Discovering Nigeria: A Family Guide to Cultural Adventures',
                'category' => 'TOURISM - VISITING OTHER COUNTRIES FOR LEISURE & ADVENTURE',
                'region' => 'All',
              ),
              15 => 
              array (
                'match_title' => 'Recognizing the Signs: A Family\'s Guide to Preventing Murder-Suicide',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              16 => 
              array (
                'match_title' => 'Shadows of Betrayal: Early Signs of Murder for Hire in Relationships –',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              17 => 
              array (
                'match_title' => 'Breaking Free: A Guide to Overcoming Persistent Depressive Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              18 => 
              array (
                'match_title' => 'Battling the Shadows: Understanding and Managing Recurrent Moderate Depression',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              19 => 
              array (
                'match_title' => 'Finding Light: Navigating Major Depression Without Psychosis',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              20 => 
              array (
                'match_title' => 'Climate Change and Your Mood: - Understanding the Link to Seasonal Affective Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              21 => 
              array (
                'match_title' => 'Shadow of the Mind: Navigating Major Depression Disorder with Psychotic Features',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              22 => 
              array (
                'match_title' => 'Navigating the Shadows: Understanding Post-Partum Depression and its Impact on Families',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              23 => 
              array (
                'match_title' => 'Sustaining Light: Long-Term Strategies for Major Depressive Disorder Remission',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              24 => 
              array (
                'match_title' => 'Navigating the Shadows: A Guide to Managing Recurrent Mild Depression',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              25 => 
              array (
                'match_title' => 'Navigating Schizophrenia: A Guide for Patients and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              26 => 
              array (
                'match_title' => 'Navigating Schizoaffective Disorder: A Patient’s Guide to Understanding and Treatment',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              27 => 
              array (
                'match_title' => 'Navigating Cyclothymic Disorder: A Guide for Adults and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              28 => 
              array (
                'match_title' => 'Navigating Cyclothymia: A Guide for Adolescents and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              29 => 
              array (
                'match_title' => 'Balancing the Scales: A Comprehensive Guide to Managing Bipolar Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              30 => 
              array (
                'match_title' => 'Navigating Bipolar 1: A Comprehensive Guide for Individuals and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              31 => 
              array (
                'match_title' => 'Navigating Bipolar 2: A Comprehensive Guide to Effective Management',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              32 => 
              array (
                'match_title' => 'Enabling Patterns: Psychological Effects of Parenting During Separation',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              33 => 
              array (
                'match_title' => 'Reconnect and Rekindle -Healing After Separation',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              34 => 
              array (
                'match_title' => 'Navigating Separation Anxiety- A Guide for Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              35 => 
              array (
                'match_title' => 'Calm Within: Mastering Anxiety for A Successful Life',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              36 => 
              array (
                'match_title' => 'Overcoming Social Anxiety: A Family Guide to Empowerment and Resilience',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              37 => 
              array (
                'match_title' => 'Navigating Anxiety: A Comprehensive Guide to Managing Generalized Anxiety Disorder in Adults',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              38 => 
              array (
                'match_title' => 'Navigating Anxiety: Strategies for Adults in Everyday Life',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              39 => 
              array (
                'match_title' => 'Healing Mechanism – Robot Therapy for Anxiety and Depression',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              40 => 
              array (
                'match_title' => 'Navigating the Storm – Understanding Medication Induced -Bipolar Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              41 => 
              array (
                'match_title' => 'Navigating the Shadows: Understanding Stimulant Induced Depressive Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              42 => 
              array (
                'match_title' => 'Navigating Stimulant-Induced Psychosis: A Family Guide to Recovery',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              43 => 
              array (
                'match_title' => 'The Unseen Battle: Diagnosis and Early Intervention Perinatal Psychosis',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              44 => 
              array (
                'match_title' => 'Sleepwalking Uncovered: A Comprehensive Guide for Families',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              45 => 
              array (
                'match_title' => 'Healing Together: A Family Guide to Understanding Borderline Personality Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              46 => 
              array (
                'match_title' => 'Breaking Free: A Guide to Recovering from Narcissistic Abuse',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              47 => 
              array (
                'match_title' => 'Navigating Paranoia – Understanding and Treating Paranoid Personality Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              48 => 
              array (
                'match_title' => 'Navigating Antisocial Personality- A Guide for Individuals and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              49 => 
              array (
                'match_title' => 'Navigating Avoidance: A Practical Guide to Managing Avoidance Personality Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              50 => 
              array (
                'match_title' => 'Navigating Life with Obsessive Compulsive Personality Disorder: A Guide for Individuals & Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              51 => 
              array (
                'match_title' => 'Family Matters: Coping Strategies for Loved Ones of Individuals with Dependent Personality Disorder',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              52 => 
              array (
                'match_title' => 'Understanding Your Triggers: Strategies for Avoidance and Recovery',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              53 => 
              array (
                'match_title' => 'Navigating Schizotypal Traits: A Comprehensive Guide for Individuals and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              54 => 
              array (
                'match_title' => 'Navigating Solitude: A Guide to Managing Schizoid Personality Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              55 => 
              array (
                'match_title' => 'Therapeutic Approaches to Histrionic Personality Disorder: A Guide for Families and Caregivers',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              56 => 
              array (
                'match_title' => 'Mind Matters: Managing Mental Health in Disability Support',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              57 => 
              array (
                'match_title' => 'Navigating the Dangers – Understanding Medication Overdose and It’s Implications',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              58 => 
              array (
                'match_title' => 'Navigating Shadows: Understanding Mood Disorders in Chronic Pain',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              59 => 
              array (
                'match_title' => 'Unlocking the Mind: Brain Fingerprinting for Mental Health Insights',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              60 => 
              array (
                'match_title' => 'Broken Trust: The Impact of Pathological Lying on Relationships',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              61 => 
              array (
                'match_title' => 'Understanding Intermittent Explosive Disorder: A Guide for Parents and Individual',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              62 => 
              array (
                'match_title' => 'Navigating the Storm: Parenting Strategies for Children with Oppositional Defiant Disorder',
                'category' => 'CHILD DEVELOPMENT AND WELLNESS',
                'region' => 'All',
              ),
              63 => 
              array (
                'match_title' => 'The Silent Struggle -Understanding Selective Mutism Together',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              64 => 
              array (
                'match_title' => 'Healing Together: Family Support Strategies for PTSD Recovery',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              65 => 
              array (
                'match_title' => 'Paths to Peace: Effective Treatments for PTSD and Co-occurring Disorders',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              66 => 
              array (
                'match_title' => 'Navigating Noncompliance: A Guide for Families Managing Medication in Mental Health',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              67 => 
              array (
                'match_title' => 'Finding Calm: Coping Strategies for Children with Generalized Anxiety Disorder',
                'category' => 'CHILD DEVELOPMENT AND WELLNESS',
                'region' => 'All',
              ),
              68 => 
              array (
                'match_title' => 'The Art of Healing – Creative Pathways for Rape Trauma Recovery',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              69 => 
              array (
                'match_title' => 'Breaking the Chain - A Family Guide to Overcoming Nicotine Addiction',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              70 => 
              array (
                'match_title' => 'Red Flags in Romance Spotting Women to Avoid Before You Say I Do',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              71 => 
              array (
                'match_title' => 'Red Flags – Men to Avoid Before You Say I Do',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              72 => 
              array (
                'match_title' => 'Living with TBI – A Caregiver’s Guide to Daily Strategies and Support',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              73 => 
              array (
                'match_title' => 'Breaking the Cycle: A Patient’s Guide to Managing OCD',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              74 => 
              array (
                'match_title' => 'Breaking Barriers: A Comprehensive Guide to Understanding and Treating Erectile Dysfunction',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              75 => 
              array (
                'match_title' => 'Building Bridges: Effective Therapy for Couples Facing Sexual Challenge',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              76 => 
              array (
                'match_title' => 'Parenting with Purpose. Strategies and Support for Raising Children with Intellectual Disabilities',
                'category' => 'CHILD DEVELOPMENT AND WELLNESS',
                'region' => 'All',
              ),
              77 => 
              array (
                'match_title' => 'Healing Hearts: Navigating Grief After Loss of a Loved One',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              78 => 
              array (
                'match_title' => 'Together in Grief- A Guide to Healing After Pregnancy Loss',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              79 => 
              array (
                'match_title' => 'Navigating the Storm: Understanding and Overcoming Acute Stress Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              80 => 
              array (
                'match_title' => 'Breaking Free – Mindfulness Techniques to Overcome Addiction',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              81 => 
              array (
                'match_title' => 'Breaking the Cycle – A Journey to Overcoming Kleptomania',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              82 => 
              array (
                'match_title' => 'Unlocking Freedom: A Journey Out of Emotional Imprisonment',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              83 => 
              array (
                'match_title' => 'Rebuilding Lives: A Guide to Success After Prison',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              84 => 
              array (
                'match_title' => 'Empowering ADHD Families – Strategies for Effective Parenting and Support',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              85 => 
              array (
                'match_title' => 'The Power of Support – Building Emotional Intimacy After Menopause',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              86 => 
              array (
                'match_title' => 'Embracing Alternatives – Holistic Approaches to Managing Menopause',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              87 => 
              array (
                'match_title' => 'Mastering Control: A Comprehensive Guide to Managing Premature Ejaculation for Couple',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              88 => 
              array (
                'match_title' => 'Beyond the Surgery – Navigating Emotional Wellness After Transplant',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              89 => 
              array (
                'match_title' => 'A Healthy New Life – Lifestyle Modifications for Kidney Transplant Recipients',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              90 => 
              array (
                'match_title' => 'Beyond the Fury- Strategies for Managing Explosive Anger',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              91 => 
              array (
                'match_title' => 'Caregiver’s Guide – Coping Strategies for Supporting Loved Ones with Dissociative Amnesia',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              92 => 
              array (
                'match_title' => 'Navigating the Mind – A Family Guide to Managing Dissociative Identity Disorder',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              93 => 
              array (
                'match_title' => 'Pathways to Healing: Long-term Recovery Strategies for Anorexia Nervosa',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              94 => 
              array (
                'match_title' => 'Navigating Alzheimer’s – A Comprehensive Guide for Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              95 => 
              array (
                'match_title' => 'Together We Rise -A Guide to Youth Suicide Prevention',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              96 => 
              array (
                'match_title' => 'Betel Nuts – Health Consequences and Cultural Significance',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              97 => 
              array (
                'match_title' => 'Beyond the Diagnosis: Life After Gall Bladder Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              98 => 
              array (
                'match_title' => 'Beyond the Procedure: Life After Endoscopic Gastroplasty',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              99 => 
              array (
                'match_title' => 'Bodyweight Bliss: Effective Exercises for Weight Loss Without Equipment',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              100 => 
              array (
                'match_title' => 'Caffeine and Medication Interactions: Key Consideration for Individuals',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              101 => 
              array (
                'match_title' => 'Digestive Harmony: Managing Your Health After Gallbladder Removed',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              102 => 
              array (
                'match_title' => 'Digital Predators: Online Recruitment Tactics for Traffickers',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              103 => 
              array (
                'match_title' => 'Finding Your Voice: A Survivor’s Guide to Life After Throat Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              104 => 
              array (
                'match_title' => 'Love and Loyalty – The Hidden Bonds of Drug Trafficking',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              105 => 
              array (
                'match_title' => 'Navigating Bile Duct Cancer: Essential Insights for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              106 => 
              array (
                'match_title' => 'Navigating Medication Induced Sleep Disorder: A Family Guide',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              107 => 
              array (
                'match_title' => 'Shift Work and Sleep – Managing the Impact on Your Wellbeing',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              108 => 
              array (
                'match_title' => 'Sleep Apnea Uncovered: Essential Insight for Individuals and Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              109 => 
              array (
                'match_title' => 'Sleep Well: Hygiene Practices for Managing Restless Legs Syndrome',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              110 => 
              array (
                'match_title' => 'Smoke and Mirrors: How Cigarette Smoking Undermines Medication Efficacy',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              111 => 
              array (
                'match_title' => 'Staying Safe- A Family Guide to Understanding Human Trafficking',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              112 => 
              array (
                'match_title' => 'Understanding Cataracts: A Comprehensive Guide for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              113 => 
              array (
                'match_title' => 'Understanding Narcolepsy: A Family Guide to Symptoms and Managements',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              114 => 
              array (
                'match_title' => 'Understanding Osteosarcoma: A Comprehensive Guide for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              115 => 
              array (
                'match_title' => 'Catching It Early: Techniques for Detecting Skin Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              116 => 
              array (
                'match_title' => 'Early Detection Saves Lives – Screening Techniques for Ovarian Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              117 => 
              array (
                'match_title' => 'Empowered Choices: A Young Woman\'s Guide to Cervical Cancer Prevention',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              118 => 
              array (
                'match_title' => 'Navigating Ovarian Cancer: A Comprehensive Guide for Patients and Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              119 => 
              array (
                'match_title' => 'Navigating Polycystic Ovarian Disease: A Guide to Fertility, Mental Health & Relationship',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              120 => 
              array (
                'match_title' => 'Rising From the Shadows: Life After Cervical Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              121 => 
              array (
                'match_title' => 'The Dark Side of Green: Cannabis and Long-Term Mental Health Effects',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              122 => 
              array (
                'match_title' => 'A Healthy New Life – Lifestyle Modifications for Kidney Transplant Recipients',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              123 => 
              array (
                'match_title' => 'Beyond Conventional Care: Exploring Alternative Therapies for Rheumatoid Arthritis',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              124 => 
              array (
                'match_title' => 'Early Insights: Navigating Gastric Cancer Detection and Beyond',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              125 => 
              array (
                'match_title' => 'Heartfelt Recovery: A Guide to Life After a Heart Attack',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              126 => 
              array (
                'match_title' => 'Life Beyond the Bypass: A Family Guide to Thriving After Gastric Surgery',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              127 => 
              array (
                'match_title' => 'Navigating Diabetes: A Family Guide to Type 1 and Type 2 Management',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              128 => 
              array (
                'match_title' => 'Navigating Fibromyalgia: A Family Guide to Understanding and Managing Symptoms',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              129 => 
              array (
                'match_title' => 'Navigating Gestational Diabetes – A Comprehensive Guide for Expecting Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              130 => 
              array (
                'match_title' => 'Understanding Sickle Cell Disease: A Guide for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              131 => 
              array (
                'match_title' => 'Your First Experience of Childbirth: A Guide for New Parents',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              132 => 
              array (
                'match_title' => 'Guilt and Mental Health: Therapeutic Approaches for Individual and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              133 => 
              array (
                'match_title' => 'Understanding Pyromania: A Family Guide to Early Detection and Management',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              134 => 
              array (
                'match_title' => 'Together We Thrive: Collaborative Efforts to Prevent Gang Involvement',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              135 => 
              array (
                'match_title' => 'Understanding Psoriasis- Causes, Complications and Effective Management for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              136 => 
              array (
                'match_title' => 'Surviving Beyond Survival – Long-Term Mental Health Effects After Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              137 => 
              array (
                'match_title' => 'Together We Heal – Family Strategies for Supporting Alcoholism Recovery',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              138 => 
              array (
                'match_title' => 'Stories of Strength: Personal Triumphs Over Body Dysmorphic Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              139 => 
              array (
                'match_title' => 'Recognizing the Red Flags: Early Signs of Substance Abuse in Children',
                'category' => 'CHILD DEVELOPMENT AND WELLNESS',
                'region' => 'All',
              ),
              140 => 
              array (
                'match_title' => 'Paths to Peace: Effective Treatments for PTSD and Co-occurring Disorders',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              141 => 
              array (
                'match_title' => 'Navigating Recovery: A Parent’s Guide to Eating Disorders in Children',
                'category' => 'CHILD DEVELOPMENT AND WELLNESS',
                'region' => 'All',
              ),
              142 => 
              array (
                'match_title' => 'Mind Over Matter – Mental Health Strategies for Defeating Negative Thoughts',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              143 => 
              array (
                'match_title' => 'Healing Together: A Family Guide to Overcoming Sex Addiction',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              144 => 
              array (
                'match_title' => 'Harmonious Co-Parenting After Divorce: Strategies for Success',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              145 => 
              array (
                'match_title' => 'Grudge Management: Strategies for Stress Relief and Emotional Freedom',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              146 => 
              array (
                'match_title' => 'Embracing Connections: Strategies to Overcome Loneliness',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              147 => 
              array (
                'match_title' => 'Calm the Storm: Practical Anger Management Techniques for individuals',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              148 => 
              array (
                'match_title' => 'Breaking Free: A Guide to Overcoming Sex Addiction',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              149 => 
              array (
                'match_title' => 'A Holistic Path: Integrated Approaches to Healing from Addiction',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              150 => 
              array (
                'match_title' => 'Understanding Your Triggers: Strategies for Avoidance and Recovery',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              151 => 
              array (
                'match_title' => 'Navigating Bulimia: A Family Guide to Recovery and Support',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              152 => 
              array (
                'match_title' => 'Healing Bonds: Navigating Reactive Attachment Disorder in Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              153 => 
              array (
                'match_title' => 'Beyond the Bullet: Psychological Support for Those Affected by Gun Violence',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              154 => 
              array (
                'match_title' => 'Beyond the Walls: Finding Freedom Within',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              155 => 
              array (
                'match_title' => 'Breaking Free: A Guide to Escaping Domestic Violence',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              156 => 
              array (
                'match_title' => 'Breaking the Cycle: Strategies to Escape Generational Poverty',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              157 => 
              array (
                'match_title' => 'Embracing Change: Coping Strategies for Emotional Pain Post Breakup',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              158 => 
              array (
                'match_title' => 'Enabling Patterns: Psychological Effects of Parenting During Separation',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              159 => 
              array (
                'match_title' => 'Healing Hearts: Therapeutic Approaches to Survivor’s Guilt',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              160 => 
              array (
                'match_title' => 'Mental Health Matters: Resources for LGBTQ Youth and Allies',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              161 => 
              array (
                'match_title' => 'Mind Matters: Addressing Mental Health and Injustice in our Communities',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              162 => 
              array (
                'match_title' => 'Navigating Change: A Comprehensive Guide to Managing Adjustment Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              163 => 
              array (
                'match_title' => 'Navigating Polycystic Ovarian Disease: A Guide to Fertility, Mental Health & Relationship',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              164 => 
              array (
                'match_title' => 'Shadows of Faith: Healing from Cult Trauma',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              165 => 
              array (
                'match_title' => 'Surviving the Emotional Rollercoaster: A Guide for Break-up Recovery',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              166 => 
              array (
                'match_title' => 'Together in Strength: Building Support Groups for LGBTQ Youth and Families',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              167 => 
              array (
                'match_title' => 'Understanding Munchausen Syndrome: A Comprehensive Guide for Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              168 => 
              array (
                'match_title' => 'Understanding Stockholm Syndrome: A Guide for Individuals and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              169 => 
              array (
                'match_title' => 'Understanding the Shadows: Unravelling the Pathogenesis of Suicide',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              170 => 
              array (
                'match_title' => 'Unseen Betrayal: Identifying the Early Signs of Cheating and Its Mental Health Consequence',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              171 => 
              array (
                'match_title' => 'A Holistic Path: Integrated Approaches to Healing from Addiction',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              172 => 
              array (
                'match_title' => 'Coping With Nightmares: Strategies for Individuals and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              173 => 
              array (
                'match_title' => 'Embracing You – The Essential Guide to Self-love and Compassion',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              174 => 
              array (
                'match_title' => 'Healing Together: Family Strategies for Overcoming Fentanyl',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              175 => 
              array (
                'match_title' => 'Heartbreak and Healing: The Emotional Toll of Loving Someone in Prison',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              176 => 
              array (
                'match_title' => 'Heartfelt Conversation: Navigating Emotional Turmoil in Family Disputes',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              177 => 
              array (
                'match_title' => 'Navigating Cannabis Induced Psychotic Disorder: A Guide for Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              178 => 
              array (
                'match_title' => 'Navigating Schizophreniform: A Guide for Individuals and Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              179 => 
              array (
                'match_title' => 'Recognizing the Signs: Identifying Benzodiazepine Addiction in Yourself and Loved Ones',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              180 => 
              array (
                'match_title' => 'Shattered Faith: Navigating Mental Health After Mega Churches Collapse',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              181 => 
              array (
                'match_title' => 'The Healing Power of Forgiveness – Transforming Mental Wellbeing',
                'category' => 'OTHERS',
                'region' => 'All',
              ),
              182 => 
              array (
                'match_title' => 'Together in Grief- A Guide to Healing After Pregnancy Loss',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              183 => 
              array (
                'match_title' => 'Breaking the Silence: Understanding and Overcoming Female Orgasmic Disorder',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              184 => 
              array (
                'match_title' => 'Navigating Alcohol: A Guide for Bariatric Patient’s and Their Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              185 => 
              array (
                'match_title' => 'Navigation Brief Psychotic Disorder: A Guide for Families and Caregivers',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              186 => 
              array (
                'match_title' => 'Navigating Disagreements: The Psychology Behind Marital Conflicts',
                'category' => 'FAMILY, PARENTING AND RELATIONSHIP',
                'region' => 'All',
              ),
              187 => 
              array (
                'match_title' => 'Navigating Frontotemporal Neurocognitive Disorders: A Family Guide',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              188 => 
              array (
                'match_title' => 'Navigating Sleep Terrors: A Family’s Guide to Understanding and Managing Nighttime Fears',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              189 => 
              array (
                'match_title' => 'Navigating Tic Disorder: A Comprehensive Guide for Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              190 => 
              array (
                'match_title' => 'Signs and Symptoms of Internet Gambling Addiction – A Guide for Families',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              191 => 
              array (
                'match_title' => 'Navigating New Worlds: Emotional Intelligence for Immigrants Facing Racism',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'USA - Canada',
              ),
              192 => 
              array (
                'match_title' => 'Love and Resilience: The Impact of Post-Partum Psychosis on Relationships',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              193 => 
              array (
                'match_title' => 'Navigating Chronic Pain: A Family Guide to Understanding and Management',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              194 => 
              array (
                'match_title' => 'Shadows of the Mind- Understanding Serial Killers and Mental Health',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              195 => 
              array (
                'match_title' => 'Love and Resilience: The Impact of Post-Partum Psychosis on Relationships',
                'category' => 'MENTAL HEALTH AWARENESS',
                'region' => 'All',
              ),
              196 => 
              array (
                'match_title' => 'Inspiring Journeys Immigrant Entrepreneurs Making It in Canada',
                'category' => 'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
                'region' => 'All',
              ),
              197 => 
              array (
                'match_title' => 'Breaking the Chains A Family Guide to Overcoming PMDD',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              198 => 
              array (
                'match_title' => 'Breaking Free A Family Guide to Managing Cocaine Addiction',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              199 => 
              array (
                'match_title' => 'Navigating the Storm A Family Guide to Bath Salt Addiction Recovery',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              200 => 
              array (
                'match_title' => 'Beyond the Battle Life After Leukemia',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              201 => 
              array (
                'match_title' => 'Beyond the Diagnosis Navigating Life After Brain Cancer',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              202 => 
              array (
                'match_title' => 'Breaking Free A Survivor s Guide to Escaping Spousal Abuse',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              203 => 
              array (
                'match_title' => 'Effective Interventions Family Strategies for Preventing Addiction',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              204 => 
              array (
                'match_title' => 'Coping Together Strategies for Couples Facing Penetration Disorders',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              205 => 
              array (
                'match_title' => 'Relationship Dynamics How Delayed Ejaculation Affects Intimacy and Communication',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              206 => 
              array (
                'match_title' => 'Understanding Hoarding Disorder A Comprehensive Guide for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              207 => 
              array (
                'match_title' => 'Understanding Tourette’s Disorder A Comprehensive Guide for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              208 => 
              array (
                'match_title' => 'Understanding Transient Global Amnesia A Guide for Patients and Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              209 => 
              array (
                'match_title' => 'Understanding Trichotillomania A Comprehensive Guide for Families',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              210 => 
              array (
                'match_title' => 'Unlocking Desire A Couple s Guide to Understanding Male Hypoactive Sexual Desire Disorder',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
              211 => 
              array (
                'match_title' => 'Unveiling Desire Understanding Female Sexual Interest and Arousal Disorders',
                'category' => 'MEDICAL HEALTH & WELLNESS',
                'region' => 'All',
              ),
        ];
    }
};
