# ImmigrationKnowHow Platform

A full-featured immigration services marketplace built with Laravel 13, Vue 3, Inertia.js, and Tailwind CSS.

## Features

- **Multi-role authentication**: Users, Service Providers, Admins, Super Admins
- **Service Provider Marketplace**: Search, filter, and connect with immigration professionals
- **Identity Verification**: Secure document upload and verification workflow
- **Messaging System**: Real-time conversations between users and providers
- **Digital Library**: Ebooks and audiobooks with download tracking
- **Review System**: Ratings, responses, and helpfulness voting
- **Provider Analytics**: Lead tracking, conversion funnels, and trends
- **Admin Dashboard**: User management, verification queue, content moderation
- **Affiliate System**: Track referral links and clicks

## Tech Stack

- **Backend**: Laravel 13, PHP 8.4+
- **Frontend**: Vue 3, Inertia.js, Tailwind CSS
- **UI Components**: Headless UI, Heroicons
- **Authorization**: Spatie Laravel Permission
- **Storage**: AWS S3 (optional, for secure document storage)

## Requirements

- PHP 8.4+
- Composer 2.x
- Node.js 18+ and npm
- SQLite, MySQL, or PostgreSQL

## Installation

### 1. Clone and Install Dependencies

```bash
cd immigration-knowhow
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

For SQLite (simplest for development):
```bash
touch database/database.sqlite
```

For MySQL/PostgreSQL, update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=immigration_knowhow
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Run Migrations and Seed

```bash
php artisan migrate
php artisan db:seed
```

### 5. Build Frontend Assets

```bash
npm run build
# or for development with hot reload:
npm run dev
```

### 6. Start the Server

```bash
php artisan serve
```

Visit `http://localhost:8000`

## Default Roles & Permissions

The seeder creates four roles:
- **user**: Basic user access
- **provider**: Service provider features
- **admin**: Administrative functions
- **super_admin**: Full system access

## Directory Structure

```
app/
├── Enums/              # UserRole, ServiceType, LeadStatus, VerificationStatus
├── Http/
│   ├── Controllers/
│   │   ├── Admin/      # Admin panel controllers
│   │   ├── Auth/       # Authentication
│   │   ├── Provider/   # Provider dashboard controllers
│   │   └── User/       # User dashboard controllers
│   └── Middleware/
├── Models/             # Eloquent models
├── Notifications/      # Email/database notifications
└── Policies/           # Authorization policies

resources/js/
├── Components/         # Reusable Vue components
├── Layouts/           # Page layouts (App, Auth, Provider, Admin)
└── Pages/             # Inertia page components
    ├── Admin/
    ├── Auth/
    ├── Library/
    ├── Marketplace/
    ├── Messages/
    ├── Provider/
    └── User/
```

## Configuration

### AWS S3 (for secure document storage)

Update `.env`:
```env
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_PRIVATE_BUCKET=your-private-bucket
```

### Mail Configuration

For production, configure SMTP in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

## Development

```bash
# Start Laravel dev server
php artisan serve

# Start Vite dev server (in another terminal)
npm run dev

# Run tests
php artisan test

# Clear caches
php artisan optimize:clear
```

## Production Deployment

```bash
composer install --optimize-autoloader --no-dev
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

## Design System

- **Primary Color**: Ocean Blue (#3B95F3)
- **Secondary Color**: Warm Gold (#F59E0B)
- **Accent Color**: Teal (#14B8A6)
- **Fonts**: DM Sans (body), Outfit (display), JetBrains Mono (code)

## License

MIT License
