# Resident (Owner & Tenant) Mobile App & REST API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a production-grade, secure REST API backend for mobile clients with unified response envelopes, pagination, filtering, and FCM push notifications, paired with a complete Flutter cross-platform resident mobile application blueprint.

**Architecture:** Laravel Sanctum provides token-based resident authentication. A dedicated `/api/v1/resident/*` REST API exposes live ledger balances from `JournalService`, itemized monthly bills via `BillSummary`, maintenance tickets with photo uploads, payment submissions, and digital notices. Event listeners dispatch asynchronous FCM push notifications to registered resident devices. The mobile client (Flutter) provides 4-tab resident navigation with offline balance caching and bilingual support.

**Architecture Diagram:**

```mermaid
graph TD
    subgraph "Resident Mobile App (Flutter)"
        UI[Resident Mobile UI: iOS / Android]
        DioClient[Dio HTTP Client + Auth Interceptor]
        FCMClient[Firebase Messaging Receiver]
    end

    subgraph "Laravel Core API Layer"
        Sanctum[Sanctum Token Auth Guard]
        APIResponse[Unified ApiResponse Envelope Trait]
        ResidentControllers[Api/V1/Resident Controllers]
        Ledger[JournalService & Double-Entry Ledger]
        BillEngine[BillSummary & Billing Engine]
        DeviceTokens[UserDeviceToken Model & Registry]
        FCMService[PushNotificationService (FCM)]
    end

    subgraph "Web Back-Office Triggers"
        BillGen[Bill Generation Action]
        PayApprove[Payment Approval in PaymentSubmissionList]
        NoticePost[Notice Creation Desk]
        TicketResolve[Maintenance Ticket Resolution]
    end

    UI --> DioClient
    DioClient --> Sanctum
    Sanctum --> ResidentControllers
    ResidentControllers --> APIResponse
    ResidentControllers --> Ledger
    ResidentControllers --> BillEngine
    ResidentControllers --> DeviceTokens

    BillGen -->|Event| FCMService
    PayApprove -->|Event| FCMService
    NoticePost -->|Event| FCMService
    TicketResolve -->|Event| FCMService

    FCMService -->|Push Notification Alert| FCMClient
    FCMClient --> UI
```

**Tech Stack:** Laravel 13, PHP 8.5, PostgreSQL 18, Laravel Sanctum, spatie/laravel-permission 8, Firebase Cloud Messaging (FCM v1), Flutter (Dart), PHPUnit 12.

## Global Constraints

- **Single Source of Truth:** Monetary balances are NEVER stored in static database columns; they are always dynamically derived via `JournalService` and `BillSummary`.
- **BCMath Precision:** All financial amounts must use string representation with 2 decimal places and `bcadd`/`bcsub`/`bccomp`.
- **Tenant Data Isolation:** Residents (owners and renters) must NEVER have access to another flat's data. All queries are scoped strictly to authenticated user's flats.
- **Unified JSON Envelopes:** All endpoints must return standard envelopes: `{ success: true, message: "...", data: ..., meta: ... }` for success and `{ success: false, message: "...", errors: ... }` for failures.
- **Code Standards:** Run `vendor/bin/pint --format agent` and ensure `composer check` passes after every task.

---

### Task 1: API Infrastructure, Laravel Sanctum & Unified Response Envelope

**Files:**
- Create: `app/Http/Responses/ApiResponse.php`
- Create: `database/migrations/2026_09_10_130000_create_personal_access_tokens_table.php`
- Modify: `bootstrap/app.php`
- Create: `routes/api.php`
- Test: `tests/Feature/Api/ApiResponseTest.php`

**Interfaces:**
- Consumes: Standard HTTP requests and JSON data payloads.
- Produces: `ApiResponse::success(mixed $data, string $message, int $status)`, `ApiResponse::paginated(LengthAwarePaginator $paginator, string $message)`, `ApiResponse::error(string $message, int $status, ?array $errors)`.

- [ ] **Step 1: Write the failing unit/feature test for `ApiResponse`**

```php
// tests/Feature/Api/ApiResponseTest.php
namespace Tests\Feature\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope_structure(): void
    {
        $response = ApiResponse::success(['id' => 10], 'Data retrieved successfully');
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('Data retrieved successfully', $data['message']);
        $this->assertSame(10, $data['data']['id']);
    }

    public function test_paginated_envelope_structure(): void
    {
        $items = collect([['id' => 1], ['id' => 2]]);
        $paginator = new LengthAwarePaginator($items, 20, 2, 1, [
            'path' => 'http://localhost/api/v1/test',
        ]);

        $response = ApiResponse::paginated($paginator, 'Items listed');
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame('Items listed', $data['message']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(10, $data['meta']['last_page']);
        $this->assertSame(20, $data['meta']['total']);
    }

    public function test_error_envelope_structure(): void
    {
        $response = ApiResponse::error('Validation failed', 422, ['phone' => ['Invalid phone']]);
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame('Validation failed', $data['message']);
        $this->assertSame(['Invalid phone'], $data['errors']['phone']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/ApiResponseTest.php`  
Expected: FAIL with `Class "App\Http\Responses\ApiResponse" not found`.

- [ ] **Step 3: Implement `ApiResponse` and configure Sanctum & API routing**
Create `app/Http/Responses/ApiResponse.php`:
```php
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], $status);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
```
Run `php artisan install:api --no-interaction` to generate `routes/api.php` and sanctum migrations, and register API routing in `bootstrap/app.php`.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/ApiResponseTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Http/Responses/ApiResponse.php tests/Feature/Api/ApiResponseTest.php bootstrap/app.php routes/api.php database/migrations/*personal_access_tokens*
git commit -m "feat: setup api routing, sanctum tokens, and unified api response envelope"
```

---

### Task 2: Resident Authentication & FCM Device Token Management

**Files:**
- Create: `database/migrations/2026_09_10_131000_create_user_device_tokens_table.php`
- Create: `app/Models/UserDeviceToken.php`
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/AuthApiTest.php`

**Interfaces:**
- `POST /api/v1/auth/login`: `{ login: string, password: string }` -> returns `{ token, user, flats }`
- `POST /api/v1/auth/logout`: Revokes token.
- `GET /api/v1/auth/me`: Returns resident profile and associated flats.
- `POST /api/v1/auth/fcm-token`: `{ token: string, device_type: string }` -> stores/updates token in `user_device_tokens`.

- [ ] **Step 1: Write failing feature test for resident login & device token registration**

```php
// tests/Feature/Api/AuthApiTest.php
namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_login_with_email_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resident@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['owner_id' => $owner->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'resident@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email'],
                    'flats' => [['id', 'number']],
                ],
            ]);
    }

    public function test_resident_can_register_fcm_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Tenant->value);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/fcm-token', [
            'token' => 'mock-fcm-device-token-xyz',
            'device_type' => 'android',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $user->id,
            'token' => 'mock-fcm-device-token-xyz',
            'device_type' => 'android',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/AuthApiTest.php`  
Expected: FAIL with 404 Route not found.

- [ ] **Step 3: Create migration, model, and controller**
Create migration `create_user_device_tokens_table`:
```php
Schema::create('user_device_tokens', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('token', 255)->unique();
    $table->string('device_type', 20)->default('android'); // 'android', 'ios'
    $table->timestamps();
});
```
Implement `app/Models/UserDeviceToken.php`:
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'device_type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```
Implement `app/Http/Controllers/Api/V1/AuthController.php` supporting both email and phone login, token issuance, logout, me, and FCM token registry using `ApiResponse`.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/AuthApiTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Models/UserDeviceToken.php database/migrations/*user_device_tokens* app/Http/Controllers/Api/V1/AuthController.php routes/api.php tests/Feature/Api/AuthApiTest.php
git commit -m "feat: add resident auth, multi-flat discovery, and fcm token management api"
```

---

### Task 3: Resident Multi-Flat Discovery & Live Financial Dashboard API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Resident/DashboardController.php`
- Create: `app/Http/Resources/Api/V1/ResidentDashboardResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ResidentDashboardApiTest.php`

**Interfaces:**
- `GET /api/v1/resident/flats`: Returns array of flats owned or leased by user.
- `GET /api/v1/resident/dashboard?flat_id={id}`: Returns `{ total_due, advance_held, current_month_charges, arrears, latest_bill, recent_payments, active_notices, my_tickets }`.

- [ ] **Step 1: Write failing test for Resident Dashboard API**

```php
// tests/Feature/Api/ResidentDashboardApiTest.php
namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
    }

    public function test_resident_gets_live_balances_and_summary(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '402',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/dashboard?flat_id=' . $flat->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'flat' => ['id', 'number'],
                    'balances' => [
                        'total_due',
                        'advance_held',
                        'current_month_charges',
                        'arrears',
                    ],
                    'latest_bill',
                    'active_notices',
                    'recent_payments',
                    'my_tickets',
                ],
            ]);
    }

    public function test_resident_forbidden_from_viewing_other_resident_flat(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole(Role::Owner->value);
        $userB = User::factory()->create();
        $userB->assignRole(Role::Owner->value);

        $flatB = Flat::factory()->create();

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/resident/dashboard?flat_id=' . $flatB->id);

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/ResidentDashboardApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement DashboardController utilizing `JournalService` and `BillSummary`**
Ensure policy checks that `$user->owner?->flats->contains('id', $flatId) || $user->tenant?->flat_id === $flatId`. Call `JournalService` for live ledger balances. Return wrapped `ApiResponse::success()`.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/ResidentDashboardApiTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Http/Controllers/Api/V1/Resident/DashboardController.php routes/api.php tests/Feature/Api/ResidentDashboardApiTest.php
git commit -m "feat: add resident dashboard and multi-flat metrics api"
```

---

### Task 4: Resident Monthly Bills & Itemized Breakdown API with Filtering & Pagination

**Files:**
- Create: `app/Http/Controllers/Api/V1/Resident/BillApiController.php`
- Create: `app/Http/Resources/Api/V1/BillResource.php`
- Create: `app/Http/Resources/Api/V1/BillDetailResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ResidentBillsApiTest.php`

**Interfaces:**
- `GET /api/v1/resident/bills?flat_id={id}&status={status}&year={year}&page={page}&per_page={per_page}`: Returns paginated list of bills.
- `GET /api/v1/resident/bills/{bill}`: Detailed breakdown of charge heads.
- `GET /api/v1/resident/bills/{bill}/pdf`: Downloadable PDF.

- [ ] **Step 1: Write failing tests for Bills listing with filters & item breakdown**

```php
// tests/Feature/Api/ResidentBillsApiTest.php
namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentBillsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
    }

    public function test_resident_can_filter_and_paginate_bills(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['owner_id' => $owner->id]);

        ServiceChargeBill::factory()->count(5)->create([
            'flat_id' => $flat->id,
            'status' => 'unpaid',
            'billing_month' => '2026-08',
        ]);
        ServiceChargeBill::factory()->count(2)->create([
            'flat_id' => $flat->id,
            'status' => 'paid',
            'billing_month' => '2026-07',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/v1/resident/bills?flat_id=' . $flat->id . '&status=unpaid&per_page=3'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonCount(3, 'data');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/ResidentBillsApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement `BillApiController` and Eloquent API Resources**
Implement `BillResource` and `BillDetailResource`. Filter by status, year, billing_month. Ensure `paginate(min(request('per_page', 15), 50))` and return via `ApiResponse::paginated()`.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/ResidentBillsApiTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Http/Controllers/Api/V1/Resident/BillApiController.php app/Http/Resources/Api/V1/Bill* routes/api.php tests/Feature/Api/ResidentBillsApiTest.php
git commit -m "feat: add resident bills listing, breakdown, and filtering api"
```

---

### Task 5: Resident Offline Payment Submissions & Receipt Downloads API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Resident/PaymentSubmissionApiController.php`
- Create: `app/Http/Resources/Api/V1/PaymentSubmissionResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ResidentPaymentSubmissionApiTest.php`

**Interfaces:**
- `GET /api/v1/resident/payment-submissions?flat_id={id}&status={status}&page={page}`: Paginated submissions list.
- `POST /api/v1/resident/payment-submissions`: Uploads bKash/Bank TrxID + slip picture.
- `GET /api/v1/resident/payments?flat_id={id}`: Paginated official posted payments with receipt link.

- [ ] **Step 1: Write failing test for payment submission and listing**

```php
// tests/Feature/Api/ResidentPaymentSubmissionApiTest.php
namespace Tests\Feature\Api;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResidentPaymentSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_submit_offline_payment_with_slip(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['owner_id' => $owner->id]);

        $file = UploadedFile::fake()->image('bkash_slip.jpg');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/resident/payment-submissions', [
            'flat_id' => $flat->id,
            'amount' => '3500.00',
            'payment_method' => PaymentMethod::Bkash->value,
            'reference_number' => 'TRX8971239',
            'payment_date' => now()->toDateString(),
            'slip' => $file,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('payment_submissions', [
            'flat_id' => $flat->id,
            'amount' => '3500.00',
            'reference_number' => 'TRX8971239',
            'status' => 'pending',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/ResidentPaymentSubmissionApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement `PaymentSubmissionApiController`**
Validate file uploads, store slip on public disk, create `PaymentSubmission` record, and return `ApiResponse::success()`.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/ResidentPaymentSubmissionApiTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Http/Controllers/Api/V1/Resident/PaymentSubmissionApiController.php app/Http/Resources/Api/V1/PaymentSubmissionResource.php routes/api.php tests/Feature/Api/ResidentPaymentSubmissionApiTest.php
git commit -m "feat: add resident payment submission and receipt api"
```

---

### Task 6: Resident Maintenance Ticketing & Photo Attachments API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Resident/MaintenanceRequestApiController.php`
- Create: `app/Http/Resources/Api/V1/MaintenanceRequestResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ResidentMaintenanceApiTest.php`

**Interfaces:**
- `GET /api/v1/resident/maintenance-requests?flat_id={id}&status={status}&category={category}&page={page}`: Paginated tickets.
- `POST /api/v1/resident/maintenance-requests`: Submit issue ticket (`title`, `category`, `priority`, `description`).
- `GET /api/v1/resident/maintenance-requests/{id}`: Show ticket timeline and staff assignment.

- [ ] **Step 1: Write failing test for creating and filtering maintenance tickets**

```php
// tests/Feature/Api/ResidentMaintenanceApiTest.php
namespace Tests\Feature\Api;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\Role;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_create_ticket(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/resident/maintenance-requests', [
            'flat_id' => $flat->id,
            'title' => 'Water leakage under kitchen sink',
            'description' => 'Continuous dripping from the main pipe valve.',
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::High->value,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('maintenance_requests', [
            'flat_id' => $flat->id,
            'title' => 'Water leakage under kitchen sink',
            'status' => 'open',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/ResidentMaintenanceApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement `MaintenanceRequestApiController` and Resource**
Validate input, scope to user's flat, persist, and return standard JSON envelope.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/ResidentMaintenanceApiTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Http/Controllers/Api/V1/Resident/MaintenanceRequestApiController.php app/Http/Resources/Api/V1/MaintenanceRequestResource.php routes/api.php tests/Feature/Api/ResidentMaintenanceApiTest.php
git commit -m "feat: add resident maintenance requests and ticketing api"
```

---

### Task 7: Digital Notice Board API & Building Announcements

**Files:**
- Create: `app/Http/Controllers/Api/V1/Resident/NoticeApiController.php`
- Create: `app/Http/Resources/Api/V1/NoticeResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ResidentNoticeApiTest.php`

**Interfaces:**
- `GET /api/v1/resident/notices?type={type}&page={page}`: Paginated active notices (pinned notices listed first).
- `GET /api/v1/resident/notices/{id}`: Single notice content.

- [ ] **Step 1: Write failing test for active notice listing (scoped and pinned)**

```php
// tests/Feature/Api/ResidentNoticeApiTest.php
namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentNoticeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_sees_building_notices_with_pinned_priority(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        Notice::factory()->create([
            'building_id' => $building->id,
            'title' => 'Routine notice',
            'is_pinned' => false,
            'published_at' => now()->subDay(),
        ]);
        Notice::factory()->create([
            'building_id' => $building->id,
            'title' => 'Urgent generator repair',
            'is_pinned' => true,
            'published_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/resident/notices');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Urgent generator repair');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Feature/Api/ResidentNoticeApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement `NoticeApiController`**
Filter notices by authenticated user's building(s), apply `scopeActive()`, order by `is_pinned DESC` then `published_at DESC`.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Feature/Api/ResidentNoticeApiTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Http/Controllers/Api/V1/Resident/NoticeApiController.php app/Http/Resources/Api/V1/NoticeResource.php routes/api.php tests/Feature/Api/ResidentNoticeApiTest.php
git commit -m "feat: add digital notice board api for resident announcements"
```

---

### Task 8: FCM Push Notification Service & Web Operator Event Hooks

**Files:**
- Create: `app/Services/Notification/PushNotificationService.php`
- Create: `app/Jobs/SendResidentPushNotificationJob.php`
- Modify: `app/Livewire/GenerateBills.php`
- Modify: `app/Livewire/Billing/PaymentSubmissionList.php`
- Modify: `app/Livewire/Masters/NoticeList.php`
- Modify: `app/Livewire/Admin/MaintenanceRequestList.php`
- Test: `tests/Unit/Services/PushNotificationServiceTest.php`

**Interfaces:**
- Consumes: Target user IDs, title, body, payload data.
- Produces: Dispatches FCM push messages to devices stored in `user_device_tokens`.

- [ ] **Step 1: Write unit test for PushNotificationService and Device Token targeting**

```php
// tests/Unit/Services/PushNotificationServiceTest.php
namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserDeviceToken;
use App\Services\Notification\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_fcm_notification_to_user_devices(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/mock/messages/123'], 200),
        ]);

        $user = User::factory()->create();
        UserDeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-device-token-111',
            'device_type' => 'android',
        ]);

        $service = new PushNotificationService();
        $sentCount = $service->sendToUser($user, 'New Bill Generated', 'Your bill for September is ready', [
            'type' => 'bill',
            'bill_id' => '101',
        ]);

        $this->assertSame(1, $sentCount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --compact tests/Unit/Services/PushNotificationServiceTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement `PushNotificationService` and asynchronous dispatch job**
Implement FCM HTTP v1 dispatch with graceful error handling (deleting expired/stale tokens automatically when FCM returns `UNREGISTERED`). Hook into bill generation, payment approval, notices, and ticket resolution.

- [ ] **Step 4: Run tests and verify they pass**
Run: `php artisan test --compact tests/Unit/Services/PushNotificationServiceTest.php`  
Expected: PASS. Format with Pint: `vendor/bin/pint --format agent`.

- [ ] **Step 5: Commit changes**
```bash
git add app/Services/Notification/ app/Jobs/SendResidentPushNotificationJob.php tests/Unit/Services/PushNotificationServiceTest.php
git commit -m "feat: add fcm push notification service and event triggers"
```

---

### Task 9: Cross-Platform Resident Mobile App (Flutter) Project Blueprint & Contracts

**Files:**
- Create: `mobile/README.md` (Architecture, setup, env config)
- Create: `mobile/lib/core/network/api_client.dart` (Dio client with auth interceptor & unified response parsing)
- Create: `mobile/lib/core/models/api_response.dart` (Dart model for `{ success, message, data, meta }`)
- Create: `mobile/lib/features/dashboard/models/dashboard_state.dart`
- Create: `mobile/lib/features/bills/models/bill_model.dart`

**Interfaces:**
- Produces: Production-ready client structure, networking contracts, state management patterns, and screen templates for Flutter app developers.

- [ ] **Step 1: Write Dart API response envelope model and Dio networking client**
- [ ] **Step 2: Define feature-first folder structure (`core`, `features/auth`, `features/dashboard`, `features/bills`, `features/tickets`, `features/notices`)**
- [ ] **Step 3: Document setup instructions, environment configurations (`API_BASE_URL`), and Firebase setup in `mobile/README.md`**
- [ ] **Step 4: Verify syntax and linting**
- [ ] **Step 5: Commit changes**
```bash
git add mobile/
git commit -m "feat: add resident flutter mobile app project architecture and api contracts"
```

---

## Verification Plan

### Automated Tests
Run the entire test suite including all new API feature tests:
```bash
composer check
```
Or targeted test execution:
```bash
php artisan test --compact --filter=Api
```

### Manual Verification
1. **API Endpoints Test:**
   * Run local server: `php artisan serve`.
   * Send `POST /api/v1/auth/login` using Postman / cURL. Verify bearer token and multi-flat list.
   * Send `GET /api/v1/resident/dashboard?flat_id={id}` with `Authorization: Bearer <token>`. Verify live BDT balances match double-entry ledger.
   * Send `POST /api/v1/resident/payment-submissions` with sample image. Check approval queue on web app (`/billing/submissions`).
2. **Push Notification Verification:**
   * Register mock FCM token via `/api/v1/auth/fcm-token`.
   * Approve payment in Web UI; verify background queue triggers FCM message.
