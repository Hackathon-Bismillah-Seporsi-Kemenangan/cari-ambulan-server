# CariAmbulan Server

Backend for **CariAmbulan**, an ambulance dispatch service. It provides:

- a **JSON API** (`/api/v1`) used by the Flutter driver app ([`cariAmbulanc`](../cariAmbulanc)),
- a **web dashboard** (Blade) for administrators, hospitals, drivers and users,
- the **dispatch engine** that offers each emergency to the nearest available driver.

|                 |                                         |
| --------------- | --------------------------------------- |
| Framework       | Laravel 13, PHP 8.3+ (developed on 8.5) |
| Database        | PostgreSQL                              |
| API auth        | Laravel Sanctum (bearer tokens)         |
| Frontend assets | Vite + Tailwind, installed with Bun     |
| Tests           | Pest                                    |
| Code style      | Laravel Pint                            |

---

## Table of contents

1. [Quick start](#quick-start)
2. [Demo accounts](#demo-accounts)
3. [Running with the mobile app](#running-with-the-mobile-app)
4. [Trying a full emergency flow](#trying-a-full-emergency-flow)
5. [How dispatch works](#how-dispatch-works)
6. [API reference](#api-reference)
7. [Project structure](#project-structure)
8. [Testing and code style](#testing-and-code-style)
9. [Troubleshooting](#troubleshooting)
10. [Known limitations](#known-limitations)
11. [License](#license)

---

## Quick start

### Requirements

- PHP 8.3+ with the `pdo_pgsql` extension
- Composer 2
- PostgreSQL 14+
- Bun (only needed for the web dashboard's CSS/JS)

### 1. Install and configure

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cariambulan
DB_USERNAME=your_user
DB_PASSWORD=your_password

APP_URL=http://localhost:8080
APP_LOCAL_TIMEZONE=Asia/Jakarta   # used for "trips today" counts
ADMIN_PASSWORD=                   # required outside local, see below
```

> **Never commit `.env`.** It's git-ignored and holds your database password and `APP_KEY`. Share real values with the team privately, not in the repo, issues or chat logs.

Create the database if it doesn't exist:

```bash
createdb cariambulan
```

### 2. Migrate and seed

```bash
php artisan migrate --seed
```

When `APP_ENV=local`, seeding also creates **demo hospitals, a driver and a patient** (see [Demo accounts](#demo-accounts)). To start over from scratch:

```bash
php artisan migrate:fresh --seed
```

### 3. Build dashboard assets

```bash
npm install
npm run build      # or `npm run dev` while editing views
```

### 4. Run the server

```bash
php artisan serve --host=0.0.0.0 --port=8080
```

> **Why `0.0.0.0` and port 8080?** `0.0.0.0` lets the Android emulator and real phones on your network reach the server. The mobile app expects port **8080** by default. If you use another port, pass it to the app (see below).

### 5. Run the scheduler (recommended)

Unanswered offers are re-dispatched by a job that runs every minute. In a second terminal:

```bash
php artisan schedule:work
```

Without it, re-dispatch still happens whenever a driver's app polls for offers, so the demo works either way. In production, add the usual cron entry: `* * * * * php artisan schedule:run`.

---

## Demo accounts

| Role           | Email                   | Password     | Notes                                                                                             |
| -------------- | ----------------------- | ------------ | ------------------------------------------------------------------------------------------------- |
| Administrator  | `admin@cariambulan.id`  | `Admin@123`  | Web dashboard                                                                                     |
| Driver         | `driver@cariambulan.id` | `Driver@123` | Budi Santoso, ambulance **B 1199 SGM**, Ambulans ALS. Log in to the mobile app with this account. |
| Patient (user) | `pasien@cariambulan.id` | `Pasien@123` | Siti Rahmawati, blood type O, allergic to penicillin                                              |

Demo hospitals in central Jakarta: **RS Medika**, **RS Sehat Sentosa** and **RS Bunda Kasih**, each with coordinates and an ambulance entrance.

> Demo data (driver, patient, hospitals) is only seeded in the `local` environment.
>
> The **admin account** is seeded everywhere. Locally it falls back to `Admin@123`. On any other environment (staging, production) seeding **fails unless `ADMIN_PASSWORD` is set in `.env`**, so no server runs with the public demo password.

Roles: `administrator`, `user`, `driver`, `hospital_admin`.

---

## Running with the mobile app

1. Start this server on `0.0.0.0:8080` (see above).
2. Run the Flutter app. Instructions are in the app's README. The app picks the URL automatically:

    | Where the app runs      | URL the app uses                                                                              |
    | ----------------------- | --------------------------------------------------------------------------------------------- |
    | Android emulator        | `http://10.0.2.2:8080` (the emulator's alias for your computer)                               |
    | iOS simulator / desktop | `http://127.0.0.1:8080`                                                                       |
    | Real phone              | Pass your computer's LAN IP: `flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8080` |

3. Log in as `driver@cariambulan.id` / `Driver@123` and switch **Online**.

---

## Trying a full emergency flow

The patient app doesn't exist yet, so this command plays the patient and creates a call near the driver:

```bash
php artisan demo:emergency
# or for a specific driver:
php artisan demo:emergency --driver=driver@cariambulan.id
```

It creates a "Henti Jantung" emergency about 1.5 km from the driver's last location and offers it to the nearest online driver. In the app:

1. The **Panggilan Darurat** screen appears with a 30-second countdown.
2. **Terima** → **Menuju pasien** (route to the patient).
3. **Saya Sudah Tiba** → **Mulai ke Rumah Sakit**, which assigns the nearest hospital.
4. **Serahkan Pasien ke IGD** → the order is completed and the driver is back online.

The driver must be **online** and have sent a location in the last 10 minutes, or the command will say no driver is available.

### Trying a call from the reporter

While the driver has an accepted order (Menuju pasien or Menuju rumah sakit):

```bash
php artisan demo:call
```

The **Panggilan masuk · order aktif** panel appears in the app within about 3 seconds. **Jawab** shows the in-call bar, **Tolak** declines, and no answer within 30 s marks the call `missed`.

> Only the call **state** is real so far (ringing, answered, declined, missed, ended). Voice audio needs a calling service (Agora, Twilio or WebRTC), which hasn't been chosen yet.

Check the result in the database:

```bash
php artisan tinker --execute 'echo App\Models\EmergencyRequest::latest("request_at")->first()->status;'
```

---

## How dispatch works

```
patient creates request ──► status: searching_driver
        │
        ▼
offer to nearest dispatchable driver (DispatchLog: offered, 30 s)
        │
   ┌────┴─────────────┬──────────────────┐
 accept             decline           no answer in 30 s
   │                  │                  │
   ▼                  └──► offer to next driver (declined/timeout drivers are skipped)
en_route_to_pickup
   │  "Saya Sudah Tiba"
   ▼
arrive_at_pickup
   │  "Mulai ke Rumah Sakit" (nearest hospital is assigned)
   ▼
en_route_to_hospital
   │  "Serahkan Pasien ke IGD"
   ▼
completed  (driver goes back online)
```

A driver is **dispatchable** when all of these are true:

- availability is `online`,
- they sent a location in the last **10 minutes** (`DriverProfile::LOCATION_FRESH_MINUTES`),
- they have an ambulance,
- they have no pending offer.

The key rules:

- **One offer at a time.** Each request has at most one pending offer, and each driver gets at most one pending offer. Rows are locked (`lockForUpdate`) so two simultaneous requests can't go to the same driver.
- **Offers expire after 30 s** (`DispatchLog::OFFER_SECONDS`). The API sends `seconds_remaining` so the app's countdown follows the server clock.
- **Re-dispatch.** `EmergencyRequest::redispatchWaiting()` times out stale offers and re-offers waiting requests. It runs on every driver poll and every minute via the scheduler (`routes/console.php`).
- **ETA** is a rough estimate: distance at 25 km/h city speed.

Status values:

| Enum                      | Values                                                                                                                            |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `EmergencyStatus`         | `searching_driver`, `driver_assigned`, `en_route_to_pickup`, `arrive_at_pickup`, `en_route_to_hospital`, `completed`, `cancelled` |
| `DriverAvailability`      | `offline`, `online`, `busy`                                                                                                       |
| `DispatchStatus`          | `offered`, `accepted`, `declined`, `timeout`                                                                                      |
| `CallStatus` (trip_calls) | `ringing`, `answered`, `declined`, `missed`, `ended`                                                                              |

---

## API reference

Base URL: `/api/v1`. Every endpoint except login needs the header `Authorization: Bearer <token>` and `Accept: application/json`.

### Auth

| Method | Path           | Description                                             |
| ------ | -------------- | ------------------------------------------------------- |
| POST   | `/auth/login`  | Body `{email, password, device_name}` → `{token, user}` |
| GET    | `/auth/me`     | Current user with roles                                 |
| POST   | `/auth/logout` | Revokes the current token                               |

### General

| Method | Path                                       | Role   | Description                                                                                                                                                                |
| ------ | ------------------------------------------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET    | `/hospitals/nearest?latitude=&longitude=`  | any    | Nearest hospital with distance                                                                                                                                             |
| GET    | `/ambulances/nearest?latitude=&longitude=` | `user` | Nearest available ambulance: position (~100 m), `distance_km`, `eta_minutes`; `{data:null}` if none                                                                        |
| POST   | `/emergency-requests`                      | `user` | Create an emergency (starts dispatch). `emergency_type`: Kecelakaan, Henti Jantung, Kehamilan, Sesak Nafas or Lainnya. 409 if the user already has an open request         |
| GET    | `/emergency-requests/current`              | `user` | The user's request that is still searching or under way, or `{data:null}` (app resumes it after a restart)                                                                 |
| GET    | `/emergency-requests/{id}`                 | `user` | Search progress of the user's own request: `status`, `drivers_contacted`, `nearby_ambulances` (free, within 10 km, ~100 m precision). Polling it also moves dispatch along |
| POST   | `/emergency-requests/{id}/cancel`          | `user` | Cancel while still `searching_driver` (409 after a driver accepts); stops the driver's ringing offer                                                                       |
| POST   | `/emergency-requests/{id}/calls`           | `user` | Reporter calls the driver of their active order. Rings for 30 s.                                                                                                           |

### Driver (`role:driver`)

| Method | Path                                      | Description                                                                                                                |
| ------ | ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| GET    | `/driver/profile`                         | Profile, ambulance and `today.completed_trips`                                                                             |
| PUT    | `/driver/availability`                    | Body `{status: online\|offline, latitude, longitude}` (coordinates required when going online). Returns 409 during a trip. |
| POST   | `/driver/location`                        | Body `{latitude, longitude}`. Logged to the trip when one is active.                                                       |
| GET    | `/driver/offers/current`                  | The pending offer (or `null`). Polled every 3 s by the app.                                                                |
| GET    | `/driver/trip`                            | The active trip (or `null`). Used to resume after an app restart.                                                          |
| POST   | `/driver/requests/{id}/accept`            | Accept the offer                                                                                                           |
| POST   | `/driver/requests/{id}/decline`           | Decline the offer                                                                                                          |
| POST   | `/driver/requests/{id}/arrive`            | Arrived at the pickup                                                                                                      |
| POST   | `/driver/requests/{id}/start-to-hospital` | Patient on board. Assigns the nearest hospital.                                                                            |
| POST   | `/driver/requests/{id}/complete`          | Handover done. The driver goes back online and any live call ends.                                                         |
| GET    | `/driver/calls/current`                   | The reporter's call that is ringing or answered on the active trip (or `null`). Polled every 3 s on trip screens.          |
| POST   | `/driver/calls/{id}/answer`               | Answer a ringing call. Returns 409 if it already stopped ringing.                                                          |
| POST   | `/driver/calls/{id}/decline`              | Decline a ringing call                                                                                                     |
| POST   | `/driver/calls/{id}/end`                  | Hang up an answered call                                                                                                   |

Error responses:

| Code | Meaning                                                                        |
| ---- | ------------------------------------------------------------------------------ |
| 401  | Missing or expired token. The app logs out.                                    |
| 403  | Wrong role, or the user has no driver profile                                  |
| 404  | The trip isn't assigned to this driver                                         |
| 409  | Wrong step (e.g. "complete" before "arrive"), or an offer that already expired |
| 422  | Validation error. `errors.{field}[0]` holds a readable message.                |

Quick test with curl:

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"driver@cariambulan.id","password":"Driver@123","device_name":"curl"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s http://localhost:8080/api/v1/driver/profile \
  -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN"
```

### Web dashboard

Log in at `http://localhost:8080/login`. `/dashboard` redirects to the page for your role: `/dashboard/admin`, `/dashboard/hospital`, `/dashboard/driver` or `/dashboard/user`.

---

## Project structure

```
app/
├── Enums/                      EmergencyStatus, DriverAvailability, DispatchStatus
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/             AuthController, HospitalController, EmergencyRequestController
│   │   │   └── Driver/         Profile, Availability, Location, Offer, Trip, Call controllers
│   │   ├── Dashboard/          Role dashboards (Blade)
│   │   └── Admin/              User & role management
│   ├── Requests/Api/V1/        Form request validation
│   └── Resources/V1/           JSON shapes returned by the API
├── Models/                     User, DriverProfile, Ambulance, Hospital,
│   │                           EmergencyRequest (dispatch logic), DispatchLog,
│   │                           TripLocationLog, TripCall, UserMedicalProfile, Role
│   └── Concerns/CalculatesDistance.php   Haversine distance
database/
├── migrations/
├── factories/
└── seeders/                    RoleSeeder, AdminUserSeeder, DemoDataSeeder
routes/
├── api.php                     /api/v1 routes
├── web.php                     Dashboard routes
└── console.php                 Scheduler + `demo:emergency` / `demo:call` commands
tests/Feature/Http/Controllers/ Tests mirror the controller paths
```

---

## Testing and code style

```bash
php artisan test --compact          # all tests
php artisan test --filter=Trip      # a subset
vendor/bin/pint --dirty             # format changed files
```

Tests run on an in-memory SQLite database (see `phpunit.xml`), so they never touch your PostgreSQL data. PHP needs the `pdo_sqlite` extension.

Conventions for contributors (also in `AGENTS.md`):

- New endpoints go under `/api/v1`, return **API Resources** and validate with **Form Requests**.
- Create files with `php artisan make:...`.
- Every change needs a Pest test. Test files mirror the class path, and tests use `it('...')`.
- Run Pint before committing.
- Ask before adding a new Composer/NPM dependency.

---

## Troubleshooting

| Problem                                        | Fix                                                                                                                                                                             |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Address already in use` on port 8000/8080     | Another project is using the port. Use another one (`--port=8090`) and pass `API_BASE_URL` to the app.                                                                          |
| App shows "Tidak dapat terhubung ke server"    | Server must run with `--host=0.0.0.0`. On a real phone, use your computer's LAN IP and make sure both are on the same Wi-Fi. Check your firewall.                               |
| `demo:emergency` says no driver available      | The driver must be **online** in the app with a recent location. Toggle Online and wait a few seconds.                                                                          |
| Offer never appears in the app                 | The driver may have already declined or timed out on that request; each request is offered to a driver once. Create a new one with `demo:emergency`.                            |
| `could not find driver (pgsql)`                | Install/enable the PHP `pdo_pgsql` extension.                                                                                                                                   |
| `Vite manifest not found` on dashboard         | Run `npm run build` (or `npm run dev`).                                                                                                                                         |
| Login works on web but API returns 401         | Send `Accept: application/json` and `Authorization: Bearer <token>`. Tokens are deleted on logout.                                                                              |
| App feels slow / requests take several seconds | `php artisan serve` handles one request at a time, and the app polls every 3 s. Start it with workers: `PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8080`. |
| "Trips today" count looks wrong                | Check `APP_LOCAL_TIMEZONE` in `.env` (default `Asia/Jakarta`).                                                                                                                  |
| Old test data is in the way                    | `php artisan migrate:fresh --seed` (deletes **all** data).                                                                                                                      |

---

## Known limitations

- **Polling, not push.** Drivers only receive calls while the app is open (polled every 3 s). Push notifications (FCM or Laravel Reverb) are needed for production.
- **No re-offer after decline.** A driver who declined or let an offer time out is never offered the same request again. If no other driver is available, the request waits.
- **"Hospital notified"** currently means the request shows up on the hospital dashboard. There is no real-time alert yet.
- **No driver rating** is stored yet. The app shows "–".
- **Hospital admins** are linked to hospitals through driver profiles. This needs a proper `hospital_id` on the user.
- **Voice calls have no audio yet.** Calls are tracked end to end, but the audio service isn't connected.
- The patient (user) side of the app can send an emergency and follow the search, but tracking the ambulance after a driver accepts isn't built yet. `php artisan demo:emergency` still works for testing the driver side alone.

---

## License

This project is licensed under the [VIEW ONLY LICENSE](LICENSE).

