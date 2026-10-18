# Konco Space Admin Dashboard

Web admin dashboard untuk coffee shop **Konco Space**. Mengelola menu, stok bahan baku, diskon, order, pegawai, opname stok, dan laporan penjualan.

Bagian dari ekosistem Konco Space: backend REST API + admin web (repo ini) + Flutter customer/employee app. Kedua app mobile (`customer_app`, `employee_app`) konsumsi API ini lewat token Sanctum (`Authorization: Bearer`) — tidak ada koneksi DB langsung dari mobile.

Stack: **Laravel 13**, **PHP 8.4**, **Tailwind CSS v4**, **Vite**, **PostgreSQL/SQLite**.

> **Urutan menjalankan ekosistem:** jalankan **webadmin (repo ini) dulu** karena database + API ada di sini. Setelah `http://127.0.0.1:8000` hidup, baru jalankan `cofflow-employee` / `cofflow-customer`. Lihat [Quick Start](#quick-start-lokal).

---

## Fitur Utama

- **Autentikasi role-based** — login admin via web session (Sanctum untuk mobile API).
- **Dashboard analitik** — kartu ringkasan hari ini, chart penjualan 7 hari, top 5 menu 30 hari, **panel statistik per-candle** (klik bar di chart → drill down statistik harian).
- **Manajemen Menu** — CRUD menu, upload foto via `ImageUploadService` (Supabase Storage dengan automatic local fallback), kategori, status aktif/nonaktif, editor **BOM** (Bill of Materials).
- **Manajemen Condiment & Condiment BOM** — grup add-on (single/multi select) dengan **drag-to-reorder** opsi. Opsi condiment dapat memiliki resep bahan baku sendiri (**Condiment BOM**, misal: opsi *Large* menambah potongan 50ml susu).
- **Manajemen Stok & Audit Trail** — bahan baku, restock, highlight kritis (`current_stock < minimum_stock`). Setiap perubahan tercatat di tabel `stock_adjustments` (`type`: `manual`, `restock`, `order_deduct`, `order_restore`, `opname`) dengan delta dan user yang bertanggung jawab.
- **Stock Restoration Guard** — pelacakan `stock_restored_at` pada pesanan untuk menjamin stok hanya dikembalikan tepat satu kali saat pesanan dibatalkan.
- **Opname Stok** — kasir/admin catat stok fisik end-of-shift, admin review & approve untuk menyesuaikan `current_stock`. Variance tracking + reason code.
- **Diskon** — 3 jenis: per-produk, kode promo, event discount (multi-menu), plus tab **Reward Poin** untuk mengatur voucher yang bisa ditukar member.
- **Membership & Poin** — member customer mendapat poin dari pesanan aplikasi customer (lihat [Program Poin Member](#program-poin-member)). Halaman *Membership* berisi daftar member, riwayat poin, penyesuaian poin manual, dan pengaturan.
- **Order Monitor** — daftar order read-only dengan filter status/tipe/tanggal. Tampilan terurut per hari secara kronologis (`DATE(created_at) ASC, queue_number ASC`) dengan format nomor antrean 3 digit (`#001`, `#002`, dst.) yang mereset setiap pergantian hari.
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

Warna dan icon **mengikuti `cofflow-employee`** (acuan tema ketiga app). Brand tokens didefinisikan di [resources/css/app.css](resources/css/app.css) via Tailwind v4 `@theme`:

| Token | Hex | Sama dengan (employee) | Pakai untuk |
|---|---|---|---|
| `primary` | `#3E2723` | `AppColors.primary` | Espresso brown — header, sidebar, primary CTAs |
| `secondary` | `#F5F5DC` | `AppColors.background` | Oatmeal cream — page background |
| `accent` | `#81C784` | `AppColors.accent` | Matcha green — sukses, add buttons, today bar |
| `accent-dark` | `#2E7D32` | — | Teks di atas `accent-50` supaya terbaca |
| `alert` | `#E57373` | `AppColors.alert` | Cinnamon red — error, kritis, delete |

Komponen reusable:
- **`.btn-action`** + variants (`.btn-add` / `.btn-edit` / `.btn-delete` / `.btn-info`) — konsistensi tombol aksi.
- **`.status-pill`** + variants (`.is-success` / `.is-danger` / `.is-warning` / `.is-info` / `.is-muted` / `.is-strong`) — badge status seragam di seluruh halaman.
- **`<x-icon>`** ([resources/views/components/icon.blade.php](resources/views/components/icon.blade.php)) — icon **Material Icons (outlined)**, set yang sama dengan `Icons.*` di Flutter. Tanpa dependency tambahan.
  ```blade
  <x-icon name="dashboard" class="h-5 w-5" />
  <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
  ```
  Tambah icon baru: ambil isi `<svg>` dari `https://unpkg.com/@material-design-icons/svg/outlined/<nama>.svg`, lalu tambahkan ke array `$paths` dengan key = nama icon Flutter (mis. `receipt_long`).

Typography: **Poppins** untuk display/heading, **Roboto** untuk body.

---

## Quick Start Lokal

### Prasyarat

| Tool | Versi | Cek |
|---|---|---|
| PHP | **8.4** (minimal 8.3; `composer.lock` berisi Symfony 8 yang butuh 8.4) | `php -v` |
| Ekstensi PHP | `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `sodium` | `php -m` |
| Composer | 2.x | `composer -V` |
| Node.js + npm | 20+ | `node -v` |
| Database | SQLite (default, tanpa install) atau PostgreSQL 15 | — |

### Setup pertama kali (sekali saja)

Jalankan dari folder `cofflow-webadmin`:

```powershell
# 1. Install dependency PHP + JS
composer install
npm install

# 2. Buat file environment + app key
copy .env.example .env            # Linux/Mac: cp .env.example .env
php artisan key:generate

# 3. Buat database SQLite + isi data dummy
New-Item database\database.sqlite -ItemType File   # Linux/Mac: touch database/database.sqlite
php artisan migrate --seed

# 4. Link folder upload gambar + build CSS/JS
php artisan storage:link
npm run build
```

### Menjalankan (setiap kali)

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Buka **http://127.0.0.1:8000/login**. Biarkan terminal ini tetap terbuka — employee/customer app memanggil API di `http://127.0.0.1:8000/api`.

Opsional, saat mengedit Blade/CSS: jalankan `npm run dev` di terminal kedua untuk hot reload. Ini **bukan** pengganti `php artisan serve`.

Cek API hidup:

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/auth/login -Method Post -ContentType 'application/json' `
  -Headers @{Accept='application/json'} -Body '{"email":"kasir@cofflow.test","password":"password"}'
```

Harus mengembalikan `success: True` + `token`.

### Lanjut: jalankan employee app

Setelah server di atas hidup, buka terminal baru:

```powershell
cd ..\cofflow-employee
flutter run -d chrome --web-port 8080 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
```

Detail lain (emulator Android, device fisik) ada di README `cofflow-employee`.

### Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| `Composer detected issues in your platform: ... PHP version ">= 8.4.0"` | PHP lokal lebih lama (mis. XAMPP 8.2) | Upgrade ke PHP 8.4. Darurat untuk dev saja: `composer install --ignore-platform-req=php --ignore-platform-req=ext-sodium` |
| `'php' is not recognized` | PHP tidak ada di PATH | Tambah folder PHP ke PATH, mis. `$env:PATH = "C:\xampp\php;$env:PATH"` |
| `Class "...ServiceProvider" not found` | `vendor/` tidak sinkron dengan `composer.lock` | `composer install`, lalu `php artisan package:discover` |
| `Vite manifest not found` | Asset belum di-build | `npm run build` |
| Halaman tampil tanpa style setelah ganti CSS | Cache view/asset lama | `npm run build` lalu `php artisan view:clear` |
| Gambar menu tidak muncul | Belum ada symlink storage | `php artisan storage:link` |
| Employee app: `Connection refused` / CORS | Server webadmin belum jalan atau beda port | Jalankan `php artisan serve --host=127.0.0.1 --port=8000` dulu |
| Server mati / error saat simpan, ubah, hapus (PATCH/PUT/DELETE) | PHP 8.2 (mis. XAMPP) crash di request ini | Pakai PHP 8.4, mis. `& "$env:USERPROFILE\php84\php.exe" artisan serve --host=127.0.0.1 --port=8000` |
| Jam pesanan selisih 7 jam | Timezone belum diset | Isi `APP_TIMEZONE=Asia/Jakarta` di `.env`, lalu `php artisan config:clear` |
| `no such column: min_item_price` / tabel poin tidak ada | Migration baru belum dijalankan | `php artisan migrate` lalu `php artisan db:seed --class=LoyaltySeeder` |

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
- **~117 order** tersebar 7 hari terakhir (lebih banyak di hari recent):
  - **Nomor Antrean Harian Terurut & Reset per Hari**: Nomor antrean (`queue_number`) diurutkan kronologis dari pagi ke malam dan mereset kembali ke `#001` setiap pergantian hari.
  - **Bebas Duplikasi Antrean Aktif**: Hari-hari lampau dipastikan berstatus final (`completed` / `cancelled`), sedangkan status aktif (`pending`, `processing`, `ready`) hanya berada di hari ini sehingga tidak ada nomor antrean aktif ganda.
  - Mix walk-in/preorder ~65/35
  - Pakai promo code 18% probability
  - Jam dibobotkan ke 12:00 & 18:00 (peak hour realistic)
- **4 diskon** (1 produk untuk Latte, 2 promo code, 1 event Coffee Fest)
- **Pengaturan poin + 3 reward** (`LoyaltySeeder`): Potongan Rp 10.000 (10 poin), Diskon 20% (15 poin), Diskon 50% khusus Gold (40 poin). Semua customer mulai dari 0 poin.
- **2 opname stok** (1 approved kemarin, 1 pending hari ini)

Reset & re-seed:
```bash
php artisan migrate:fresh --seed
```

---

## Struktur Direktori

```
app/
├── Console/Commands/                 # ExpireUnpaidOrders command (scheduler)
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
├── Models/                           # Eloquent models (Menu, Order, BomItem, CondimentBomItem, StockAdjustment, UserVoucher, dll)
├── Observers/
│   └── OrderObserver.php             # FCM trigger, stock + voucher restore on cancel, award poin on complete
├── Services/
│   ├── StockService.php              # Validasi + potong stok (Menu BOM + Condiment BOM, with locking), restorasi
│   ├── DiscountService.php           # Multi-layer discount calc + voucher poin (batch-optimized)
│   ├── LoyaltyService.php            # Poin member, tier, reward, voucher
│   ├── MidtransService.php           # Payment gateway + webhook verification
│   ├── FcmService.php                # Push notification + DB persist
│   ├── ImageUploadService.php        # Upload gambar menu
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
├── migrations/                       # Skema lengkap (~25 file)
└── seeders/
    ├── DatabaseSeeder.php            # Master + base lookups & seeded users
    ├── DummyDataSeeder.php           # Order + diskon + opname dummy
    └── LoyaltySeeder.php             # Pengaturan poin + reward
resources/
├── css/app.css                       # Tailwind v4 @theme + button/status classes
└── views/admin/                      # Blade templates per resource
routes/
├── api.php                           # REST API (mobile) — 4 tier auth
└── web.php                           # Admin dashboard (web)
tests/
├── Unit/
│   ├── DiscountServiceTest.php       # Kalkulasi diskon percentage/fixed
│   ├── DiscountValidationTest.php    # Validasi aturan diskon
│   └── QueueServiceTest.php          # Queue number: first-of-day, increment, reset
└── Feature/
    ├── AuthApiTest.php               # Register, login, me, logout
    ├── MenuApiTest.php               # Menu index, show, categories
    ├── OrderApiTest.php              # Create, index, show, payment status
    ├── OrderValidationTest.php       # Aturan walk-in, condiment
    ├── StockConcurrencyAndDeductionTest.php # Lock + potong stok Menu/Condiment BOM
    ├── ExpireUnpaidOrdersTest.php    # Auto-cancel order belum bayar + restore stok
    └── LoyaltyPointsTest.php         # Poin, reward, voucher
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
| `stock_adjustments` | Riwayat mutasi stok (audit) |
| `condiment_bom_items` | BOM per condiment option |
| `loyalty_settings` / `point_rewards` / `point_transactions` / `user_vouchers` | Program poin member |

### Catatan Arsitektur

- **Business logic dibungkus Service**, bukan di Controller. Contoh: `StockService::validateAndDeductForOrder`, `StockService::restoreForOrder`, `StockService::recordAdjustment`, `StockService::applyOpnameAdjustment`, `DiscountService::calculateOrderTotal`.
- **OrderController** memotong stok (Menu BOM + Condiment BOM) di dalam transaksi setelah order item dibuat. **OrderObserver** menangani restore stok + voucher saat cancel, award poin saat completed, dan FCM. Daftarnya di `AppServiceProvider::boot`.
- **Stock Audit & Concurrency** — setiap mutasi stok mencatat riwayat di `stock_adjustments`. Restorasi stok dilindungi kolom `stock_restored_at` untuk idempoten.
- **Discount tidak stack** — saat banyak diskon eligible per item, hanya nilai terbesar yang dipakai.
- **Status transitions** divalidasi di `StaffOrderController` API:
  `pending → processing → ready → completed`, `pending|processing → cancelled` (restore stok).

---

## Endpoint API (Ringkas)

Base URL: `https://{host}/api`. Format response konsisten:

```json
{ "success": true, "data": { ... }, "message": "OK" }
```

### Public (tanpa auth)
- `POST /auth/register`, `POST /auth/login` (throttle: 10/menit)
- `GET /menus`, `GET /categories`
- `POST /webhook/midtrans` (signature-verified)

### Auth (semua role)
- `POST /auth/logout`, `GET /auth/me`, `POST /auth/change-password`
- `POST /auth/fcm-token`
- `POST /orders`, `GET /orders`, `GET /orders/{id}`, `GET /orders/{id}/payment-status`
- `POST /orders/quote` (hitung total + estimasi poin sebelum pesan)
- `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`

### Customer (loyalty)
- `GET /loyalty`, `GET /loyalty/history`, `GET /loyalty/rewards`, `POST /loyalty/rewards/{id}/redeem`, `GET /loyalty/vouchers`

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
| `tests/Feature/OrderValidationTest.php` | — | Feature | Walk-in hanya kasir/admin, validasi condiment |
| `tests/Feature/StockConcurrencyAndDeductionTest.php` | — | Feature | Lock + potong stok Menu BOM & Condiment BOM |
| `tests/Feature/ExpireUnpaidOrdersTest.php` | — | Feature | Auto-cancel order belum bayar + restore stok |
| `tests/Feature/LoyaltyPointsTest.php` | — | Feature | Poin member, reward, voucher |
| `tests/Unit/DiscountValidationTest.php` | — | Unit | Validasi aturan diskon |

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
2. Storage → **New bucket**: `koncospace-images`, public ON.
3. SQL Editor → jalankan policy:
   ```sql
   CREATE POLICY "public_read" ON storage.objects
   FOR SELECT USING (bucket_id = 'koncospace-images');

   CREATE POLICY "auth_upload" ON storage.objects
   FOR INSERT WITH CHECK (bucket_id = 'koncospace-images');
   ```
4. Isi `.env`:
   ```env
   SUPABASE_URL=https://xxxxxxx.supabase.co
   SUPABASE_KEY=sb_secret_...
   SUPABASE_BUCKET=koncospace-images
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
   FIREBASE_PROJECT_ID=koncospace-backend
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

Quick tunnel (`--url`) ganti hostname tiap restart. Untuk URL tetap (mis. `api.koncospace.my.id`) pakai named tunnel. Butuh akun Cloudflare + domain yang sudah di-manage Cloudflare.

**JANGAN bikin A record manual dengan isi IPv4.** Tunnel tidak punya IP tetap. DNS record yang benar = CNAME ke `<tunnel-id>.cfargotunnel.com`, dan `cloudflared` yang bikin otomatis (langkah 4 di bawah).

1. **Pastikan domain Active dulu.** Kalau di dashboard status masih `pending`, Cloudflare belum pegang DNS-mu dan tidak ada record yang jalan. Buka **Overview** → Cloudflare kasih 2 nameserver (mis. `xena.ns.cloudflare.com`). Login ke registrar tempat beli domain → ganti nameserver mereka dengan 2 dari Cloudflare. Tunggu sampai status berubah jadi **Active** (bisa beberapa menit–jam).

2. Login tunnel (browser kebuka, pilih domain-nya):
   ```powershell
   cloudflared tunnel login
   ```

3. Buat tunnel — catat tunnel ID + path file credentials `.json` yang dicetak:
   ```powershell
   cloudflared tunnel create koncospace
   ```

4. Bikin DNS record otomatis (ini pengganti dialog "Add record" manual):
   ```powershell
   cloudflared tunnel route dns koncospace api.koncospace.my.id
   ```
   CNAME `api` muncul sendiri di DNS Records.

5. Buat `C:\Users\<user>\.cloudflared\config.yml`:
   ```yaml
   tunnel: koncospace
   credentials-file: C:\Users\<user>\.cloudflared\<TUNNEL-ID>.json
   ingress:
     - hostname: api.koncospace.my.id
       service: http://localhost:8000
     - service: http_status:404
   ```

6. Pastikan Laravel jalan (`php artisan serve`), lalu jalankan tunnel:
   ```powershell
   cloudflared tunnel run koncospace
   ```

7. Update `.env` (lihat langkah 2 di atas) pakai `api.koncospace.my.id`, lalu `php artisan config:clear`.

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

## Program Poin Member

Aturan (bisa diubah di **Membership → Pengaturan**):

- Hanya pesanan **preorder dari aplikasi customer** yang dapat poin. Walk-in di kasir tidak.
- **1 poin per produk** yang harga menunya ≥ **Rp 10.000** (`min_item_price`). Produk di bawah itu 0 poin.
- Admin bisa isi **Poin member per item** di form menu untuk mengganti nilai otomatis.
- Opsi tambahan (condiment), diskon produk/event, kode promo, dan voucher poin **tidak** mengubah jumlah poin.
- Poin masuk saat pesanan berstatus **completed** (sekali saja, dicatat di `points_awarded_at`) dan customer dapat notifikasi.
- **Tier** dihitung dari total poin yang pernah didapat (`lifetime_points`): Bronze 0, Silver 50, Gold 150. Tier hanya membatasi reward yang bisa ditukar, tidak mengalikan poin. Menukar poin tidak menurunkan tier.
- Poin ditukar menjadi **voucher** (tab *Reward Poin* di halaman Diskon). Satu pesanan maksimal satu voucher dan tidak bisa digabung dengan kode promo. Voucher kembali jika pesanan dibatalkan.
- Penyesuaian manual admin hanya mengubah saldo, tidak mengubah tier.

Tabel: `loyalty_settings`, `point_rewards`, `point_transactions`, `user_vouchers`, plus kolom poin di `users` dan `orders`. Test: `php artisan test --filter=LoyaltyPointsTest`.

---

## Keamanan

- **Auth API:** Sanctum bearer tokens, rate-limited login (10/menit).
- **Auth Web:** Session-based, `EnsureAdmin` middleware cek `is_active` + role.
- **Password change:** Invalidasi semua token lama + issue token baru.
- **Webhook:** Midtrans signature verification — **webhook ditolak bila server key kosong**.
- **Config:** Semua service credential dibaca via `config()`, aman setelah `config:cache`.
- **Stock:** Deduction menggunakan `lockForUpdate()` di dalam transaction — data integrity terjaga saat concurrent access.
- **Debug logging:** `LogInterceptor` hanya aktif di debug mode.

---

## Lisensi

Kode internal proyek Konco Space. Tidak untuk distribusi publik tanpa izin.

Framework Laravel di-license di bawah [MIT](https://opensource.org/licenses/MIT).
