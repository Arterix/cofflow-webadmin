# Cofflow Admin Dashboard

Web admin dashboard untuk coffee shop **Cofflow**. Mengelola menu, stok bahan baku, diskon, order, pegawai, opname stok, dan laporan penjualan.

Bagian dari ekosistem Cofflow: backend REST API + admin web (repo ini) + Flutter customer/employee app. Kedua app mobile (`customer_app`, `employee_app`) konsumsi API ini lewat token Sanctum (`Authorization: Bearer`) — tidak ada koneksi DB langsung dari mobile.

Stack: **Laravel 13**, **PHP 8.3+**, **Tailwind CSS v4**, **Vite**, **PostgreSQL/SQLite**.

---

## Fitur Utama

- **Autentikasi role-based** — login admin via web session (Sanctum untuk mobile API).
- **Dashboard analitik** — kartu ringkasan hari ini, chart penjualan 7 hari, top 5 menu 30 hari, **panel statistik per-candle** (klik bar di chart → drill down statistik harian).
- **Manajemen Menu** — CRUD menu, upload foto, kategori, status aktif/nonaktif, editor **BOM** (Bill of Materials).
- **Manajemen Condiment** — grup add-on (single/multi select) dengan **drag-to-reorder** opsi.
- **Manajemen Stok** — bahan baku, restock, highlight kritis (`current_stock < minimum_stock`).
- **Opname Stok** — kasir/admin catat stok fisik end-of-shift, admin review & approve untuk menyesuaikan `current_stock`. Variance tracking + reason code.
- **Diskon** — 3 jenis: per-produk, kode promo, event discount (multi-menu).
- **Order Monitor** — daftar order read-only dengan filter status/tipe/tanggal.
- **Pegawai** — kelola akun kasir/admin, aktif/nonaktif.
- **Laporan** — laporan rentang tanggal: total order, revenue, per metode bayar, top 10 menu.
- **Push Notification** — FCM alerts ke kasir/admin saat order baru, stok kritis, status berubah.
- **Payment Gateway** — integrasi Midtrans (QRIS + Virtual Account) dengan webhook signature verification.

---

## Arsitektur

### Pola: MVC + Service Layer + Observer

```
Request → Controller (thin) → Service (business logic) → Model → Database
                                    ↕
                              Observer (side effects: FCM, stock)
```

- **Controller tipis** — hanya validasi input, delegasi ke service, dan return response.
- **5 Service class** mengenkapsulasi business logic:

| Service | Tanggung Jawab |
|---|---|
| `StockService` | Validasi stok, deduction (dengan `lockForUpdate` untuk mencegah race condition), restoration pada cancel, opname adjustment |
| `DiscountService` | Kalkulasi diskon multi-layer (produk, event, promo), batch-load untuk optimasi query |
| `MidtransService` | Payment gateway QRIS/VA, webhook signature verification (reject di production bila key kosong) |
| `FcmService` | Push notification via Firebase + persist ke DB |
| `QueueService` | Generate queue number harian dengan atomic locking |

- **OrderObserver** — hook ke event `created`/`updated` untuk trigger FCM alerts dan stock restoration saat cancel.
- **Dual controller hierarchy**: `Api/` return JSON via `ApiResponse` trait; `Web/` return Blade views.
- **Discount tidak stack** — saat banyak diskon eligible per item, hanya nilai terbesar yang dipakai.
- **Status FSM** divalidasi di `StaffOrderController`:
  `pending → processing → ready → completed`, `pending|processing → cancelled` (restore stok).

### Concurrency & Data Integrity

- **Stock deduction** menggunakan `lockForUpdate()` pada ingredients di dalam `DB::transaction()` — mencegah race condition saat concurrent orders.
- **Queue number** menggunakan `lockForUpdate()` pada `MAX(queue_number)` — mencegah duplicate queue numbers.
- **Order creation atomic** — validasi stok, create order, insert items, deduct stok semua di dalam satu transaction. Gagal di step manapun → rollback semua.
- **Webhook signature** — di-reject di production bila server key tidak dikonfigurasi. Hanya bypass di local/testing.

### API Response Format

Semua endpoint menggunakan envelope konsisten:

```json
{ "success": true, "data": { ... }, "message": "OK" }
```

Error di-wrap secara global di `bootstrap/app.php`:
- `ValidationException` → 422
- `AuthenticationException` → 401
- `ModelNotFoundException` → 404
- Generic → 500

---

## Design System

Brand tokens didefinisikan di [resources/css/app.css](resources/css/app.css) via Tailwind v4 `@theme`:

| Token | Hex | Pakai untuk |
|---|---|---|
| `primary` | `#3E2723` | Espresso brown — header, sidebar, primary CTAs |
| `secondary` | `#DCD2B0` | Oatmeal cream — page background |
| `accent` | `#81C784` | Matcha green — sukses, add buttons, today bar |
| `alert` | `#E57373` | Cinnamon red — error, kritis, delete |

Komponen reusable:
- **`.btn-action`** + variants (`.btn-add` / `.btn-edit` / `.btn-delete` / `.btn-info`) — konsistensi tombol aksi.
- **`.status-pill`** + variants (`.is-success` / `.is-danger` / `.is-warning` / `.is-info` / `.is-muted` / `.is-strong`) — badge status seragam di seluruh halaman.

Typography: **Poppins** untuk display/heading, **Roboto** untuk body.

---

## Setup Lokal

### Prasyarat

- PHP 8.3+
- Composer 2.x
- Node.js 18+ + npm
- SQLite (default, bundled) atau PostgreSQL 15

### Langkah

```bash
# 1. Clone & install dependencies
git clone <repo-url> cofflow-admin
cd cofflow-admin
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (SQLite default — file otomatis dibuat)
touch database/database.sqlite        # Linux/Mac
# atau Windows PowerShell: New-Item database/database.sqlite -ItemType File

php artisan migrate --seed

# 4. Build assets + jalankan server
npm run dev          # terminal 1 — Vite dev server
php artisan serve    # terminal 2 — Laravel app di http://localhost:8000
```

### Login Seeded

| Email | Password | Role |
|---|---|---|
| `admin@cofflow.test` | `password` | admin |
| `kasir@cofflow.test` | `password` | kasir |
| `customer@cofflow.test` | `password` | customer |

Plus 5 customer dummy (`cust1@cofflow.test` … `cust5@cofflow.test`).

---

## Dummy Data

`php artisan migrate --seed` mengisi:

- **3 kategori** (Coffee, Non-Coffee, Snack)
- **6 menu** dengan BOM lengkap
- **5 bahan baku** dengan stok awal
- **3 condiment group** (Ukuran, Tingkat Manis, Extra)
- **~117 order** tersebar 7 hari terakhir (lebih banyak di hari recent)
  - Status: completed/ready/processing/pending/cancelled
  - Mix walk-in/preorder ~65/35
  - Pakai promo code 18% probability
  - Jam dibobotkan ke 12:00 & 18:00 (peak hour realistic)
- **4 diskon** (1 produk untuk Latte, 2 promo code, 1 event Coffee Fest)
- **2 opname stok** (1 approved kemarin, 1 pending hari ini)

Reset & re-seed:
```bash
php artisan migrate:fresh --seed
```

---

## Struktur Direktori

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/                      # Mobile REST API endpoints
│   │   │   ├── AuthController        # register, login, logout, me, changePassword, FCM token
│   │   │   ├── MenuController        # index, show, categories (public)
│   │   │   ├── OrderController       # store, index, show, paymentStatus, destroy
│   │   │   ├── NotificationController# CRUD notifikasi
│   │   │   ├── WebhookController     # Midtrans webhook handler
│   │   │   ├── Admin/                # Full admin API (menu, stok, diskon, analytics, staff)
│   │   │   └── Staff/                # Staff order management + queue
│   │   └── Web/
│   │       ├── Admin/                # Web admin Blade controllers
│   │       └── Auth/                 # Web login
│   └── Middleware/
│       ├── CheckRole.php             # API: variadic roles check
│       └── EnsureAdmin.php           # Web: session-based admin gate
├── Models/                           # 17 Eloquent models
├── Observers/
│   └── OrderObserver.php             # FCM trigger + stock restore on cancel
├── Services/
│   ├── StockService.php              # Stock validation, deduction (with locking), restoration
│   ├── DiscountService.php           # Multi-layer discount calc (batch-optimized)
│   ├── MidtransService.php           # Payment gateway + webhook verification
│   ├── FcmService.php                # Push notification + DB persist
│   └── QueueService.php              # Daily queue number (atomic)
├── Traits/
│   └── ApiResponse.php               # Consistent JSON envelope
└── Providers/
    └── AppServiceProvider.php        # Observer registration
database/
├── factories/                        # Model factories untuk testing
│   ├── UserFactory.php
│   ├── MenuCategoryFactory.php
│   ├── MenuFactory.php
│   └── OrderFactory.php
├── migrations/                       # Skema lengkap (~22 file)
└── seeders/
    ├── DatabaseSeeder.php            # Master + base lookups
    └── DummyDataSeeder.php           # Order + diskon + opname dummy
resources/
├── css/app.css                       # Tailwind v4 @theme + button/status classes
└── views/admin/                      # Blade templates per resource
routes/
├── api.php                           # REST API (mobile) — 4 tier auth
└── web.php                           # Admin dashboard (web)
tests/
├── Unit/
│   ├── DiscountServiceTest.php       # Kalkulasi diskon percentage/fixed
│   └── QueueServiceTest.php          # Queue number: first-of-day, increment, reset
└── Feature/
    ├── AuthApiTest.php               # Register, login, me, logout
    ├── MenuApiTest.php               # Menu index, show, categories
    └── OrderApiTest.php              # Create, index, show, payment status
```


---

## Database Schema (Ringkas)

| Tabel | Deskripsi |
|---|---|
| `users` | Akun dengan role (customer/kasir/admin), FCM token, status aktif |
| `menus` + `menu_categories` | Menu dengan kategori, harga, status aktif |
| `condiment_groups` + `condiment_options` | Add-on (single/multi select) dengan additional price |
| `menu_condiment_groups` | Pivot M:M menu ↔ condiment group |
| `ingredients` + `bom_items` | Bahan baku + Bill of Materials per menu |
| `orders` + `order_items` + `order_item_condiments` | Order dengan items, condiment snapshot, payment info |
| `order_status_logs` | Timeline perubahan status order |
| `product_discounts` / `promo_codes` / `event_discounts` | 3 jenis diskon |
| `notifications` | In-app notification per user |
| `stock_opnames` + `stock_opname_items` | Opname stok per shift |

---

## Endpoint API (Ringkas)

Base URL: `https://{host}/api`

### Public (tanpa auth)
- `POST /auth/register`, `POST /auth/login` (throttle: 10/menit)
- `GET /menus`, `GET /categories`
- `POST /webhook/midtrans` (signature-verified)

### Auth (semua role)
- `POST /auth/logout`, `GET /auth/me`, `POST /auth/change-password`
- `POST /auth/fcm-token`
- `POST /orders`, `GET /orders`, `GET /orders/{id}`, `GET /orders/{id}/payment-status`
- `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`

### Staff (kasir/admin)
- `GET /staff/orders`, `GET /staff/orders/{id}`, `PATCH /staff/orders/{id}/status`
- `GET /staff/queue/active`, `GET /staff/queue/estimate`

### Admin only
- CRUD: categories, menus (+ BOM + condiment attach), condiments, ingredients (+ restock), discounts
- `GET /admin/analytics/*` — summary, weekly sales, top menus, peak hours, report
- CRUD staff management

---

## Skrip Berguna

```bash
php artisan migrate:fresh --seed      # Reset DB + seed dummy
php artisan route:list                # Daftar routes
php artisan route:list --name=admin   # Filter admin routes
php artisan tinker                    # REPL untuk eksplorasi model
php artisan config:cache              # Cache config untuk production
php artisan config:clear              # Clear config cache
npm run build                         # Build production assets
npm run dev                           # Vite dev (hot reload)
php artisan test                      # Jalankan semua test
php artisan test --testsuite=Unit     # Unit test saja
php artisan test --testsuite=Feature  # Feature test saja
```

---

## Test

### Jalankan Test

```bash
php artisan test
```

> **Prasyarat:** Feature tests membutuhkan database test. Pastikan `phpunit.xml` sudah dikonfigurasi dengan `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:` untuk CI, atau gunakan database terpisah.

### Test Coverage

| File | Jumlah | Tipe | Cakupan |
|------|--------|------|---------|
| `tests/Unit/DiscountServiceTest.php` | 6 | Unit | Kalkulasi diskon percentage/fixed, edge cases (zero, exceeds base) |
| `tests/Unit/QueueServiceTest.php` | 3 | Unit (DB) | Queue number: first-of-day, increment, daily reset |
| `tests/Feature/AuthApiTest.php` | 7 | Feature | Register, login, me, logout, validasi duplicate email/password pendek |
| `tests/Feature/MenuApiTest.php` | 6 | Feature | Menu index, show, categories, 404, public access tanpa auth |
| `tests/Feature/OrderApiTest.php` | 8 | Feature | Create order (cash), auth required, validasi, index own orders, show, payment status |

### Factories

| Factory | Model | Default |
|---------|-------|---------|
| `MenuCategoryFactory` | `MenuCategory` | Random name + slug |
| `MenuFactory` | `Menu` | Random name, price 10k-80k, is_active = true |
| `OrderFactory` | `Order` | pending, cash, unpaid, random queue number |
| `UserFactory` | `User` | (bawaan Laravel) |

---

## Integrasi Eksternal

### Supabase (Storage + Postgres opsional)

1. Buat project di [supabase.com](https://supabase.com) → Settings → API → copy `Project URL` + `secret key` (atau `service_role` key di project lama).
2. Storage → **New bucket**: `cofflow-images`, public ON.
3. SQL Editor → jalankan policy:
   ```sql
   CREATE POLICY "public_read" ON storage.objects
   FOR SELECT USING (bucket_id = 'cofflow-images');

   CREATE POLICY "auth_upload" ON storage.objects
   FOR INSERT WITH CHECK (bucket_id = 'cofflow-images');
   ```
4. Isi `.env`:
   ```env
   SUPABASE_URL=https://xxxxxxx.supabase.co
   SUPABASE_KEY=sb_secret_...
   SUPABASE_BUCKET=cofflow-images
   ```
5. **Opsional** — switch DB dari SQLite ke Supabase Postgres (Settings → Database → Connection string):
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=db.xxxxxxx.supabase.co
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres
   DB_PASSWORD=...
   ```
   Lalu `php artisan migrate --seed`.

### Firebase Cloud Messaging

1. Buat project di [Firebase Console](https://console.firebase.google.com) → Project Settings → **Service accounts** tab.
2. Klik **Generate new private key** (bahasa apapun — JSON yang di-download identik).
3. Pindahkan ke `storage/app/firebase-credentials.json`:
   ```powershell
   Move-Item "$env:USERPROFILE\Downloads\*firebase-adminsdk*.json" "storage\app\firebase-credentials.json"
   ```
4. Pastikan **Cloud Messaging API (V1)** Enabled di Project Settings → Cloud Messaging.
5. Install SDK + isi `.env`:
   ```bash
   composer require kreait/laravel-firebase
   ```
   ```env
   FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
   FIREBASE_PROJECT_ID=cofflow-backend
   ```
6. `FcmService::dispatch` ([app/Services/FcmService.php](app/Services/FcmService.php)) sudah ter-wire ke `kreait/laravel-firebase`. Notifikasi otomatis persist ke DB + kirim FCM bila `user.fcm_token` terisi.

### Midtrans

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxx
MIDTRANS_IS_PRODUCTION=false
```

> **Penting:** Webhook signature di-reject di production bila `MIDTRANS_SERVER_KEY` kosong. Pastikan key sudah diisi sebelum deploy. Di local/testing, signature bypass otomatis aktif untuk kemudahan development.

Semua konfigurasi Midtrans dibaca via `config('services.midtrans.*')`, bukan `env()` langsung — aman setelah `php artisan config:cache`.

### Cloudflare Tunnel (expose lokal ke mobile app)

Mobile app (employee/customer) butuh HTTPS endpoint untuk hit Laravel API. `cloudflared` tunnel = cara cepat tanpa deploy.

**Penting:** tunnel hanya forward traffic, BUKAN runtime PHP. Laravel tetap harus jalan di `localhost:8000` (via `php artisan serve`, Laragon, Herd, atau Valet). Kalau tidak ada yang listen di port itu → tunnel balas `502 Bad Gateway`.

Minimum setup = 2 terminal:
- Terminal 1: `php artisan serve` (atau Laragon/Herd auto-jalan)
- Terminal 2: `cloudflared tunnel --url http://localhost:8000`

Vite (`npm run dev`) opsional — hanya perlu kalau lagi edit Blade/CSS dengan hot reload. Bukan pengganti `artisan serve`.

#### Langkah

1. Install [cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/) lalu start tunnel:
   ```powershell
   cloudflared tunnel --url http://localhost:8000
   ```
   Output cetak URL seperti `https://xxxx-xxxx-xxxx.trycloudflare.com`. Copy.

2. Update `.env`:
   ```env
   APP_URL=https://xxxx-xxxx-xxxx.trycloudflare.com
   SANCTUM_STATEFUL_DOMAINS=xxxx-xxxx-xxxx.trycloudflare.com
   SESSION_DOMAIN=.trycloudflare.com
   FRONTEND_URL=https://xxxx-xxxx-xxxx.trycloudflare.com
   ```
   Lalu:
   ```powershell
   php artisan config:clear
   php artisan route:clear
   ```

3. Trust proxy header dari Cloudflare biar Laravel kenali request sebagai HTTPS (hindari mixed-content + redirect loop). Edit [bootstrap/app.php](bootstrap/app.php) — di dalam `withMiddleware`:
   ```php
   $middleware->trustProxies(at: '*');
   ```

4. Point mobile app ke tunnel URL via `--dart-define`:
   ```bash
   flutter run --dart-define=API_BASE_URL=https://xxxx-xxxx-xxxx.trycloudflare.com/api
   ```

5. Smoke test:
   ```powershell
   curl -sf -o $null -w "Status: %{http_code}`n" https://xxxx-xxxx-xxxx.trycloudflare.com/up
   ```

#### Named tunnel (URL stabil — butuh domain sendiri)

Quick tunnel (`--url`) ganti hostname tiap restart. Untuk URL tetap (mis. `api.cofflow.my.id`) pakai named tunnel. Butuh akun Cloudflare + domain yang sudah di-manage Cloudflare.

**JANGAN bikin A record manual dengan isi IPv4.** Tunnel tidak punya IP tetap. DNS record yang benar = CNAME ke `<tunnel-id>.cfargotunnel.com`, dan `cloudflared` yang bikin otomatis (langkah 4 di bawah).

1. **Pastikan domain Active dulu.** Kalau di dashboard status masih `pending`, Cloudflare belum pegang DNS-mu dan tidak ada record yang jalan. Buka **Overview** → Cloudflare kasih 2 nameserver (mis. `xena.ns.cloudflare.com`). Login ke registrar tempat beli domain → ganti nameserver mereka dengan 2 dari Cloudflare. Tunggu sampai status berubah jadi **Active** (bisa beberapa menit–jam).

2. Login tunnel (browser kebuka, pilih domain-nya):
   ```powershell
   cloudflared tunnel login
   ```

3. Buat tunnel — catat tunnel ID + path file credentials `.json` yang dicetak:
   ```powershell
   cloudflared tunnel create cofflow
   ```

4. Bikin DNS record otomatis (ini pengganti dialog "Add record" manual):
   ```powershell
   cloudflared tunnel route dns cofflow api.cofflow.my.id
   ```
   CNAME `api` muncul sendiri di DNS Records.

5. Buat `C:\Users\<user>\.cloudflared\config.yml`:
   ```yaml
   tunnel: cofflow
   credentials-file: C:\Users\<user>\.cloudflared\<TUNNEL-ID>.json
   ingress:
     - hostname: api.cofflow.my.id
       service: http://localhost:8000
     - service: http_status:404
   ```

6. Pastikan Laravel jalan (`php artisan serve`), lalu jalankan tunnel:
   ```powershell
   cloudflared tunnel run cofflow
   ```

7. Update `.env` (lihat langkah 2 di atas) pakai `api.cofflow.my.id`, lalu `php artisan config:clear`.

#### Catatan

- Quick tunnel (`--url`) = hostname random tiap restart. Named tunnel (di atas) = URL tetap.
- Tiap rotate hostname → update `.env` + restart `php artisan serve` + rebuild/restart mobile app.
- Webhook eksternal (Midtrans callback, FCM token registration) ikut pakai URL tunnel baru.

### Verifikasi

```bash
php artisan config:clear
php artisan tinker
>>> config('services.supabase')
>>> config('services.firebase')
>>> app('firebase.messaging')      # Kreait\Firebase\Messaging instance
```

### File yang TIDAK boleh ter-commit

- `.env`
- `storage/app/firebase-credentials.json`
- `database/database.sqlite`

Jangan paste private key / secret di chat, log, atau commit message. Rotate kalau bocor (Firebase: IAM → delete key; Supabase: Settings → API → rotate).

---

## Keamanan

- **Auth API:** Sanctum bearer tokens, rate-limited login (10/menit).
- **Auth Web:** Session-based, `EnsureAdmin` middleware cek `is_active` + role.
- **Password change:** Invalidasi semua token lama + issue token baru.
- **Webhook:** Midtrans signature verification — **reject di production bila key kosong**.
- **Config:** Semua service credential dibaca via `config()`, aman setelah `config:cache`.
- **Stock:** Deduction menggunakan `lockForUpdate()` di dalam transaction — data integrity terjaga saat concurrent access.
- **Debug logging:** `LogInterceptor` hanya aktif di debug mode.

---

## Lisensi

Kode internal proyek Cofflow. Tidak untuk distribusi publik tanpa izin.

Framework Laravel di-license di bawah [MIT](https://opensource.org/licenses/MIT).
