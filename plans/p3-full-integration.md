# P3 Full Integration — P1 + P2 + P3 Merged (Updated 17 Apr 2026)

> **Branch:** `p3-full-integration`
> **Base:** P3 `main` (PR #7 merged)
> **What this PR does:** Adds P1 and P2 code into P3's codebase so all three parties' code can run together on staging. Every addition is marked with `// from P1` or `// from P2` comments.

---

## File List (9 files changed, +293 lines)

| # | File | What was added | Source |
|---|------|---------------|--------|
| 1 | `routes/api.php` | `/user` endpoint, `/auth/token` route | P1 |
| 2 | `app/Providers/AppServiceProvider.php` | 14 service singletons in `register()` | P2 |
| 3 | `app/Http/Controllers/Api/AuthController.php` | `issue()` (PWA cookie → token), `checkPassword()` (WP multi-format), `ApiResponse` trait | P1 |
| 4 | `app/Models/WpUser.php` | `HasFactory`, `getAuthIdentifierName()`, `getAuthIdentifier()`, `getAuthPassword()`, `isAdmin()`, `isCoach()` | P1 |
| 5 | `app/Models/EduBmi.php` | `$primaryKey` | P1 |
| 6 | `app/Models/EduClass.php` | `HasFactory`, `$fillable`, `$incrementing`, `scopeByYear()`, `scopeByDistrict()` | P2 |
| 7 | `app/Models/EduClassUser.php` | `$primaryKey`, `$fillable`, `scopeForCoach()`, `scopeWhereAnyRoleJsonLike()` | P2 |
| 8 | `app/Models/EduLevel.php` | `HasFactory`, `$fillable`, `$primaryKey`, `getParsedDataAttribute()`, `scopeRoots()`, `scopeChildrenOf()` | P2 |
| 9 | `app/Models/EduResult.php` | `HasFactory`, `$fillable`, `$primaryKey`, extended casts, `student()` alias, `examLevel()` | P2 |

---

## Files NOT changed (already correct)

| File | Reason |
|------|--------|
| `bootstrap/app.php` | P3 already has: `then:` callback (student.php + coach.php), `statefulApi()`, 403 handler, 419 handlers, all Scheme D exception rendering |
| `app/Http/Controllers/Controller.php` | P3 already has `AuthorizesRequests` trait |
| `config/auth.php` | Already fully merged on staging (P1 did this) |
| `phpunit.xml` | P3 already uses MySQL (`edu_test`) instead of SQLite |

---

## What each source contributes

### From P1 (auth foundation)
- **`routes/api.php`** — `GET /api/user` (returns authenticated user via Sanctum) and `GET /api/auth/token` (exchanges WP cookie for Bearer token)
- **`AuthController.php`** — `issue()` method for PWA cookie-to-token exchange; `checkPassword()` with 4-tier WP password verification (MD5, WP 6.8+, phpass, bcrypt)
- **`WpUser.php`** — `getAuthIdentifierName()`, `getAuthIdentifier()`, `getAuthPassword()` (required by Laravel auth), `isAdmin()`, `isCoach()` convenience methods, `HasFactory` trait
- **`EduBmi.php`** — explicit `$primaryKey = 'id'`

### From P2 (admin features — Stage 2)
- **`AppServiceProvider.php`** — 14 service singletons: `StudentOrderService`, `CoachBonusCalculationService`, `ClassService`, `AttendanceSummaryService`, `ClassMonthFacade`, `ClassStudentQueryService`, `StudentFeeServiceCommon`, `StudentPaymentServiceCommon`, `AttendanceService`, `ClassStudentListService`, `CoachBonusReportService`, `CoachEntranceFeeService`, `DistrictManagementService`, `PrivateClassService`
- **`EduClass.php`** — `HasFactory`, `$fillable`, `$incrementing = false`, `scopeByYear()`, `scopeByDistrict()` (P3 adds null-safe variants `scopeForYear()`/`scopeForDistrict()` alongside these)
- **`EduClassUser.php`** — `$primaryKey`, `$fillable`, `scopeForCoach()` (whereJsonContains), `scopeWhereAnyRoleJsonLike()` (P3 adds `scopeWhereTeacher()`, `studentIdsForTeacher()`, `allTeacherIds()` alongside these)
- **`EduLevel.php`** — `HasFactory`, `$fillable`, `$primaryKey`, `getParsedDataAttribute()`, `scopeRoots()`, `scopeChildrenOf()` (P3 adds `descendants()`, `getTree()` alongside these)
- **`EduResult.php`** — `HasFactory`, `$fillable`, `$primaryKey`, extended casts (float for lap times), `student()` relationship (alias of P3's `user()`), `examLevel()` relationship

---

## After this PR is merged and deployed

1. P3 uploads **Type A files** (52 new files) to staging
2. P3 runs staging verification + Check-01 evidence capture
