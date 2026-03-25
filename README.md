# EDU Swimming — P3 Swimming Class System

Laravel 11 application for managing swimming classes, student BMI tracking, test results, and coach management.

## Requirements

- PHP 8.2+
- Composer
- SQLite

## Setup

```bash
# Clone the repository
git clone git@github.com:haideralise/edu-stages.git
cd edu-stages

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed the database
php artisan db:seed
```

## Running the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` and log in.

## Seeded Users

All passwords are `password`.

| Username | Role | Display Name |
|----------|------|-------------|
| admin | Admin | Admin User |
| coach_lee | Coach | Coach Lee |
| coach_wong | Coach | Coach Wong |
| student_chan | Student | Chan Tai Man |
| student_li | Student | Li Ka Yan |
| student_wong | Student | Wong Siu Ming |
| student_lam | Student | Lam Hoi Yin |
| student_ng | Student | Ng Chi Wai |

## Running Tests

```bash
php artisan test
```

63 tests (182 assertions) covering auth API, class API, BMI CRUD, test results, coach results, policies, and models.

## Features (Stage 1)

- **Authentication**: Web login + API token auth (Sanctum), WordPress password support (phpass/bcrypt/MD5)
- **Student BMI**: Full CRUD — add, edit, delete BMI records (own records only)
- **Student Test Results**: View own results grouped by level tree
- **Coach Results**: View results for students in own classes
- **Role-based navigation**: Students see BMI/results, coaches see coach results, admins see all
- **API endpoints**: Login, logout, me, classes (paginated with filters)
