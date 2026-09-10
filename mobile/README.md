# Resident Mobile App (Flutter) — Utility Accounts System

A cross-platform mobile application (iOS & Android) designed specifically for **Flat Owners** and **Renters (Tenants)** of residential buildings managed by the **Utility Accounts System (UAS)**.

---

## 1. High-Level Architecture & Principles

- **Separation of Concerns:** Management, billing computation, journal entries, and accounting reconciliation are strictly handled via the Laravel Web Back-Office. The mobile application acts as a resident self-service client for notifications, live balance checks, bill views, offline payment submissions, and maintenance ticketing.
- **Double-Entry Financial Integrity:** In accordance with the system invariants, balances are **never cached** statically. All balances (`total_due`, `advance_held`, `current_month_charges`, `arrears`) are computed dynamically by the backend `JournalService` and `BillSummary` on each request.
- **Unified API Response Envelope:** All network calls conform to the backend envelope:
  ```json
  {
    "success": true,
    "message": "...",
    "data": { ... },
    "meta": { "current_page": 1, "per_page": 15, "total": 45 },
    "links": { "next": "...", "prev": null }
  }
  ```
- **State Management:** BLoC (Business Logic Component) pattern using `flutter_bloc` and `equatable`.
- **Networking:** `dio` HTTP client configured with automated Sanctum Bearer token injection and session expiration hooks.

---

## 2. Directory Structure

```
mobile/
├── .env.example
├── pubspec.yaml
├── README.md
└── lib/
    ├── main.dart
    ├── core/
    │   ├── constants/
    │   │   └── api_endpoints.dart
    │   ├── models/
    │   │   └── api_response.dart
    │   ├── network/
    │   │   └── api_client.dart
    │   ├── storage/
    │   │   └── secure_storage_service.dart
    │   └── theme/
    │       └── app_theme.dart
    └── features/
        ├── auth/
        │   ├── bloc/
        │   │   ├── auth_bloc.dart
        │   │   ├── auth_event.dart
        │   │   └── auth_state.dart
        │   ├── models/
        │   │   └── user_model.dart
        │   └── screens/
        │       ├── login_screen.dart
        │       └── profile_screen.dart
        ├── dashboard/
        │   ├── bloc/
        │   │   └── dashboard_bloc.dart
        │   ├── models/
        │   │   └── dashboard_state.dart
        │   └── screens/
        │       └── resident_dashboard_screen.dart
        ├── bills/
        │   ├── bloc/
        │   │   └── bills_bloc.dart
        │   ├── models/
        │   │   └── bill_model.dart
        │   └── screens/
        │       ├── bills_list_screen.dart
        │       └── bill_detail_screen.dart
        ├── payments/
        │   ├── bloc/
        │   │   └── payment_submission_bloc.dart
        │   ├── models/
        │   │   └── payment_model.dart
        │   └── screens/
        │       ├── submit_payment_screen.dart
        │       └── payment_history_screen.dart
        ├── maintenance/
        │   ├── bloc/
        │   │   └── maintenance_bloc.dart
        │   ├── models/
        │   │   └── ticket_model.dart
        │   └── screens/
        │       ├── create_ticket_screen.dart
        │       └── tickets_list_screen.dart
        └── notices/
            ├── bloc/
            │   └── notice_bloc.dart
            ├── models/
            │   └── notice_model.dart
            └── screens/
                ├── notice_board_screen.dart
                └── notice_detail_screen.dart
```

---

## 3. Environment Configuration

Create a `.env` file in the `mobile/` root directory:

```properties
# Local development against backend
API_BASE_URL=http://10.0.2.2:8000/api/v1  # Android Emulator
# API_BASE_URL=http://localhost:8000/api/v1  # iOS Simulator
# API_BASE_URL=https://uas.example.com/api/v1 # Staging / Production

FCM_SENDER_ID=your_fcm_sender_id
```

---

## 4. API Integration Endpoints

All endpoints require `Authorization: Bearer <sanctum_token>` unless marked public:

| Feature | Method | Endpoint | Description |
| :--- | :--- | :--- | :--- |
| **Auth** | `POST` | `/api/v1/auth/login` | Public. Email/Phone + password. Returns token & user flats. |
| **Auth** | `POST` | `/api/v1/auth/logout` | Revokes current access token. |
| **Auth** | `GET` | `/api/v1/auth/me` | Current resident profile and assigned flats. |
| **Auth** | `POST` | `/api/v1/auth/fcm-token` | Registers Firebase device token (`token`, `device_type`). |
| **Dashboard**| `GET` | `/api/v1/resident/flats` | List of flats owned or rented by resident. |
| **Dashboard**| `GET` | `/api/v1/resident/dashboard?flat_id={id}` | Live ledger balance, latest bill, notices, recent receipts. |
| **Bills** | `GET` | `/api/v1/resident/bills?flat_id={id}&status={status}&year={year}` | Paginated bills with status filter (`unpaid`, `paid`). |
| **Bills** | `GET` | `/api/v1/resident/bills/{id}` | Itemized breakdown of charge heads and rates. |
| **Bills** | `GET` | `/api/v1/resident/bills/{id}/pdf` | Official PDF bill download. |
| **Payments** | `POST` | `/api/v1/resident/payment-submissions` | Multipart upload: `flat_id`, `amount`, `payment_method`, `reference_number`, `slip`. |
| **Payments** | `GET` | `/api/v1/resident/payment-submissions?flat_id={id}` | Verification queue status (`pending`, `verified`, `rejected`). |
| **Payments** | `GET` | `/api/v1/resident/payments?flat_id={id}` | Approved posted payments with money receipt link. |
| **Tickets** | `GET` | `/api/v1/resident/maintenance-requests?flat_id={id}` | Maintenance tickets with status and staff assignment. |
| **Tickets** | `POST` | `/api/v1/resident/maintenance-requests` | Create ticket (`category`, `title`, `description`, `priority`). |
| **Notices** | `GET` | `/api/v1/resident/notices` | Building announcements, prioritized by pinned status. |

---

## 5. Firebase Cloud Messaging (Push Notifications)

The backend dispatches notifications asynchronously via `SendResidentPushNotificationJob` triggered by web operator actions:

1. **Monthly Bill Generated:** Sent when monthly bills are generated. Deep links to `/bills/{id}`.
2. **Payment Submission Approved / Rejected:** Sent when operator verifies offline bank/bKash slip.
3. **New Notice Published:** Sent to all residents of the building when a notice is posted.
4. **Maintenance Ticket Updated:** Sent when operator assigns staff or resolves an issue.

### Client Integration:
```dart
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
  // Foreground notification display using flutter_local_notifications
});

FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
  final payloadType = message.data['type'];
  if (payloadType == 'new_bill') {
    // Navigate to Bills tab
  } else if (payloadType == 'payment_submission') {
    // Navigate to Payments history
  }
});
```

---

## 6. Offline Payment Flow & Verification

1. Resident receives monthly bill.
2. Resident pays via external channel (bKash merchant, Nagad, or Bank deposit).
3. Resident opens mobile app, selects **Submit Payment**, attaches receipt screenshot/photo, enters TrxID and amount.
4. Web Management Operator reviews submission at `/billing/submissions`:
   - If accepted: Operator approves, which automatically posts a double-entry payment journal, clears dues, and pushes an FCM notification to resident.
   - If rejected: Operator enters a rejection reason, triggering an immediate FCM push alert to resident.

---

## 7. Getting Started

### Prerequisites:
- Flutter SDK `>=3.24.0`
- Dart SDK `>=3.5.0`
- Android Studio / Xcode configured

### Setup & Run:
```bash
cd mobile
flutter pub get
flutter run
```

### Run Tests:
```bash
flutter test
```
