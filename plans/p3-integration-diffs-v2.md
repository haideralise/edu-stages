# P3 Integration Diffs v2 — Against Current Staging (9 Apr 2026)

> **Base:** Current staging snapshot (P1 baseline + P2 Stage 2, downloaded 9 Apr 2026)
> **Target:** Staging + P3 additions only (all P1/P2 code preserved)
> **EduResult.php:** Skipped — P2 already has the relationships P3 needs (`student()`, `eduClass()`)

---

## File List

| # | File | Purpose |
|---|------|---------|
| 1 | `bootstrap/app.php` | Load `student.php` + `coach.php` via `then:` callback; add `statefulApi()`; add 403 handler + HttpException 419 fallback |
| 2 | `routes/api.php` | Add P3 API routes: `/logout`, `/me`, `/classes`, `/account/bmi`, `/account/results`, `/account/growth-chart` |
| 3 | `app/Http/Controllers/Controller.php` | Add `AuthorizesRequests` trait for Gate/Policy enforcement |
| 4 | `app/Providers/AppServiceProvider.php` | Register P3 Gate/Policy bindings in `boot()` (P2 singletons in `register()` unchanged) |
| 5 | `app/Http/Controllers/Api/AuthController.php` | Add `logout()`, `me()`, `formatUser()` methods + `ApiResponse` trait (P1's `issue`/`login`/`checkPassword` unchanged) |
| 6 | `app/Models/WpUser.php` | Add `birthdate`/`gender` accessors; eager-load optimisation in `getMetaValue()` |
| 7 | `app/Models/EduBmi.php` | Add `category` accessor, `user()` relationship, `scopeForUser()`, `normalizeDate()`, `calculateBmi()` |
| 8 | `app/Models/EduClass.php` | Add null-safe `scopeForYear()` + `scopeForDistrict()` alongside P2's existing scopes |
| 9 | `app/Models/EduClassUser.php` | Add `scopeWhereTeacher()` (JSON_CONTAINS), `studentIdsForTeacher()`, `allTeacherIds()` |
| 10 | `app/Models/EduLevel.php` | Add `descendants()` recursive relationship, `getTree()` static builder |
| 11 | `phpunit.xml` | Switch test DB from SQLite to MySQL (required for JSON_CONTAINS) |

---

## Diffs

### 1. `bootstrap/app.php`

```diff
--- a/bootstrap/app.php
+++ b/bootstrap/app.php
@@ -7,6 +7,8 @@
 use Illuminate\Http\Request;
 use Illuminate\Validation\ValidationException;
 use Illuminate\Session\TokenMismatchException;
+use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
+use Symfony\Component\HttpKernel\Exception\HttpException;
 use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

 return Application::configure(basePath: dirname(__DIR__))
@@ -15,6 +17,10 @@
         api: __DIR__ . '/../routes/api.php',
         commands: __DIR__ . '/../routes/console.php',
         health: '/up',
+        then: function () {
+            require __DIR__ . '/../routes/student.php';
+            require __DIR__ . '/../routes/coach.php';
+        },
     )
     ->withMiddleware(function (Middleware $middleware): void {
         $middleware->alias([
@@ -22,6 +28,7 @@
             'role.admin' => \App\Http\Middleware\AdminMiddleware::class,
             'role.coach' => \App\Http\Middleware\CoachMiddleware::class,
         ]);
+        $middleware->statefulApi();
     })
     ->withProviders([
         App\Providers\AuthServiceProvider::class,
@@ -48,6 +55,16 @@
             }
         });

+        // Scheme D — 403 Forbidden
+        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
+            if ($request->expectsJson() || $request->is('api/*')) {
+                return response()->json([
+                    'message' => 'Forbidden',
+                    'code'    => 'FORBIDDEN',
+                ], 403);
+            }
+        });
+
         // Scheme D — 404 Not Found
         $exceptions->render(function (NotFoundHttpException $e, Request $request) {
             if ($request->expectsJson() || $request->is('api/*')) {
@@ -58,15 +75,26 @@
             }
         });

+        // Scheme D — 419 CSRF Token Mismatch
         $exceptions->render(function (TokenMismatchException $e, Request $request) {
             if ($request->is('edu/*') || $request->expectsJson()) {
                 return response()->json([
-                    'message' => 'CSRF token失效',
+                    'message' => 'CSRF token mismatch',
                     'code'    => 'CSRF_MISMATCH',
                 ], 419);
             }
         });

+        // Scheme D — 419 CSRF (HttpException fallback)
+        $exceptions->render(function (HttpException $e, Request $request) {
+            if ($e->getStatusCode() === 419 && ($request->expectsJson() || $request->is('api/*'))) {
+                return response()->json([
+                    'message' => 'CSRF token mismatch',
+                    'code'    => 'TOKEN_MISMATCH',
+                ], 419);
+            }
+        });
+
         // Scheme D — 500 Server Error
         $exceptions->render(function (Throwable $e, Request $request) {
             if ($request->expectsJson() || $request->is('api/*')) {
```

### 2. `routes/api.php`

```diff
--- a/routes/api.php
+++ b/routes/api.php
@@ -1,6 +1,10 @@
 <?php

 use App\Http\Controllers\Api\AuthController;
+use App\Http\Controllers\Api\BmiController;
+use App\Http\Controllers\Api\Chart2Controller;
+use App\Http\Controllers\Api\EduClassController as ApiClassController;
+use App\Http\Controllers\Api\ResultController;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\Route;

@@ -13,3 +17,25 @@

 // Login with WP credentials
 Route::post('/login', [AuthController::class, 'login']);
+
+/*
+|--------------------------------------------------------------------------
+| Protected — Sanctum token required
+|--------------------------------------------------------------------------
+*/
+Route::middleware('auth:sanctum')->group(function () {
+
+    // Auth
+    Route::post('/logout', [AuthController::class, 'logout']);
+    Route::get('/me', [AuthController::class, 'me']);
+
+    // Classes (coach/admin, readonly)
+    Route::get('/classes', [ApiClassController::class, 'index']);
+
+    // Student account endpoints (09eng §PWA Student)
+    Route::prefix('account')->group(function () {
+        Route::get('/bmi', [BmiController::class, 'index']);
+        Route::get('/results', [ResultController::class, 'index']);
+        Route::get('/growth-chart', [Chart2Controller::class, 'index']);
+    });
+});
```

### 3. `app/Http/Controllers/Controller.php`

```diff
--- a/app/Http/Controllers/Controller.php
+++ b/app/Http/Controllers/Controller.php
@@ -2,7 +2,9 @@

 namespace App\Http\Controllers;

+use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
+
 abstract class Controller
 {
-    //
+    use AuthorizesRequests;
 }
```

### 4. `app/Providers/AppServiceProvider.php`

```diff
--- a/app/Providers/AppServiceProvider.php
+++ b/app/Providers/AppServiceProvider.php
@@ -2,6 +2,11 @@

 namespace App\Providers;

+use App\Models\EduBmi;
+use App\Models\EduResult;
+use App\Policies\BmiPolicy;
+use App\Policies\Chart2Policy;
+use App\Policies\ResultPolicy;
 use App\Services\AttendanceService;
 use App\Services\ClassService;
 use App\Services\Common\AttendanceSummaryService;
@@ -11,6 +16,7 @@
 use App\Services\Common\StudentFeeServiceCommon;
 use App\Services\Common\StudentPaymentServiceCommon;
 use App\Services\StudentOrderService;
+use Illuminate\Support\Facades\Gate;
 use Illuminate\Support\ServiceProvider;

 class AppServiceProvider extends ServiceProvider
@@ -36,6 +42,14 @@
      */
     public function boot(): void
     {
-        //
+        Gate::policy(EduBmi::class, BmiPolicy::class);
+        Gate::policy(EduResult::class, ResultPolicy::class);
+
+        Gate::define('bmi.listApi', [new BmiPolicy, 'listApi']);
+        Gate::define('result.listApi', [new ResultPolicy, 'listApi']);
+
+        $chart2Policy = new Chart2Policy;
+        Gate::define('chart2.viewAny', [$chart2Policy, 'viewAny']);
+        Gate::define('chart2.view', [$chart2Policy, 'view']);
     }
 }
```

### 5. `app/Http/Controllers/Api/AuthController.php`

```diff
--- a/app/Http/Controllers/Api/AuthController.php
+++ b/app/Http/Controllers/Api/AuthController.php
@@ -4,11 +4,15 @@

 use App\Http\Controllers\Controller;
 use App\Models\WpUser;
+use App\Traits\ApiResponse;
+use Illuminate\Http\JsonResponse;
 use Illuminate\Http\Request;
 use App\Services\PasswordHash;

 class AuthController extends Controller
 {
+    use ApiResponse;
+
     // PWA: validate WP cookie → issue Sanctum Bearer token
     public function issue(Request $request)
     {
@@ -63,6 +67,31 @@
         ]);
     }

+    // P3: Revoke current token
+    public function logout(Request $request): JsonResponse
+    {
+        $request->user()->currentAccessToken()->delete();
+
+        return $this->success(['message' => 'Logged out']);
+    }
+
+    // P3: Return authenticated user info
+    public function me(Request $request): JsonResponse
+    {
+        return $this->success($this->formatUser($request->user()));
+    }
+
+    private function formatUser(WpUser $user): array
+    {
+        return [
+            'id' => $user->ID,
+            'user_login' => $user->user_login,
+            'user_email' => $user->user_email,
+            'display_name' => $user->display_name,
+            'role' => $user->resolveRole(),
+        ];
+    }
+
     private function checkPassword(string $password, string $hash): bool
     {
         // 1. MD5 (very old WP)
```

### 6. `app/Models/WpUser.php`

```diff
--- a/app/Models/WpUser.php
+++ b/app/Models/WpUser.php
@@ -2,6 +2,7 @@

 namespace App\Models;

+use Illuminate\Database\Eloquent\Casts\Attribute;
 use Illuminate\Database\Eloquent\Factories\HasFactory;
 use Illuminate\Foundation\Auth\User as Authenticatable;
 use Laravel\Sanctum\HasApiTokens;
@@ -53,10 +54,26 @@
         return $this->hasOne(EduUser::class, 'user_id', 'ID');
     }

+    // ── Accessors ─────────────────────────────────────────────────
+
+    protected function birthdate(): Attribute
+    {
+        return Attribute::get(fn () => $this->getMetaValue('billing_birthdate'));
+    }
+
+    protected function gender(): Attribute
+    {
+        return Attribute::get(fn () => $this->getMetaValue('billing_gender'));
+    }
+
     // ── Helpers ──────────────────────────────────────────────────

     public function getMetaValue(string $key): ?string
     {
+        if ($this->relationLoaded('meta')) {
+            return $this->meta->firstWhere('meta_key', $key)?->meta_value;
+        }
+
         return $this->meta()->where('meta_key', $key)->value('meta_value');
     }

```

### 7. `app/Models/EduBmi.php`

```diff
--- a/app/Models/EduBmi.php
+++ b/app/Models/EduBmi.php
@@ -2,6 +2,9 @@

 namespace App\Models;

+use App\Support\BmiForAge;
+use Illuminate\Database\Eloquent\Builder;
+use Illuminate\Database\Eloquent\Casts\Attribute;
 use Illuminate\Database\Eloquent\Model;

 class EduBmi extends Model
@@ -24,7 +27,67 @@
     protected function casts(): array
     {
         return [
+            'user_id' => 'integer',
+            'height' => 'float',
+            'weight' => 'float',
+            'hc' => 'float',
+            'bmi' => 'float',
             'date' => 'integer',
         ];
     }
+
+    // ── Accessors ──────────────────────────────────────────────
+
+    protected function category(): Attribute
+    {
+        return Attribute::get(function (): string {
+            $birthdate = null;
+            $gender = null;
+
+            if ($this->relationLoaded('user') && $this->user) {
+                $birthdate = $this->user->birthdate;
+                $gender = $this->user->gender;
+            }
+
+            return BmiForAge::categorize($this->bmi, $birthdate, $gender, $this->date);
+        });
+    }
+
+    // ── Relationships ────────────────────────────────────────────
+
+    public function user()
+    {
+        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
+    }
+
+    // ── Scopes ───────────────────────────────────────────────────
+
+    public function scopeForUser(Builder $query, int $userId): Builder
+    {
+        return $query->where('user_id', $userId);
+    }
+
+    // ── Helpers ──────────────────────────────────────────────────
+
+    /**
+     * Convert a YYYY-MM-DD string or timestamp to a unix integer.
+     */
+    public static function normalizeDate(mixed $date): int
+    {
+        return is_numeric($date) ? (int) $date : (int) strtotime($date);
+    }
+
+    /**
+     * Calculate BMI from height (cm) and weight (kg).
+     */
+    public static function calculateBmi(float $height, float $weight): float
+    {
+        if ($height <= 0) {
+            return 0;
+        }
+
+        $heightM = $height / 100;
+
+        return round($weight / ($heightM * $heightM), 2);
+    }
 }
```

### 8. `app/Models/EduClass.php`

```diff
--- a/app/Models/EduClass.php
+++ b/app/Models/EduClass.php
@@ -45,7 +45,7 @@
     }

     // ---------------------------------------------------------------
-    // Scopes
+    // Scopes (P2)
     // ---------------------------------------------------------------

     public function scopeByYear(Builder $query, string $year): Builder
@@ -61,6 +61,28 @@
     }

     // ---------------------------------------------------------------
+    // Scopes (P3 — null-safe variants)
+    // ---------------------------------------------------------------
+
+    public function scopeForYear(Builder $query, ?string $year): Builder
+    {
+        return $year ? $query->where('class_year', $year) : $query;
+    }
+
+    public function scopeForDistrict(Builder $query, null|int|array $districtId): Builder
+    {
+        if (is_null($districtId)) {
+            return $query;
+        }
+
+        if (is_array($districtId)) {
+            return $query->whereIn('district_id', $districtId);
+        }
+
+        return $query->where('district_id', $districtId);
+    }
+
+    // ---------------------------------------------------------------
     // Relationships
     // ---------------------------------------------------------------

```

### 9. `app/Models/EduClassUser.php`

```diff
--- a/app/Models/EduClassUser.php
+++ b/app/Models/EduClassUser.php
@@ -5,6 +5,7 @@
 use Illuminate\Database\Eloquent\Builder;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;
+use Illuminate\Support\Collection;

 class EduClassUser extends Model
 {
@@ -48,6 +49,8 @@
         return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
     }

+    // ── Scopes (P2) ───────────────────────────────────────────────
+
     public function scopeForCoach(Builder $query, int $coachId): Builder
     {
         return $query->whereJsonContains('teacher', (string)$coachId);
@@ -64,4 +67,31 @@
                 ->orWhere('student_transfer', 'like', '%' . $needle . '%');
         });
     }
+
+    // ── Scopes (P3) ───────────────────────────────────────────────
+
+    public function scopeWhereTeacher(Builder $query, int $userId): Builder
+    {
+        return $query->whereRaw('JSON_CONTAINS(teacher, ?)', [json_encode((string) $userId)]);
+    }
+
+    // ── Query helpers (P3) ────────────────────────────────────────
+
+    public static function studentIdsForTeacher(int $teacherId): Collection
+    {
+        return static::whereTeacher($teacherId)
+            ->pluck('student')
+            ->flatMap(fn ($s) => $s ?? [])
+            ->map(fn ($id) => (int) $id)
+            ->unique()
+            ->values();
+    }
+
+    public static function allTeacherIds(): Collection
+    {
+        return static::pluck('teacher')
+            ->flatMap(fn ($t) => $t ?? [])
+            ->map(fn ($id) => (int) $id)
+            ->unique();
+    }
 }
```

### 10. `app/Models/EduLevel.php`

```diff
--- a/app/Models/EduLevel.php
+++ b/app/Models/EduLevel.php
@@ -6,6 +6,7 @@
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;
 use Illuminate\Database\Eloquent\Relations\HasMany;
+use Illuminate\Support\Collection;

 class EduLevel extends Model
 {
@@ -61,4 +62,19 @@
     {
         return $this->hasMany(self::class, 'pid');
     }
+
+    // ── P3 additions ──────────────────────────────────────────────
+
+    public function descendants()
+    {
+        return $this->children()->with('descendants');
+    }
+
+    /**
+     * Build full level tree from root nodes (pid = 0).
+     */
+    public static function getTree(): Collection
+    {
+        return self::where('pid', 0)->with('descendants')->get();
+    }
 }
```

### 11. `phpunit.xml`

```diff
--- a/phpunit.xml
+++ b/phpunit.xml
@@ -23,9 +23,8 @@
         <env name="BCRYPT_ROUNDS" value="4"/>
         <env name="BROADCAST_CONNECTION" value="null"/>
         <env name="CACHE_STORE" value="array"/>
-        <env name="DB_CONNECTION" value="sqlite"/>
-        <env name="DB_DATABASE" value=":memory:"/>
-        <env name="DB_URL" value=""/>
+        <env name="DB_CONNECTION" value="mysql"/>
+        <env name="DB_DATABASE" value="edu_test"/>
         <env name="MAIL_MAILER" value="array"/>
         <env name="QUEUE_CONNECTION" value="sync"/>
         <env name="SESSION_DRIVER" value="array"/>
```

---

## Notes for P1

1. **All P1 and P2 code is preserved.** Every diff only **adds** P3 methods/imports or fixes the 419 message text.
2. **`EduResult.php` is NOT included** — P2 already has the `student()`, `eduClass()`, and `examLevel()` relationships that P3 needs.
3. **`config/auth.php` is NOT included** — already fully merged on staging.
4. **`routes/student.php` and `routes/coach.php`** are Type A (new files) and will be uploaded by P3 separately after these diffs are merged.
5. **P3 Type A files** (52 new files: controllers, policies, form requests, resources, traits, support classes, views, tests, seeders) will be uploaded by P3 after P1 confirms these diffs are deployed.
