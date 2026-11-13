# Laporan Audit Keamanan & Potensi Bug — `CariAmbulan-server`

- **Tanggal Audit**: 11 November 2026
- **Target Repositori**: `d:\Proj\PHP\CariAmbulan-server`
- **Ref Sumber**: `bb231ccb150000070b8e4c2688d8fee7d76fdad0 (dirty)` *(termasuk perubahan lokal pada dashboard RS & test terkait yang belum di-commit)*
- **Kebijakan Eksekusi**: **Read-Only Static Source Audit** (`sandboxed-source-and-local-only`) — Audit dilakukan murni melalui pembacaan kode sumber, konfigurasi Docker/CI, migrasi PostgreSQL, Blade/JS frontend, dan kode inti framework (`vendor/`) tanpa mengeksekusi aplikasi, kontainer, ataupun payload terhadap layanan aktif.

---

## 1. Ringkasan Eksekutif

Audit menyeluruh terhadap **14 unit cakupan** (**196 berkas kode sumber unik** di luar `vendor/`) menghasilkan total **66 catatan teraudit**:

| Kategori Verifikasi (`findings.json`) | Jumlah | Keterangan |
|---|---:|---|
| `confirmed` (Terbukti Runtime) | **0** | Eksekusi kode/tes target dinonaktifkan pada host Windows non-sandboxed |
| `needs_validation` (Terverifikasi di Kode Sumber) | **56** | Akar masalah & alur *source-to-sink* terverifikasi langsung pada kode; menunggu uji sandbox lokal/staging |
| `rejected` (Ditolak / False Positive) | **10** | Terbantahkan oleh kontrol framework/validasi/migrasi, dead code, atau cakupan self-principal |

### Distribusi Estimasi Risiko Triage (`56 needs_validation`)

> **Catatan Metodologi**: Tingkat risiko (*Critical / High / Medium / Low*) di bawah adalah **estimasi prioritas perbaikan berbasis analisis statis** (*triage priority*), bukan skor keparahan runtime yang telah dieksploitasi.

| Estimasi Triage | Jumlah | Fokus Utama |
|---|---:|---|
| **Critical (Est.)** | **6** | Bypass verifikasi JWT webhook LiveKit, DOM XSS realtime di dashboard RS, channel WebSocket publik membocorkan PII & sinyal WebRTC, seeding kredensial demo & fallback rahasia Compose, serta race transisi status trip/driver tanpa lock |
| **High (Est.)** | **22** | Fallback `driverProfiles` IDOR dashboard RS, bypass `share_on_sos=false` pada data medis pasien, pembajakan FCM `device_tokens`, pendaftaran RS publik tanpa moderasi/throttle, token admin Sanctum tanpa kedaluwarsa, pemanggilan LiveKit lintas user tanpa otorisasi, serta paparan plaintext di `/login` & port Compose |
| **Medium (Est.)** | **22** | Potensi HTTP 500 PostgreSQL akibat panjang `max:` melebihi `varchar(N)` / parameter non-UUID, error `TypeError` & `NOT NULL` pada profil medis, DOM XSS Nominatim, CSRF meta kosong di layout dashboard, IDempotency TOCTOU webhook, serta endpoint tanpa rate-limit |
| **Low (Est.)** | **6** | `Driver\TripController::index` membaca `user->name` (selalu `null`), data `health_facilities` pada `/register` tidak tersimpan, filter UI/badge `critical` mati pada Blade/JS, dan celah narrow TOCTOU pada `TripCallController`/`CallSignalingController` |

### 6 Prioritas Perbaikan Segera (Critical Triage)

| # | Domain | Fingerprint | Masalah Inti & Lokasi Utama |
|---:|---|---|---|
| 1 | Client DOM & Blade | `client-dom-xss-echo-hospital-queue-innerhtml` | **Stored/Broadcast DOM-Based XSS in Hospital Realtime Queue via Unescaped Echo Payload Interpolation** — [`app/Http/Requests/Api/V1/RegisterRequest.php:28`](../app/Http/Requests/Api/V1/RegisterRequest.php#L28) → [`resources/js/app.js:224`](../resources/js/app.js#L224) |
| 2 | Dispatch & Fleet Concurrency | `driver-trip-unlocked-transitions-double-booking-and-call-state-leak` | **Unlocked state transitions and missing driver lock in Driver\TripController allow cancelled-trip resurrection, driver double-booking, audit corruption, and stale Redis call leaks** — [`routes/api.php:131`](../routes/api.php#L131) → [`app/Http/Controllers/Api/V1/Driver/TripController.php:232`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L232) |
| 3 | Infra & Deployment | `infra-hardcoded-fallback-secrets-and-reverb-keys` | **Hardcoded fallback secrets in Compose and shared Reverb credentials across Compose, Reverb config, and Vite Echo client** — [`compose.yaml:21`](../compose.yaml#L21) → [`vendor/laravel/reverb/src/Protocols/Pusher/Http/Controllers/Controller.php:115`](../vendor/laravel/reverb/src/Protocols/Pusher/Http/Controllers/Controller.php#L115) |
| 4 | Infra & Deployment | `infra-unconditional-demo-data-seeding` | **Unconditional SEED_DEMO_DATA=true in compose.yaml seeds predictable demo accounts in non-local deployments** — [`compose.yaml:64`](../compose.yaml#L64) → [`database/seeders/DemoDataSeeder.php:25`](../database/seeders/DemoDataSeeder.php#L25) |
| 5 | LiveKit & Webhooks | `livekit-participant-jwt-forges-webhooks` | **Participant Room JWT Forges Arbitrary LiveKit Webhooks Due to Optional sha256 Claim and Shared Signing Secret** — [`routes/api.php:97`](../routes/api.php#L97) → [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:123`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L123) |
| 6 | WebSockets & Broadcast Channels | `public-reverb-channels-expose-patient-pii-and-webrtc-signaling` | **Public Unauthenticated Broadcast Channels Expose All Patient PII, Medical Notes, Ambulance Plates, and WebRTC SDP/ICE Signaling** — [`app/Models/EmergencyRequest.php:59`](../app/Models/EmergencyRequest.php#L59) → [`app/Events/EmergencyRequestStatusUpdated.php:52`](../app/Events/EmergencyRequestStatusUpdated.php#L52) |

---

## 2. Daftar Ringkas Temuan Prioritas High (22 Item)

| # | Domain | Fingerprint | Judul Temuan | Titik Masuk / Sink |
|---:|---|---|---|---|
| 1 | Admin RBAC & Schema | `admin-roles-core-role-deletion-and-rename-lockout` | RoleManagementController allows deleting or renaming core system roles, causing admin lockout and RBAC breakage | [`app/Http/Middleware/RoleMiddleware.php:27`](../app/Http/Middleware/RoleMiddleware.php#L27) |
| 2 | Admin RBAC & Schema | `admin-users-missing-token-and-session-revocation-and-fk-crash` | User role change/deletion fails to revoke Sanctum tokens and sessions, and destroy() crashes on non-cascading foreign keys without self-deletion guard | [`database/migrations/2026_11_02_100001_create_role_user_table.php:16`](../database/migrations/2026_11_02_100001_create_role_user_table.php#L16) |
| 3 | Mobile API Auth & Push | `auth-login-admin-wildcard-tokens-missing-role-guards-and-case-sensitive-email` | AuthController::login mints never-expiring wildcard tokens for admin/hospital roles, shared API routes and /calls lack role/relationship guards, and Postgres email queries are case-sensitive | [`routes/api.php:97`](../routes/api.php#L97) |
| 4 | Call Lifecycle & Signaling | `call-start-orphan-ringing-lockout-and-room-failure` | Orphan Ringing Call Permanently Locks Both Participants When Token Generation Fails After DB Commit, Plus Ignored Room Failures and Missing Queue Worker | [`app/Services/Call/LiveKitTokenService.php:154`](../app/Services/Call/LiveKitTokenService.php#L154) |
| 5 | Client DOM & Blade | `client-blade-js-attr-xss-users-show` | Stored XSS in Admin User Delete Confirmation via Blade Interpolation Inside onsubmit JS String | [`resources/views/admin/users/show.blade.php:122`](../resources/views/admin/users/show.blade.php#L122) |
| 6 | Dispatch & Fleet Concurrency | `close-abandoned-unscheduled` | EmergencyRequest::closeAbandoned() is implemented for 2-hour abandoned trips but never scheduled in routes/console.php | [`routes/console.php:24`](../routes/console.php#L24) |
| 7 | Mobile API Auth & Push | `device-token-hijacking-and-non-atomic-upsert` | Cross-user FCM device token hijacking and non-atomic upsert in DeviceTokenController::store | [`app/Http/Controllers/Api/V1/DeviceTokenController.php:43`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L43) |
| 8 | Medical Privacy & Resources | `emergency-resource-ignores-share-on-sos-and-leaks-to-offers` | EmergencyRequestResource ignores share_on_sos=false and leaks full medical profile to unaccepted offer recipients | [`app/Http/Resources/V1/EmergencyRequestResource.php:23`](../app/Http/Resources/V1/EmergencyRequestResource.php#L23) |
| 9 | Dispatch & Fleet Concurrency | `emergency-store-toctou-duplicate-open-sos` | TOCTOU race in EmergencyRequestController::store permits multiple concurrent open SOS requests per user | [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:54`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L54) |
| 10 | Mobile API Auth & Push | `fcm-service-unconfigured-simulation-and-v1-bugs` | FcmService simulates 'sent' in production when unconfigured, drops v1 notification payload, sends unauthenticated requests on partial config, and mis-invalidates tokens | [`app/Services/Notification/FcmService.php:301`](../app/Services/Notification/FcmService.php#L301) |
| 11 | Hospital Dashboard | `hospital-dashboard-driver-profile-fallback-idor` | driverProfiles fallback in HospitalDashboardController::index and updateRequestStatus grants tenant access without hospital admin ownership | [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:323`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L323) |
| 12 | Hospital Dashboard | `hospital-dashboard-update-status-race-and-invalid-transitions` | updateRequestStatus() lacks lockForUpdate(), state transition checks, and TripCall/DispatchLog cleanup when resetting or closing requests | [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:404`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L404) |
| 13 | Infra & Deployment | `infra-deploy-entrypoint-missing-prod-env-debug-guards` | CI/CD deploy workflow and entrypoint fail to enforce APP_ENV=production and APP_DEBUG=false | [`database/seeders/AdminUserSeeder.php:19`](../database/seeders/AdminUserSeeder.php#L19) |
| 14 | Infra & Deployment | `infra-missing-queue-worker-unprocessed-call-jobs` | No queue worker service in compose.yaml or deploy.yml while QUEUE_CONNECTION=database leaves call jobs unprocessed | [`compose.yaml:57`](../compose.yaml#L57) |
| 15 | Infra & Deployment | `infra-plaintext-ports-exposed-bypassing-caddy` | Unencrypted PostgreSQL (5433), HTTP Nginx (8080), and Reverb WS/HTTP (8081) exposed on 0.0.0.0 bypassing Caddy TLS | [`docker/nginx.conf:4`](../docker/nginx.conf#L4) |
| 16 | Infra & Deployment | `infra-public-login-hardcoded-demo-credentials` | Public login page unconditionally renders plaintext administrator and hospital passwords with one-click autofill | [`resources/views/auth/login.blade.php:51`](../resources/views/auth/login.blade.php#L51) |
| 17 | Infra & Deployment | `infra-wildcard-trust-proxies-exposed-http-spoofing` | trustProxies(at: '*') with directly exposed :8080 enables X-Forwarded-For rate-limit bypass and X-Forwarded-Proto/Host spoofing | [`app/Http/Controllers/Auth/LoginController.php:34`](../app/Http/Controllers/Auth/LoginController.php#L34) |
| 18 | LiveKit & Webhooks | `livekit-webhook-unguarded-participant-identity` | Unguarded LiveKit Participant Identity Causes PostgreSQL 500 FK/UUID Crash and Arbitrary User Injection into call_participants | [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:111`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L111) |
| 19 | Call Lifecycle & Signaling | `tripcall-bulk-updates-leave-stale-redis-state-across-trips` | Bulk TripCall Updates Bypass Eloquent Model Events, Leaving 2-Hour Stale Redis Keys Surfaced Across Subsequent Trips | [`app/Http/Controllers/Api/V1/Driver/CallController.php:42`](../app/Http/Controllers/Api/V1/Driver/CallController.php#L42) |
| 20 | Dispatch & Fleet Concurrency | `unlocked-widen-radius-stale-redispatch-and-discarded-driver-lock` | Unlocked widenRadius(), stale buffered model overwrite in redispatchWaiting(), and discarded DriverProfile lock in offerToNextDriver() | [`app/Models/EmergencyRequest.php:280`](../app/Models/EmergencyRequest.php#L280) |
| 21 | Call Lifecycle & Signaling | `unrestricted-call-initiation-pii-and-dos` | Unrestricted User Calling Enables Persistent Incoming-Call DoS and User Name/Phone Enumeration | [`app/Http/Resources/CallResource.php:39`](../app/Http/Resources/CallResource.php#L39) |
| 22 | Web Auth & Registration | `web-register-open-hospital-admin-poisoning` | Unthrottled open self-registration immediately grants hospital_admin role and publishes unverified hospitals to emergency dispatch | [`app/Models/Hospital.php:56`](../app/Models/Hospital.php#L56) |

---

## 3. Detail Lengkap Potensi Bug & Kerentanan (`needs_validation` — 56 Item)

Berikut seluruh **56 temuan** dikelompokkan berdasarkan subsistem untuk memudahkan tim melakukan perbaikan terarah per modul.

### 3.1 LiveKit & Webhooks (4 Temuan)

#### [Critical (Est.)] `livekit-participant-jwt-forges-webhooks` — Participant Room JWT Forges Arbitrary LiveKit Webhooks Due to Optional sha256 Claim and Shared Signing Secret

- **Deskripsi & Potensi Dampak**: LiveKitTokenService::createAudioToken() signs participant LiveKit room JWTs with HS256 using the shared apiSecret, sets iss to apiKey, includes a video grant, and omits any sha256 claim. On the unauthenticated webhook endpoints POST /api/v1/webhooks/livekit and POST /api/v1/livekit/webhook, LiveKitTokenService::verifyWebhookSignature() decodes the Bearer JWT via Firebase\JWT\JWT::decode() using the same apiSecret and only checks body integrity inside if (isset($decoded->sha256)). Consequently, any authenticated user (including self-registered user or driver accounts) who obtains a participant token from POST /api/v1/calls, POST /api/v1/calls/{call}/accept, or GET|POST /api/v1/calls/{call}/token can submit that participant JWT to forge arbitrary LiveKit webhook events (participant_joined, participant_left, room_finished) with arbitrary JSON bodies for any known room_name.
- **Akar Masalah**: LiveKitTokenService::verifyWebhookSignature() accepts HS256 JWTs signed with the participant-token secret without requiring a non-empty sha256 body-hash claim or rejecting participant claims such as video/sub, allowing participant JWTs from createAudioToken() to pass webhook signature verification for arbitrary bodies.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:97`](../routes/api.php#L97) (`api.v1.calls.store`) — Any Sanctum-authenticated user (including self-registered accounts via POST /api/v1/auth/register) initiates a call or fetches a room token via /api/v1/calls, /accept, or /token.
  2. **[PROPAGATION]** [`app/Services/Call/LiveKitTokenService.php:72`](../app/Services/Call/LiveKitTokenService.php#L72) (`LiveKitTokenService::createAudioToken`) — Mints a participant JWT signed with HS256 using $this->apiSecret, iss => $this->apiKey, video grant, and no sha256 claim.
  3. **[PROPAGATION]** [`routes/api.php:37`](../routes/api.php#L37) (`api.v1.webhooks.livekit`) — Attacker submits the participant JWT in Authorization: Bearer to unauthenticated POST /api/v1/webhooks/livekit (or /api/v1/livekit/webhook) with arbitrary webhook JSON.
  4. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:29`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L29) (`LiveKitWebhookController::handle`) — Delegates signature validation of rawBody and Authorization header to LiveKitTokenService::verifyWebhookSignature().
  5. **[PROPAGATION]** [`app/Services/Call/LiveKitTokenService.php:139`](../app/Services/Call/LiveKitTokenService.php#L139) (`LiveKitTokenService::verifyWebhookSignature`) — Verifies HS256 signature and $decoded->iss === $this->apiKey, then wraps body SHA-256 verification in if (isset($decoded->sha256)), returning true when sha256 is absent.
  6. **[SINK]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:123`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L123) (`LiveKitWebhookController::handleParticipantJoined`) — Processes forged participant_joined/participant_left/room_finished events, inserting CallParticipant rows and mutating calls.status (e.g., flipping Ringing to Accepted without receiver consent or terminating active calls).
- **Bukti Tambahan**:
  - [`app/Services/Call/LiveKitTokenService.php:90`](../app/Services/Call/LiveKitTokenService.php#L90) — createAudioToken() builds payload with iss => $this->apiKey, sub, video grant, and signs with $this->apiSecret (HS256) at line 106 with no sha256 claim.
  - [`app/Services/Call/LiveKitTokenService.php:122`](../app/Services/Call/LiveKitTokenService.php#L122) — verifyWebhookSignature() decodes Bearer JWT with $this->apiSecret (line 133), checks iss === $this->apiKey (line 135), and only verifies body SHA-256 inside if (isset($decoded->sha256)) (lines 139-146).
  - [`routes/api.php:37`](../routes/api.php#L37) — POST /api/v1/webhooks/livekit (line 37) and POST /api/v1/livekit/webhook (line 39) are registered outside auth:sanctum without additional authentication or throttling.
  - [`app/Http/Controllers/Api/V1/CallController.php:49`](../app/Http/Controllers/Api/V1/CallController.php#L49) — store() (line 49), accept() (line 90), and token() (lines 167, 182) return LiveKit participant JWTs to authenticated callers/receivers.
  - [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:103`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L103) — handleParticipantJoined() (lines 103-129) and handleRoomFinished() (lines 158-181) apply state transitions after verifyWebhookSignature() succeeds.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHPUnit/HTTP tests on this Windows host lacking OS-enforced sandbox isolation.
  - Runtime exploitability requires LIVEKIT_API_KEY and a >=32-char LIVEKIT_API_SECRET to be configured in the target environment (both default empty in .env.example and compose.yaml).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated Linux sandbox with dummy LIVEKIT_API_KEY and >=32-byte LIVEKIT_API_SECRET, call POST /api/v1/calls as dummy user A targeting dummy user B to capture connection.token and room_name, then POST forged participant_joined JSON events for B and A to /api/v1/webhooks/livekit using Authorization: Bearer <connection.token> and assert HTTP 200 and calls.status === 'accepted' without B invoking /accept.
  - *Lingkungan Deploy*: Owner inspects deployment environment variables to confirm whether LIVEKIT_API_KEY and LIVEKIT_API_SECRET are set and confirms via source/release check whether LiveKitTokenService::verifyWebhookSignature() enforces a mandatory sha256 claim; do not send forged webhooks to production.
- **Rekomendasi Perbaikan Terkecil**: In LiveKitTokenService::verifyWebhookSignature(), require non-empty $this->apiKey, require isset($decoded->sha256) && is_string($decoded->sha256) && $decoded->sha256 !== '', require isset($decoded->exp), and reject tokens carrying participant claims such as isset($decoded->video) or isset($decoded->sub).

#### [High (Est.)] `livekit-webhook-unguarded-participant-identity` — Unguarded LiveKit Participant Identity Causes PostgreSQL 500 FK/UUID Crash and Arbitrary User Injection into call_participants

- **Deskripsi & Potensi Dampak**: LiveKitWebhookController::handleParticipantJoined() takes $participantData['identity'] directly from webhook payloads and passes it as user_id to CallParticipant::updateOrCreate() without checking Str::isUuid($identity), verifying user existence, or checking $call->isParticipant($identity). Because call_participants.user_id is a PostgreSQL foreignUuid('user_id')->constrained('users'), non-UUID identities (e.g., LiveKit egress/SIP/bot identities or crafted strings) trigger SQLSTATE[22P02] 500 errors, nonexistent UUIDs trigger SQLSTATE[23503] FK violation 500 errors, and arbitrary existing user UUIDs outside the call are inserted into call_participants, incrementing $joinedCount >= 2 and flipping Ringing calls to Accepted. Unconditional updateOrCreate() also overwrites joined_at on rejoin, skewing Call::getMediaStartTime().
- **Akar Masalah**: LiveKitWebhookController::handleParticipantJoined() passes unvalidated participant.identity directly into CallParticipant::updateOrCreate(['user_id' => $identity, 'joined_at' => now()]) without UUID format checks, participant membership verification ($call->isParticipant()), or preserving original joined_at.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:37`](../routes/api.php#L37) (`api.v1.webhooks.livekit`) — Webhook request (either legitimate LiveKit participant_joined event or forged webhook via participant JWT) arrives at POST /api/v1/webhooks/livekit.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:76`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L76) (`LiveKitWebhookController::handle`) — Dispatches participant_joined event to handleParticipantJoined($call, $payload['participant'] ?? []).
  3. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:105`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L105) (`LiveKitWebhookController::handleParticipantJoined`) — Extracts $identity = $participantData['identity'] ?? null and checks only truthiness (! $identity) with no Str::isUuid() or $call->isParticipant($identity) validation.
  4. **[SINK]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:111`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L111) (`LiveKitWebhookController::handleParticipantJoined`) — Executes CallParticipant::updateOrCreate(['call_id' => $call->id, 'livekit_identity' => $identity], ['user_id' => $identity, 'joined_at' => now()]), crashing PostgreSQL on non-UUID/unknown UUIDs, injecting arbitrary existing users, overwriting joined_at, and advancing status to Accepted when $joinedCount >= 2.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:105`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L105) — Lines 105-128 pass $identity directly into CallParticipant::updateOrCreate() as user_id and joined_at => now(), then evaluate $call->participants()->whereNotNull('joined_at')->count() >= 2.
  - [`database/migrations/2026_11_09_140001_create_call_participants_table.php:17`](../database/migrations/2026_11_09_140001_create_call_participants_table.php#L17) — call_participants.user_id is defined as foreignUuid('user_id')->constrained('users')->cascadeOnDelete() with unique(['call_id', 'user_id']) at line 23.
  - [`app/Models/Call.php:136`](../app/Models/Call.php#L136) — Call::isParticipant() defines authorized participants (caller_id / receiver_id / callee_id), and getMediaStartTime() (lines 180-194) takes max(joined_at) across participants.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHPUnit/PostgreSQL tests on this Windows host without OS-enforced sandboxing.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated sandbox against PostgreSQL, send a signed participant_joined webhook for an existing Call with participant.identity set to (a) 'egress-bot' (expecting SQLSTATE[22P02] 500), (b) a random unused UUID (expecting SQLSTATE[23503] 500), and (c) a third dummy user's UUID alongside caller UUID (expecting third user insertion and Call status flipping to accepted).
  - *Lingkungan Deploy*: Owner verifies via LiveKit webhook logs or code inspection whether non-UUID identities (e.g., egress/SIP) are emitted and confirms no unguarded CallParticipant::updateOrCreate path remains deployed.
- **Rekomendasi Perbaikan Terkecil**: In LiveKitWebhookController::handleParticipantJoined(), validate is_string($identity) && Str::isUuid($identity) && $call->isParticipant($identity), and update only the matching CallParticipant row while setting joined_at only when null ($participant->joined_at ?? now()).

#### [Medium (Est.)] `livekit-webhook-idempotency-toctou-and-missing-lock` — Webhook Idempotency TOCTOU Race, Premature Commit Before Processing, Non-Array JSON Crash, and Missing Call Row Locking

- **Deskripsi & Potensi Dampak**: LiveKitWebhookController::handle() has four integrity/reliability flaws: (1) json_decode($rawBody, true) ?? [] leaves non-null scalar JSON payloads as non-arrays and handle() fails to validate array shape on $payload, $payload['id'], $payload['event'], and $payload['participant'], allowing non-null non-array $payload['participant'] values on participant_joined (line 76) or participant_left (line 80) for an existing room to trigger TypeError 500s (and array-valued id/event to fail at lines 51/58), while scalar root JSON or non-array $payload['room'] silently consumes an idempotency record and returns HTTP 200 via empty($roomName) at lines 40 and 63; (2) idempotency checks ProcessedWebhook::where('event_id', $eventId)->exists() before a separate DB::transaction creating ProcessedWebhook, risking UniqueConstraintViolationException (500) under concurrent duplicate deliveries; (3) ProcessedWebhook commits BEFORE $call is looked up and mutated, so any failure in handleParticipantJoined() or handleRoomFinished() permanently drops retries as already_processed; and (4) Call::where('room_name', $roomName)->first() runs without DB::transaction or lockForUpdate(), allowing participant_joined or room_finished to race with concurrent lifecycle transitions or double-broadcast CallEnded.
- **Akar Masalah**: LiveKitWebhookController::handle() lacks array shape guards on decoded JSON, separates check-then-insert ProcessedWebhook idempotency from call processing across uncommitted/unlocked boundaries, and omits DB::transaction/lockForUpdate() on Call state transitions.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:37`](../routes/api.php#L37) (`api.v1.webhooks.livekit`) — Signed (or LW-01-forged) LiveKit webhook request enters POST /api/v1/webhooks/livekit.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:37`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L37) (`LiveKitWebhookController::handle`) — Decodes raw JSON without is_array() guard on root, room, or participant objects.
  3. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:43`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L43) (`LiveKitWebhookController::handle`) — Performs non-atomic exists() check then commits ProcessedWebhook::create() (lines 50-56) prior to loading Call.
  4. **[SINK]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:67`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L67) (`LiveKitWebhookController::handle`) — Loads Call without lockForUpdate() and executes unlocked state updates in handleParticipantJoined() (lines 120-128) and handleRoomFinished() (lines 158-181).
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:37`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L37) — $payload = json_decode($rawBody, true) ?? [] accepts scalar JSON values; lines 38-40 do not validate array shape, and lines 76 and 80 pass unguarded $payload['participant'] ?? [] directly to array-typehinted handleParticipantJoined()/handleParticipantLeft() (throwing TypeError when participant is a non-null scalar).
  - [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:43`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L43) — exists() check (lines 43-48) races with ProcessedWebhook::create() inside an isolated DB::transaction (lines 50-56) committed before call lookup at line 67.
  - [`database/migrations/2026_11_09_140002_create_processed_webhooks_table.php:16`](../database/migrations/2026_11_09_140002_create_processed_webhooks_table.php#L16) — processed_webhooks.event_id has a database unique constraint that throws UniqueConstraintViolationException on concurrent inserts.
  - [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:120`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L120) — handleParticipantJoined() (lines 120-128) and handleRoomFinished() (lines 158-181) check isTerminal() and update Call outside any lockForUpdate() transaction.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHP/PostgreSQL concurrency tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL test harness, send (a) a signed participant_joined webhook for an existing Call room with a scalar participant field (e.g. {"event":"participant_joined","room":{"name":"<room>"},"participant":"bad"}) to observe TypeError 500, (b) concurrent identical signed webhooks with the same event_id to observe UniqueConstraintViolationException, and (c) a failing participant_joined payload followed by a valid retry with the same event_id to verify retry loss.
  - *Lingkungan Deploy*: Owner inspects processed_webhooks logs or error tracking for 500 errors on duplicate LiveKit deliveries or malformed payloads without sending test traffic to production.
- **Rekomendasi Perbaikan Terkecil**: Validate is_array($payload) (and nested room/participant arrays), then inside a single DB::transaction atomically insert into processed_webhooks via insertOrIgnore() (returning early if 0 rows inserted) and reload Call with lockForUpdate() before handling the event, dispatching CallEnded via DB::afterCommit().

#### [Medium (Est.)] `livekit-webhook-ignores-tripcalls` — LiveKitWebhookController Ignores TripCall Room Webhooks, Leaving Dropped Trip Calls Live and Blocking Peer Callbacks

- **Deskripsi & Potensi Dampak**: Although TripCall generates a unique LiveKit room_name on creation and CallController::token() issues LiveKit room tokens for TripCall participants, LiveKitWebhookController::handle() only queries App\Models\Call by room_name and returns ignored_unknown_room when no Call matches — after already inserting the webhook event_id into processed_webhooks. If a TripCall participant crashes or drops connection and LiveKit emits room_finished or participant_left, the TripCall stays in status 'answered' indefinitely in PostgreSQL (and for up to 7200s in Redis TripCallStateStore), causing TripCallController::store() to reject the other party's callback attempt with HTTP 409 ('Lawan bicara sedang menelepon Anda.').
- **Akar Masalah**: LiveKitWebhookController::handle() resolves room_name solely against App\Models\Call and returns ignored_unknown_room without checking App\Models\TripCall, while consuming the ProcessedWebhook event_id.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:37`](../routes/api.php#L37) (`api.v1.webhooks.livekit`) — LiveKit Cloud delivers room_finished or participant_left webhook for a TripCall room_name issued via CallController::token().
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:50`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L50) (`LiveKitWebhookController::handle`) — Records event_id in processed_webhooks before checking room ownership.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:67`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L67) (`LiveKitWebhookController::handle`) — Queries only Call::where('room_name', $roomName)->first() and returns ignored_unknown_room (line 70) without resolving TripCall, leaving live TripCalls open and blocking peer callbacks in TripCallController::store().
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/CallController.php:151`](../app/Http/Controllers/Api/V1/CallController.php#L151) — CallController::token() falls back to TripCall::find($call) and mints LiveKit room JWTs for $tripCall->room_name ?? (string) $tripCall->id (lines 166-167).
  - [`app/Models/TripCall.php:35`](../app/Models/TripCall.php#L35) — TripCall::booted() assigns UUID room_name on creating; scopeLive() (lines 82-89) treats Answered calls as permanently live with no timeout.
  - [`app/Http/Controllers/Api/V1/LiveKitWebhookController.php:67`](../app/Http/Controllers/Api/V1/LiveKitWebhookController.php#L67) — Line 67 queries only Call::where('room_name', $roomName)->first() and returns ignored_unknown_room at line 70.
  - [`app/Http/Controllers/Api/V1/TripCallController.php:41`](../app/Http/Controllers/Api/V1/TripCallController.php#L41) — If an answered TripCall from one party remains unclosed, the other participant attempting POST /api/v1/emergency-requests/{id}/calls hits HTTP 409 at lines 42-49.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHPUnit/HTTP tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed test harness, create an answered TripCall with room_name, send a valid signed room_finished webhook for that room_name to /api/v1/webhooks/livekit, verify response is ignored_unknown_room while trip_calls.status remains 'answered', and verify the peer receives 409 when calling POST /api/v1/emergency-requests/{id}/calls.
  - *Lingkungan Deploy*: Owner checks server logs for 'LiveKit webhook received: room_finished' followed by ignored_unknown_room responses correlating with active TripCall room names.
- **Rekomendasi Perbaikan Terkecil**: When Call::where('room_name', $roomName)->lockForUpdate()->first() returns null in LiveKitWebhookController::handle(), fall back to TripCall::where('room_name', $roomName)->lockForUpdate()->first() and close live TripCalls on room_finished (status => Ended, ended_at => now(), broadcast CallStatusUpdated).

---

### 3.2 WebSockets & Broadcast Channels (2 Temuan)

#### [Critical (Est.)] `public-reverb-channels-expose-patient-pii-and-webrtc-signaling` — Public Unauthenticated Broadcast Channels Expose All Patient PII, Medical Notes, Ambulance Plates, and WebRTC SDP/ICE Signaling

- **Deskripsi & Potensi Dampak**: EmergencyRequestStatusUpdated (dispatched automatically on every EmergencyRequest create/status update in EmergencyRequest::booted()) broadcasts on public Channel('emergency-requests') and Channel('hospital.{id}'), streaming request_code, status, hospital_id, emergency_type, severity_level, patient_name, patient_phone, patient_notes, driver_name, and ambulance_plate to any unauthenticated Reverb subscriber. Similarly, CallSignalEvent broadcasts WebRTC SDP offers/answers and ICE candidates on public Channel('call.{id}'), and CallStatusUpdated broadcasts caller_id, caller_name, driver_id, and emergency_request_id on public Channel('call.{id}'), Channel('user.{id}'), and Channel('driver.{id}'). In compose.yaml/config/reverb.php, Reverb runs with public app keys ('cariambulan-key' / 'medicgo-key'), allowed_origins => ['*'], and accept_client_events_from => 'members', allowing any unauthenticated WebSocket client on port 8081/8443 to subscribe to public channels (and send client-* whispers on subscribed channels).
- **Akar Masalah**: EmergencyRequestStatusUpdated, CallSignalEvent, and CallStatusUpdated broadcast sensitive patient PII, ambulance dispatch details, and WebRTC signaling over unauthenticated Illuminate\Broadcasting\Channel instances rather than PrivateChannels with authorization in routes/channels.php, paired with Reverb allowed_origins ['*'] and accept_client_events_from 'members'.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Models/EmergencyRequest.php:59`](../app/Models/EmergencyRequest.php#L59) (`EmergencyRequest::booted`) — Every EmergencyRequest creation or status update triggers EmergencyRequestStatusUpdated (and CallSignalingController::signal/answer/decline/end and TripCallController::store trigger CallSignalEvent and CallStatusUpdated).
  2. **[PROPAGATION]** [`app/Events/EmergencyRequestStatusUpdated.php:26`](../app/Events/EmergencyRequestStatusUpdated.php#L26) (`EmergencyRequestStatusUpdated::broadcastOn`) — Targets unauthenticated public Illuminate\Broadcasting\Channel('emergency-requests') and Channel('hospital.{id}'), just as CallSignalEvent (line 22) and CallStatusUpdated (line 32) target public call.*/user.*/driver.* channels.
  3. **[SINK]** [`app/Events/EmergencyRequestStatusUpdated.php:52`](../app/Events/EmergencyRequestStatusUpdated.php#L52) (`EmergencyRequestStatusUpdated::broadcastWith`) — Serializes patient_name, patient_phone, patient_notes, emergency_type, severity_level, driver_name, and ambulance_plate (and CallSignalEvent/CallStatusUpdated serialize SDP/ICE data and user/driver IDs) over Reverb to any unauthenticated WebSocket client.
- **Bukti Tambahan**:
  - [`app/Events/EmergencyRequestStatusUpdated.php:28`](../app/Events/EmergencyRequestStatusUpdated.php#L28) — Broadcasts on new Channel('emergency-requests') and new Channel('hospital.'.$id) with patient_name, patient_phone, patient_notes, driver_name, and ambulance_plate (lines 56-70).
  - [`app/Events/CallSignalEvent.php:24`](../app/Events/CallSignalEvent.php#L24) — Broadcasts WebRTC SDP/ICE signaling data on public new Channel('call.'.$this->callId).
  - [`app/Events/CallStatusUpdated.php:34`](../app/Events/CallStatusUpdated.php#L34) — Broadcasts caller_id, caller_name, driver_id, and emergency_request_id on public Channel('call.{id}'), Channel('user.{id}'), and Channel('driver.{id}').
  - [`config/reverb.php:86`](../config/reverb.php#L86) — Both Reverb app configs ('cariambulan-key' and 'medicgo-key') set allowed_origins => ['*'] (lines 86, 109) and accept_client_events_from => 'members' (lines 91, 114).
  - [`resources/js/app.js:172`](../resources/js/app.js#L172) — Dashboard client subscribes to public window.Echo.channel(`hospital.${hospitalId}`) and window.Echo.channel('emergency-requests') using public VITE_REVERB_APP_KEY in resources/js/echo.js:6.
  - [`compose.yaml:18`](../compose.yaml#L18) — Sets BROADCAST_CONNECTION: reverb and exposes Reverb on ports 8081 (line 137) and 8443 via Caddy (line 113).
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits starting Reverb and connecting a local WebSocket test client on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated loopback sandbox running Reverb, open an unauthenticated WebSocket connection to /app/cariambulan-key, send {"event":"pusher:subscribe","data":{"channel":"emergency-requests"}}, trigger a dummy EmergencyRequest creation or status update, and observe patient_name, patient_phone, and patient_notes delivered to the unauthenticated socket.
  - *Lingkungan Deploy*: Owner verifies BROADCAST_CONNECTION and whether ports 8081/8443 are externally reachable, and audits client apps/dashboards for PrivateChannel migration readiness.
- **Rekomendasi Perbaikan Terkecil**: Convert emergency-requests, hospital.{id}, call.{id}, user.{id}, and driver.{id} channels to PrivateChannel with strict authorization in routes/channels.php (restricting emergency-requests to administrator, hospital.{id} to administrator or owning hospital_admin, and TripCall channels to the order's reporter and currently assigned driver), update resources/js/app.js to Echo.private(...), and disable client events (accept_client_events_from => 'none') and restrict allowed_origins in config/reverb.php.

#### [Medium (Est.)] `broadcasting-auth-missing-sanctum-middleware` — /broadcasting/auth Missing auth:sanctum Middleware Blocks Mobile Sanctum Clients from Subscribing to Private Call Channels

- **Deskripsi & Potensi Dampak**: bootstrap/app.php registers broadcasting via withRouting(channels: __DIR__.'/../routes/channels.php') without calling ->withBroadcasting(..., ['middleware' => ['web', 'auth:sanctum']]) or setting channel guards in routes/channels.php. In Laravel's ApplicationBuilder::withRouting(), passing channels invokes withBroadcasting($channels) with empty attributes, causing BroadcastManager::routes(null) to register /broadcasting/auth under ['middleware' => ['web']] only. Because config/auth.php defaults to the session-based web guard and Broadcaster::retrieveUser() calls $request->user() when channel options specify no guards, Sanctum bearer-token requests from mobile clients authenticate as null and fail PusherBroadcaster::auth() with HTTP 403 on PrivateChannel('users.{userId}.calls') and PrivateChannel('calls.{id}').
- **Akar Masalah**: bootstrap/app.php uses default withRouting(channels: ...) middleware (['web']) and routes/channels.php omits explicit guards, leaving /broadcasting/auth unable to authenticate Sanctum bearer-token requests.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`bootstrap/app.php:14`](../bootstrap/app.php#L14) (`Application::configure`) — Passes channels: __DIR__.'/../routes/channels.php' to withRouting(), which internally registers /broadcasting/auth with only ['middleware' => ['web']].
  2. **[PROPAGATION]** [`routes/channels.php:15`](../routes/channels.php#L15) (`users.{userId}.calls`) — Registers private channel callbacks without ['guards' => ['web', 'sanctum']], so Broadcaster::retrieveUser() uses the default web session guard (config/auth.php:19).
  3. **[SINK]** [`app/Events/Call/CallIncoming.php:24`](../app/Events/Call/CallIncoming.php#L24) (`CallIncoming::broadcastOn`) — Broadcasts CallIncoming (along with CallAccepted, CallCancelled, CallEnded, CallMissed, CallRejected) on PrivateChannel('users.{userId}.calls'), which Sanctum mobile clients cannot authorize via /broadcasting/auth (HTTP 403).
- **Bukti Tambahan**:
  - [`bootstrap/app.php:14`](../bootstrap/app.php#L14) — withRouting(channels: __DIR__.'/../routes/channels.php') delegates to ApplicationBuilder::withBroadcasting($channels) with empty attributes, yielding BroadcastManager::routes(null) => ['middleware' => ['web']].
  - [`config/auth.php:19`](../config/auth.php#L19) — Default auth guard is 'web' (session driver, lines 41-44); no Sanctum statefulApi() middleware is enabled in bootstrap/app.php.
  - [`routes/channels.php:15`](../routes/channels.php#L15) — users.{userId}.calls (lines 15-17) and calls.{id} (lines 19-23) define no guards option.
  - [`app/Events/Call/CallIncoming.php:29`](../app/Events/Call/CallIncoming.php#L29) — Uses PrivateChannel('users.'.$receiverId.'.calls') (as do CallAccepted, CallCancelled, CallEnded, CallMissed, and CallRejected in app/Events/Call/).
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running HTTP feature tests against /broadcasting/auth on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed Laravel test harness, issue a Sanctum personal access token for dummy user U (without a web session cookie) and POST /broadcasting/auth with Authorization: Bearer <token>, channel_name=private-users.<U.id>.calls, socket_id=123.456; confirm HTTP 403.
  - *Lingkungan Deploy*: Owner runs php artisan route:list --path=broadcasting/auth to observe middleware on the deployed route (expected ['web'] only) and reviews whether mobile apps subscribe to private channels.
- **Rekomendasi Perbaikan Terkecil**: Replace channels: in bootstrap/app.php with ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth:sanctum']]) (or expose an auth:sanctum broadcast auth route / specify ['guards' => ['web', 'sanctum']] on channels if web guests/403 semantics are preferred over 401).

---

### 3.3 Client DOM & Blade (5 Temuan)

#### [Critical (Est.)] `client-dom-xss-echo-hospital-queue-innerhtml` — Stored/Broadcast DOM-Based XSS in Hospital Realtime Queue via Unescaped Echo Payload Interpolation

- **Deskripsi & Potensi Dampak**: Unauthenticated mobile registration (POST /api/v1/auth/register) and profile updates (PUT /api/v1/me) accept arbitrary HTML/JS characters in full_name (max:200) and phone (max:20). Creating an EmergencyRequest leaves hospital_id=null and fires EmergencyRequestStatusUpdated on public channels 'emergency-requests' and 'hospital.{id}' with raw patient_name, patient_phone, and other fields. resources/js/app.js processes all events where hospital_id is falsy across every open hospital dashboard and inserts raw payload strings into innerHTML (newRow.innerHTML, driverCell.innerHTML, statusCell.innerHTML, toast.innerHTML), executing arbitrary JavaScript in hospital_admin/administrator browser sessions.
- **Akar Masalah**: resources/js/app.js assigns unescaped template literals containing EmergencyRequestStatusUpdated broadcast fields to innerHTML while API validation permits arbitrary HTML markup in user full_name/phone and events broadcast on public channels.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Requests/Api/V1/RegisterRequest.php:28`](../app/Http/Requests/Api/V1/RegisterRequest.php#L28) (`RegisterRequest::rules`) — Accepts arbitrary strings up to 200 chars in full_name and 20 chars in phone (line 30) on public registration (also UpdateProfileRequest.php:20-21).
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:54`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L54) (`EmergencyRequestController::store`) — Creates an EmergencyRequest with hospital_id=null, triggering EmergencyRequest::booted() created hook (app/Models/EmergencyRequest.php:68).
  3. **[PROPAGATION]** [`app/Events/EmergencyRequestStatusUpdated.php:56`](../app/Events/EmergencyRequestStatusUpdated.php#L56) (`EmergencyRequestStatusUpdated::broadcastWith`) — Returns unescaped patient_name (line 63), patient_phone (line 64), driver_name (line 67), ambulance_plate (line 68), request_code (line 58), and status (line 59) on public Channel('emergency-requests') (line 29) and Channel('hospital.{id}') (line 33).
  4. **[PROPAGATION]** [`resources/js/app.js:177`](../resources/js/app.js#L177) (`initRealtimeHospitalQueue::handleUpdate`) — Listens on hospital.${hospitalId} and emergency-requests; processes events where data.hospital_id is null or falsy on any open hospital dashboard.
  5. **[SINK]** [`resources/js/app.js:224`](../resources/js/app.js#L224) (`initRealtimeHospitalQueue::handleUpdate`) — Interpolates raw payload fields into newRow.innerHTML (lines 224-262, including patient_name at 227 and patient_phone in href="tel:..." at 228), driverCell.innerHTML (lines 201-204), statusCell.innerHTML via getStatusBadgeHtml (lines 135, 189, 241), and toast.innerHTML via showLiveToast (lines 150-158).
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/AuthController.php:25`](../app/Http/Controllers/Api/V1/AuthController.php#L25) — Unauthenticated mobile API registration creates user/driver accounts directly.
  - [`app/Http/Requests/Api/V1/UpdateProfileRequest.php:20`](../app/Http/Requests/Api/V1/UpdateProfileRequest.php#L20) — Profile update endpoint also accepts unconstrained full_name and phone strings.
  - [`app/Models/EmergencyRequest.php:68`](../app/Models/EmergencyRequest.php#L68) — Model created hook dispatches EmergencyRequestStatusUpdated.
  - [`resources/views/dashboard/hospital.blade.php:530`](../resources/views/dashboard/hospital.blade.php#L530) — Renders #requests-table[data-hospital-id] which activates initRealtimeHospitalQueue in resources/js/app.js.
  - [`resources/js/app.js:227`](../resources/js/app.js#L227) — newRow.innerHTML interpolates data.patient_name (line 227) and data.patient_phone (line 228, both text and tel: href) unescaped.
  - [`resources/js/app.js:155`](../resources/js/app.js#L155) — showLiveToast (lines 155-156) and driverCell.innerHTML (lines 201-204) also interpolate payload strings unescaped.
- **Penghambat Validasi (`blockers`)**:
  - Browser/Reverb execution is blocked under sandboxed-source-and-local-only on this non-sandboxed Windows host.
  - Live exploitation via Echo requires BROADCAST_CONNECTION=reverb (enabled in compose.yaml, though .env.example defaults to log) AND matching VITE_REVERB_* build settings (currently mismatched in resources/js/echo.js:6-9 unless supplied during npm run build).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Configure BROADCAST_CONNECTION=reverb and matching VITE_REVERB_* in an isolated environment, log into /dashboard/hospital as hospital_admin, register a user via POST /api/v1/auth/register with full_name='<img src=x onerror=alert(document.domain)>', and submit POST /api/v1/emergency-requests to observe DOM XSS execution.
  - *Lingkungan Deploy*: Have the owner verify built JS assets in public/build/assets/ for VITE_REVERB_* connectivity and confirm Echo realtime updates use safe DOM textContent nodes rather than innerHTML.
- **Rekomendasi Perbaikan Terkecil**: Replace all innerHTML template interpolation in resources/js/app.js (getStatusBadgeHtml, showLiveToast, driverCell, newRow) with createElement/textContent, sanitize tel: hrefs to digits/+/-/()/spaces, switch EmergencyRequestStatusUpdated to authorized PrivateChannel('hospital.' . $hospitalId), and restrict special markup characters in full_name/phone validation.

#### [High (Est.)] `client-blade-js-attr-xss-users-show` — Stored XSS in Admin User Delete Confirmation via Blade Interpolation Inside onsubmit JS String

- **Deskripsi & Potensi Dampak**: Unauthenticated mobile registration (POST /api/v1/auth/register) and profile update (PUT /api/v1/me) accept arbitrary full_name strings including single quotes and JavaScript syntax. On /admin/users/{user}, resources/views/admin/users/show.blade.php embeds {{ $user->full_name }} inside a single-quoted JavaScript string within the delete form's onsubmit attribute. Because browsers HTML-decode &#039; back to ' inside event-handler attributes before JavaScript parsing, clicking 'Hapus User' executes attacker-supplied JavaScript in the administrator's session.
- **Akar Masalah**: Blade {{ }} applies HTML entity encoding rather than JavaScript string escaping inside an inline onsubmit event-handler attribute.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Requests/Api/V1/RegisterRequest.php:28`](../app/Http/Requests/Api/V1/RegisterRequest.php#L28) (`RegisterRequest::rules`) — Allows single quotes and JS tokens in full_name (max:200) on unauthenticated registration (also UpdateProfileRequest.php:20).
  2. **[PROPAGATION]** [`app/Http/Controllers/Admin/UserManagementController.php:98`](../app/Http/Controllers/Admin/UserManagementController.php#L98) (`UserManagementController::show`) — Loads $user and passes it to admin.users.show (line 106).
  3. **[SINK]** [`resources/views/admin/users/show.blade.php:122`](../resources/views/admin/users/show.blade.php#L122) (`admin/users/show.blade.php`) — Embeds {{ $user->full_name }} inside single-quoted JS string literal in HTML onsubmit="return confirm('...');".
- **Bukti Tambahan**:
  - [`resources/views/admin/users/show.blade.php:122`](../resources/views/admin/users/show.blade.php#L122) — onsubmit="return confirm('Apakah Anda yakin ingin menghapus {{ $user->full_name }}? Tindakan ini tidak dapat dibatalkan.');"
  - [`app/Http/Requests/Api/V1/RegisterRequest.php:28`](../app/Http/Requests/Api/V1/RegisterRequest.php#L28) — Validates full_name solely as ['required', 'string', 'max:200'].
  - [`app/Http/Controllers/Admin/UserManagementController.php:106`](../app/Http/Controllers/Admin/UserManagementController.php#L106) — Renders admin.users.show with $user.
- **Penghambat Validasi (`blockers`)**:
  - Browser execution is blocked under sandboxed-source-and-local-only on this non-sandboxed Windows host.
  - Execution requires an authenticated administrator to view /admin/users/{user} and click the 'Hapus User' submit button.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Register a dummy user via POST /api/v1/auth/register with full_name="'+alert(document.domain)+'", render /admin/users/{id} as administrator in an isolated browser, click 'Hapus User', and observe alert execution before the confirm prompt.
  - *Lingkungan Deploy*: Inspect rendered /admin/users/{id} HTML in production to confirm inline onsubmit JS interpolation has been replaced with data-* attributes and external event listeners.
- **Rekomendasi Perbaikan Terkecil**: Remove inline onsubmit from resources/views/admin/users/show.blade.php, pass the message via data-confirm-message="... {{ $user->full_name }} ...", and handle confirmation in resources/js/app.js via confirm(form.dataset.confirmMessage).

#### [Medium (Est.)] `client-dom-xss-hospital-nominatim-innerhtml` — DOM-Based XSS in Hospital Setup Map Geocoding via Unescaped External Nominatim Response

- **Deskripsi & Potensi Dampak**: When an authenticated hospital_admin without a linked hospital opens /dashboard/hospital, the setup map script queries https://nominatim.openstreetmap.org/search and passes the first three comma-separated segments of the external result.display_name into updateSetupCoords(), which assigns the unescaped string directly into gpsBadge.innerHTML. Note that the separate query interpolation at line 988 originates from the user's own input and is self-XSS only, whereas result.display_name comes from external crowdsourced OpenStreetMap/Nominatim data.
- **Akar Masalah**: updateSetupCoords interpolates external third-party Nominatim display_name strings directly into gpsBadge.innerHTML instead of setting textContent.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`resources/views/dashboard/hospital.blade.php:990`](../resources/views/dashboard/hospital.blade.php#L990) (`searchLocation`) — Fetches external JSON from https://nominatim.openstreetmap.org/search when setup map (#setupHospitalMap, rendered only when $hospital is null at lines 139-299) searches for a location.
  2. **[PROPAGATION]** [`resources/views/dashboard/hospital.blade.php:999`](../resources/views/dashboard/hospital.blade.php#L999) (`searchLocation`) — Extracts result.display_name.split(',').slice(0, 3).join(',') and passes it as detail to updateSetupCoords(lat, lng, displayName) (line 1001).
  3. **[SINK]** [`resources/views/dashboard/hospital.blade.php:965`](../resources/views/dashboard/hospital.blade.php#L965) (`updateSetupCoords`) — Interpolates raw detail into gpsBadge.innerHTML without HTML escaping.
- **Bukti Tambahan**:
  - [`resources/views/dashboard/hospital.blade.php:965`](../resources/views/dashboard/hospital.blade.php#L965) — gpsBadge.innerHTML interpolates raw ${detail ? ' (' + detail + ')' : ''}.
  - [`resources/views/dashboard/hospital.blade.php:990`](../resources/views/dashboard/hospital.blade.php#L990) — Fetches unescaped JSON from third-party https://nominatim.openstreetmap.org/search API.
  - [`resources/views/dashboard/hospital.blade.php:999`](../resources/views/dashboard/hospital.blade.php#L999) — Passes first 3 comma-separated parts of result.display_name to updateSetupCoords at line 1001.
  - [`resources/views/dashboard/hospital.blade.php:139`](../resources/views/dashboard/hospital.blade.php#L139) — @if(! $hospital) gates setup map rendering to hospital_admin accounts without a hospital record.
- **Penghambat Validasi (`blockers`)**:
  - Browser execution and external network interaction are blocked under sandboxed-source-and-local-only.
  - Exploiting the Nominatim sink depends on external OpenStreetMap/Nominatim returning unescaped HTML/event-handler markup inside the first three comma-separated segments of display_name for a searched hospital query (user-typed query at line 988 is self-XSS only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated local browser session with a mocked Nominatim response returning HTML in result.display_name (e.g. '<img src=x onerror=alert(1)>, Jakarta'), trigger setup map search on /dashboard/hospital as a hospital_admin without a hospital and observe script execution.
  - *Lingkungan Deploy*: -
- **Rekomendasi Perbaikan Terkecil**: Construct the badge <span> using document.createElement('span') and textContent with gpsBadge.replaceChildren(...) in resources/views/dashboard/hospital.blade.php instead of assigning innerHTML, and validate finite numeric lat/lng values.

#### [Medium (Est.)] `client-missing-csrf-meta-dynamic-echo-form` — Missing CSRF Meta Tag in Dashboard Layout Leaves Empty _token in Dynamic Echo Status Forms

- **Deskripsi & Potensi Dampak**: resources/views/layouts/dashboard.blade.php (used by /dashboard/hospital) omits <meta name="csrf-token"> from <head> (layouts/wide.blade.php also omits it, though wide.blade.php is only used by auth/register.blade.php). When resources/js/app.js dynamically inserts a status-update form for a live Echo emergency request row, it reads meta[name="csrf-token"] and sets the hidden _token input to ''. When Sec-Fetch-Site is absent or not 'same-origin', Laravel's PreventRequestForgery falls back to tokensMatch() and fails with HTTP 419.
- **Akar Masalah**: layouts/dashboard.blade.php does not render the CSRF meta tag queried by resources/js/app.js when creating dynamic status forms.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`resources/views/layouts/dashboard.blade.php:3`](../resources/views/layouts/dashboard.blade.php#L3) (`<head>`) — Renders <head> without <meta name="csrf-token"> (layouts/wide.blade.php:3 also omits it).
  2. **[PROPAGATION]** [`resources/js/app.js:248`](../resources/js/app.js#L248) (`initRealtimeHospitalQueue::handleUpdate`) — Sets hidden _token input using document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', evaluating to ''.
  3. **[SINK]** [`vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php:102`](../vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php#L102) (`PreventRequestForgery::handle`) — Rejects empty _token in tokensMatch() (lines 168-175) with TokenMismatchException (HTTP 419) whenever hasValidOrigin() (Sec-Fetch-Site === 'same-origin', line 147) does not pass.
- **Bukti Tambahan**:
  - [`resources/views/layouts/dashboard.blade.php:4`](../resources/views/layouts/dashboard.blade.php#L4) — Head includes only charset, viewport, and description meta tags; no csrf-token meta element exists.
  - [`resources/views/layouts/wide.blade.php:4`](../resources/views/layouts/wide.blade.php#L4) — Head likewise omits csrf-token meta element (used by auth/register.blade.php).
  - [`resources/js/app.js:248`](../resources/js/app.js#L248) — Dynamically builds <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''}">.
  - [`vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php:145`](../vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php#L145) — hasValidOrigin() passes without checking _token only when Sec-Fetch-Site is 'same-origin'.
- **Penghambat Validasi (`blockers`)**:
  - Browser/HTTP test execution is blocked under sandboxed-source-and-local-only on this non-sandboxed Windows host.
  - Dynamic row insertion requires active Echo/Reverb delivery, and HTTP 419 failure occurs only when Sec-Fetch-Site: same-origin is omitted or stripped.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Open /dashboard/hospital with active Echo/Reverb, trigger a new EmergencyRequest so app.js inserts a dynamic row, inspect the hidden _token value (''), and submit the form without Sec-Fetch-Site to observe HTTP 419.
  - *Lingkungan Deploy*: Inspect /dashboard/hospital HTML <head> for <meta name="csrf-token"> and confirm dynamically inserted rows carry a non-empty CSRF token.
- **Rekomendasi Perbaikan Terkecil**: Add <meta name="csrf-token" content="{{ csrf_token() }}"> to resources/views/layouts/dashboard.blade.php (and resources/views/layouts/wide.blade.php for layout parity), and populate dynamic form inputs via DOM APIs.

#### [Low (Est.)] `client-hospital-status-filter-and-ui-desync` — Active Incoming Triage Truncation, Dead 'critical' Severity Checks, and Unsynced Realtime Indicator in Hospital/Admin Views

- **Deskripsi & Potensi Dampak**: Functional/UI reliability defects across hospital and admin views: (1) HospitalDashboardController::index caps $recentRequests at limit(25) and hospital.blade.php derives $activeIncoming in-view from $recentRequests, dropping older open emergencies from the IGD triage alert banner once >25 newer requests exist; (2) hospital.blade.php, app.js, and admin.blade.php check severity_level === 'critical' even though StoreEmergencyRequestRequest and the emergency_requests migration restrict severity_level to ['high', 'medium', 'low'] (so 'high' requests never render the pulsing danger dot on the hospital queue, and welcome.blade.php documents invalid 'critical'); (3) #ws-status-indicator statically displays green 'WebSocket Realtime' with no Echo/Pusher connection listener. Note that legacy status strings 'arrived_at_pickup'/'on_board_to_hospital' in hospital.blade.php:417,419 are harmless dead duplicates because valid EmergencyStatus enum values are already present on lines 411-414.
- **Akar Masalah**: Dashboard queries and templates were not fully synchronized with EmergencyStatus enum values, severity_level schema ('high'|'medium'|'low'), unbounded active-triage visibility, and Echo connection state.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Requests/Api/V1/StoreEmergencyRequestRequest.php:28`](../app/Http/Requests/Api/V1/StoreEmergencyRequestRequest.php#L28) (`StoreEmergencyRequestRequest::rules`) — Restricts severity_level to ['high', 'medium', 'low'] (matching DB enum at 2026_11_02_100006_create_emergency_requests_table.php:19), while HospitalDashboardController::index caps $recentRequests at limit(25) (HospitalDashboardController.php:93).
  2. **[SINK]** [`resources/views/dashboard/hospital.blade.php:410`](../resources/views/dashboard/hospital.blade.php#L410) (`hospital.blade.php`) — Derives $activeIncoming from capped $recentRequests (line 410), checks unreachable severity_level === 'critical' (line 559; app.js:233; admin.blade.php:169), and renders static #ws-status-indicator (line 508).
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:93`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L93) — Applies ->limit(25) to $recentRequests, truncating active emergencies from $activeIncoming once >25 newer requests exist.
  - [`database/migrations/2026_11_02_100006_create_emergency_requests_table.php:19`](../database/migrations/2026_11_02_100006_create_emergency_requests_table.php#L19) — Defines severity_level enum strictly as ['high', 'medium', 'low'].
  - [`resources/views/dashboard/hospital.blade.php:559`](../resources/views/dashboard/hospital.blade.php#L559) — Checks $req->severity_level === 'critical' (same in resources/js/app.js:233, admin.blade.php:169, and example payload in welcome.blade.php:320), omitting pulsing danger dot for 'high'.
  - [`resources/views/dashboard/hospital.blade.php:508`](../resources/views/dashboard/hospital.blade.php#L508) — Renders static green #ws-status-indicator ('WebSocket Realtime') never updated by Echo/Pusher connection events.
  - [`resources/views/dashboard/hospital.blade.php:417`](../resources/views/dashboard/hospital.blade.php#L417) — Includes dead legacy status strings 'arrived_at_pickup' (line 417) and 'on_board_to_hospital' (line 419) alongside valid EmergencyStatus enum values (lines 411-414).
- **Penghambat Validasi (`blockers`)**:
  - Browser/runtime verification is blocked under sandboxed-source-and-local-only; this is a functional/UI consistency defect rather than a direct security boundary violation.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Create >25 emergency requests for a hospital while keeping an earlier one in activeTrip() status, plus a severity_level='high' request, and render /dashboard/hospital with Reverb stopped to observe banner truncation, missing pulsing indicator, and static green #ws-status-indicator.
  - *Lingkungan Deploy*: -
- **Rekomendasi Perbaikan Terkecil**: Query $activeIncoming separately without limit(25) using EmergencyStatus::activeTrip(), remove dead legacy status strings, check severity_level === 'high' across hospital.blade.php/app.js/admin.blade.php/welcome.blade.php, and bind #ws-status-indicator to window.Echo.connector.pusher.connection state events.

---

### 3.4 Infra & Deployment (7 Temuan)

#### [Critical (Est.)] `infra-hardcoded-fallback-secrets-and-reverb-keys` — Hardcoded fallback secrets in Compose and shared Reverb credentials across Compose, Reverb config, and Vite Echo client

- **Deskripsi & Potensi Dampak**: compose.yaml falls back to DB_PASSWORD:-secret (lines 12, 159) and REVERB_APP_SECRET:-cariambulan-secret (line 21), overriding config/reverb.php's APP_KEY-derived HMAC secret whenever .env omits or leaves REVERB_APP_SECRET empty (as .env.example does). config/reverb.php also defines a second app (medicgo-app / medicgo-key) sharing the same secret. With cariambulan-secret, an attacker can forge Pusher HTTP API signatures (injecting events, enumerating channels, or terminating connections) and forge private-channel subscription tokens. Separately, resources/js/echo.js falls back to mismatched app key 'qhbpeh3fhgzvx1pxqag4', port 8080, and scheme 'http', which .github/workflows/deploy.yml bakes into frontend assets because VITE_REVERB_* is unset during npm run build.
- **Akar Masalah**: compose.yaml supplies known literal secret fallbacks for PostgreSQL and Reverb, config/reverb.php shares that secret with medicgo-app, and echo.js/deploy.yml default to mismatched plaintext WebSocket parameters.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`compose.yaml:21`](../compose.yaml#L21) (`x-broadcast-env / x-db-env`) — Resolves REVERB_APP_SECRET:-cariambulan-secret (line 21), REVERB_APP_KEY:-cariambulan-key (line 20), REVERB_APP_ID:-cariambulan (line 19), and DB_PASSWORD:-secret (lines 12, 159) when unset or empty in .env.
  2. **[PROPAGATION]** [`config/reverb.php:78`](../config/reverb.php#L78) (`apps.apps`) — Consumes env('REVERB_APP_SECRET') for cariambulan and shares the exact same secret with hardcoded app medicgo-app / medicgo-key (lines 100-102).
  3. **[SINK]** [`vendor/laravel/reverb/src/Protocols/Pusher/Http/Controllers/Controller.php:115`](../vendor/laravel/reverb/src/Protocols/Pusher/Http/Controllers/Controller.php#L115) (`Controller::verifySignature`) — Authenticates Pusher HTTP API requests (EventsController, ChannelsController, UsersTerminateController) and private-channel subscriptions (InteractsWithPrivateChannels::verify) via HMAC-SHA256 using $application->secret().
- **Bukti Tambahan**:
  - [`compose.yaml:12`](../compose.yaml#L12) — Hardcodes DB_PASSWORD: ${DB_PASSWORD:-secret} (and POSTGRES_PASSWORD at line 159), whereas config/database.php:94 defaults password to empty string ''.
  - [`compose.yaml:21`](../compose.yaml#L21) — Hardcodes REVERB_APP_SECRET: ${REVERB_APP_SECRET:-cariambulan-secret}; .env.example omits REVERB_* completely, causing Compose to inject cariambulan-secret and override config/reverb.php:78 and config/broadcasting.php:38.
  - [`config/reverb.php:100`](../config/reverb.php#L100) — Defines second app key 'medicgo-key' / app_id 'medicgo-app' sharing the same secret at line 101.
  - [`resources/js/echo.js:6`](../resources/js/echo.js#L6) — Falls back to public key 'qhbpeh3fhgzvx1pxqag4', port 8080, and scheme 'http' (lines 6-9); .github/workflows/deploy.yml:36 runs npm run build without setting VITE_REVERB_*.
  - [`vendor/laravel/reverb/src/Protocols/Pusher/Channels/Concerns/InteractsWithPrivateChannels.php:36`](../vendor/laravel/reverb/src/Protocols/Pusher/Channels/Concerns/InteractsWithPrivateChannels.php#L36) — Verifies private channel subscriptions using HMAC-SHA256 with $connection->app()->secret().
- **Penghambat Validasi (`blockers`)**:
  - Actual .env values for DB_PASSWORD and REVERB_APP_SECRET on the VPS are excluded from git and rsync (.github/workflows/deploy.yml:42) and cannot be observed from source.
  - Container execution is blocked under sandboxed-source-and-local-only.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Render docker compose config with an .env copied from .env.example (without DB_PASSWORD or REVERB_APP_SECRET set) and inspect the resolved environment for db, app, and reverb containers.
  - *Lingkungan Deploy*: Have the deployment owner verify in the VPS .env (without printing secret values) that non-default high-entropy DB_PASSWORD and REVERB_APP_SECRET values are configured, and inspect built public/build/assets/*.js for VITE_REVERB_* values.
- **Rekomendasi Perbaikan Terkecil**: Require DB_PASSWORD and REVERB_APP_SECRET via ${VAR:?must be set} in compose.yaml, remove the unused medicgo-app entry from config/reverb.php, remove hardcoded fallback connection values in resources/js/echo.js, and supply production VITE_REVERB_* values in .github/workflows/deploy.yml.

#### [Critical (Est.)] `infra-unconditional-demo-data-seeding` — Unconditional SEED_DEMO_DATA=true in compose.yaml seeds predictable demo accounts in non-local deployments

- **Deskripsi & Potensi Dampak**: compose.yaml sets SEED_DEMO_DATA: "true" on the app service deployed by .github/workflows/deploy.yml. On every container boot, docker/entrypoint.sh executes php artisan db:seed --class=DemoDataSeeder --force, bypassing DatabaseSeeder's app()->isLocal() check and provisioning active hospital_admin, driver, and user accounts with hardcoded public passwords.
- **Akar Masalah**: compose.yaml unconditionally enables SEED_DEMO_DATA="true" and docker/entrypoint.sh invokes DemoDataSeeder directly without checking app()->isLocal().
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`compose.yaml:64`](../compose.yaml#L64) (`services.app.environment`) — Hardcodes SEED_DEMO_DATA: "true" on the app container started by .github/workflows/deploy.yml:87.
  2. **[PROPAGATION]** [`docker/entrypoint.sh:33`](../docker/entrypoint.sh#L33) (`entrypoint SEED_DEMO_DATA hook`) — Executes php artisan db:seed --class=DemoDataSeeder --force on startup (lines 32-34), bypassing DatabaseSeeder's app()->isLocal() guard (DatabaseSeeder.php:19).
  3. **[SINK]** [`database/seeders/DemoDataSeeder.php:25`](../database/seeders/DemoDataSeeder.php#L25) (`DemoDataSeeder::run`) — Provisions hospital_admin (hospital@cariambulan.id / rs.sehat@cariambulan.id with Hospital@123), driver (driver@cariambulan.id with Driver@123), and user (pasien@cariambulan.id with Pasien@123) via User::firstOrCreate.
- **Bukti Tambahan**:
  - [`compose.yaml:64`](../compose.yaml#L64) — Sets SEED_DEMO_DATA: "true" on services.app.environment.
  - [`docker/entrypoint.sh:32`](../docker/entrypoint.sh#L32) — Unconditionally runs DemoDataSeeder --force whenever SEED_DEMO_DATA=true (lines 32-34).
  - [`database/seeders/DatabaseSeeder.php:19`](../database/seeders/DatabaseSeeder.php#L19) — Guards DemoDataSeeder behind if (app()->isLocal()), which entrypoint.sh bypasses.
  - [`database/seeders/DemoDataSeeder.php:30`](../database/seeders/DemoDataSeeder.php#L30) — Seeds Hospital@123 for hospital@cariambulan.id (line 30) and rs.sehat@cariambulan.id (line 38), Driver@123 (line 75), and Pasien@123 (line 97).
- **Penghambat Validasi (`blockers`)**:
  - Container and database execution is blocked under sandboxed-source-and-local-only on this non-sandboxed Windows host.
  - Whether demo accounts remain active with their seeded passwords on the deployed VPS depends on runtime PostgreSQL user rows and any out-of-band compose override outside the repository.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated Docker Compose or SQLite test harness with APP_ENV=production and SEED_DEMO_DATA=true, run DemoDataSeeder / entrypoint logic and verify hospital@cariambulan.id, rs.sehat@cariambulan.id, driver@cariambulan.id, and pasien@cariambulan.id are created with public passwords.
  - *Lingkungan Deploy*: Have the deployment owner inspect the VPS compose config and query the users table (with non-secret existence/timestamp checks) to verify whether seeded demo accounts exist in production and rotate/remove them.
- **Rekomendasi Perbaikan Terkecil**: Remove SEED_DEMO_DATA: "true" from compose.yaml (default to "false"), and guard DemoDataSeeder::run() and docker/entrypoint.sh with app()->isLocal() checks.

#### [High (Est.)] `infra-deploy-entrypoint-missing-prod-env-debug-guards` — CI/CD deploy workflow and entrypoint fail to enforce APP_ENV=production and APP_DEBUG=false

- **Deskripsi & Potensi Dampak**: docker/entrypoint.sh initializes missing .env files by copying .env.example (which sets APP_ENV=local, APP_DEBUG=true, LOG_LEVEL=debug, and empty ADMIN_PASSWORD), and also runs composer install without --no-dev if vendor/autoload.php is absent. .github/workflows/deploy.yml modifies APP_URL, SESSION_SECURE_COOKIE, and LIVEKIT_URL in .env, but never sets or verifies APP_ENV=production or APP_DEBUG=false. Any deployment using an .env derived from .env.example without manual editing leaks full debug error pages/stack traces and leaves app()->isLocal() true, bypassing AdminUserSeeder's ADMIN_PASSWORD requirement (allowing 'Admin@123') and DatabaseSeeder's local-only demo guard.
- **Akar Masalah**: entrypoint.sh bootstraps .env from local-debug defaults and deploy.yml fails to enforce APP_ENV=production and APP_DEBUG=false in deployed .env configuration.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`docker/entrypoint.sh:10`](../docker/entrypoint.sh#L10) (`entrypoint`) — Copies .env.example (APP_ENV=local, APP_DEBUG=true, empty ADMIN_PASSWORD) to .env when .env does not exist (lines 9-11).
  2. **[PROPAGATION]** [`.github/workflows/deploy.yml:66`](../.github/workflows/deploy.yml#L66) (`Ensure HTTPS settings`) — Patches only APP_URL, SESSION_SECURE_COOKIE, and LIVEKIT_URL in .env (lines 58-77) without enforcing APP_ENV=production or APP_DEBUG=false.
  3. **[SINK]** [`database/seeders/AdminUserSeeder.php:19`](../database/seeders/AdminUserSeeder.php#L19) (`AdminUserSeeder::run`) — Accepts default public 'Admin@123' password when app()->isLocal() is true (lines 19-20), while APP_DEBUG=true enables detailed exception/trace rendering.
- **Bukti Tambahan**:
  - [`.env.example:2`](../.env.example#L2) — Defaults APP_ENV=local (line 2), APP_DEBUG=true (line 4), ADMIN_PASSWORD= (line 8), and LOG_LEVEL=debug (line 24).
  - [`docker/entrypoint.sh:6`](../docker/entrypoint.sh#L6) — Runs composer install without --no-dev when vendor/autoload.php is absent (lines 5-7) and copies .env.example on lines 9-11.
  - [`.github/workflows/deploy.yml:66`](../.github/workflows/deploy.yml#L66) — Rewrites APP_URL and SESSION_SECURE_COOKIE (lines 66-68) and cleans up LIVEKIT_API_URL/TURN_SECRET (lines 74-77) without setting APP_ENV or APP_DEBUG.
  - [`database/seeders/AdminUserSeeder.php:19`](../database/seeders/AdminUserSeeder.php#L19) — Enforces non-empty ADMIN_PASSWORD only when ! app()->isLocal().
- **Penghambat Validasi (`blockers`)**:
  - The live .env file on the VPS (/root/testing-bang-/.env) is excluded from git and rsync and is not visible in source.
  - Execution is blocked under sandboxed-source-and-local-only.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Initialize an isolated container from .env.example + entrypoint.sh and verify config('app.env') is 'local', config('app.debug') is true, and AdminUserSeeder succeeds with empty ADMIN_PASSWORD.
  - *Lingkungan Deploy*: Have the deployment owner verify APP_ENV=production, APP_DEBUG=false, LOG_LEVEL, and non-empty ADMIN_PASSWORD in /root/testing-bang-/.env on the VPS.
- **Rekomendasi Perbaikan Terkecil**: Explicitly enforce APP_ENV=production, APP_DEBUG=false, and LOG_LEVEL=warning in .github/workflows/deploy.yml (and fail deployment if ADMIN_PASSWORD/DB_PASSWORD/REVERB_APP_SECRET are empty), and pass --no-dev if composer install runs in entrypoint.sh.

#### [High (Est.)] `infra-missing-queue-worker-unprocessed-call-jobs` — No queue worker service in compose.yaml or deploy.yml while QUEUE_CONNECTION=database leaves call jobs unprocessed

- **Deskripsi & Potensi Dampak**: config/queue.php and .env.example default QUEUE_CONNECTION to database, and compose.yaml does not override it. Neither compose.yaml nor .github/workflows/deploy.yml runs php artisan queue:work. CallLifecycleService dispatches three ShouldQueue jobs (SendIncomingCallNotification, delayed ExpireRingingCall, and CleanupCallRoom), and routes/console.php only cleans up legacy TripCall records (TripCall::markMissed), not App\Models\Call. As a result, FCM incoming-call pushes never send, LiveKit room cleanup jobs pile up, and unanswered ringing Call records never expire, blocking caller and callee in Call::ongoing() checks.
- **Akar Masalah**: Production Compose and CI/CD deploy definitions omit a queue worker container while queueing CallLifecycleService ShouldQueue jobs on the database driver.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Api/V1/CallController.php:49`](../app/Http/Controllers/Api/V1/CallController.php#L49) (`CallController::store`) — Invokes CallLifecycleService::startCall on POST /api/v1/calls (also accept/reject/cancel/end endpoints).
  2. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:90`](../app/Services/Call/CallLifecycleService.php#L90) (`CallLifecycleService::startCall`) — Dispatches ShouldQueue jobs SendIncomingCallNotification (line 90), ExpireRingingCall (line 94), and CleanupCallRoom (lines 170, 201, 243) while checking Call::ongoing() at lines 43-49.
  3. **[SINK]** [`compose.yaml:57`](../compose.yaml#L57) (`services`) — Runs only app, web, caddy, scheduler (schedule:work), reverb, redis, and db with QUEUE_CONNECTION=database (.env.example:41, config/queue.php:16) and no queue:work container.
- **Bukti Tambahan**:
  - [`.env.example:41`](../.env.example#L41) — Sets QUEUE_CONNECTION=database.
  - [`config/queue.php:16`](../config/queue.php#L16) — Defaults queue connection to env('QUEUE_CONNECTION', 'database').
  - [`.github/workflows/deploy.yml:87`](../.github/workflows/deploy.yml#L87) — Starts only app, web, caddy, scheduler, and reverb services with no queue worker.
  - [`app/Services/Call/CallLifecycleService.php:43`](../app/Services/Call/CallLifecycleService.php#L43) — Rejects new calls with HTTP 409 when Call::ongoing() exists for caller (line 43) or receiver (line 47); un-expired ringing Calls block both parties.
  - [`app/Jobs/Call/ExpireRingingCall.php:16`](../app/Jobs/Call/ExpireRingingCall.php#L16) — Implements ShouldQueue to expire ringing Call rows after ringing_timeout.
  - [`routes/console.php:29`](../routes/console.php#L29) — Schedules TripCall::markMissed() everyMinute for legacy TripCall only, with no fallback expiration for App\Models\Call.
- **Penghambat Validasi (`blockers`)**:
  - Actual QUEUE_CONNECTION value in the VPS .env and whether an external supervisor runs queue:work are outside repository visibility.
  - Container execution is blocked under sandboxed-source-and-local-only.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: With QUEUE_CONNECTION=database and no queue:work running, call CallLifecycleService::startCall in a test and verify jobs accumulate in the jobs table while the Call stays in ringing status past ringing_timeout and blocks subsequent calls with HTTP 409.
  - *Lingkungan Deploy*: Have the owner check QUEUE_CONNECTION in the VPS .env, check running containers/processes for queue:work, and check pending row counts in the PostgreSQL jobs table.
- **Rekomendasi Perbaikan Terkecil**: Add a dedicated queue worker service running php artisan queue:work --sleep=3 --tries=3 --max-time=3600 to compose.yaml and include it in .github/workflows/deploy.yml.

#### [High (Est.)] `infra-plaintext-ports-exposed-bypassing-caddy` — Unencrypted PostgreSQL (5433), HTTP Nginx (8080), and Reverb WS/HTTP (8081) exposed on 0.0.0.0 bypassing Caddy TLS

- **Deskripsi & Potensi Dampak**: Alongside Caddy TLS on ports 80/443/8443, compose.yaml publishes web on 0.0.0.0:8080, reverb on 0.0.0.0:8081, and PostgreSQL (postgres:17-alpine without TLS) on 0.0.0.0:5433. Direct access to :8080 bypasses Caddy TLS and X-Forwarded-* sanitization; :8081 exposes unencrypted ws:// and Reverb's Pusher HTTP API; :5433 exposes PostgreSQL directly.
- **Akar Masalah**: compose.yaml publishes internal backend services (web, reverb, db) on wildcard host interfaces instead of keeping them internal behind Caddy.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`compose.yaml:85`](../compose.yaml#L85) (`services.web.ports / services.reverb.ports / services.db.ports`) — Publishes web on ${APP_PORT:-8080}:80 (line 85), reverb on 8081:8081 (line 137), and db on ${FORWARD_DB_PORT:-5433}:5432 (line 162) on 0.0.0.0.
  2. **[SINK]** [`docker/nginx.conf:4`](../docker/nginx.conf#L4) (`server`) — Serves unencrypted HTTP on port 80 (host 8080) bypassing Caddy's TLS proxy (docker/Caddyfile:4-11), while PostgreSQL accepts non-TLS connections (config/database.php:99 sslmode=prefer).
- **Bukti Tambahan**:
  - [`compose.yaml:85`](../compose.yaml#L85) — Publishes Nginx HTTP on host port 8080 (0.0.0.0) bypassing Caddy TLS.
  - [`compose.yaml:137`](../compose.yaml#L137) — Publishes Reverb WebSocket and Pusher HTTP API on host port 8081 (0.0.0.0) bypassing Caddy wss:// on 8443.
  - [`compose.yaml:162`](../compose.yaml#L162) — Publishes postgres:17-alpine on host port 5433 (0.0.0.0) with no TLS configured.
  - [`docker/Caddyfile:4`](../docker/Caddyfile#L4) — Configures HTTPS (443) and WSS (8443) termination intended to front web:80 and reverb:8081.
  - [`config/database.php:99`](../config/database.php#L99) — pgsql connection defaults sslmode to 'prefer'.
- **Penghambat Validasi (`blockers`)**:
  - External reachability of host ports 8080, 8081, and 5433 depends on VPS host firewall (e.g., DOCKER-USER iptables rules) or cloud security groups, which are outside repository source.
  - Container execution is blocked under sandboxed-source-and-local-only.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Run docker compose config on compose.yaml to verify web (8080), reverb (8081), and db (5433) bind to 0.0.0.0 without 127.0.0.1 host restriction.
  - *Lingkungan Deploy*: Have the infrastructure owner inspect VPS firewall / DOCKER-USER iptables / cloud security group rules and host listening sockets (ss -tlnp) to verify whether 5433, 8080, and 8081 are reachable externally.
- **Rekomendasi Perbaikan Terkecil**: Remove host ports mappings from web, reverb, and db in production Compose (or bind strictly to 127.0.0.1:...) so all external ingress passes through Caddy TLS on 443 and 8443.

#### [High (Est.)] `infra-public-login-hardcoded-demo-credentials` — Public login page unconditionally renders plaintext administrator and hospital passwords with one-click autofill

- **Deskripsi & Potensi Dampak**: GET /login renders resources/views/auth/login.blade.php without an environment or debug guard, exposing plaintext admin@cariambulan.id (Admin@123) and hospital@cariambulan.id (Hospital@123) credentials in HTML and inline JavaScript autofill buttons to any unauthenticated visitor.
- **Akar Masalah**: resources/views/auth/login.blade.php hardcodes privileged demo credentials and autofill JavaScript unconditionally without gating on app()->isLocal().
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/web.php:23`](../routes/web.php#L23) (`guest login route`) — Maps unauthenticated GET /login to LoginController::showLoginForm.
  2. **[PROPAGATION]** [`app/Http/Controllers/Auth/LoginController.php:21`](../app/Http/Controllers/Auth/LoginController.php#L21) (`LoginController::showLoginForm`) — Returns view('auth.login') unconditionally regardless of APP_ENV or APP_DEBUG.
  3. **[SINK]** [`resources/views/auth/login.blade.php:51`](../resources/views/auth/login.blade.php#L51) (`auth/login.blade.php`) — Outputs plaintext admin@cariambulan.id (Admin@123) and hospital@cariambulan.id (Hospital@123) in HTML (lines 51-52) and autofill script (lines 80-94).
- **Bukti Tambahan**:
  - [`resources/views/auth/login.blade.php:51`](../resources/views/auth/login.blade.php#L51) — HTML unconditionally renders Admin@123 and Hospital@123 (lines 51-52) alongside #autofillAdminBtn and #autofillHospitalBtn (lines 42-47).
  - [`resources/views/auth/login.blade.php:82`](../resources/views/auth/login.blade.php#L82) — Inline <script> binds click handlers populating #email and #password with plaintext Admin@123 and Hospital@123 (lines 82-93).
  - [`app/Http/Controllers/Auth/LoginController.php:21`](../app/Http/Controllers/Auth/LoginController.php#L21) — showLoginForm() passes straight to auth.login with no environment check.
- **Penghambat Validasi (`blockers`)**:
  - Browser/HTTP server execution is blocked under sandboxed-source-and-local-only on this non-sandboxed Windows host.
  - Exploitability against live accounts depends on whether DemoDataSeeder (INFRA-02) or local AdminUserSeeder (INFRA-08) passwords remain active on the deployed database.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Render view('auth.login') locally under APP_ENV=production and APP_DEBUG=false and confirm the plaintext Admin@123 and Hospital@123 strings and autofill script still appear in the rendered HTML.
  - *Lingkungan Deploy*: Have the deployment owner inspect GET /login response HTML in the deployed environment and verify whether the displayed credentials have been removed and demo passwords disabled.
- **Rekomendasi Perbaikan Terkecil**: Remove the demo credential panel and autofill JavaScript from resources/views/auth/login.blade.php, or wrap both blocks in @if (app()->isLocal() && config('app.debug')).

#### [High (Est.)] `infra-wildcard-trust-proxies-exposed-http-spoofing` — trustProxies(at: '*') with directly exposed :8080 enables X-Forwarded-For rate-limit bypass and X-Forwarded-Proto/Host spoofing

- **Deskripsi & Potensi Dampak**: bootstrap/app.php calls $middleware->trustProxies(at: '*'), instructing Symfony Request to treat 0.0.0.0/0 and ::/0 as trusted proxies for all X-Forwarded-* headers. Because compose.yaml publishes Nginx directly on 0.0.0.0:8080 bypassing Caddy and docker/nginx.conf passes X-Forwarded-* headers unmodified to PHP-FPM, attackers hitting :8080 can spoof X-Forwarded-For to bypass throttle:6,1 on /api/v1/auth/login and /api/v1/auth/register and the 5-attempt RateLimiter on /login, spoof X-Forwarded-Host to alter CallSignalingController::iceServers TURN fallback hosts, and spoof X-Forwarded-Proto.
- **Akar Masalah**: bootstrap/app.php trusts wildcard proxies ('*') while Nginx port 8080 is published directly without proxy-header sanitization.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`compose.yaml:85`](../compose.yaml#L85) (`services.web.ports`) — Publishes Nginx HTTP on 0.0.0.0:8080 directly bypassing Caddy.
  2. **[PROPAGATION]** [`docker/nginx.conf:17`](../docker/nginx.conf#L17) (`location ~ \.php$`) — Forwards client-supplied HTTP_X_FORWARDED_* headers directly to app:9000 via fastcgi_params (line 20).
  3. **[PROPAGATION]** [`bootstrap/app.php:24`](../bootstrap/app.php#L24) (`withMiddleware`) — Sets $middleware->trustProxies(at: '*'), making TrustProxies register ['0.0.0.0/0', '::/0'] for all X-Forwarded-* headers.
  4. **[PROPAGATION]** [`vendor/symfony/http-foundation/Request.php:2239`](../vendor/symfony/http-foundation/Request.php#L2239) (`Request::normalizeAndFilterClientIps`) — Strips every IP matching 0.0.0.0/0 and falls back to $firstTrustedIp (the leftmost attacker-controlled X-Forwarded-For IP).
  5. **[SINK]** [`app/Http/Controllers/Auth/LoginController.php:34`](../app/Http/Controllers/Auth/LoginController.php#L34) (`LoginController::login`) — Builds login rate-limit key from $request->ip() (and routes/api.php:30,34 throttle:6,1 / CallSignalingController.php:111 $request->getHost() / Request::isSecure()).
- **Bukti Tambahan**:
  - [`bootstrap/app.php:24`](../bootstrap/app.php#L24) — Calls $middleware->trustProxies(at: '*');
  - [`vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:123`](../vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php#L123) — Expands '*' to ['0.0.0.0/0', '::/0'] for X-Forwarded-For/Host/Port/Proto/Prefix/AWS-ELB.
  - [`vendor/symfony/http-foundation/Request.php:2248`](../vendor/symfony/http-foundation/Request.php#L2248) — Returns [$firstTrustedIp] when all IPs match trustedProxies.
  - [`routes/api.php:30`](../routes/api.php#L30) — Applies throttle:6,1 (keyed by client IP) to POST /api/v1/auth/register (line 30) and POST /api/v1/auth/login (line 34).
  - [`app/Http/Controllers/Auth/LoginController.php:34`](../app/Http/Controllers/Auth/LoginController.php#L34) — Constructs 5-attempt RateLimiter key using $request->ip().
  - [`app/Http/Controllers/Api/V1/CallSignalingController.php:111`](../app/Http/Controllers/Api/V1/CallSignalingController.php#L111) — Falls back to $request->getHost() (honoring X-Forwarded-Host) for TURN server URLs when TURN_HOST is unset.
- **Penghambat Validasi (`blockers`)**:
  - Whether host port 8080 is externally reachable or firewalled at the VPS/cloud edge is outside repository visibility.
  - Local HTTP/container execution is blocked under sandboxed-source-and-local-only.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed local Laravel test, issue >5 failed POST /login or >6 POST /api/v1/auth/login requests while rotating X-Forwarded-For headers and verify rate limiting is bypassed.
  - *Lingkungan Deploy*: Have the owner verify host firewall rules for port 8080 and confirm Caddy overwrites incoming X-Forwarded-* headers when accessed over ports 80/443.
- **Rekomendasi Perbaikan Terkecil**: Restrict trustProxies(at: ...) to the internal Docker proxy subnet/Caddy IP instead of '*', unpublish port 8080 in compose.yaml, and sanitize X-Forwarded-* headers at Nginx if Nginx remains reachable.

---

### 3.5 Dispatch & Fleet Concurrency (7 Temuan)

#### [Critical (Est.)] `driver-trip-unlocked-transitions-double-booking-and-call-state-leak` — Unlocked state transitions and missing driver lock in Driver\TripController allow cancelled-trip resurrection, driver double-booking, audit corruption, and stale Redis call leaks

- **Deskripsi & Potensi Dampak**: Driver\TripController::transition() (arrive, startToHospital, complete) runs with no DB::transaction or lockForUpdate(), letting stale model updates overwrite Cancelled trips back to active states while cancel() already set the driver Online. accept() locks EmergencyRequest but never locks DriverProfile before checking activeTrip(), allowing two concurrent accept() calls (or AvailabilityController::update) to double-book a driver or desynchronize availability_status. decline() updates DispatchLog without lock/transaction, overwriting Accepted/Timeout states. Mass calls()->live()->update() in complete(), cancel(), and closeAbandoned() bypasses TripCall's saved observer, leaving 2-hour TripCallStateStore Redis entries that Driver\CallController::current() returns on subsequent trips.
- **Akar Masalah**: Missing DB::transaction and lockForUpdate() on EmergencyRequest/DriverProfile/DispatchLog across TripController and AvailabilityController, plus mass TripCall::update() bypassing Eloquent saved observers for TripCallStateStore.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:131`](../routes/api.php#L131) (`api.v1.driver.requests.*`) — Authenticated driver invokes accept, decline, arrive, start-to-hospital, or complete concurrently with cancel/other offers/availability updates.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/Driver/TripController.php:216`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L216) (`Driver\TripController::transition`) — transition() reads stale route-bound status without lock; accept() (line 86) checks activeTrip() without locking DriverProfile; decline() (line 120) and complete() (line 188) run unlocked.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/Driver/TripController.php:232`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L232) (`Driver\TripController::transition`) — Overwrites Cancelled requests back to active states, double-books drivers, corrupts DispatchLog status, and mass-updates calls()->live()->update() (line 199) without clearing TripCallStateStore.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:216`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L216) — transition() (lines 216-233) executes without DB::transaction or lockForUpdate(), racing with EmergencyRequestController::cancel() (lines 107-148) and HospitalDashboardController::updateRequestStatus() (lines 343-418).
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:80`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L80) — accept() locks EmergencyRequest at line 82 but never locks DriverProfile before activeTrip() check at line 86, racing with other accept() calls and unlocked AvailabilityController::update() (lines 12-46).
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:120`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L120) — decline() (lines 120-140) performs no transaction or lockForUpdate(), allowing overwrites of Accepted or Timeout DispatchLog rows.
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:199`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L199) — complete() calls calls()->live()->update() (like EmergencyRequestController.php:142, EmergencyRequest.php:361, HospitalDashboardController.php:357,376), skipping TripCall::saved (TripCall.php:41) so TripCallStateStore (lines 26-30) retains 7200s answered-call Redis snapshots returned by Driver\CallController::current() (lines 36-46) on the next trip.
- **Penghambat Validasi (`blockers`)**:
  - Target execution against concurrent PostgreSQL transactions and Redis cache is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL + Redis harness: race cancel() vs arrive(); race two accept() calls on different requests; and answer a TripCall on trip 1, call complete(), start trip 2, and query GET /api/v1/driver/calls/current to confirm trip 1's ended call is returned.
  - *Lingkungan Deploy*: Check production emergency_requests for drivers with >1 activeTrip() or Busy drivers with 0 active trips, and check TRIP_CALL_STORE driver configuration.
- **Rekomendasi Perbaikan Terkecil**: Wrap transition(), decline(), complete(), and AvailabilityController::update in DB::transaction with consistent lockForUpdate() ordering (EmergencyRequest and DriverProfile), lock DriverProfile in accept(), and explicitly forget TripCallStateStore entries (or iterate individual TripCall updates) when ending calls.

#### [High (Est.)] `close-abandoned-unscheduled` — EmergencyRequest::closeAbandoned() is implemented for 2-hour abandoned trips but never scheduled in routes/console.php

- **Deskripsi & Potensi Dampak**: EmergencyRequest::closeAbandoned() exists to cancel open emergency requests idle for 2 hours and release drivers offline, but routes/console.php schedules only redispatchWaiting() and TripCall::markMissed(). Abandoned orders therefore remain open forever, permanently blocking the reporter from placing new SOS requests (409) and trapping the driver in activeTrip() (409).
- **Akar Masalah**: EmergencyRequest::closeAbandoned() is omitted from Laravel's task schedule in routes/console.php and has no other production caller.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[SINK]** [`routes/console.php:24`](../routes/console.php#L24) (`console scheduler`) — Schedules only redispatchWaiting() (line 24) and TripCall::markMissed() (line 29), never calling EmergencyRequest::closeAbandoned().
- **Bukti Tambahan**:
  - [`app/Models/EmergencyRequest.php:332`](../app/Models/EmergencyRequest.php#L332) — Implements closeAbandoned() using ABANDONED_AFTER_HOURS = 2 (line 95) to cancel stalled requests and free drivers.
  - [`routes/console.php:24`](../routes/console.php#L24) — Lines 24-32 schedule only redispatchWaiting() and TripCall::markMissed(); no other files in app/ or routes/ invoke closeAbandoned().
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:49`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L49) — Stalled open requests indefinitely block new SOS creation with HTTP 409.
  - [`app/Http/Controllers/Api/V1/Driver/AvailabilityController.php:17`](../app/Http/Controllers/Api/V1/Driver/AvailabilityController.php#L17) — Stalled active trips indefinitely block driver availability updates with HTTP 409.
- **Penghambat Validasi (`blockers`)**:
  - Deployment cron/scheduler execution configuration outside routes/console.php (e.g., external cron calling custom commands) is outside repository source, though no command wrapper exists in app/.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Inspect php artisan schedule:list in a sandboxed environment to confirm closeAbandoned is absent.
  - *Lingkungan Deploy*: Owner checks running scheduler jobs and queries emergency_requests for open rows older than 2 hours.
- **Rekomendasi Perbaikan Terkecil**: Schedule Schedule::call(fn () => EmergencyRequest::closeAbandoned())->everyFiveMinutes()->name('close-abandoned-emergencies')->withoutOverlapping() in routes/console.php.

#### [High (Est.)] `emergency-store-toctou-duplicate-open-sos` — TOCTOU race in EmergencyRequestController::store permits multiple concurrent open SOS requests per user

- **Deskripsi & Potensi Dampak**: EmergencyRequestController::store checks $request->user()->emergencyRequests()->open()->exists() outside any DB::transaction or row lock and without a database partial unique index, allowing concurrent POST /api/v1/emergency-requests requests to create multiple simultaneous open SOS orders for the same user.
- **Akar Masalah**: Unprotected check-then-insert on open emergency_requests per user without transaction/lockForUpdate or partial unique DB constraint.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:65`](../routes/api.php#L65) (`api.v1.emergency-requests.store`) — Concurrent POST /api/v1/emergency-requests calls from an authenticated civilian user reach store().
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:48`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L48) (`EmergencyRequestController::store`) — Checks open()->exists() without a transaction or User row lock.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:54`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L54) (`EmergencyRequestController::store`) — Inserts multiple open EmergencyRequest records and dispatches offers (line 63) for each.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:48`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L48) — Lines 48-63 execute open()->exists(), create(), and offerToNextDriver() without DB::transaction or lockForUpdate().
  - [`database/migrations/2026_11_02_100006_create_emergency_requests_table.php:14`](../database/migrations/2026_11_02_100006_create_emergency_requests_table.php#L14) — Defines no partial unique index enforcing at most one open emergency_request per user_id.
- **Penghambat Validasi (`blockers`)**:
  - Target execution against concurrent connections/PostgreSQL is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Send two parallel POST /api/v1/emergency-requests requests for the same dummy user against PostgreSQL in a sandboxed harness and verify multiple open emergency_requests rows are created.
  - *Lingkungan Deploy*: Check production emergency_requests table for users with >1 concurrent open status records.
- **Rekomendasi Perbaikan Terkecil**: Wrap EmergencyRequestController::store in DB::transaction locking the User row via lockForUpdate() before checking open()->exists(), and add a PostgreSQL partial unique index on emergency_requests(user_id) for open statuses.

#### [High (Est.)] `unlocked-widen-radius-stale-redispatch-and-discarded-driver-lock` — Unlocked widenRadius(), stale buffered model overwrite in redispatchWaiting(), and discarded DriverProfile lock in offerToNextDriver()

- **Deskripsi & Potensi Dampak**: EmergencyRequestController::widenRadius() and EmergencyRequest::widenRadius() read and update search_radius_km without transactions or lockForUpdate(). EmergencyRequest::redispatchWaiting() chunks SearchingDriver rows via ->each() without locks and calls widenRadius() on the stale buffered model, regressing concurrent retrySearch() (25 km) or widenRadius() progress. In offerToNextDriver(), DriverProfile::query()->lockForUpdate()->find($candidate->id) discards the returned locked model and never re-checks availability_status === 'online' or activeTrip() === null under lock.
- **Akar Masalah**: Missing lockForUpdate() and re-validation in widenRadius() and redispatchWaiting(), plus discarded locked DriverProfile instance and missing availability/activeTrip checks in offerToNextDriver().
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:77`](../routes/api.php#L77) (`api.v1.emergency-requests.radius`) — User radius expansion (POST /emergency-requests/{id}/radius) and retrySearch (line 80) run concurrently with scheduled/triggered redispatchWaiting() (routes/console.php:24).
  2. **[PROPAGATION]** [`app/Models/EmergencyRequest.php:405`](../app/Models/EmergencyRequest.php#L405) (`EmergencyRequest::redispatchWaiting`) — Iterates un-locked buffered EmergencyRequest models and invokes widenRadius() (lines 215-237) without locking or re-fetching.
  3. **[SINK]** [`app/Models/EmergencyRequest.php:280`](../app/Models/EmergencyRequest.php#L280) (`EmergencyRequest::offerToNextDriver`) — widenRadius() overwrites search_radius_km with stale tier progression, while offerToNextDriver() discards the locked DriverProfile from find() and never re-checks availability_status or activeTrip().
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:156`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L156) — widenRadius() (lines 156-172) checks status/radius and calls widenRadius() without transaction or lockForUpdate().
  - [`app/Models/EmergencyRequest.php:215`](../app/Models/EmergencyRequest.php#L215) — widenRadius() (lines 215-237) reads in-memory search_radius_km and writes search_radius_km/radius_changed_at without lock or SearchingDriver guard.
  - [`app/Models/EmergencyRequest.php:402`](../app/Models/EmergencyRequest.php#L402) — redispatchWaiting() (lines 402-412) fetches SearchingDriver rows via chunked each() without locks and calls $request->widenRadius() on the stale buffered model.
  - [`app/Models/EmergencyRequest.php:280`](../app/Models/EmergencyRequest.php#L280) — Calls DriverProfile::query()->lockForUpdate()->find($candidate->id) without assigning the result, checking only pending dispatchLogs on stale $candidate (line 282) rather than availability_status and activeTrip().
- **Penghambat Validasi (`blockers`)**:
  - Target execution against concurrent PostgreSQL queries is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL test, interleave redispatchWaiting() chunk iteration with retrySearch() to observe search_radius_km regression from 25 back to 5, and race AvailabilityController::update(offline)/TripController::accept with offerToNextDriver() to observe offers issued to busy/offline drivers.
  - *Lingkungan Deploy*: Monitor dispatch_logs for offers created for drivers already busy/offline or search_radius_km regressions.
- **Rekomendasi Perbaikan Terkecil**: Lock and refresh EmergencyRequest inside a DB::transaction in widenRadius() (re-checking SearchingDriver and search_radius_km); re-check under lock in redispatchWaiting(); and capture the locked DriverProfile in offerToNextDriver() to re-verify availability_status === Online and activeTrip() === null (while also locking DriverProfile in accept() and AvailabilityController::update).

#### [Medium (Est.)] `unbounded-address-storage-and-full-table-memory-loads` — Unbounded address storage and full-table PHP memory loads in Hospital::nearestTo, AmbulanceController::nearest, and EmergencySearchResource

- **Deskripsi & Potensi Dampak**: StoreUserAddressRequest imposes no max length on a TEXT column and UserAddressController enforces no row cap or pagination, while Hospital::nearestTo(), AmbulanceController::nearest(), and EmergencySearchResource::toArray() load entire hospitals or dispatchable driver_profiles tables into PHP memory without SQL bounding boxes or limits on unthrottled endpoints.
- **Akar Masalah**: Missing length/count/pagination bounds on user addresses and omitted SQL spatial bounding/limiting in nearest-hospital and nearest-ambulance queries.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:53`](../routes/api.php#L53) (`api.v1.me.addresses.store`) — Unthrottled routes expose POST/GET /me/addresses (52-53), GET /hospitals/nearest (56), GET /ambulances/nearest (59), and GET /emergency-requests/{id} (71).
  2. **[PROPAGATION]** [`app/Http/Requests/Api/V1/StoreUserAddressRequest.php:21`](../app/Http/Requests/Api/V1/StoreUserAddressRequest.php#L21) (`StoreUserAddressRequest::rules`) — Allows unbounded address strings, while AmbulanceController::nearest (line 19) and EmergencySearchResource::toArray (line 43) omit $radiusKm when calling DriverProfile::nearestDispatchableTo().
  3. **[SINK]** [`app/Models/Hospital.php:58`](../app/Models/Hospital.php#L58) (`Hospital::nearestTo`) — Executes full-table ->get() into PHP memory (alongside DriverProfile::nearestDispatchableTo at line 120 and UserAddressController::index at line 20).
- **Bukti Tambahan**:
  - [`app/Http/Requests/Api/V1/StoreUserAddressRequest.php:21`](../app/Http/Requests/Api/V1/StoreUserAddressRequest.php#L21) — Validates 'address' => ['required', 'string'] with no max: against TEXT column in 2026_11_05_040249_create_user_addresses_table.php:18.
  - [`app/Http/Controllers/Api/V1/UserAddressController.php:20`](../app/Http/Controllers/Api/V1/UserAddressController.php#L20) — index() returns unpaginated ->get() and store() at line 30 enforces no per-user address count cap.
  - [`app/Models/Hospital.php:56`](../app/Models/Hospital.php#L56) — nearestTo() loads all hospitals with coordinates via ->get(), invoked on GET /hospitals/nearest, DispatchOfferResource.php:22, and TripController.php:164.
  - [`app/Http/Controllers/Api/V1/AmbulanceController.php:19`](../app/Http/Controllers/Api/V1/AmbulanceController.php#L19) — Calls DriverProfile::nearestDispatchableTo($lat, $lng) without $radiusKm, skipping the SQL bounding box at DriverProfile.php:113.
  - [`app/Http/Resources/V1/EmergencySearchResource.php:43`](../app/Http/Resources/V1/EmergencySearchResource.php#L43) — Calls nearestDispatchableTo() without self::NEARBY_RADIUS_KM before filtering <= 25 in PHP (line 45) and runs queries even after status leaves SearchingDriver.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only), and production dataset scale and memory limits are outside repository source.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed fixture test, insert large address payloads up to nginx client_max_body_size (10m) and inspect SQL query count/bound behavior of nearestTo() and nearestDispatchableTo() with seeded hospitals/drivers.
  - *Lingkungan Deploy*: Observe production row counts in hospitals, driver_profiles, and user_addresses plus PHP memory_limit.
- **Rekomendasi Perbaikan Terkecil**: Add max:500 and per-user row cap/pagination to UserAddress endpoints, compute Haversine/bounding-box + LIMIT 1 in SQL for Hospital::nearestTo() and AmbulanceController::nearest(), pass NEARBY_RADIUS_KM in EmergencySearchResource, and skip nearby-driver queries once no longer searching_driver.

#### [Medium (Est.)] `unthrottled-retry-search-deletes-dispatch-logs-and-dispatch-spam` — Unthrottled retrySearch() deletes DispatchLog audit history and unthrottled SOS store/cancel/retry endpoints enable driver push spam

- **Deskripsi & Potensi Dampak**: EmergencyRequestController::retrySearch() hard-deletes Declined and Timeout DispatchLog records outside a transaction/lock and without requiring search exhaustion or cooldown, destroying dispatch audit history and allowing immediate re-ringing of declining drivers. Additionally, emergency-requests store, cancel, radius, and retry routes lack throttle middleware, enabling tight store/cancel or retry loops to spam drivers with FCM wake pushes and contend for OFFER_FANOUT=3 slots.
- **Akar Masalah**: retrySearch() deletes historical DispatchLog rows without exhaustion/cooldown/transaction checks and emergency-request mutation routes lack rate limiting.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:80`](../routes/api.php#L80) (`api.v1.emergency-requests.retry`) — Unthrottled POST /api/v1/emergency-requests/{id}/retry (and store/cancel/radius at lines 65, 74, 77) accepts rapid calls from any self-registered user.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:186`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L186) (`EmergencyRequestController::retrySearch`) — Resets search_radius_km=25 without lock, cooldown, or checking isSearchExhausted().
  3. **[SINK]** [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:192`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L192) (`EmergencyRequestController::retrySearch`) — Hard-deletes Declined/Timeout DispatchLog rows and invokes offerToNextDriver() (line 196), triggering OFFER_FANOUT=3 offers and FCM pushes (EmergencyRequest.php:317).
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:192`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L192) — Deletes dispatchLogs()->whereIn('status', [Declined, Timeout]) without transaction/lock or checking search_exhausted_at/cooldown.
  - [`routes/api.php:65`](../routes/api.php#L65) — Lines 65-82 define no throttle middleware on store, cancel, radius, or retry.
  - [`app/Models/EmergencyRequest.php:256`](../app/Models/EmergencyRequest.php#L256) — Offers up to OFFER_FANOUT = 3 drivers per wave and queues post-response FCM wakeups at line 317.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only), and external reverse-proxy rate limiting outside the repository is unknown.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed harness, decline an offer as a dummy driver, immediately call POST /api/v1/emergency-requests/{id}/retry repeatedly, and verify prior Declined DispatchLog rows are deleted and the same driver receives repeated Offered rows/FCM pushes.
  - *Lingkungan Deploy*: Verify whether any external WAF/reverse-proxy rate limit sits in front of Caddy/nginx for /api/v1/emergency-requests*.
- **Rekomendasi Perbaikan Terkecil**: Preserve DispatchLog rows by adding an offer round/attempt column instead of deleting rows, require search_exhausted_at or cooldown inside DB::transaction+lockForUpdate in retrySearch(), and add throttle middleware to SOS store/cancel/radius/retry.

#### [Low (Est.)] `driver-trips-index-null-patient-name` — Driver\TripController::index reads nonexistent $trip->user?->name returning null for patient_name

- **Deskripsi & Potensi Dampak**: Driver\TripController::index maps patient_name from $trip->user?->name, whereas User has column full_name and no name column or accessor, causing GET /api/v1/driver/trips to always return patient_name: null (and maps request_at from created_at instead of request_at).
- **Akar Masalah**: Driver\TripController::index references nonexistent attribute $trip->user?->name instead of $trip->user?->full_name.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:121`](../routes/api.php#L121) (`api.v1.driver.trips.index`) — Authenticated driver calls GET /api/v1/driver/trips.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/Driver/TripController.php:28`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L28) (`TripController::index`) — Loads completed/cancelled emergencyRequests with user relation.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/Driver/TripController.php:43`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L43) (`TripController::index`) — Reads $trip->user?->name, which evaluates to null for every trip.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:43`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L43) — Sets 'patient_name' => $trip->user?->name (and line 42 sets 'request_at' => $trip->created_at?->toIso8601String()).
  - [`app/Models/User.php:16`](../app/Models/User.php#L16) — Defines fillable 'full_name' with no 'name' attribute or accessor.
  - [`database/migrations/0001_01_01_000000_create_users_table.php:16`](../database/migrations/0001_01_01_000000_create_users_table.php#L16) — Defines users.full_name with no name column.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Call GET /api/v1/driver/trips for a driver with a completed dummy EmergencyRequest and observe data.0.patient_name is null.
  - *Lingkungan Deploy*: No deployment dependency; reproducible purely from application logic.
- **Rekomendasi Perbaikan Terkecil**: Change $trip->user?->name to $trip->user?->full_name and $trip->created_at to ($trip->request_at ?? $trip->created_at) in Driver\TripController::index.

---

### 3.6 Call Lifecycle & Signaling (10 Temuan)

#### [High (Est.)] `call-start-orphan-ringing-lockout-and-room-failure` — Orphan Ringing Call Permanently Locks Both Participants When Token Generation Fails After DB Commit, Plus Ignored Room Failures and Missing Queue Worker

- **Deskripsi & Potensi Dampak**: CallLifecycleService::startCall() commits the Call (status => Ringing) and CallParticipant rows inside DB::transaction BEFORE calling liveKitService->createRoom(), tokenService->createAudioTokenCredentials(), and ExpireRingingCall::dispatch(). When LIVEKIT_API_KEY/LIVEKIT_API_SECRET are unset or <32 bytes, LiveKitService::createRoom() returns a simulated array while createAudioTokenCredentials() throws RuntimeException (HTTP 500) after committing the Ringing row and before dispatching ExpireRingingCall. Even with LiveKit credentials configured, createRoom() returns null on LiveKit HTTP error without startCall() aborting, compose.yaml runs no queue worker for QUEUE_CONNECTION=database, ExpireRingingCall skips calls under the sync driver when elapsed < timeoutSeconds - 2, and routes/console.php schedules no Call expiration fallback — leaving Ringing calls permanently blocking both caller and receiver via Call::scopeOngoing().
- **Akar Masalah**: CallLifecycleService::startCall() commits the Ringing Call prior to LiveKit credential validation, room creation, and expiration job scheduling, ignores createRoom() failures, and relies solely on a delayed ExpireRingingCall job without a queue worker service or scheduled fallback sweep.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:97`](../routes/api.php#L97) (`api.v1.calls.store`) — Authenticated user sends POST /api/v1/calls to initiate a 1-on-1 call.
  2. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:54`](../app/Services/Call/CallLifecycleService.php#L54) (`CallLifecycleService::startCall`) — Commits Ringing Call and CallParticipant rows inside DB::transaction (lines 54-81) before external LiveKit operations.
  3. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:84`](../app/Services/Call/CallLifecycleService.php#L84) (`CallLifecycleService::startCall`) — Calls liveKitService->createRoom($roomName) ignoring null/simulated results, then calls tokenService->createAudioTokenCredentials() at line 87 before ExpireRingingCall::dispatch() at line 94.
  4. **[SINK]** [`app/Services/Call/LiveKitTokenService.php:154`](../app/Services/Call/LiveKitTokenService.php#L154) (`LiveKitTokenService::ensureConfigured`) — Throws RuntimeException after DB commit when LiveKit credentials are missing or <32 chars, returning HTTP 500 with no call_id while leaving a permanent Ringing Call that triggers 409 via Call::scopeOngoing().
- **Bukti Tambahan**:
  - [`app/Services/Call/CallLifecycleService.php:54`](../app/Services/Call/CallLifecycleService.php#L54) — DB::transaction commits Ringing Call (lines 54-81) prior to createRoom() (line 84), createAudioTokenCredentials() (line 87), and ExpireRingingCall::dispatch() (line 94).
  - [`app/Services/Call/LiveKitService.php:70`](../app/Services/Call/LiveKitService.php#L70) — createRoom() returns a simulated array when credentials are empty (lines 70-78) and returns null on LiveKit HTTP failure/exception (lines 96-103).
  - [`app/Services/Call/LiveKitTokenService.php:154`](../app/Services/Call/LiveKitTokenService.php#L154) — ensureConfigured() throws RuntimeException if apiKey/apiSecret is empty or strlen(apiSecret) < 32.
  - [`app/Jobs/Call/ExpireRingingCall.php:33`](../app/Jobs/Call/ExpireRingingCall.php#L33) — Skips expiring calls if started_at->diffInSeconds(now()) < ($timeoutSeconds - 2), preventing expiration under sync queue driver.
  - [`compose.yaml:57`](../compose.yaml#L57) — Defines app, web, caddy, scheduler, reverb, redis, and db services with no queue:work container, while .env.example:41 defaults QUEUE_CONNECTION=database and .env.example:75-76 defaults empty LiveKit credentials.
  - [`routes/console.php:29`](../routes/console.php#L29) — Schedules TripCall::markMissed() every minute, with no scheduled expiration sweep for the calls table.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHPUnit/Docker containers on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed harness with empty LIVEKIT_API_KEY/LIVEKIT_API_SECRET, invoke POST /api/v1/calls as dummy user A to user B, confirm HTTP 500, and observe subsequent POST /api/v1/calls calls involving A or B return HTTP 409 due to the orphaned Ringing row; also test ExpireRingingCall with QUEUE_CONNECTION=sync or without a worker.
  - *Lingkungan Deploy*: Owner checks deployed .env for QUEUE_CONNECTION, LIVEKIT_API_KEY/LIVEKIT_API_SECRET length, whether a queue:work process is running, and whether orphaned Ringing rows exist in calls.
- **Rekomendasi Perbaikan Terkecil**: Generate and validate LiveKit token credentials and verify createRoom() succeeds before committing the Ringing Call (or catch failures to mark the Call Failed and rethrow cleanly), require real credentials outside local/testing, add a scheduled cleanup sweep for expired Ringing calls in routes/console.php, and run a queue worker in compose.yaml.

#### [High (Est.)] `tripcall-bulk-updates-leave-stale-redis-state-across-trips` — Bulk TripCall Updates Bypass Eloquent Model Events, Leaving 2-Hour Stale Redis Keys Surfaced Across Subsequent Trips

- **Deskripsi & Potensi Dampak**: TripCallStateStore caches Answered TripCall snapshots in Redis for 7200 seconds via TripCall's Eloquent saved and deleted model events. However, five lifecycle paths update TripCall rows using query-builder bulk ->update() calls that bypass Eloquent events and never call TripCallStateStore::forget(): TripCall::markMissed(), EmergencyRequestController::cancel(), Driver\TripController::complete(), HospitalDashboardController::updateRequestStatus(), and EmergencyRequest::closeAbandoned(). Worse, Driver\CallController::current() fetches the Redis snapshot via findForUser() and loads TripCall::find($snapshot['id']) without checking $call->emergency_request_id === $trip->id or whether the TripCall is still Ringing/Answered in PostgreSQL, surfacing the prior patient's ended call (name, phone, request_code) during the driver's subsequent trip for up to 2 hours. Additionally, TripCall::saved writes Redis synchronously inside TripCallController::store()'s DB::transaction before commit.
- **Akar Masalah**: Five bulk TripCall query-builder updates bypass TripCall::saved/deleted observers that maintain TripCallStateStore, and Driver\CallController::current() trusts cached Redis snapshots without verifying trip ownership or live call status in PostgreSQL.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:135`](../routes/api.php#L135) (`api.v1.driver.requests.complete`) — A trip with an Answered TripCall is closed via Driver\TripController::complete() (or EmergencyRequestController::cancel(), HospitalDashboardController::updateRequestStatus(), EmergencyRequest::closeAbandoned(), or TripCall::markMissed()).
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/Driver/TripController.php:199`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L199) (`Driver\TripController::complete`) — Executes $emergencyRequest->calls()->live()->update(['status' => 'ended', ...]), bypassing TripCall::saved so TripCallStateStore keeps trip-call:{id} and trip-call:user:{driverUserId} in Redis for up to 7200s.
  3. **[PROPAGATION]** [`app/Services/TripCallStateStore.php:54`](../app/Services/TripCallStateStore.php#L54) (`TripCallStateStore::findForUser`) — Driver starts a new trip and polls GET /api/v1/driver/calls/current; findForUser() returns the stale snapshot referencing the previous trip's call.
  4. **[SINK]** [`app/Http/Controllers/Api/V1/Driver/CallController.php:42`](../app/Http/Controllers/Api/V1/Driver/CallController.php#L42) (`Driver\CallController::current`) — Loads TripCall::find($snapshot['id']) without checking $call->emergency_request_id === $trip->id or live status, returning the prior trip/patient's ended call details to the driver on the new trip.
- **Bukti Tambahan**:
  - [`app/Models/TripCall.php:41`](../app/Models/TripCall.php#L41) — booted() syncs TripCallStateStore only in Eloquent saved/deleted events (lines 41-55), while TripCall::markMissed() (lines 102-108) uses bulk query-builder ->update().
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:142`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L142) — cancel() closes live TripCalls via bulk ->update() (lines 142-145).
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:199`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L199) — complete() closes live TripCalls via bulk ->update() (lines 199-202).
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:357`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L357) — updateRequestStatus() closes live TripCalls via bulk ->update() in Completed (lines 357-360) and Cancelled (lines 376-379) branches.
  - [`app/Models/EmergencyRequest.php:361`](../app/Models/EmergencyRequest.php#L361) — closeAbandoned() closes live TripCalls via bulk ->update() (lines 361-364).
  - [`app/Http/Controllers/Api/V1/Driver/CallController.php:36`](../app/Http/Controllers/Api/V1/Driver/CallController.php#L36) — current() reads findForUser() and returns TripCall::find($snapshot['id']) without verifying $call->emergency_request_id === $trip->id or ($call->isRinging() || $call->hasStatus(CallStatus::Answered)).
  - [`app/Services/TripCallStateStore.php:26`](../app/Services/TripCallStateStore.php#L26) — Answered calls are cached in Redis with a 7200-second TTL (lines 26-27).
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHPUnit/Redis integration tests on this Windows host (which also lacks php-redis).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed harness with Redis enabled, create an answered TripCall between dummy patient P1 and driver D, complete the trip via POST /api/v1/driver/requests/{trip1}/complete, assign driver D to a new trip2 for patient P2, and call GET /api/v1/driver/calls/current as D to verify P1's ended TripCall is returned.
  - *Lingkungan Deploy*: Owner checks Redis keys matching trip-call:* against PostgreSQL trip_calls statuses or checks driver app reports of ended calls reappearing on new trips.
- **Rekomendasi Perbaikan Terkecil**: Replace bulk ->update() calls on TripCall with model iteration + update() (or forget() each affected TripCall after commit), enforce $call->emergency_request_id === $trip->id and ($call->isRinging() || $call->hasStatus(CallStatus::Answered)) in Driver\CallController::current(), and move TripCallStateStore::sync() to DB::afterCommit().

#### [High (Est.)] `unrestricted-call-initiation-pii-and-dos` — Unrestricted User Calling Enables Persistent Incoming-Call DoS and User Name/Phone Enumeration

- **Deskripsi & Potensi Dampak**: POST /api/v1/calls is accessible to any auth:sanctum user without role gating, rate limiting, or relationship authorization. StartCallRequest only verifies that receiver_id/callee_id is a UUID present in users and not equal to the caller's own ID. CallLifecycleService::startCall() enforces no active EmergencyRequest or role relationship, checks Call::query()->forUser($receiverId)->ongoing()->exists() (returning 409 to anyone else calling the target), dispatches FCM push notifications, and returns CallResource containing receiver.name (full_name) and receiver.phone immediately in the HTTP 201 response prior to acceptance. Any self-registered user can harvest full_name and phone numbers for known/discovered user UUIDs, spam push notifications, and lock victims in ongoing Ringing calls blocking legitimate calls.
- **Akar Masalah**: StartCallRequest and CallLifecycleService::startCall() lack relationship/role authorization and initiation rate limits while CallResource exposes callee full_name and phone immediately upon call creation and ongoing()->exists() creates a per-target single-call lock.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:97`](../routes/api.php#L97) (`api.v1.calls.store`) — Any self-registered user (via POST /api/v1/auth/register) submits POST /api/v1/calls with an arbitrary target user UUID.
  2. **[PROPAGATION]** [`app/Http/Requests/Call/StartCallRequest.php:18`](../app/Http/Requests/Call/StartCallRequest.php#L18) (`StartCallRequest::rules`) — Validates only UUID format, exists:users,id, and Rule::notIn([$userId]) with no relationship, role, or rate limit check.
  3. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:47`](../app/Services/Call/CallLifecycleService.php#L47) (`CallLifecycleService::startCall`) — Enforces receiver exclusivity via ongoing()->exists() (409), creates a Ringing Call, and dispatches FCM notification (line 90) and CallIncoming broadcast (line 98).
  4. **[SINK]** [`app/Http/Resources/CallResource.php:39`](../app/Http/Resources/CallResource.php#L39) (`CallResource::toArray`) — Immediately returns receiver/callee full_name and phone in the 201 response to the unverified caller while locking the victim's ongoing call slot.
- **Bukti Tambahan**:
  - [`routes/api.php:29`](../routes/api.php#L29) — POST /api/v1/auth/register allows public self-registration as user or driver (RegisterRequest.php:41); POST /api/v1/calls (line 97) has only auth:sanctum middleware.
  - [`app/Http/Requests/Call/StartCallRequest.php:23`](../app/Http/Requests/Call/StartCallRequest.php#L23) — receiver_id and callee_id rules check only uuid, exists:users,id, and notIn([$userId]).
  - [`app/Services/Call/CallLifecycleService.php:47`](../app/Services/Call/CallLifecycleService.php#L47) — forUser($receiverId)->ongoing()->exists() throws ConflictHttpException (409) while a call is Ringing or Accepted; SendIncomingCallNotification is dispatched at line 90.
  - [`app/Http/Resources/CallResource.php:39`](../app/Http/Resources/CallResource.php#L39) — Lines 39-49 include receiver.name, receiver.phone, callee.name, and callee.phone unconditionally for any call state including Ringing.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits executing API requests against a local server/database on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated sandbox, register a dummy user A via POST /api/v1/auth/register, call POST /api/v1/calls with receiver_id set to an unrelated dummy administrator or patient B's UUID, confirm HTTP 201 leaks B's full_name and phone in data.receiver, and confirm a third user C calling B immediately receives HTTP 409.
  - *Lingkungan Deploy*: Owner reviews intended product call policy for /api/v1/calls versus /api/v1/emergency-requests/{id}/calls and checks logs for anomalous call initiation bursts from civilian accounts.
- **Rekomendasi Perbaikan Terkecil**: Authorize call initiation in CallLifecycleService::startCall() (or a Policy/FormRequest) against an active shared EmergencyRequest or explicitly permitted role pair, omit receiver.phone from CallResource until the call is Accepted (or restrict to active trip participants), and add per-caller rate limiting.

#### [Medium (Est.)] `call-history-unbounded-pagination-filters-and-metadata` — Unvalidated Call History Pagination/Filters and Unbounded call_type/metadata Inputs Trigger DoS and PostgreSQL 500s

- **Deskripsi & Potensi Dampak**: CallController::index() passes (int) $request->input('per_page', 15) and unvalidated status, date_from, and date_to filters directly to CallService::getCallHistory(). Because Laravel's Query\Builder::limit() ignores negative values ($value >= 0) and offset() clamps to >= 0 while Eloquent\Builder::paginate() treats -1 as truthy, per_page=-1 omits SQL LIMIT entirely and returns all historical rows (while huge per_page values execute unbounded LIMIT), and malformed date_from/date_to strings trigger PostgreSQL SQLSTATE[22007] 500 errors (or array query params trigger array-to-string conversion 500s). Additionally, StartCallRequest validates call_type as ['nullable', 'string'] without max:32 (overflowing PostgreSQL varchar(32) with SQLSTATE[22001] 500) and metadata as ['nullable', 'array'] without size bounds up to nginx's 10MB body limit, persisting bloated payloads returned to both caller and receiver.
- **Akar Masalah**: CallController::index() lacks query parameter validation and pagination bounds for per_page, status, date_from, and date_to, and StartCallRequest lacks length/enum validation on call_type and size/item bounds on metadata.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:96`](../routes/api.php#L96) (`api.v1.calls.index`) — Authenticated user sends GET /api/v1/calls?per_page=-1 (or huge per_page, malformed date_from/date_to, or array status), or POST /api/v1/calls with >32-char call_type or multi-MB metadata.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/CallController.php:34`](../app/Http/Controllers/Api/V1/CallController.php#L34) (`CallController::index`) — Casts per_page to int with no bounds and forwards unvalidated $request->only(['status', 'date_from', 'date_to']) to CallService::getCallHistory().
  3. **[SINK]** [`app/Services/Call/CallService.php:35`](../app/Services/Call/CallService.php#L35) (`CallService::getCallHistory`) — Executes unbounded paginate($perPage) (omitting SQL LIMIT for negative per_page) and raw where('created_at', '>=', $filters['date_from']) / where('status', ...) against PostgreSQL.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/CallController.php:33`](../app/Http/Controllers/Api/V1/CallController.php#L33) — Passes unvalidated status, date_from, date_to and unbounded (int) per_page to CallService::getCallHistory().
  - [`app/Services/Call/CallService.php:23`](../app/Services/Call/CallService.php#L23) — Applies status, date_from, date_to directly in where() clauses (lines 23-33) and calls ->paginate($perPage) at line 35.
  - [`app/Http/Requests/Call/StartCallRequest.php:35`](../app/Http/Requests/Call/StartCallRequest.php#L35) — call_type is ['nullable', 'string'] with no max:32/enum rule, and metadata is ['nullable', 'array'] with no max keys/size limit.
  - [`database/migrations/2026_11_08_124323_create_calls_table.php:20`](../database/migrations/2026_11_08_124323_create_calls_table.php#L20) — calls.call_type is varchar(32) (also enforced in 2026_11_09_140003_update_calls_table_fields.php:26) and calls.metadata is json (line 29), returned via CallResource.php:56.
  - [`docker/nginx.conf:9`](../docker/nginx.conf#L9) — Sets client_max_body_size 10m, allowing multi-megabyte JSON metadata payloads.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running HTTP/PostgreSQL validation tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL test harness, verify Query\Builder::limit(-1) omits SQL LIMIT via GET /api/v1/calls?per_page=-1, verify ?date_from=not-a-date returns 500 SQLSTATE[22007], and verify POST /api/v1/calls with 40-char call_type returns 500 SQLSTATE[22001] while oversized metadata persists and renders in receiver's CallResource.
  - *Lingkungan Deploy*: Owner checks application error logs for SQLSTATE[22007] / SQLSTATE[22001] on call endpoints without sending malformed probes to production.
- **Rekomendasi Perbaikan Terkecil**: Validate index() inputs via a FormRequest (per_page: integer|min:1|max:100, status: Rule::enum(CallStatus::class), date_from/date_to: date), and in StartCallRequest constrain call_type to ['nullable', 'string', 'max:32', Rule::in(['audio', 'voice'])] and bound metadata (array|max:20 plus bounded string/scalar leaf rules).

#### [Medium (Est.)] `call-lifecycle-missing-transaction-row-locking` — Missing Transaction Row Locking (lockForUpdate) in acceptCall, rejectCall, cancelCall, and endCall

- **Deskripsi & Potensi Dampak**: CallLifecycleService::acceptCall(), rejectCall(), cancelCall(), and endCall() evaluate status eligibility ($call->canBeAccepted(), canBeRejected(), canBeCancelled(), canBeEnded()) outside DB::transaction using the unlocked route-bound Call model, then call $call->update(...) inside DB::transaction without Call::query()->lockForUpdate()->findOrFail($call->id) or re-checking status. Concurrent transitions (such as caller cancelCall or ExpireRingingCall racing with receiver acceptCall, or dual endCall requests) execute on stale status, permitting a cancelled/expired/rejected call to flip to Accepted (minting receiver credentials for a room scheduled for deletion) or emitting duplicate CallEnded broadcasts.
- **Akar Masalah**: CallLifecycleService::acceptCall(), rejectCall(), cancelCall(), and endCall() perform check-then-act state checks before DB::transaction without row-level lockForUpdate() or post-lock status re-validation.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:99`](../routes/api.php#L99) (`api.v1.calls.accept`) — Concurrent lifecycle requests hit POST /api/v1/calls/{call}/{accept,reject,cancel,end}.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/CallController.php:90`](../app/Http/Controllers/Api/V1/CallController.php#L90) (`CallController::accept`) — Passes unlocked route-model-bound Call instance to CallLifecycleService::acceptCall/rejectCall/cancelCall/endCall.
  3. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:118`](../app/Services/Call/CallLifecycleService.php#L118) (`CallLifecycleService::acceptCall`) — Checks $call->canBeAccepted() (and likewise lines 153, 184, 215 in rejectCall, cancelCall, endCall) outside DB::transaction.
  4. **[SINK]** [`app/Services/Call/CallLifecycleService.php:122`](../app/Services/Call/CallLifecycleService.php#L122) (`CallLifecycleService::acceptCall`) — Updates $call inside DB::transaction without lockForUpdate() or re-checking status (lines 122-128, 157-164, 188-195, 229-237), overwriting concurrent terminal transitions and issuing credentials/CleanupCallRoom/CallEnded races.
- **Bukti Tambahan**:
  - [`app/Services/Call/CallLifecycleService.php:118`](../app/Services/Call/CallLifecycleService.php#L118) — acceptCall() checks canBeAccepted() at line 118 before DB::transaction (lines 122-128); rejectCall() (lines 153-164), cancelCall() (lines 184-195), and endCall() (lines 215-237) follow the same unlocked pattern.
  - [`app/Jobs/Call/ExpireRingingCall.php:25`](../app/Jobs/Call/ExpireRingingCall.php#L25) — ExpireRingingCall uses Call::lockForUpdate()->find($this->callId) inside DB::transaction, unlike CallLifecycleService.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running concurrent DB race tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL test harness, run concurrent acceptCall and cancelCall (or ExpireRingingCall) on the same Ringing Call and verify that without lockForUpdate() inside acceptCall's transaction, acceptCall can overwrite Cancelled/Missed back to Accepted and mint credentials.
  - *Lingkungan Deploy*: Owner reviews CallLifecycleService and production logs/DB rows for calls showing answered_at alongside cancelled/rejected/timeout end_reason or duplicate CallEnded broadcasts.
- **Rekomendasi Perbaikan Terkecil**: Reload the Call with Call::query()->lockForUpdate()->findOrFail($call->id) inside DB::transaction in acceptCall(), rejectCall(), cancelCall(), and endCall(), verify participant role and eligibility inside the transaction, and dispatch broadcasts and CleanupCallRoom via DB::afterCommit().

#### [Medium (Est.)] `call-missing-uuid-constraints-and-tripcall-token-bypass` — Missing UUID Constraints Cause PostgreSQL 500s Across Call Endpoints, Plus TripCall Token Fallback and Hospital Driver Reset Bypass Active-Call/Participant Boundaries

- **Deskripsi & Potensi Dampak**: Two coupled gaps weaken TripCall token and route enforcement: (1) None of the UUID-keyed call/emergency routes in routes/api.php (nor calls.{id} in routes/channels.php or Route::pattern in AppServiceProvider) enforce UUID constraints, and CallController::show(), end(), and token() pass unvalidated string $call to Call::find() and TripCall::find() without Str::isUuid(), causing PostgreSQL SQLSTATE[22P02] 500 errors on non-UUID inputs; (2) CallController::token()'s TripCall fallback authorizes $isCaller || $isPatient || $isDriver, only rejects [Ended, Declined, Missed] (omitting Rejected and Failed), never calls markMissedIfExpired(), and never checks $emergency?->isActiveTrip(). Crucially, when HospitalDashboardController::updateRequestStatus() resets an order to searching_driver (lines 398-409), it clears driver_profile_id = null without ending live TripCalls (unlike Completed/Cancelled branches), so the former unassigned driver retains $isCaller === true in CallController::token() and can keep minting LiveKit room tokens even after removal or reassignment to a new driver (whose own TripCallController::store() call is also blocked with 409 by the unclosed former driver call).
- **Akar Masalah**: UUID call routes/channels lack UUID format constraints before PostgreSQL UUID queries, CallController::token() omits Rejected/Failed, expiry, and active-trip/current-assignment checks on TripCalls, and HospitalDashboardController::updateRequestStatus() clears driver_profile_id on searching_driver resets without closing live TripCalls.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:103`](../routes/api.php#L103) (`api.v1.calls.token`) — Authenticated user sends GET|POST /api/v1/calls/{call}/token (or /show, /end, /accept, /signal) with a non-UUID id or a TripCall id.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/CallController.php:151`](../app/Http/Controllers/Api/V1/CallController.php#L151) (`CallController::token`) — Passes string $call directly to Call::find()/TripCall::find() with no Str::isUuid() guard, then evaluates $isCaller || $isPatient || $isDriver without isActiveTrip() or markMissedIfExpired().
  3. **[PROPAGATION]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:404`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L404) (`HospitalDashboardController::updateRequestStatus`) — When resetting status to searching_driver (lines 398-409), sets driver_profile_id = null and frees the driver without terminating live TripCalls on the EmergencyRequest.
  4. **[SINK]** [`app/Http/Controllers/Api/V1/CallController.php:167`](../app/Http/Controllers/Api/V1/CallController.php#L167) (`CallController::token`) — Mints valid 1-hour LiveKit audio room credentials for expired (>30s), Rejected/Failed, post-trip TripCalls, or for a former driver unassigned by hospital reset who still satisfies $isCaller.
- **Bukti Tambahan**:
  - [`routes/api.php:98`](../routes/api.php#L98) — Routes in calls/{call}, calls/{tripCall}, driver/calls/{tripCall}, and emergency-requests/{emergencyRequest} omit ->whereUuid(...), and AppServiceProvider defines no Route::pattern().
  - [`routes/channels.php:19`](../routes/channels.php#L19) — calls.{id} invokes Call::find($id) directly without Str::isUuid($id).
  - [`app/Http/Controllers/Api/V1/CallController.php:153`](../app/Http/Controllers/Api/V1/CallController.php#L153) — TripCall token branch authorizes $isCaller || $isPatient || $isDriver, only rejects [Ended, Declined, Missed] at line 161 (omitting App\Enums\CallStatus::Rejected and Failed), omits markMissedIfExpired(), and omits $emergency?->isActiveTrip().
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:398`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L398) — SearchingDriver branch (lines 398-409) clears driver_profile_id without closing $emergencyRequest->calls()->live() (unlike Completed at lines 357-360 and Cancelled at lines 376-379).
  - [`app/Http/Controllers/Api/V1/TripCallController.php:41`](../app/Http/Controllers/Api/V1/TripCallController.php#L41) — Unclosed live TripCall from a former driver remains matched by $emergencyRequest->calls()->live()->get(), causing a newly assigned driver's store() call to abort 409.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running PHPUnit/PostgreSQL tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL harness: (1) request GET /api/v1/calls/not-a-uuid/token and observe 500 SQLSTATE[22P02]; (2) place a TripCall as driver D on an active trip, have hospital_admin reset the order to searching_driver (and optionally assign driver D2), then fetch GET /api/v1/calls/{tripCall}/token as removed driver D and verify a valid LiveKit token is returned and D2 gets 409 on POST /api/v1/emergency-requests/{id}/calls.
  - *Lingkungan Deploy*: Owner checks error logs for SQLSTATE[22P02] on /api/v1/calls/* and reviews whether hospital status resets to searching_driver leave live rows in trip_calls.
- **Rekomendasi Perbaikan Terkecil**: Add ->whereUuid(...) on UUID route parameters and Str::isUuid() guards in CallController and routes/channels.php; in CallController::token(), call $tripCall->markMissedIfExpired(), require $tripCall->isRinging() || $tripCall->hasStatus(CallStatus::Answered), require $emergency?->isActiveTrip(), and check current trip participation ($isPatient || $isDriver); and in HospitalDashboardController::updateRequestStatus() SearchingDriver branch, close live TripCalls cleanly (updating Redis state).

#### [Medium (Est.)] `call-start-pair-only-cache-lock-race` — Pair-Only Cache Lock Allows Concurrent Callers to Ring One Receiver Simultaneously

- **Deskripsi & Potensi Dampak**: CallLifecycleService::startCall() derives its concurrency lock key from the caller-receiver pair ('call_init_lock_'.min($caller->id, $receiverId).'_'.max($caller->id, $receiverId)). Concurrent requests from different callers to the same receiver (C1->R and C2->R), or from one caller to different receivers (C->R1 and C->R2), compute distinct lock keys, acquire separate cache locks simultaneously, pass ongoing()->exists() before either DB::transaction commits, and insert multiple simultaneous Ringing Call rows for the same user (with no partial unique index backing it up in PostgreSQL).
- **Akar Masalah**: CallLifecycleService::startCall() acquires a cache lock keyed by the (caller, receiver) pair rather than locking both individual participants in sorted order, and the calls schema lacks partial unique indexes enforcing at-most-one ongoing call per user.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:97`](../routes/api.php#L97) (`api.v1.calls.store`) — Two concurrent POST /api/v1/calls requests arrive (e.g., callers C1 and C2 targeting receiver R, or C targeting R1 and R2).
  2. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:38`](../app/Services/Call/CallLifecycleService.php#L38) (`CallLifecycleService::startCall`) — Computes distinct pair-scoped cache lock keys (call_init_lock_<min>_<max>), allowing both requests into the closure concurrently.
  3. **[PROPAGATION]** [`app/Services/Call/CallLifecycleService.php:47`](../app/Services/Call/CallLifecycleService.php#L47) (`CallLifecycleService::startCall`) — Both concurrent transactions observe Call::query()->forUser($receiverId)->ongoing()->exists() === false before either commits.
  4. **[SINK]** [`app/Services/Call/CallLifecycleService.php:55`](../app/Services/Call/CallLifecycleService.php#L55) (`CallLifecycleService::startCall`) — Both transactions commit Ringing Call rows for the same user, violating 1-on-1 exclusivity.
- **Bukti Tambahan**:
  - [`app/Services/Call/CallLifecycleService.php:38`](../app/Services/Call/CallLifecycleService.php#L38) — Lock key at line 38 uses min((string) $caller->id, $receiverId).'_'.max((string) $caller->id, $receiverId) instead of per-user locks for both parties.
  - [`database/migrations/2026_11_08_124323_create_calls_table.php:32`](../database/migrations/2026_11_08_124323_create_calls_table.php#L32) — calls table defines only non-unique indexes on ['caller_id', 'status'] and ['receiver_id', 'status'] with no partial unique index on ongoing statuses.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running concurrent HTTP/DB tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL harness, dispatch two concurrent POST /api/v1/calls requests from dummy callers C1 and C2 to dummy receiver R (or lock-step two DB sessions past ongoing()->exists()) and verify both insert Ringing rows for R.
  - *Lingkungan Deploy*: Owner checks calls table for overlapping ongoing Call rows sharing a caller_id or receiver_id.
- **Rekomendasi Perbaikan Terkecil**: Acquire per-user locks (or lock both User rows with User::whereIn('id', [$caller->id, $receiverId])->orderBy('id')->lockForUpdate()->get()) inside DB::transaction before checking ongoing()->exists(), and add PostgreSQL partial unique indexes for ongoing calls.

#### [Medium (Est.)] `driver-call-controller-skips-caller-check-and-ringing-end` — Driver\CallController::ensureCallee() Skips caller_id Check, Allowing Driver to Answer/Decline Own Call, While end() Ignores Ringing Calls

- **Deskripsi & Potensi Dampak**: Driver\CallController::ensureCallee() only checks $this->ensureAssigned($call->emergencyRequest, $this->driver($request)), omitting CallSignalingController::ensureCallee()'s check that $tripCall->caller_id !== $request->user()->id. Consequently, when a driver initiates a TripCall, they can call POST /api/v1/driver/calls/{tripCall}/answer to self-answer their own ringing call (forcing status to answered, extending Redis TTL in TripCallStateStore to 7200s, and broadcasting CallStatusUpdated('answered')) or decline their own call. Conversely, Driver\CallController::end() only updates calls where hasStatus(CallStatus::Answered), silently returning 204 when a driver tries to end a Ringing call. Neither Driver\CallController nor CallSignalingController uses DB::transaction/lockForUpdate() on TripCall state transitions.
- **Akar Masalah**: Driver\CallController::ensureCallee() omits the caller_id !== user_id check enforced in CallSignalingController::ensureCallee(), Driver\CallController::end() ignores Ringing status, and neither controller locks the TripCall row in a transaction.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:125`](../routes/api.php#L125) (`api.v1.driver.calls.answer`) — Authenticated driver who placed a TripCall calls POST /api/v1/driver/calls/{tripCall}/answer (or /decline, /end).
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/Driver/CallController.php:101`](../app/Http/Controllers/Api/V1/Driver/CallController.php#L101) (`Driver\CallController::ensureCallee`) — Calls only $this->ensureAssigned($call->emergencyRequest, $this->driver($request)) without checking (string) $call->caller_id === (string) $request->user()->id.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/Driver/CallController.php:55`](../app/Http/Controllers/Api/V1/Driver/CallController.php#L55) (`Driver\CallController::answer`) — Updates the driver's own Ringing TripCall to Answered (and decline() at line 71 marks it Declined, while end() at line 86 ignores Ringing calls and returns 204).
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/Driver/CallController.php:101`](../app/Http/Controllers/Api/V1/Driver/CallController.php#L101) — ensureCallee() (lines 101-104) checks only ensureAssigned(), omitted caller_id check; answer() (lines 49-63) and decline() (lines 65-80) allow caller self-answer/self-decline; end() (lines 82-96) ignores Ringing calls.
  - [`app/Http/Controllers/Api/V1/CallSignalingController.php:172`](../app/Http/Controllers/Api/V1/CallSignalingController.php#L172) — CallSignalingController::ensureCallee() (lines 172-176) explicitly aborts 403 if $tripCall->caller_id === $request->user()->id.
  - [`routes/api.php:124`](../routes/api.php#L124) — Exposes POST /api/v1/driver/calls/{tripCall}/answer, /decline, and /end.
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running Pest feature tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed test harness, create a TripCall initiated by dummy driver D on an active trip, POST /api/v1/driver/calls/{tripCall}/answer as D, confirm HTTP 200 and status='answered' (contrasting with 403 on /api/v1/calls/{tripCall}/answer), and verify POST /api/v1/driver/calls/{tripCall}/end on a Ringing call returns 204 while status remains 'ringing'.
  - *Lingkungan Deploy*: Owner inspects TripCall records or logs for calls answered immediately by the same driver session without patient action.
- **Rekomendasi Perbaikan Terkecil**: Enforce (string) $call->caller_id !== (string) $request->user()->id in Driver\CallController::ensureCallee() for answer() and decline(), enforce ensureAssigned() in end() while permitting ending Ringing and Answered calls only, and wrap transitions in DB::transaction with TripCall::query()->lockForUpdate()->findOrFail($tripCall->id).

#### [Low (Est.)] `api-callsignaling-end-signal-state-bypass` — CallSignalingController::end() Overwrites Terminal TripCall Statuses and signal() Omits Expiry, Rejected/Failed, and Payload Checks

- **Deskripsi & Potensi Dampak**: CallSignalingController::end() (invoked via CallController::end() fallback on POST /api/v1/calls/{call}/end) checks only ! $tripCall->hasStatus(CallStatus::Ended) without lockForUpdate(), allowing participants to overwrite already Missed, Declined, Rejected, or Failed TripCalls to Ended, reset ended_at, and broadcast CallStatusUpdated('ended'). Similarly, CallSignalingController::signal() (POST /api/v1/calls/{tripCall}/signal) rejects only Ended, Declined, and Missed, omitting CallStatus::Rejected and Failed, omitting $tripCall->markMissedIfExpired() (so >30s expired ringing calls still broadcast SDP/ICE events), omitting $tripCall->emergencyRequest?->isActiveTrip(), and validating 'data' as 'required' with no type/size bounds.
- **Akar Masalah**: CallSignalingController::end() checks only !$tripCall->hasStatus(CallStatus::Ended) without locking or terminal-status checks, and CallSignalingController::signal() omits expiry, Rejected/Failed, active-trip, and payload-size checks.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:102`](../routes/api.php#L102) (`api.v1.calls.end`) — Authenticated participant calls POST /api/v1/calls/{call}/end (or POST /api/v1/calls/{tripCall}/signal at line 110).
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/CallController.php:132`](../app/Http/Controllers/Api/V1/CallController.php#L132) (`CallController::end`) — Falls back to TripCall::find($call) and delegates to CallSignalingController::end($request, $tripCall).
  3. **[SINK]** [`app/Http/Controllers/Api/V1/CallSignalingController.php:156`](../app/Http/Controllers/Api/V1/CallSignalingController.php#L156) (`CallSignalingController::end`) — Updates any non-Ended TripCall (including Missed, Declined, Rejected, Failed) to Ended and broadcasts CallStatusUpdated('ended'); likewise signal() (lines 125-150) broadcasts CallSignalEvent on expired ringing, Rejected, Failed, or post-trip calls with unbounded 'data'.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/CallSignalingController.php:156`](../app/Http/Controllers/Api/V1/CallSignalingController.php#L156) — end() only tests if (! $tripCall->hasStatus(CallStatus::Ended)) with no DB::transaction/lockForUpdate(), overwriting Missed/Declined/Rejected/Failed calls.
  - [`app/Http/Controllers/Api/V1/CallSignalingController.php:128`](../app/Http/Controllers/Api/V1/CallSignalingController.php#L128) — signal() aborts only on Ended, Declined, or Missed (omitting Rejected/Failed from App\Enums\CallStatus:13,15), skips markMissedIfExpired() and isActiveTrip(), and sets 'data' => 'required' at line 139.
  - [`app/Http/Controllers/Api/V1/CallController.php:130`](../app/Http/Controllers/Api/V1/CallController.php#L130) — CallController::end() delegates TripCall IDs to CallSignalingController::end().
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running Pest feature tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed harness, create a TripCall with status='missed' (or 'declined') and POST /api/v1/calls/{tripCall}/end as a participant to verify status flips to 'ended'; create a Ringing TripCall older than 30s (or with status='rejected') and POST /api/v1/calls/{tripCall}/signal to confirm HTTP 200 and CallSignalEvent broadcast.
  - *Lingkungan Deploy*: Owner inspects TripCall audit/state records for ended calls whose ended_at was updated significantly after rang_at without answered_at.
- **Rekomendasi Perbaikan Terkecil**: In CallSignalingController::end(), lock the TripCall inside DB::transaction, call markMissedIfExpired(), and only transition Ringing or Answered calls to Ended; in signal(), call markMissedIfExpired(), require ($tripCall->isRinging() || $tripCall->hasStatus(CallStatus::Answered)) && $tripCall->emergencyRequest?->isActiveTrip(), and validate data as a bounded array.

#### [Low (Est.)] `api-tripcall-store-active-trip-toctou` — TripCallController::store Validates isActiveTrip() and Driver Assignment Before Locking EmergencyRequest

- **Deskripsi & Potensi Dampak**: TripCallController::store() checks participant membership ($isPatient / $isDriver) and $emergencyRequest->isActiveTrip() before entering DB::transaction(). Inside the transaction, line 38 executes EmergencyRequest::query()->lockForUpdate()->find($emergencyRequest->id) without assigning or refreshing the returned locked model and without re-checking isActiveTrip() or driver assignment. Under concurrent trip cancellation (EmergencyRequestController::cancel), completion (Driver\TripController::complete), or hospital status override (HospitalDashboardController::updateRequestStatus), store() can pass the unlocked pre-check, block on lockForUpdate() while the trip transitions to cancelled/completed/searching_driver (after existing live calls were already closed), and then create a new Ringing TripCall on an inactive or unassigned emergency request.
- **Akar Masalah**: TripCallController::store() evaluates isActiveTrip() and driver assignment outside DB::transaction() and discards the lockForUpdate() query result instead of re-checking state on the locked EmergencyRequest row.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:83`](../routes/api.php#L83) (`api.v1.emergency-requests.calls.store`) — Participant calls POST /api/v1/emergency-requests/{emergencyRequest}/calls concurrently with trip cancellation, completion, or hospital reset.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/TripCallController.php:30`](../app/Http/Controllers/Api/V1/TripCallController.php#L30) (`TripCallController::store`) — Checks $isPatient/$isDriver (lines 24-25) and $emergencyRequest->isActiveTrip() (line 30) on the unlocked model before DB::transaction().
  3. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/TripCallController.php:38`](../app/Http/Controllers/Api/V1/TripCallController.php#L38) (`TripCallController::store`) — Acquires row lock via EmergencyRequest::query()->lockForUpdate()->find($emergencyRequest->id) but discards the returned model and skips re-validating active trip/assignment.
  4. **[SINK]** [`app/Http/Controllers/Api/V1/TripCallController.php:63`](../app/Http/Controllers/Api/V1/TripCallController.php#L63) (`TripCallController::store`) — Inserts a new Ringing TripCall onto an already cancelled, completed, or driver-unassigned EmergencyRequest.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/TripCallController.php:24`](../app/Http/Controllers/Api/V1/TripCallController.php#L24) — Lines 24-33 evaluate $isPatient, $isDriver, and $emergencyRequest->isActiveTrip() before DB::transaction().
  - [`app/Http/Controllers/Api/V1/TripCallController.php:38`](../app/Http/Controllers/Api/V1/TripCallController.php#L38) — Line 38 discards the return value of EmergencyRequest::query()->lockForUpdate()->find($emergencyRequest->id) and proceeds directly to calls()->create() at line 63.
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:109`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L109) — cancel() acquires lockForUpdate() on EmergencyRequest and terminates live TripCalls at lines 142-145; Driver\TripController::complete() (lines 188-205) does not even use lockForUpdate().
- **Penghambat Validasi (`blockers`)**:
  - Execution policy sandboxed-source-and-local-only prohibits running concurrent PostgreSQL transaction tests on this Windows host.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed PostgreSQL test harness, pause Session A (TripCallController::store) after line 30 while Session B runs EmergencyRequestController::cancel() to commit status='cancelled' and close live calls, then resume Session A and confirm a Ringing TripCall is inserted on the cancelled request.
  - *Lingkungan Deploy*: Owner queries trip_calls created_at > emergency_requests.cancelled_at/completed_at to check whether race-created orphan TripCalls occurred.
- **Rekomendasi Perbaikan Terkecil**: Capture $locked = EmergencyRequest::query()->with('driverProfile')->lockForUpdate()->findOrFail($emergencyRequest->id) inside DB::transaction in TripCallController::store() and re-verify participant membership and $locked->isActiveTrip() on $locked before creating a TripCall.

---

### 3.7 Mobile API Auth & Push (5 Temuan)

#### [High (Est.)] `auth-login-admin-wildcard-tokens-missing-role-guards-and-case-sensitive-email` — AuthController::login mints never-expiring wildcard tokens for admin/hospital roles, shared API routes and /calls lack role/relationship guards, and Postgres email queries are case-sensitive

- **Deskripsi & Potensi Dampak**: config/sanctum.php sets expiration to null and AuthController::login issues unrestricted ['*'] Sanctum tokens with no role check (unlike web LoginController) even for administrator and hospital_admin users (though web routes use the web session guard). Shared routes under auth:sanctum (/me/medical-profile, /me/addresses, /hospitals/nearest, /device-tokens, /calls*) omit role: middleware, allowing any authenticated user to initiate LiveKit 1-on-1 calls to any user UUID via POST /api/v1/calls (StartCallRequest). Additionally, AuthController::login and RegisterRequest query/check email case-sensitively on PostgreSQL.
- **Akar Masalah**: AuthController::login lacks role filtering and token expiration/ability scoping, routes/api.php leaves shared endpoints and arbitrary-receiver 1-on-1 calling unguarded by role or trip relationship, and email lookup/uniqueness relies on case-sensitive equality.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:33`](../routes/api.php#L33) (`api.v1.auth.login`) — POST /api/v1/auth/login accepts credentials for any role, including administrator and hospital_admin, as well as self-registered users/drivers.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/AuthController.php:66`](../app/Http/Controllers/Api/V1/AuthController.php#L66) (`AuthController::login`) — Queries email case-sensitively without role validation and calls $user->createToken($deviceName) at line 75 with wildcard ['*'] abilities and null expiration (config/sanctum.php:53).
  3. **[SINK]** [`routes/api.php:97`](../routes/api.php#L97) (`api.v1.calls.store`) — Shared auth:sanctum routes (lines 43-56, 87-89, 92-112) lack role guards; POST /api/v1/calls (StartCallRequest:23, CallLifecycleService:35) lets any authenticated user ring and lock out any user UUID via FCM/LiveKit.
- **Bukti Tambahan**:
  - [`config/sanctum.php:53`](../config/sanctum.php#L53) — Sets 'expiration' => null so issued personal access tokens never expire.
  - [`app/Http/Controllers/Api/V1/AuthController.php:66`](../app/Http/Controllers/Api/V1/AuthController.php#L66) — Performs case-sensitive where('email', ...) with no role restriction (unlike Auth/LoginController.php:51-59) and creates permanent ['*'] tokens at line 75.
  - [`app/Http/Requests/Api/V1/RegisterRequest.php:29`](../app/Http/Requests/Api/V1/RegisterRequest.php#L29) — Uses case-sensitive Rule::unique('users', 'email') with a plain unique('email') index in 2026_11_07_024355_add_unique_email_to_users_table.php:15.
  - [`routes/api.php:42`](../routes/api.php#L42) — Applies only auth:sanctum without role: to /me/medical-profile (50-51), /me/addresses (52-54), /hospitals/nearest (56), /device-tokens (87-89), and /calls* (92-112); line 83 (TripCallController::store) omits role: intentionally to allow both trip reporter and assigned driver.
  - [`app/Http/Requests/Call/StartCallRequest.php:23`](../app/Http/Requests/Call/StartCallRequest.php#L23) — Validates receiver_id only with exists:users,id and notIn([$userId]), allowing arbitrary cross-role calls in CallLifecycleService::startCall (lines 35-105) that block targets with 409 busy status.
- **Penghambat Validasi (`blockers`)**:
  - Target execution against PostgreSQL is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed harness, verify an administrator token created via /api/v1/auth/login has abilities=['*'] and expires_at=null, verify a civilian token can initiate POST /api/v1/calls against an admin UUID, and test mixed-case email login/registration against PostgreSQL.
  - *Lingkungan Deploy*: Verify SANCTUM_EXPIRATION / personal_access_tokens records in production and confirm whether /api/v1/calls is intended for general calls or restricted to active-trip participants.
- **Rekomendasi Perbaikan Terkecil**: Restrict AuthController::login to mobile roles (user, driver), configure Sanctum token expiration and scoped abilities, add role:/relationship authorization on shared routes and StartCallRequest/CallLifecycleService, and normalize emails to lowercase with a case-insensitive unique index.

#### [High (Est.)] `device-token-hijacking-and-non-atomic-upsert` — Cross-user FCM device token hijacking and non-atomic upsert in DeviceTokenController::store

- **Deskripsi & Potensi Dampak**: DeviceTokenController::store looks up device_tokens globally by token without verifying device ownership or locking, overwriting user_id to the requesting user and defeating per-user delete isolation in destroy(), while concurrent POSTs for a new token race on INSERT against the global unique token index.
- **Akar Masalah**: DeviceTokenController::store executes firstOrNew(['token' => ...]) across all users outside any transaction, lock, or upsert and unconditionally reassigns user_id against a globally unique device_tokens.token column.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:88`](../routes/api.php#L88) (`api.v1.device-tokens.store`) — Authenticated POST /api/v1/device-tokens accepts an arbitrary token string from any Sanctum user with no role restriction.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/DeviceTokenController.php:40`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L40) (`DeviceTokenController::store`) — Queries DeviceToken::query()->firstOrNew(['token' => $validated['token']]) globally without transaction or lockForUpdate.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/DeviceTokenController.php:43`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L43) (`DeviceTokenController::store`) — Unconditionally overwrites user_id with the current user and calls save() at line 51, detaching the token from a prior owner (enabling subsequent DELETE /api/v1/device-tokens) or racing on INSERT.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Api/V1/DeviceTokenController.php:40`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L40) — Uses firstOrNew(['token' => $validated['token']]) and overwrites 'user_id' => $user->id at line 44 before save() at line 51 with no transaction, lock, or upsert despite the comment at line 39.
  - [`app/Http/Controllers/Api/V1/DeviceTokenController.php:67`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L67) — destroy() scopes deletion to $user->deviceTokens()->where('token', $token), which is bypassed once an attacker claims ownership via store().
  - [`app/Http/Requests/Call/DeleteDeviceTokenRequest.php:20`](../app/Http/Requests/Call/DeleteDeviceTokenRequest.php#L20) — Requires only a raw token string without ownership or device-attestation proof.
  - [`database/migrations/2026_11_09_140000_create_device_tokens_table.php:17`](../database/migrations/2026_11_09_140000_create_device_tokens_table.php#L17) — Enforces $table->string('token')->unique() globally with no composite [user_id, token] unique constraint.
  - [`tests/Feature/DeviceToken/DeviceTokenTest.php:30`](../tests/Feature/DeviceToken/DeviceTokenTest.php#L30) — Existing test asserts cross-user reassignment on shared devices without proof of possession.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is disabled on this Windows non-sandboxed host (sandboxed-source-and-local-only), preventing local concurrency/HTTP execution.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In an isolated sandboxed test harness, run concurrent POST /api/v1/device-tokens requests with a fresh dummy token to confirm UniqueConstraintViolationException (HTTP 500), and test User B calling store() with User A's token followed by destroy() and FcmService push routing.
  - *Lingkungan Deploy*: Verify whether shared-device reassignment is required by mobile clients after missed logouts and confirm whether device_tokens unique constraint collisions appear in production logs.
- **Rekomendasi Perbaikan Terkecil**: Require an explicit device-transfer/proof-of-possession token or reject cross-user hijacking when owned by another active session (clearing tokens properly on logout), and replace firstOrNew()+save() with atomic DB upsert inside a transaction.

#### [High (Est.)] `fcm-service-unconfigured-simulation-and-v1-bugs` — FcmService simulates 'sent' in production when unconfigured, drops v1 notification payload, sends unauthenticated requests on partial config, and mis-invalidates tokens

- **Deskripsi & Potensi Dampak**: FcmService::sendToToken returns 'sent' regardless of APP_ENV when projectId and serverKey are empty, sends unauthenticated HTTP POSTs when FCM_PROJECT_ID is set without FIREBASE_CREDENTIALS, omits the $notification payload in FCM v1 despite apns-push-type: alert and sendIncomingCall title/body, permanently disables tokens on INVALID_ARGUMENT, and omits logging on legacy fallback failures.
- **Akar Masalah**: FcmService::sendToToken lacks environment guards on push simulation, lacks a fail-fast check for missing service-account credentials when projectId is set, drops $notification in the v1 JSON body, and treats payload-level INVALID_ARGUMENT responses identically to UNREGISTERED tokens.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Services/Notification/FcmService.php:194`](../app/Services/Notification/FcmService.php#L194) (`FcmService::sendIncomingCall`) — Passes title/body $notification and $payload to sendToToken(), updating last_used_at on 'sent' and setting is_active=false on 'invalid' (same pattern in sendToUser at line 111).
  2. **[PROPAGATION]** [`app/Services/Notification/FcmService.php:253`](../app/Services/Notification/FcmService.php#L253) (`FcmService::sendToToken`) — Simulates 'sent' without checking APP_ENV when empty($projectId) && empty($serverKey), fails to abort when projectId is set with credentials()===null (line 265), and omits $notification in the v1 message payload (lines 272-285).
  3. **[SINK]** [`app/Services/Notification/FcmService.php:301`](../app/Services/Notification/FcmService.php#L301) (`FcmService::sendToToken`) — Returns 'invalid' for HTTP 404 or errorCode in ['UNREGISTERED', 'INVALID_ARGUMENT'], causing callers at lines 128 and 233 to permanently set is_active=false on valid device tokens.
- **Bukti Tambahan**:
  - [`app/Services/Notification/FcmService.php:253`](../app/Services/Notification/FcmService.php#L253) — Lines 253-257 return 'sent' with only Log::debug whenever projectId and serverKey are empty, independent of APP_ENV; lines 124 and 229 then mark last_used_at and increment sent count.
  - [`app/Services/Notification/FcmService.php:265`](../app/Services/Notification/FcmService.php#L265) — Checks ($accessToken === null && $this->credentials() !== null), allowing FCM_PROJECT_ID without FIREBASE_CREDENTIALS to send unauthenticated requests at lines 269-271.
  - [`app/Services/Notification/FcmService.php:272`](../app/Services/Notification/FcmService.php#L272) — Lines 272-285 drop the $notification array passed by sendIncomingCall() (lines 222-225) while setting apns-push-type: alert at line 282.
  - [`app/Services/Notification/FcmService.php:301`](../app/Services/Notification/FcmService.php#L301) — Treats 'INVALID_ARGUMENT' as 'invalid', deactivating tokens at lines 128 and 233 even when the 400 error stems from message payload fields.
  - [`app/Services/Notification/FcmService.php:323`](../app/Services/Notification/FcmService.php#L323) — Legacy FCM fallback (lines 323-335) returns 'failed' without logging HTTP status or error details.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only), and production Firebase env configuration (APP_ENV, FCM_PROJECT_ID, FIREBASE_CREDENTIALS, FCM_SERVER_KEY) is outside repository source.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed unit test using Http::fake(), test FcmService under APP_ENV=production with empty credentials, with FCM_PROJECT_ID set and missing credentials, with sendIncomingCall payload inspection, and with an FCM v1 400 INVALID_ARGUMENT response.
  - *Lingkungan Deploy*: Owner inspects runtime config values for firebase.project_id, firebase.credentials_path, and firebase.server_key (via config:show firebase) and checks logs for FCM v1 HTTP 400/401 or simulated send entries.
- **Rekomendasi Perbaikan Terkecil**: Restrict simulated 'sent' strictly to local/testing environments, fail fast when projectId lacks valid credentials, include 'notification' in the v1 payload when non-empty (plus apns alert payload), only return 'invalid' for registration-token errors (404/UNREGISTERED), and log legacy FCM failures.

#### [Medium (Est.)] `device-token-length-mismatch-http-500` — StoreDeviceTokenRequest allows 500-character tokens against VARCHAR(255) column causing HTTP 500

- **Deskripsi & Potensi Dampak**: StoreDeviceTokenRequest validates token with max:500 while device_tokens.token is defined as VARCHAR(255), causing SQLSTATE[22001] unhandled HTTP 500 errors on PostgreSQL/MySQL for tokens between 256 and 500 characters.
- **Akar Masalah**: StoreDeviceTokenRequest::rules permits up to 500 characters for token while the device_tokens migration creates a default 255-character string column.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:88`](../routes/api.php#L88) (`api.v1.device-tokens.store`) — Authenticated POST /api/v1/device-tokens routes to DeviceTokenController::store via StoreDeviceTokenRequest.
  2. **[PROPAGATION]** [`app/Http/Requests/Call/StoreDeviceTokenRequest.php:20`](../app/Http/Requests/Call/StoreDeviceTokenRequest.php#L20) (`StoreDeviceTokenRequest::rules`) — Validates 'token' => ['required', 'string', 'max:500'], allowing 256-500 character values.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/DeviceTokenController.php:51`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L51) (`DeviceTokenController::store`) — Calls $deviceToken->save(), writing the 256-500 character string into the VARCHAR(255) token column.
- **Bukti Tambahan**:
  - [`app/Http/Requests/Call/StoreDeviceTokenRequest.php:20`](../app/Http/Requests/Call/StoreDeviceTokenRequest.php#L20) — Validates 'token' => ['required', 'string', 'max:500'].
  - [`database/migrations/2026_11_09_140000_create_device_tokens_table.php:17`](../database/migrations/2026_11_09_140000_create_device_tokens_table.php#L17) — Defines $table->string('token')->unique(), creating VARCHAR(255) on PostgreSQL/MySQL (Schema::defaultStringLength is unmodified in AppServiceProvider).
  - [`app/Http/Requests/Call/DeleteDeviceTokenRequest.php:20`](../app/Http/Requests/Call/DeleteDeviceTokenRequest.php#L20) — Omits a max length constraint on 'token'.
  - [`phpunit.xml:27`](../phpunit.xml#L27) — Tests run on SQLite (:memory:), which ignores VARCHAR(255) length bounds unlike .env.example DB_CONNECTION=pgsql.
- **Penghambat Validasi (`blockers`)**:
  - Target execution against a PostgreSQL instance is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Run POST /api/v1/device-tokens with a 300-character dummy token against a sandboxed PostgreSQL database to observe SQLSTATE[22001] HTTP 500.
  - *Lingkungan Deploy*: Verify production DB driver (PostgreSQL) and column type of device_tokens.token in the deployed schema.
- **Rekomendasi Perbaikan Terkecil**: Alter device_tokens.token to VARCHAR(512) (or TEXT with hash index) and align max:255/max:500 across StoreDeviceTokenRequest and DeleteDeviceTokenRequest.

#### [Medium (Est.)] `unthrottled-device-tokens-push-amplification-and-logout-retention` — Unthrottled uncapped device token registration amplifies FCM worker latency and AuthController::logout leaves FCM tokens active

- **Deskripsi & Potensi Dampak**: GET/POST/DELETE /api/v1/device-tokens lack throttle and role middleware and enforce no per-user active token limit, allowing an authenticated user to register unbounded tokens that cause sequential 3s/5s HTTP loops in queued SendIncomingCallNotification jobs (or synchronous when QUEUE_CONNECTION=sync) and post-response defer() callbacks (TripCallController::store, EmergencyRequest::offerToNextDriver). Additionally, AuthController::logout deletes only currentAccessToken() without deactivating device_tokens.
- **Akar Masalah**: device-tokens routes lack rate limits and per-user active token caps while FcmService loops synchronously over all active tokens, and AuthController::logout omits device token cleanup.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:88`](../routes/api.php#L88) (`api.v1.device-tokens.store`) — Unthrottled POST /api/v1/device-tokens lets any authenticated user register unlimited distinct active tokens.
  2. **[PROPAGATION]** [`app/Http/Controllers/Api/V1/DeviceTokenController.php:33`](../app/Http/Controllers/Api/V1/DeviceTokenController.php#L33) (`DeviceTokenController::store`) — Persists each new token as is_active=true without enforcing a per-user active token cap.
  3. **[SINK]** [`app/Services/Notification/FcmService.php:119`](../app/Services/Notification/FcmService.php#L119) (`FcmService::sendToUser`) — Iterates synchronously over all active tokens (3s/5s timeout each) inside defer() (TripCallController:80, EmergencyRequest:317) or queued SendIncomingCallNotification::handle -> sendIncomingCall (line 221).
- **Bukti Tambahan**:
  - [`routes/api.php:87`](../routes/api.php#L87) — Lines 87-89 register GET/POST/DELETE device-tokens with no throttle or role middleware.
  - [`app/Services/Call/CallLifecycleService.php:90`](../app/Services/Call/CallLifecycleService.php#L90) — Dispatches SendIncomingCallNotification (ShouldQueue, queued on database by default in config/queue.php:16, or inline if sync) where the worker runs FcmService::sendIncomingCall synchronous loop (FcmService.php:221-238).
  - [`app/Http/Controllers/Api/V1/TripCallController.php:80`](../app/Http/Controllers/Api/V1/TripCallController.php#L80) — Calls defer(fn () => app(FcmService::class)->sendTripCall($call)) running synchronously in the PHP worker post-response; EmergencyRequest::offerToNextDriver (line 317) similarly uses defer().
  - [`app/Http/Controllers/Api/V1/AuthController.php:87`](../app/Http/Controllers/Api/V1/AuthController.php#L87) — logout() deletes currentAccessToken() only and does not deactivate or delete any DeviceToken.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only), and runtime QUEUE_CONNECTION, PHP max_execution_time, and mobile client logout behavior are outside repository source.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: In a sandboxed harness with Http::fake() simulating slow/timeout FCM responses, register 50 dummy tokens for a user, call POST /api/v1/calls and POST /api/v1/auth/logout, and verify queue worker/defer loop duration and remaining is_active=true tokens.
  - *Lingkungan Deploy*: Inspect runtime QUEUE_CONNECTION and whether mobile clients call DELETE /api/v1/device-tokens prior to POST /api/v1/auth/logout.
- **Rekomendasi Perbaikan Terkecil**: Add throttle and role:user,driver middleware on device-tokens, cap active tokens per user (pruning oldest), and accept an optional token/device_id in AuthController::logout to deactivate the device token atomically.

---

### 3.8 Medical Privacy & Resources (3 Temuan)

#### [High (Est.)] `emergency-resource-ignores-share-on-sos-and-leaks-to-offers` — EmergencyRequestResource ignores share_on_sos=false and leaks full medical profile to unaccepted offer recipients

- **Deskripsi & Potensi Dampak**: EmergencyRequestResource::toArray includes the reporter's entire medical profile whenever is_for_self is true without checking UserMedicalProfile::share_on_sos, and DispatchOfferResource embeds EmergencyRequestResource in GET /api/v1/driver/offers/current before any driver accepts (up to OFFER_FANOUT=3 drivers per wave).
- **Akar Masalah**: EmergencyRequestResource omits a check on $medical->share_on_sos and is embedded unconditionally into DispatchOfferResource for pending unaccepted dispatch offers.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:119`](../routes/api.php#L119) (`api.v1.driver.offers.current`) — Authenticated driver polls GET /api/v1/driver/offers/current (OfferController::current).
  2. **[PROPAGATION]** [`app/Http/Resources/V1/DispatchOfferResource.php:34`](../app/Http/Resources/V1/DispatchOfferResource.php#L34) (`DispatchOfferResource::toArray`) — Embeds new EmergencyRequestResource($emergency) for unaccepted pending DispatchLog offers.
  3. **[SINK]** [`app/Http/Resources/V1/EmergencyRequestResource.php:23`](../app/Http/Resources/V1/EmergencyRequestResource.php#L23) (`EmergencyRequestResource::toArray`) — Resolves $medical = $emergency->is_for_self ? $reporter->medicalProfile : null without checking share_on_sos and serializes all medical PHI fields at lines 43-53.
- **Bukti Tambahan**:
  - [`app/Http/Resources/V1/EmergencyRequestResource.php:23`](../app/Http/Resources/V1/EmergencyRequestResource.php#L23) — Evaluates $emergency->is_for_self ? $reporter->medicalProfile : null without checking $medical->share_on_sos.
  - [`app/Http/Resources/V1/EmergencyRequestResource.php:43`](../app/Http/Resources/V1/EmergencyRequestResource.php#L43) — Lines 43-53 expose blood_type, allergies, medical_conditions, age, weight_kg, height_cm, emergency_contact_name, emergency_contact_phone, and insurance.
  - [`app/Http/Controllers/Api/V1/Driver/OfferController.php:23`](../app/Http/Controllers/Api/V1/Driver/OfferController.php#L23) — Eager-loads emergencyRequest.user.medicalProfile for pending offers and returns DispatchOfferResource at line 29.
  - [`app/Models/EmergencyRequest.php:88`](../app/Models/EmergencyRequest.php#L88) — Sets OFFER_FANOUT = 3, exposing the offer payload to up to three unaccepted drivers per wave.
  - [`database/migrations/2026_11_05_040218_update_user_medical_profiles_table.php:35`](../database/migrations/2026_11_05_040218_update_user_medical_profiles_table.php#L35) — Defines boolean share_on_sos opt-out flag on user_medical_profiles.
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Create a dummy user with share_on_sos=false and medical data, trigger an SOS offered to a dummy driver, and inspect GET /api/v1/driver/offers/current before acceptance to confirm medical fields are returned.
  - *Lingkungan Deploy*: Verify no API gateway or response filter strips medical fields from driver offer endpoints.
- **Rekomendasi Perbaikan Terkecil**: Gate medical serialization in EmergencyRequestResource on ($medical !== null && (bool) ($medical->share_on_sos ?? true)), and omit full medical PHI and reporter phone in DispatchOfferResource until a driver accepts.

#### [Medium (Est.)] `distance-trilateration-and-post-trip-driver-phone-exposure` — Exact 0.01 km distance_km enables driver GPS trilateration and EmergencySearchResource exposes driver phone indefinitely post-trip

- **Deskripsi & Potensi Dampak**: NearestAmbulanceResource and EmergencySearchResource round nearby driver coordinates to 3 decimals (~111 m) to prevent tracking drivers, yet expose distance_km rounded to 0.01 km (10 m precision) computed from exact driver coordinates on the unthrottled GET /api/v1/ambulances/nearest endpoint, allowing trilateration from 3 attacker query points. Additionally, EmergencySearchResource exposes driver.phone whenever driver_profile_id is non-null without gating on isActiveTrip().
- **Akar Masalah**: DriverProfile::nearestDispatchableTo computes 0.01 km distance_km from unrounded coordinates while NearestAmbulanceResource exposes it on an arbitrary-query endpoint, and EmergencySearchResource omits an active-trip gate around driver.phone.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:59`](../routes/api.php#L59) (`api.v1.ambulances.nearest`) — Unthrottled GET /api/v1/ambulances/nearest accepts arbitrary latitude/longitude from any self-registered user (and GET /api/v1/emergency-requests/{id} at line 71 returns EmergencySearchResource).
  2. **[PROPAGATION]** [`app/Models/DriverProfile.php:122`](../app/Models/DriverProfile.php#L122) (`DriverProfile::nearestDispatchableTo`) — Computes distance_km rounded to 2 decimals (10 m precision) from exact unrounded current_latitude/current_longitude.
  3. **[SINK]** [`app/Http/Resources/V1/NearestAmbulanceResource.php:26`](../app/Http/Resources/V1/NearestAmbulanceResource.php#L26) (`NearestAmbulanceResource::toArray`) — Outputs exact 0.01 km distance_km alongside 3-decimal rounded coordinates (lines 24-25), while EmergencySearchResource (line 68) exposes driver['phone'] even on Completed/Cancelled requests.
- **Bukti Tambahan**:
  - [`app/Http/Resources/V1/NearestAmbulanceResource.php:24`](../app/Http/Resources/V1/NearestAmbulanceResource.php#L24) — Rounds latitude/longitude to 3 decimals (lines 24-25) but outputs 0.01 km distance_km (line 26) calculated from exact driver coordinates in DriverProfile.php:122-125.
  - [`app/Http/Requests/Api/V1/NearestAmbulanceRequest.php:26`](../app/Http/Requests/Api/V1/NearestAmbulanceRequest.php#L26) — Accepts arbitrary query latitude/longitude on unthrottled GET /api/v1/ambulances/nearest (routes/api.php:59).
  - [`app/Http/Resources/V1/EmergencySearchResource.php:131`](../app/Http/Resources/V1/EmergencySearchResource.php#L131) — Also exposes 3-decimal rounded coordinates with 0.01 km distance_km in nearby_ambulances.
  - [`app/Http/Resources/V1/EmergencySearchResource.php:66`](../app/Http/Resources/V1/EmergencySearchResource.php#L66) — Exposes driver['name'] and driver['phone'] (line 68) whenever driver_profile_id !== null without gating on isActiveTrip() (unlike ambulance coordinates at lines 62-64, and unlike EmergencyHistoryResource.php:43).
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Query GET /api/v1/ambulances/nearest from three reference points in a sandboxed test to confirm distance_km reflects unrounded coordinates to 0.01 km, and query GET /api/v1/emergency-requests/{id} on a Completed request to confirm driver.phone remains exposed.
  - *Lingkungan Deploy*: Verify whether any reverse-proxy rate limit exists on /api/v1/ambulances/nearest (docker/Caddyfile and docker/nginx.conf define none).
- **Rekomendasi Perbaikan Terkecil**: Coarsen distance_km (e.g., compute from rounded coordinates or round to 0.5 km buckets) and throttle /ambulances/nearest; redact driver.phone in EmergencySearchResource when !isActiveTrip().

#### [Medium (Est.)] `update-medical-profile-typeerror-not-null-and-duplicate-rows` — UpdateMedicalProfileRequest crashes with PHP TypeError on nested arrays/JSON and SQLSTATE[23502] on share_on_sos=null, with duplicate row race

- **Deskripsi & Potensi Dampak**: UpdateMedicalProfileRequest lacks element-type validation on allergies and medical_conditions so nested arrays or JSON arrays throw TypeError in normalizeArrayField()'s array_map('trim', ...); share_on_sos allows nullable boolean against a NOT NULL column causing SQLSTATE[23502]; and user_medical_profiles.user_id lacks a unique index so concurrent updates create duplicate profile rows.
- **Akar Masalah**: UpdateMedicalProfileRequest::normalizeArrayField invokes trim() on unvalidated array elements, validatedMedicalAttributes preserves null share_on_sos for a NOT NULL column, and user_medical_profiles lacks a unique user_id constraint.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/api.php:51`](../routes/api.php#L51) (`api.v1.me.medical-profile.update`) — Authenticated PUT /api/v1/me/medical-profile enters UpdateMedicalProfileRequest.
  2. **[PROPAGATION]** [`app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php:44`](../app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php#L44) (`UpdateMedicalProfileRequest::validatedMedicalAttributes`) — Passes unvalidated allergies/medical_conditions to normalizeArrayField() calling array_map('trim', ...) at lines 65 and 71 (TypeError on nested arrays) and retains share_on_sos=null from line 32.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/MedicalProfileController.php:30`](../app/Http/Controllers/Api/V1/MedicalProfileController.php#L30) (`MedicalProfileController::update`) — Calls updateOrCreate(['user_id' => ...], ...) without lock or unique index, failing with SQLSTATE[23502] when share_on_sos is null or inserting duplicate rows under concurrency.
- **Bukti Tambahan**:
  - [`app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php:21`](../app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php#L21) — Lines 21-22 validate allergies and medical_conditions only as ['nullable'] without element type constraints.
  - [`app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php:65`](../app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php#L65) — Calls array_map('trim', $value) at line 65 and array_map('trim', $decoded) at line 71; nested arrays throw TypeError in PHP 8.4.
  - [`app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php:32`](../app/Http/Requests/Api/V1/UpdateMedicalProfileRequest.php#L32) — Allows 'share_on_sos' => ['nullable', 'boolean'], which is not defaulted/stripped in validatedMedicalAttributes().
  - [`database/migrations/2026_11_05_040218_update_user_medical_profiles_table.php:35`](../database/migrations/2026_11_05_040218_update_user_medical_profiles_table.php#L35) — Defines $table->boolean('share_on_sos')->default(true) without ->nullable().
  - [`database/migrations/2026_11_02_100002_create_user_medical_profiles_table.php:24`](../database/migrations/2026_11_02_100002_create_user_medical_profiles_table.php#L24) — Defines foreignUuid('user_id') with no unique constraint (nor added in 2026_11_05_040218).
- **Penghambat Validasi (`blockers`)**:
  - Target execution is blocked on this Windows non-sandboxed host (sandboxed-source-and-local-only).
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Send PUT /api/v1/me/medical-profile with {"allergies":[["peanut"]]} and with {"share_on_sos":null} in a sandboxed test harness to observe HTTP 500 TypeError and NOT NULL integrity exception, plus concurrent PUT requests to observe duplicate user_medical_profiles rows.
  - *Lingkungan Deploy*: Check existing user_medical_profiles rows for duplicate user_id entries before adding a unique index.
- **Rekomendasi Perbaikan Terkecil**: Validate or filter non-string elements in UpdateMedicalProfileRequest before trim(), omit or default null share_on_sos before updateOrCreate(), and add a unique constraint on user_medical_profiles.user_id.

---

### 3.9 Hospital Dashboard (5 Temuan)

#### [High (Est.)] `hospital-dashboard-driver-profile-fallback-idor` — driverProfiles fallback in HospitalDashboardController::index and updateRequestStatus grants tenant access without hospital admin ownership

- **Deskripsi & Potensi Dampak**: HospitalDashboardController::index() and updateRequestStatus() resolve the acting hospital_admin's hospital via admin_user_id first, but fall back to Hospital::whereHas('driverProfiles', fn ($q) => $q->where('user_id', $user->id))->first() when admin_user_id returns null. Unlike storeHospital(), updateHospital(), and storeDriver() (which enforce strict admin_user_id ownership), a user holding the hospital_admin role (for example, a former driver promoted via UserManagementController::update() which preserves DriverProfile) who is not admin_user_id of Hospital A gains read access to Hospital A's drivers/ambulances/patient requests and write access to Hospital A's emergency request statuses, live calls, and driver availability.
- **Akar Masalah**: HospitalDashboardController::index() and updateRequestStatus() authorize hospital access using a driverProfiles relationship fallback when admin_user_id yields null.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/web.php:68`](../routes/web.php#L68) (`dashboard.hospital routes`) — GET /dashboard/hospital (line 68) and PATCH /dashboard/hospital/requests/{emergencyRequest}/status (line 80) accept any user passing role:hospital_admin,administrator.
  2. **[PROPAGATION]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:47`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L47) (`HospitalDashboardController::index`) — Falls back to Hospital::whereHas('driverProfiles', ...) at lines 47-51 when admin_user_id lookup returns null; updateRequestStatus() repeats the identical fallback at lines 313-317.
  3. **[SINK]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:323`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L323) (`HospitalDashboardController::updateRequestStatus`) — Evaluates $isRelated against the fallback $hospital (lines 323-328) and permits mutating that hospital's emergency request statuses, ending live TripCalls, and altering driver availability.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:47`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L47) — driverProfiles fallback in index() (lines 47-51) contradicts the comment on line 42 ('strictly restricted to their own assigned hospital') and discloses hospital drivers, ambulances, and patient requests.
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:313`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L313) — driverProfiles fallback in updateRequestStatus() (lines 313-317) grants write authorization over another admin's hospital emergency requests.
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:153`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L153) — Sibling methods storeHospital() (line 153), updateHospital() (line 211), and storeDriver() (line 244) enforce strict admin_user_id ownership.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); Pest authorization test not executed.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add a Pest test in tests/Feature/Http/Controllers/Dashboard/DashboardPagesTest.php for a hospital_admin user who has a DriverProfile at Hospital A (owned by another admin_user_id): assert GET /dashboard/hospital shows the setup form rather than Hospital A's operational data, and PATCH /dashboard/hospital/requests/{id}/status returns 403.
  - *Lingkungan Deploy*: Verify that hospital_admin accounts not assigned as hospitals.admin_user_id cannot view or modify hospitals linked only via driver_profiles.
- **Rekomendasi Perbaikan Terkecil**: Remove the driverProfiles fallback from HospitalDashboardController::index() (lines 47-51) and updateRequestStatus() (lines 313-317), resolving hospital ownership strictly via Hospital::where('admin_user_id', $user->id)->first().

#### [High (Est.)] `hospital-dashboard-update-status-race-and-invalid-transitions` — updateRequestStatus() lacks lockForUpdate(), state transition checks, and TripCall/DispatchLog cleanup when resetting or closing requests

- **Deskripsi & Potensi Dampak**: HospitalDashboardController::updateRequestStatus() accepts any EmergencyStatus enum value from the hospital.blade.php dropdown without checking the current status (allowing terminal completed/cancelled requests to be reopened and driverless searching_driver requests to jump into active-trip statuses), mutates the stale route-bound $emergencyRequest inside DB::transaction() without lockForUpdate() on EmergencyRequest or DriverProfile, blindly forces DriverProfile.availability_status to 'online' without checking activeTrip(), omits expiring pending dispatchLogs on complete/cancel, and in the SearchingDriver reset branch clears driver_profile_id and ambulance_id to null without closing active TripCalls (and without clearing completed_at/cancelled_at/cancel_reason), leaving orphaned TripCall rows and TripCallStateStore keys active across driver reassignment.
- **Akar Masalah**: updateRequestStatus() mutates the stale route-bound EmergencyRequest without pessimistic row locking, validates status with Rule::in(EmergencyStatus::cases()) instead of a transition map, and omits side-effect cleanup consistent with EmergencyRequest::closeAbandoned() and EmergencyRequestController::cancel().
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:332`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L332) (`HospitalDashboardController::updateRequestStatus`) — Accepts any EmergencyStatus case from the dashboard dropdown (hospital.blade.php:583-591) with no current-status transition guard.
  2. **[PROPAGATION]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:343`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L343) (`HospitalDashboardController::updateRequestStatus`) — Enters DB::transaction using stale $emergencyRequest without EmergencyRequest::query()->lockForUpdate()->findOrFail($emergencyRequest->id) or locking DriverProfile.
  3. **[SINK]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:404`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L404) (`HospitalDashboardController::updateRequestStatus`) — Blindly sets DriverProfile availability_status='online' (lines 364, 383, 401), skips pending dispatchLogs timeout on complete/cancel, clears driver_profile_id=null at line 404 without ending live TripCalls (unlike lines 357 and 376), and invokes offerToNextDriver() (line 416) even on reopened terminal requests.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:343`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L343) — DB::transaction mutates unlocked $emergencyRequest and updates DriverProfile without lockForUpdate() or activeTrip() check (lines 363-366, 382-385, 400-403).
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:398`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L398) — SearchingDriver branch (lines 398-409) clears driver_profile_id and ambulance_id without calling $emergencyRequest->calls()->live()->update(...) to close active TripCalls.
  - [`resources/views/dashboard/hospital.blade.php:583`](../resources/views/dashboard/hospital.blade.php#L583) — Status select renders all 7 statuses unconditionally regardless of current request status.
  - [`app/Http/Controllers/Api/V1/EmergencyRequestController.php:109`](../app/Http/Controllers/Api/V1/EmergencyRequestController.php#L109) — Sibling cancel() uses EmergencyRequest::query()->lockForUpdate(), DriverProfile::query()->lockForUpdate(), and closes pending dispatchLogs.
  - [`app/Models/EmergencyRequest.php:345`](../app/Models/EmergencyRequest.php#L345) — Sibling closeAbandoned() locks EmergencyRequest and DriverProfile and checks $driver->activeTrip() === null before updating driver availability.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); concurrency/state-transition tests not executed.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add Pest tests in DashboardPagesTest.php asserting: (1) completed/cancelled requests reject transitions back to searching_driver/active states, (2) searching_driver requests without a driver cannot jump to active-trip/completed states, (3) cancelling/completing closes pending dispatchLogs, (4) resetting to searching_driver ends live TripCalls and forgets TripCallStateStore state, and (5) driver availability only turns online under lockForUpdate() when activeTrip() === null.
  - *Lingkungan Deploy*: Verify hospital dashboard status override UI only offers valid transitions and concurrent driver completion/cancellation leaves consistent driver and TripCall state.
- **Rekomendasi Perbaikan Terkecil**: Inside DB::transaction(), reload EmergencyRequest with lockForUpdate(), enforce an explicit transition matrix forbidding reopening terminal requests or driverless active-trip transitions, expire pending dispatchLogs on complete/cancel, terminate live TripCalls (and sync/forget TripCallStateStore) when resetting to searching_driver, and lock DriverProfile checking activeTrip() === null before setting availability online.

#### [Medium (Est.)] `hospital-dashboard-cancel-reason-length-overflow` — Concatenating cancel_reason and admin_notes overflows PostgreSQL varchar(255) in updateRequestStatus()

- **Deskripsi & Potensi Dampak**: HospitalDashboardController::updateRequestStatus() validates cancel_reason (max:255) and admin_notes (max:500), then concatenates them with ' (Catatan: ...)' (producing up to 767 characters, or up to 546 characters even when cancel_reason is omitted and defaults to 'Dibatalkan oleh petugas RS / Admin') before writing to emergency_requests.cancel_reason, which is varchar(255). On PostgreSQL this raises an unhandled SQLSTATE[22001] 500 error.
- **Akar Masalah**: Validation permits 255 + 500 characters across two fields that are concatenated into a single varchar(255) database column without truncation or combined length validation.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:337`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L337) (`HospitalDashboardController::updateRequestStatus`) — Validates cancel_reason (max:255) and admin_notes (max:500) at lines 337-338.
  2. **[PROPAGATION]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:369`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L369) (`HospitalDashboardController::updateRequestStatus`) — Concatenates $reason . ' (Catatan: ' . $validated['admin_notes'] . ')' at lines 369-373, producing up to 767 characters.
  3. **[SINK]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:411`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L411) (`HospitalDashboardController::updateRequestStatus`) — Updates emergency_requests.cancel_reason (varchar(255)), triggering SQLSTATE[22001] on PostgreSQL when combined length exceeds 255 characters.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:337`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L337) — cancel_reason max:255 and admin_notes max:500 (line 338) concatenated into $updates['cancel_reason'] at lines 369-373.
  - [`database/migrations/2026_11_05_040102_add_radius_and_cancel_fields_to_emergency_requests_table.php:18`](../database/migrations/2026_11_05_040102_add_radius_and_cancel_fields_to_emergency_requests_table.php#L18) — $table->string('cancel_reason')->nullable()->after('cancelled_at'); creates varchar(255).
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); SQLite ignores varchar(255) length enforcement.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add a Pest test calling PATCH /dashboard/hospital/requests/{id}/status with status=cancelled and a 500-char admin_notes, asserting cancel_reason never exceeds 255 characters (or column/validation match) and cancellation succeeds.
  - *Lingkungan Deploy*: Cancel a test request with max-length reason and admin_notes against PostgreSQL and verify no SQLSTATE[22001] occurs.
- **Rekomendasi Perbaikan Terkecil**: Truncate combined cancel_reason safely with Str::limit($reason, 255, '') (or widen emergency_requests.cancel_reason to text) and align admin_notes max length.

#### [Medium (Est.)] `hospital-dashboard-validation-exceeds-db-varchar-limits` — storeHospital(), updateHospital(), and storeDriver() validation max lengths exceed PostgreSQL varchar widths

- **Deskripsi & Potensi Dampak**: HospitalDashboardController::storeHospital() and updateHospital() validate hospital name at max:255 and emergency_phone at max:50, and storeDriver() validates driver email at max:255, exceeding migrations hospitals.name (varchar(200)), hospitals.emergency_phone (varchar(20)), and users.email (varchar(100)), causing unhandled SQLSTATE[22001] 500 crashes on PostgreSQL.
- **Akar Masalah**: Validation rules in storeHospital(), updateHospital(), and storeDriver() specify max lengths larger than the PostgreSQL column definitions.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:136`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L136) (`HospitalDashboardController::storeHospital`) — Validates name max:255 (line 136) and emergency_phone max:50 (line 138); updateHospital() repeats at lines 191,193; storeDriver() validates email max:255 at line 254.
  2. **[SINK]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:169`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L169) (`HospitalDashboardController::storeHospital`) — Hospital::create/update (lines 155, 169, 219) and User::create (line 272) write into varchar(200), varchar(20), and varchar(100) columns, throwing SQLSTATE[22001] on PostgreSQL.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:136`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L136) — storeHospital/updateHospital validate name max:255 and emergency_phone max:50 (lines 136,138,191,193); storeDriver validates email max:255 (line 254).
  - [`database/migrations/2026_11_02_100003_create_hospitals_table.php:16`](../database/migrations/2026_11_02_100003_create_hospitals_table.php#L16) — hospitals.name is varchar(200) and hospitals.emergency_phone is varchar(20) (line 18).
  - [`database/migrations/0001_01_01_000000_create_users_table.php:18`](../database/migrations/0001_01_01_000000_create_users_table.php#L18) — users.email is varchar(100).
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); SQLite in phpunit.xml ignores varchar length constraints.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add validation tests in DashboardPagesTest.php submitting 201-char hospital name, 21-char emergency_phone, and 101-char driver email to storeHospital, updateHospital, and storeDriver.
  - *Lingkungan Deploy*: Verify over-length inputs return 302 validation errors cleanly on PostgreSQL.
- **Rekomendasi Perbaikan Terkecil**: Tighten validation rules to name max:200 and emergency_phone max:20 in storeHospital() and updateHospital(), and email max:100 in storeDriver().

#### [Low (Est.)] `hospital-dashboard-non-uuid-hospital-id-crash` — Unvalidated non-UUID hospital_id triggers unhandled 500 PostgreSQL exception in storeDriver() and index()

- **Deskripsi & Potensi Dampak**: For administrator users, HospitalDashboardController::index() reads $request->query('hospital_id') and passes it directly to Hospital::find(), and storeDriver() reads $request->input('hospital_id') and passes it to Hospital::find() before $request->validate(). Because hospitals.id is a PostgreSQL uuid primary key, any non-UUID value triggers an unhandled SQLSTATE[22P02] QueryException.
- **Akar Masalah**: HospitalDashboardController::index() and storeDriver() pass untrusted hospital_id directly to Hospital::find() without Str::isUuid() or Laravel 'uuid' validation.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:241`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L241) (`HospitalDashboardController::storeDriver`) — Reads $request->input('hospital_id') prior to $request->validate() (line 252); index() similarly reads $request->query('hospital_id') at line 30.
  2. **[SINK]** [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:242`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L242) (`HospitalDashboardController::storeDriver`) — Calls Hospital::find($request->input('hospital_id')) (and Hospital::find($selectedHospitalId) at line 36), executing WHERE id = '<non-uuid>' against PostgreSQL uuid PK and raising SQLSTATE[22P02].
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:241`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L241) — Hospital::find($request->input('hospital_id')) executes before validation; hospital_id is omitted from validation rules at lines 252-260.
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:36`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L36) — Hospital::find($selectedHospitalId) executes without Str::isUuid() check (contrast updateHospital() line 190 which validates 'uuid').
  - [`database/migrations/2026_11_02_100003_create_hospitals_table.php:15`](../database/migrations/2026_11_02_100003_create_hospitals_table.php#L15) — $table->uuid('id')->primary();
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); SQLite in phpunit.xml permits non-UUID string comparisons unlike PostgreSQL.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add a Pest test sending hospital_id='not-a-uuid' as administrator to GET /dashboard/hospital and POST /dashboard/hospital/drivers, asserting clean validation/fallback without 500 error.
  - *Lingkungan Deploy*: Verify against PostgreSQL that non-UUID hospital_id query or POST values do not raise SQLSTATE[22P02] in application logs.
- **Rekomendasi Perbaikan Terkecil**: Validate 'hospital_id' => ['nullable', 'uuid', 'exists:hospitals,id'] at the start of HospitalDashboardController::storeDriver(), and guard index() with is_string($selectedHospitalId) && Str::isUuid($selectedHospitalId) before calling Hospital::find().

---

### 3.10 Web Auth & Registration (4 Temuan)

#### [High (Est.)] `web-register-open-hospital-admin-poisoning` — Unthrottled open self-registration immediately grants hospital_admin role and publishes unverified hospitals to emergency dispatch

- **Deskripsi & Potensi Dampak**: POST /register in routes/web.php has no throttle middleware (unlike throttle:6,1 on mobile API auth routes) and RegisterController::store() immediately assigns the hospital_admin role, logs the registrant in, and inserts a live Hospital row. The hospitals schema has no approval or verification flag, and Hospital::nearestTo() returns all hospitals with non-null coordinates to GET /api/v1/hospitals/nearest and Driver\TripController::startToHospital(), allowing any unauthenticated registrant to poison emergency hospital routing.
- **Akar Masalah**: routes/web.php exposes POST /register without throttle middleware, and RegisterController::store() unconditionally attaches the hospital_admin role and creates an immediately active Hospital row without verification or admin approval.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`routes/web.php:26`](../routes/web.php#L26) (`guest route group`) — Unthrottled Route::post('/register', [RegisterController::class, 'store']) accepts public registration requests.
  2. **[PROPAGATION]** [`app/Http/Controllers/Auth/RegisterController.php:63`](../app/Http/Controllers/Auth/RegisterController.php#L63) (`RegisterController::store`) — Finds or creates the hospital_admin role and attaches it to the newly created user, then inserts a Hospital row with user-supplied coordinates and emergency_phone (lines 66-74) and logs the user in (line 79).
  3. **[SINK]** [`app/Models/Hospital.php:56`](../app/Models/Hospital.php#L56) (`Hospital::nearestTo`) — Queries all hospitals with non-null latitude and longitude without any verification or approval filter, serving self-registered hospitals to /api/v1/hospitals/nearest and Driver\TripController::startToHospital().
- **Bukti Tambahan**:
  - [`routes/web.php:26`](../routes/web.php#L26) — No throttle middleware on POST /register (contrast throttle:6,1 on routes/api.php:30,34).
  - [`app/Http/Controllers/Auth/RegisterController.php:63`](../app/Http/Controllers/Auth/RegisterController.php#L63) — Immediate attachment of hospital_admin role and live Hospital creation inside DB::transaction (lines 54-77) followed by Auth::guard('web')->login($user) at line 79.
  - [`database/migrations/2026_11_02_100003_create_hospitals_table.php:14`](../database/migrations/2026_11_02_100003_create_hospitals_table.php#L14) — hospitals table schema lacks any verification, approval, or active status column.
  - [`app/Http/Controllers/Api/V1/Driver/TripController.php:164`](../app/Http/Controllers/Api/V1/Driver/TripController.php#L164) — startToHospital() assigns Hospital::nearestTo(...) directly for incoming emergency patients.
  - [`app/Http/Controllers/Api/V1/HospitalController.php:15`](../app/Http/Controllers/Api/V1/HospitalController.php#L15) — nearest() exposes Hospital::nearestTo(...) to any Sanctum-authenticated mobile user.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); rate-limiting and end-to-end dispatch behavior not executed at runtime.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add a Pest feature test in tests/Feature/RegisterTest.php posting repeated requests to /register to confirm 429 rate limiting and verifying newly registered hospitals carry a pending/unverified state excluded from Hospital::nearestTo() until approved.
  - *Lingkungan Deploy*: Confirm upstream reverse proxy (Caddy/Nginx) does not mask client IPs (trusted proxies configured in bootstrap/app.php:24) and verify unapproved partner hospitals do not appear in GET /api/v1/hospitals/nearest.
- **Rekomendasi Perbaikan Terkecil**: Add rate limiting (e.g., throttle:6,1) to POST /register in routes/web.php, add a verified_at/is_verified column to hospitals, filter Hospital::nearestTo() to verified hospitals, and require administrator approval before activating self-registered hospitals.

#### [Medium (Est.)] `web-register-blade-broken-map-js` — SyntaxError and mismatched DOM IDs in auth/register.blade.php break hospital location selection in browser

- **Deskripsi & Potensi Dampak**: In resources/views/auth/register.blade.php, the inline script block contains a partial-merge defect: HTML uses IDs map, mapSearch, btnSearch, btnGps, locationStatus, whereas the script queries hospitalMap, mapSearchInput, btnSearchLocation, btnGetLocation, gpsStatusBadge, and function setPoint() at line 261 is never closed before '});' at line 437 and references undefined variables (latEl, lngEl, label, updateCoordinates). Browsers hit a JS SyntaxError so hidden #latitude and #longitude inputs cannot be populated through the UI.
- **Akar Masalah**: Broken partial merge in resources/views/auth/register.blade.php script block left mismatched element IDs, undefined identifiers, and an unclosed function declaration.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Auth/RegisterController.php:17`](../app/Http/Controllers/Auth/RegisterController.php#L17) (`RegisterController::showRegistrationForm`) — Renders auth.register view for GET /register.
  2. **[PROPAGATION]** [`resources/views/auth/register.blade.php:188`](../resources/views/auth/register.blade.php#L188) (`auth.register HTML vs script`) — HTML renders controls with IDs map (line 188), mapSearch (line 180), btnSearch (line 182), btnGps (line 195), locationStatus (line 192), while script lines 223-249 look up btnGetLocation, gpsStatusBadge, mapSearchInput, btnSearchLocation, and hospitalMap.
  3. **[SINK]** [`resources/views/auth/register.blade.php:261`](../resources/views/auth/register.blade.php#L261) (`setPoint function`) — setPoint() opened at line 261 is never closed before line 437 '});', causing a JS SyntaxError that prevents Leaflet initialization and leaves hidden #latitude and #longitude fields (lines 201-202) empty.
- **Bukti Tambahan**:
  - [`resources/views/auth/register.blade.php:188`](../resources/views/auth/register.blade.php#L188) — Map container ID is 'map', whereas line 249 calls L.map('hospitalMap', ...).
  - [`resources/views/auth/register.blade.php:261`](../resources/views/auth/register.blade.php#L261) — Unclosed function setPoint(...) referencing undefined latEl, lngEl, label (line 270), and updateCoordinates (line 277), terminated by '});' at line 437.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); JS syntax/browser test not executed.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Parse the inline script block from resources/views/auth/register.blade.php with node --check and run a browser test confirming map click, Nominatim search, and GPS populate #latitude and #longitude without console errors.
  - *Lingkungan Deploy*: Open /register in a browser with DevTools open and verify zero JS errors, map rendering, and successful coordinate capture.
- **Rekomendasi Perbaikan Terkecil**: Rewrite the inline Leaflet script in resources/views/auth/register.blade.php to target the actual DOM IDs (#map, #mapSearch, #btnSearch, #btnGps, #locationStatus, #locationError, #latitude, #longitude) with balanced braces and consistent variable names.

#### [Medium (Est.)] `web-register-validation-exceeds-db-varchar-limits` — RegisterController::store validation max lengths exceed PostgreSQL varchar widths causing 500 crashes

- **Deskripsi & Potensi Dampak**: RegisterController::store() permits email up to 255 chars, hospital_name up to 255 chars, and emergency_phone up to 50 chars (regex {4,40}), whereas migrations define users.email as varchar(100), hospitals.name as varchar(200), and hospitals.emergency_phone as varchar(20). On PostgreSQL (configured in compose.yaml and .env.example), valid-length inputs above the column widths trigger unhandled SQLSTATE[22001] exceptions.
- **Akar Masalah**: Validation rules in RegisterController::store() use max:255 / max:50 without matching the narrower varchar(100), varchar(200), and varchar(20) column definitions in migrations.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Auth/RegisterController.php:26`](../app/Http/Controllers/Auth/RegisterController.php#L26) (`RegisterController::store`) — Accepts email (max:255 at line 26), hospital_name (max:255 at line 29), and emergency_phone (max:50, regex {4,40} at line 31).
  2. **[SINK]** [`app/Http/Controllers/Auth/RegisterController.php:55`](../app/Http/Controllers/Auth/RegisterController.php#L55) (`RegisterController::store`) — User::create() (line 55) and Hospital::create() (line 66) insert into PostgreSQL varchar(100), varchar(200), and varchar(20) columns, raising unhandled SQLSTATE[22001].
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Auth/RegisterController.php:26`](../app/Http/Controllers/Auth/RegisterController.php#L26) — 'email' max:255, 'hospital_name' max:255 (line 29), 'emergency_phone' max:50 / regex {4,40} (line 31).
  - [`database/migrations/0001_01_01_000000_create_users_table.php:18`](../database/migrations/0001_01_01_000000_create_users_table.php#L18) — $table->string('email', 100)->nullable();
  - [`database/migrations/2026_11_02_100003_create_hospitals_table.php:16`](../database/migrations/2026_11_02_100003_create_hospitals_table.php#L16) — $table->string('name', 200); and $table->string('emergency_phone', 20); (line 18).
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only), and SQLite in phpunit.xml does not enforce varchar(N) truncation limits.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add Feature tests in tests/Feature/RegisterTest.php submitting a 101-char email, 201-char hospital_name, and 21-char emergency_phone, asserting 302 validation errors rather than 500.
  - *Lingkungan Deploy*: Verify against PostgreSQL that over-length inputs return validation errors with no QueryException in logs.
- **Rekomendasi Perbaikan Terkecil**: Tighten RegisterController::store() validation rules to email max:100, hospital_name max:200, and emergency_phone max:20 with regex quantifier {4,18} (or widen the DB columns via migration).

#### [Low (Est.)] `web-register-unpersisted-health-facilities` — Validated health_facilities checkboxes on hospital registration are silently dropped

- **Deskripsi & Potensi Dampak**: resources/views/auth/register.blade.php submits health_facilities[] checkboxes (with 'IGD 24 Jam' pre-checked by default) and RegisterController::store() validates health_facilities and health_facilities.*, but Hospital::create() omits 'health_facilities', leaving the column null in the database.
- **Akar Masalah**: RegisterController::store() validates health_facilities but forgets to pass 'health_facilities' => $validated['health_facilities'] ?? [] into Hospital::create().
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`resources/views/auth/register.blade.php:145`](../resources/views/auth/register.blade.php#L145) (`auth.register view`) — Registration form submits health_facilities[] checkbox values.
  2. **[PROPAGATION]** [`app/Http/Controllers/Auth/RegisterController.php:35`](../app/Http/Controllers/Auth/RegisterController.php#L35) (`RegisterController::store`) — Validates health_facilities (line 35) and health_facilities.* (line 36) into $validated.
  3. **[SINK]** [`app/Http/Controllers/Auth/RegisterController.php:66`](../app/Http/Controllers/Auth/RegisterController.php#L66) (`RegisterController::store`) — Calls Hospital::create([...]) at lines 66-74 without including 'health_facilities', so selected facilities are discarded and the column remains null.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Auth/RegisterController.php:35`](../app/Http/Controllers/Auth/RegisterController.php#L35) — health_facilities validated at lines 35-36, omitted from Hospital::create() at lines 66-74.
  - [`app/Models/Hospital.php:14`](../app/Models/Hospital.php#L14) — Hospital model includes 'health_facilities' in #[Fillable] and casts it as 'array' at line 29.
  - [`app/Http/Controllers/Dashboard/HospitalDashboardController.php:176`](../app/Http/Controllers/Dashboard/HospitalDashboardController.php#L176) — Sibling storeHospital() explicitly persists 'health_facilities' => $validated['health_facilities'] ?? [].
  - [`tests/Feature/RegisterTest.php:36`](../tests/Feature/RegisterTest.php#L36) — Existing registration test submits health_facilities => ['IGD 24 Jam', 'ICU', 'Radiologi'] but never asserts persistence on $hospital->health_facilities.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); Pest test execution not run.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Update tests/Feature/RegisterTest.php to assert $hospital->health_facilities equals ['IGD 24 Jam', 'ICU', 'Radiologi'] after POST /register.
  - *Lingkungan Deploy*: After registering a test hospital with selected facilities, inspect the hospital dashboard Faskes tab to confirm selected facilities are persisted.
- **Rekomendasi Perbaikan Terkecil**: Add 'health_facilities' => $validated['health_facilities'] ?? [] to Hospital::create() in app/Http/Controllers/Auth/RegisterController.php.

---

### 3.11 Admin RBAC & Schema (4 Temuan)

#### [High (Est.)] `admin-roles-core-role-deletion-and-rename-lockout` — RoleManagementController allows deleting or renaming core system roles, causing admin lockout and RBAC breakage

- **Deskripsi & Potensi Dampak**: RoleManagementController::update() permits renaming any role including 'administrator', 'hospital_admin', 'driver', and 'user', and destroy() detaches all users from any role and deletes it. Because RoleMiddleware, routes/web.php, routes/api.php, LoginController, and AuthController match those exact role names, renaming or deleting 'administrator' immediately locks out all administrators (including the acting user) with 403 Forbidden.
- **Akar Masalah**: RoleManagementController::update() and destroy() lack a guard blocking modification or deletion of core system roles hardcoded across routes and controllers.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Admin/RoleManagementController.php:86`](../app/Http/Controllers/Admin/RoleManagementController.php#L86) (`RoleManagementController::destroy`) — Accepts DELETE /admin/roles/{role} (and PUT /admin/roles/{role} at line 70) for any role including 'administrator'.
  2. **[PROPAGATION]** [`app/Http/Controllers/Admin/RoleManagementController.php:89`](../app/Http/Controllers/Admin/RoleManagementController.php#L89) (`RoleManagementController::destroy`) — Executes $role->users()->detach(); $role->delete(); (or $role->update(['name' => ...]) at line 76), stripping all 'administrator' role bindings.
  3. **[SINK]** [`app/Http/Middleware/RoleMiddleware.php:27`](../app/Http/Middleware/RoleMiddleware.php#L27) (`RoleMiddleware::handle`) — Checks literal role names via $user->roles->pluck('name'), causing subsequent requests to routes/web.php:89 (role:administrator) and routes/api.php:60,115 to fail with 403 Forbidden.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Admin/RoleManagementController.php:70`](../app/Http/Controllers/Admin/RoleManagementController.php#L70) — update() validates uniqueness only and permits renaming canonical system roles.
  - [`app/Http/Controllers/Admin/RoleManagementController.php:86`](../app/Http/Controllers/Admin/RoleManagementController.php#L86) — destroy() detaches all users and deletes any role without checking protected core role names.
  - [`database/seeders/RoleSeeder.php:15`](../database/seeders/RoleSeeder.php#L15) — Defines the four canonical system roles: administrator, user, driver, hospital_admin.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); Pest test for core role protection not executed.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add Pest tests verifying PUT and DELETE on administrator, hospital_admin, driver, and user roles are rejected (403/422) while custom roles remain editable/deletable.
  - *Lingkungan Deploy*: Verify core system roles cannot be renamed or deleted in the admin roles UI.
- **Rekomendasi Perbaikan Terkecil**: Define protected system role names (['administrator', 'hospital_admin', 'driver', 'user']) and reject renaming in RoleManagementController::update() and deletion in destroy() with a validation/403 error.

#### [High (Est.)] `admin-users-missing-token-and-session-revocation-and-fk-crash` — User role change/deletion fails to revoke Sanctum tokens and sessions, and destroy() crashes on non-cascading foreign keys without self-deletion guard

- **Deskripsi & Potensi Dampak**: UserManagementController::update() updates roles without deleting Sanctum personal access tokens (which never expire under config/sanctum.php:53) or database sessions, leaving demoted/role-stripped users with valid bearer tokens for all auth:sanctum routes lacking role middleware (routes/api.php:43-56,83-112). UserManagementController::destroy() has no self-deletion or last-admin guard, never revokes tokens or sessions, never detaches roles, and executes $user->delete() directly against non-cascading RESTRICT foreign keys on role_users.user_id, emergency_requests.user_id, and trip_calls.caller_id, crashing with SQLSTATE[23503].
- **Akar Masalah**: UserManagementController::update() and destroy() omit token/session revocation and self-delete guards, and destroy() calls $user->delete() directly against non-cascading foreign keys on role_users, emergency_requests, and trip_calls.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Admin/UserManagementController.php:112`](../app/Http/Controllers/Admin/UserManagementController.php#L112) (`UserManagementController::update / destroy`) — Admin modifies roles via PUT /admin/users/{user} (line 112) or deletes a user via DELETE /admin/users/{user} (line 135).
  2. **[PROPAGATION]** [`app/Http/Controllers/Admin/UserManagementController.php:123`](../app/Http/Controllers/Admin/UserManagementController.php#L123) (`UserManagementController::update / destroy`) — update() runs roles()->sync() without calling $user->tokens()->delete() or clearing sessions; destroy() lacks auth()->id() === $user->id check and executes $user->delete() (line 137) without detaching roles or revoking tokens/sessions.
  3. **[SINK]** [`database/migrations/2026_11_02_100001_create_role_user_table.php:16`](../database/migrations/2026_11_02_100001_create_role_user_table.php#L16) (`role_users / emergency_requests / trip_calls FKs & non-role API routes`) — Non-cascading FKs reject $user->delete() with SQLSTATE[23503] 500 error for any user with a role, emergency request, or trip call; meanwhile demoted users retain active non-expiring Sanctum tokens accepted on routes/api.php:43-56,83-112.
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Admin/UserManagementController.php:135`](../app/Http/Controllers/Admin/UserManagementController.php#L135) — destroy() runs only $user->delete() with no self-delete guard, no tokens()->delete(), no session deletion, and no roles()->detach().
  - [`config/sanctum.php:53`](../config/sanctum.php#L53) — 'expiration' => null keeps issued bearer tokens valid indefinitely until deleted.
  - [`database/migrations/2026_11_02_100001_create_role_user_table.php:16`](../database/migrations/2026_11_02_100001_create_role_user_table.php#L16) — role_users.user_id defines cascadeOnUpdate() only (same for emergency_requests.user_id at 2026_11_02_100006:29 and trip_calls.caller_id at 2026_11_02_175537:17; whereas user_medical_profiles, hospitals, driver_profiles, user_addresses, calls, device_tokens, and call_participants specify nullOnDelete/cascadeOnDelete).
  - [`routes/api.php:43`](../routes/api.php#L43) — auth:sanctum routes /api/v1/auth/me, /me/*, /hospitals/nearest, /device-tokens, /calls/* have no role middleware.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); UserManagementTest.php currently has zero tests for destroy() or token revocation.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add Pest tests in tests/Feature/UserManagementTest.php verifying: (1) changing/removing roles in update() deletes personal_access_tokens and sessions for that user, (2) self-deletion or deleting the last administrator is rejected, and (3) deleting a user with roles succeeds cleanly and leaves zero tokens/sessions (while safely guarding or handling emergency_requests/trip_calls history).
  - *Lingkungan Deploy*: Confirm deleting a test user with an assigned role succeeds cleanly and revokes their existing bearer token and web session.
- **Rekomendasi Perbaikan Terkecil**: Revoke $user->tokens()->delete() and delete sessions for $user->id whenever roles change in update() and on destroy(); forbid deleting the authenticated admin or the last administrator; and in destroy() detach roles and block deletion (or soft-delete/anonymize) when emergency_requests or trip_calls history exists.

#### [Medium (Est.)] `admin-users-roles-scalar-validation-bypass` — Missing array validation rule on roles allows scalar strings to bypass validation and crash DB sync()

- **Deskripsi & Potensi Dampak**: StoreUserRequest and UserManagementController::update() define 'roles' => ['nullable'] without 'array' alongside 'roles.*' => ['uuid', 'exists:roles,id']. In Laravel's ValidationData, wildcard rules do not run on scalar strings. Both store() and update() fall back to raw $request->input('roles') when non-array and pass the unvalidated scalar to $user->roles()->sync([$roleId]), crashing PostgreSQL with SQLSTATE[22P02]/23503; in store(), User::create() runs outside a transaction and leaves an orphaned user committed.
- **Akar Masalah**: Missing 'array' rule on 'roles' and reading raw $request->input('roles') outside a transaction in UserManagementController.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Requests/Admin/StoreUserRequest.php:32`](../app/Http/Requests/Admin/StoreUserRequest.php#L32) (`StoreUserRequest::rules`) — 'roles' => ['nullable'] lacks 'array', allowing scalar strings past 'roles.*' (line 33); UserManagementController::update() repeats this at lines 116-117.
  2. **[PROPAGATION]** [`app/Http/Controllers/Admin/UserManagementController.php:75`](../app/Http/Controllers/Admin/UserManagementController.php#L75) (`UserManagementController::store`) — Reads raw $request->input('roles') and assigns scalar $rolesInput to $roleId at line 76 (and lines 120-121 in update()).
  3. **[SINK]** [`app/Http/Controllers/Admin/UserManagementController.php:79`](../app/Http/Controllers/Admin/UserManagementController.php#L79) (`UserManagementController::store`) — Passes unvalidated scalar $roleId to $user->roles()->sync([$roleId]) (and line 123 in update()), throwing SQLSTATE[22P02]/23503; in store(), User::create() (line 67) is already committed.
- **Bukti Tambahan**:
  - [`app/Http/Requests/Admin/StoreUserRequest.php:32`](../app/Http/Requests/Admin/StoreUserRequest.php#L32) — 'roles' => ['nullable'] lacks 'array' rule.
  - [`vendor/laravel/framework/src/Illuminate/Validation/ValidationData.php:57`](../vendor/laravel/framework/src/Illuminate/Validation/ValidationData.php#L57) — extractValuesForWildcards matches only 'roles.<key>' dotted keys; scalar 'roles' yields zero wildcard checks.
  - [`app/Http/Controllers/Admin/UserManagementController.php:75`](../app/Http/Controllers/Admin/UserManagementController.php#L75) — store() lines 75-79 and update() lines 116-123 read raw $request->input('roles') outside DB::transaction() and sync scalar values.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); Pest test for scalar roles payload not executed.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add tests in tests/Feature/UserManagementTest.php sending roles='not-a-uuid' and roles='00000000-0000-0000-0000-000000000000' to POST /admin/users and PUT /admin/users/{user}, asserting 302 validation errors and zero orphaned users.
  - *Lingkungan Deploy*: Verify scalar or malformed roles payloads fail validation cleanly on PostgreSQL.
- **Rekomendasi Perbaikan Terkecil**: Add 'array' and 'max:1' to 'roles' in StoreUserRequest and UserManagementController::update(), consume only $validated['role_id'] ?? ($validated['roles'][0] ?? null), and wrap UserManagementController::store() in DB::transaction().

#### [Medium (Est.)] `admin-users-update-missing-driver-profile-lifecycle` — Promoting a user to driver via UserManagementController::update() omits DriverProfile creation and demotion leaves active DriverProfile

- **Deskripsi & Potensi Dampak**: UserManagementController::update() calls $user->roles()->sync(...) without creating a DriverProfile when assigning the 'driver' role (unlike store()), leaving promoted drivers locked out of all driver API routes with 403 from DriverController::driver(). Conversely, demoting a driver does not set an existing DriverProfile offline, leaving it eligible in DriverProfile::scopeDispatchable() (which does not check user role) and preserving driverProfiles links used by HospitalDashboardController's fallback lookup.
- **Akar Masalah**: UserManagementController::update() updates only the role_users pivot without synchronizing DriverProfile creation or offline deactivation.
- **Jejak Kode (`entrypoint → sink`)**:
  1. **[ENTRYPOINT]** [`app/Http/Controllers/Admin/UserManagementController.php:112`](../app/Http/Controllers/Admin/UserManagementController.php#L112) (`UserManagementController::update`) — Accepts role_id/roles via PUT /admin/users/{user} to promote or demote an existing user.
  2. **[PROPAGATION]** [`app/Http/Controllers/Admin/UserManagementController.php:123`](../app/Http/Controllers/Admin/UserManagementController.php#L123) (`UserManagementController::update`) — Syncs role_users without creating a DriverProfile when role is 'driver' or setting an existing DriverProfile offline when leaving 'driver'.
  3. **[SINK]** [`app/Http/Controllers/Api/V1/Driver/DriverController.php:23`](../app/Http/Controllers/Api/V1/Driver/DriverController.php#L23) (`DriverController::driver`) — Aborts 403 because $request->user()->driverProfile is null for drivers promoted via update(); meanwhile demoted online drivers still match DriverProfile::scopeDispatchable() (DriverProfile.php:89-97).
- **Bukti Tambahan**:
  - [`app/Http/Controllers/Admin/UserManagementController.php:81`](../app/Http/Controllers/Admin/UserManagementController.php#L81) — store() creates DriverProfile when assigning driver role, whereas update() (lines 112-130) omits DriverProfile synchronization.
  - [`app/Http/Controllers/Api/V1/Driver/DriverController.php:21`](../app/Http/Controllers/Api/V1/Driver/DriverController.php#L21) — All driver mobile API endpoints rely on $request->user()->driverProfile being non-null.
  - [`app/Models/DriverProfile.php:89`](../app/Models/DriverProfile.php#L89) — scopeDispatchable() checks availability_status, GPS freshness, ambulance, and pending offers without checking whether the user still holds the driver role.
- **Penghambat Validasi (`blockers`)**:
  - Execution blocked on non-sandboxed Windows host (sandboxed-source-and-local-only); Pest integration tests not executed.
- **Rencana Validasi Aman**:
  - *Lokal / Sandbox*: Add tests in tests/Feature/UserManagementTest.php promoting a user to driver via PUT /admin/users/{user} and verifying DriverProfile creation and GET /api/v1/driver/profile 200; also demoting an online driver and verifying availability_status becomes offline.
  - *Lingkungan Deploy*: Promote a test user to driver in the admin UI and confirm driver API login/profile succeeds.
- **Rekomendasi Perbaikan Terkecil**: Inside DB::transaction() in UserManagementController::update(), create DriverProfile via firstOrCreate when assigning 'driver', and require no activeTrip() and set availability_status='offline' when removing 'driver'.


---

## 4. Lampiran A — Kandidat yang Ditolak / Terbantahkan (`rejected` — 10 Item)

Kandidat berikut sempat terdeteksi pada fase hunting awal, namun **ditolak** setelah verifikasi adversarial terhadap implementasi aktual, middleware framework, dan skema migrasi:

| # | Fingerprint | Judul Klaim | Alasan Penolakan |
|---:|---|---|---|
| 1 | `callsignaling-iceservers-host-reflection-self-impact` | CallSignalingController::iceServers() Reflects Request Host / X-Forwarded-Host into Caller's Own TURN URI Response | Rejected as self-impact / ungrounded cross-boundary prerequisite: GET /api/v1/calls/{tripCall}/ice-servers is an authenticated Bearer-token endpoint whose JSON response is returned solely to the requesting user ($request->user()) and is never stored, broadcast to the other participant, or cached in a shared cache in repository configuration (docker/Caddyfile, docker/nginx.conf). An attacker supplying Host or X-Forwarded-Host only reflects that host into their own response with their own user_id HMAC credential; altering another participant's response requires transport MITM (which could already rewrite the HTTP response directly without X-Forwarded-Host) or an external shared cache absent from source. |
| 2 | `client-blade-js-attr-xss-roles-index` | Stored XSS in Admin Role Delete Confirmation via Blade Interpolation Inside onsubmit JS String | Same-principal / same-privilege-tier self-impact only: only users already holding the highest-privilege 'administrator' role can create or rename Role records (routes/web.php:89) and only 'administrator' users can view /admin/roles. No lower-trust principal can introduce a malicious role name anywhere in the application. Cleanup of the inline onsubmit handler and role name regex is retained in hardening_notes. |
| 3 | `driver-location-spoofing-and-unthrottled-logs` | Unthrottled Driver\LocationController::store allows arbitrary GPS spoofing and unbounded trip_location_logs growth | Self-registered driver accounts cannot exploit GPS spoofing to intercept dispatch offers or bloat trip_location_logs because DriverProfile::scopeDispatchable, AvailabilityController::update, and TripController::accept enforce possession of a hospital-assigned Ambulance. For an authorized ambulance driver, trusting client-reported device coordinates is inherent to the fleet tracking trust model (and AvailabilityController::update also accepts client-supplied coordinates when going online). Missing route throttling and trip_location_logs deduplication are defense-in-depth hardening notes rather than a security boundary bypass. |
| 4 | `infra-missing-http-security-headers` | Missing HTTP security headers (HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) across Caddy, Nginx, and Laravel | Checklist/defense-in-depth hardening without a reachable boundary violation: bare missing security headers (HSTS, CSP, X-Content-Type-Options, Referrer-Policy, server_tokens) are not standalone vulnerabilities, and the claimed clickjacking of authenticated admin/hospital dashboards is blocked in modern browsers because config/session.php:202 enforces SameSite=lax on session cookies, preventing authenticated state from loading inside cross-origin iframes. Retained in hardening_notes. |
| 5 | `infra-php-fpm-root-rw-host-mount` | PHP-FPM workers and Artisan containers execute as root with read-write host source mount | Defense-in-depth / blast-radius hardening without a reachable boundary violation: source inspection found no unauthenticated or authenticated file-upload, arbitrary file-write, deserialization, or PHP command-execution primitive in app/ or routes/ allowing a lower-trust principal to execute code or write to /var/www/html. Retained in hardening_notes. |
| 6 | `infra-session-secure-cookie-default-null` | SESSION_SECURE_COOKIE unset by default (null) permitting plaintext session cookies over HTTP :8080 or spoofed X-Forwarded-Proto | Blocked by CI/CD deployment config and framework auto-secure behavior with no independent boundary violation beyond INFRA-04: deploy.yml:68 sets SESSION_SECURE_COOKIE=true on deployment, and even when null locally, any login via Caddy HTTPS (X-Forwarded-Proto: https) satisfies $request->isSecure() so Response::prepare() sets secureDefault=true on the regenerated session cookie (LoginController.php:62), preventing downgrade to HTTP :8080. Directly visiting http://...:8080/login exposes the login credentials themselves and is already captured by INFRA-04. Setting an explicit production fallback is retained in hardening_notes. |
| 7 | `infra-wildcard-cors-and-reverb-origins` | Wildcard CORS (allowed_origins: ['*']) and wildcard Reverb WebSocket origins expose API and public PII broadcast channels cross-origin | Prevented by framework design and not a cross-origin credential boundary: (1) Laravel's default CORS config sets supports_credentials => false and bootstrap/app.php does not enable Sanctum statefulApi(), so browsers do not attach ambient cookies cross-origin and authenticated API endpoints require Authorization: Bearer tokens that third-party pages cannot trigger ambiently; (2) Reverb does not authenticate WebSockets via ambient browser cookies (private channels require server-signed HMAC auth tokens from /broadcasting/auth), so wildcard allowed_origins does not enable Cross-Site WebSocket Hijacking, while public channels emergency-requests and hospital.{id} are already accessible to any standalone client without a browser Origin. Restricting CORS and Reverb origins is noted in hardening_notes. |
| 8 | `schema-calls-callee-id-rename-conflict` | Migration 2026_11_09_140003 renames calls.callee_id to receiver_id while App\Models\Call uses callee_id | Rejected as false on current disk source: 2026_11_08_124323_create_calls_table.php creates both receiver_id (line 17) and callee_id (line 18), and 2026_11_09_140003_update_calls_table_fields.php:16 explicitly checks ! Schema::hasColumn('calls', 'receiver_id') before renaming. On current migrations both columns exist and Call / CallLifecycleService operate without missing-column failure. |
| 9 | `unused-broken-user-register-request` | Unused UserRegisterRequest contains broken authorization, mismatched column names, and schema-exceeding max lengths | Rejected as unwired dead code: full repository grep confirmed UserRegisterRequest has zero reachable routes, controllers, or tests invoking it. |
| 10 | `web-auth-password-bcrypt-72-byte-truncation` | Web registration and admin/driver user creation lack bcrypt 72-byte password length bounds | Rejected as lacking an exploitable security boundary violation or functional defect on current configuration: with hashing.bcrypt.limit defaulting to null, PHP's password_hash/password_verify still enforce 72 bytes (576 bits) of user-chosen password entropy consistently during registration and login, causing neither a cross-principal authentication bypass nor a runtime crash. Only an unverified BCRYPT_LIMIT=72 env override could trigger an exception (noted under hardening). |

---

## 5. Lampiran B — Catatan Hardening & Kebersihan Kode (10 Item)

Hal-hal berikut tidak membuka celah lintas batas otorisasi secara langsung saat ini, namun direkomendasikan sebagai perbaikan pertahanan berlapis dan pemeliharaan kode:

- **HARD-01 — Docker & PHP-FPM Runtime Privileges** (`docker/php-fpm.conf:5-6; Dockerfile:21; compose.yaml:51`): Worker PHP-FPM dan kontainer Artisan berjalan sebagai `root` (`user = root`, `php-fpm -R`) dengan bind-mount `.:/var/www/html` read-write. Ditolak sebagai temuan lintas batas terpisah karena belum ditemukan primitif unggah file/RCE di rute, namun wajib diperketat ke `www-data` dan memisahkan volume tulis ke `storage/` & `bootstrap/cache/`.
- **HARD-02 — CORS & Reverb Origin Allowlist** (`vendor/laravel/framework/config/cors.php:22; config/reverb.php:86,109`): `allowed_origins` bernilai `['*']` di CORS bawaan (`supports_credentials => false`) dan konfigurasi Reverb. Ditolak dari kerentanan CSRF/CSWSH karena autentikasi memakai Bearer/HMAC bukan cookie lintas domain, namun tetap perlu di-publish `config/cors.php` dan mempersempit origin Reverb pada produksi.
- **HARD-03 — HTTP Security Response Headers & Nginx Server Tokens** (`docker/Caddyfile:4-11; docker/nginx.conf:3-29`): Caddy dan Nginx belum menambahkan HSTS, Content-Security-Policy, `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, dan `server_tokens off`. Tambahkan header standar pada Caddyfile sebagai pertahanan berlapis.
- **HARD-04 — Fallback Default `SESSION_SECURE_COOKIE`** (`config/session.php:172`): Set fallback default di `config/session.php` menjadi `env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')` agar deployment Compose mandiri di luar CI/CD (`deploy.yml:68`) tetap memaksa flag `Secure`.
- **HARD-05 — Admin Roles Inline `onsubmit` & Regex Nama Role** (`resources/views/admin/roles/index.blade.php:44; app/Http/Controllers/Admin/RoleManagementController.php`): Ganti interpolasi string JS inline `onsubmit="return confirm('...')"` di `roles/index.blade.php` dengan atribut `data-confirm` yang dibaca via JS terpusat serta validasi format role `^[a-z0-9_]+$`.
- **HARD-06 — Sisa Legacy Migration `calls.callee_id`** (`database/migrations/2026_11_08_124323_create_calls_table.php; 2026_11_09_140003_update_calls_table_fields.php:16-18; app/Models/Call.php`): Commit `e57e68b` menambahkan `receiver_id` secara retroaktif ke `create_calls_table`. Jika ada basis data produksi lama yang bermigrasi sebelum `e57e68b`, `update_calls_table_fields` akan me-rename `callee_id -> receiver_id` sehingga model `Call` berpotensi bentrok; konsolidasikan kolom menjadi `receiver_id` sepenuhnya.
- **HARD-07 — Paritas Panjang Bcrypt & Test Suite PostgreSQL** (`vendor/laravel/framework/config/hashing.php:34; phpunit.xml:27`): (a) Bila `BCRYPT_LIMIT=72` kelak diaktifkan, endpoint web register/admin/driver akan melempar `InvalidArgumentException` tanpa validasi 72-byte seperti `Api\V1\RegisterRequest`; (b) `phpunit.xml` memakai SQLite `:memory:` yang mengabaikan `varchar(N)` truncation, sensitivitas huruf `email`, dan `SELECT ... FOR UPDATE`—tambahkan stage test PostgreSQL di CI.
- **HARD-08 — Throttling Lokasi Driver, Deduplikasi Log, & Filter Koordinat** (`routes/api.php:118; app/Http/Resources/V1/EmergencySearchResource.php:37; app/Providers/AppServiceProvider.php`): (a) Tambahkan rate-limit & deduplikasi jarak/waktu di `POST /api/v1/driver/location`, jangan perbarui `location_updated_at` ketika driver offline; (b) tambah `whereNotNull('current_longitude')` di `EmergencySearchResource.php:37`; (c) aktifkan `Model::shouldBeStrict(!app()->isProduction())` agar atribut salah seperti `$trip->user?->name` langsung tertangkap.
- **HARD-09 — TTL Kredensial TURN Pasca-Trip, Webhook Rate Limit, & Kelas/Event Dead Code** (`app/Http/Controllers/Api/V1/CallSignalingController.php:95-123; app/Services/TripCallStateStore.php:106-114; app/Services/Call/LiveKitService.php:70-211; routes/api.php:37-40`): (a) `CallSignalingController::iceServers()` masih menerbitkan kredensial TURN 1 jam setelah trip berakhir dan tidak menutup `TURN_HOST` secara eksplisit; (b) `TripCallStateStore::terminalStatuses()` melewatkan `CallStatus::Rejected`; (c) `LiveKitService` melakukan simulasi diam-diam saat kredensial kosong; (d) rute webhook LiveKit dan `reverb.rate_limiting` tidak di-throttle; (e) hapus kelas/event mati (`UserRegisterRequest`, `CallTokenController`, `LiveKitTokenGenerator`, `Services/LiveKitTokenService`, root `App\Events\Call*`, serta link `#emergency` pada `admin-nav.blade.php:46`).
- **HARD-10 — Sisa Kebersihan Konfigurasi Frontend & Echo** (`resources/js/echo.js:6-9; resources/views/welcome.blade.php; public/hot; routes/api.php:44`): (a) `resources/js/echo.js` memakai fallback key/port Reverb lokal (`qhbpeh3fhgzvx1pxqag4`, `:8080`, `http`) yang tidak cocok dengan Compose (`cariambulan-key`, `:8081`/`8443`), dan `public/hot` tertinggal di workspace; (b) `welcome.blade.php` memuat `app.js` / inisialisasi Echo meski tidak ada widget realtime publik; (c) route `PUT /api/v1/auth/me` di `routes/api.php:44` mengarah ke `->name('')` sehingga bernama `api.v1.` dengan trailing dot.

---

## 6. Lampiran C — Ringkasan Cakupan Audit (14 Unit)

| # | Wave | Subsistem | Batas / Permukaan | Berkas Ditinjau | Temuan `needs_validation` |
|---:|---:|---|---|---:|---:|
| 1 | 1 | `deployment-infra-secrets` | `compose.yaml#runtime_config` → `docker/entrypoint.sh#deployment_and_secrets` | 84 | 7 |
| 2 | 1 | `web-client-dom` | `resources/js/app.js#Echo_innerHTML` → `resources/views/dashboard/hospital.blade.php#DOM_rendering` | 12 | 4 |
| 3 | 1 | `web-client-dom` | `resources/views/admin/users/show.blade.php#onsubmit` → `resources/views/admin/roles/index.blade.php#blade_js_attr` | 21 | 2 |
| 4 | 1 | `livekit-calls-webhooks` | `routes/api.php#/api/v1/calls` → `app/Services/Call/CallLifecycleService.php#call_auth_and_concurrency` | 18 | 6 |
| 5 | 1 | `api-auth-profiles` | `routes/api.php#/api/v1/device-tokens` → `app/Http/Controllers/Api/V1/DeviceTokenController.php#token_ownership` | 11 | 4 |
| 6 | 1 | `webrtc-signaling-broadcast` | `routes/api.php#/api/v1/driver/calls` → `app/Http/Controllers/Api/V1/Driver/CallController.php#ensureCallee_and_iceServers` | 14 | 2 |
| 7 | 2 | `webrtc-signaling-broadcast` | `routes/api.php#/api/v1/emergency-requests/{emergencyRequest}/calls` → `app/Http/Controllers/Api/V1/TripCallController.php#signaling_and_residual_surfaces` | 18 | 3 |
| 8 | 1 | `dispatch-fleet` | `routes/api.php#/api/v1/emergency-requests` → `app/Models/EmergencyRequest.php#dispatch_and_state_machine` | 30 | 5 |
| 9 | 1 | `api-auth-profiles` | `routes/api.php#/api/v1/me` → `app/Http/Resources/V1/EmergencyRequestResource.php#medical_privacy_and_auth` | 33 | 6 |
| 10 | 1 | `livekit-calls-webhooks` | `routes/api.php#/api/v1/webhooks/livekit` → `app/Services/Call/LiveKitTokenService.php#verifyWebhookSignature` | 7 | 3 |
| 11 | 1 | `webrtc-signaling-broadcast` | `routes/channels.php#broadcast_channels` → `app/Events/EmergencyRequestStatusUpdated.php#channel_privacy` | 21 | 2 |
| 12 | 1 | `web-auth-rbac` | `routes/web.php#/admin/users` → `app/Http/Controllers/Admin/UserManagementController.php#role_and_user_crud` | 23 | 4 |
| 13 | 1 | `web-auth-rbac` | `routes/web.php#/dashboard/hospital` → `app/Http/Controllers/Dashboard/HospitalDashboardController.php#hospital_admin_scope` | 22 | 5 |
| 14 | 1 | `web-auth-rbac` | `routes/web.php#POST /register` → `app/Http/Controllers/Auth/RegisterController.php#store` | 25 | 3 |
