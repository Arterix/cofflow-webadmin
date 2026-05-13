# Cofflow Admin Dashboard

Web admin dashboard untuk coffee shop **Cofflow**. Mengelola menu, stok bahan baku, diskon, order, pegawai, opname stok, dan laporan penjualan.

Bagian dari ekosistem Cofflow: backend REST API + admin web (repo ini) + Flutter customer/employee app.

Stack: **Laravel 11**, **PHP 8.2+**, **Tailwind CSS v4**, **Vite**, **PostgreSQL/SQLite**.

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

- PHP 8.2+
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
├── Http/Controllers/
│   ├── Api/                  # Mobile REST API endpoints
│   └── Web/Admin/            # Web admin Blade controllers
├── Models/                   # Eloquent models
├── Observers/OrderObserver   # BOM deduction + FCM trigger on order
└── Services/                 # StockService, DiscountService, MidtransService, FcmService
database/
├── migrations/               # Skema lengkap (~22 file)
└── seeders/
    ├── DatabaseSeeder.php    # Master + base lookups
    └── DummyDataSeeder.php   # Order + diskon + opname dummy
resources/
├── css/app.css               # Tailwind v4 @theme + button/status classes
└── views/admin/              # Blade templates per resource
routes/
├── api.php                   # REST API (mobile)
└── web.php                   # Admin dashboard (web)
context/                      # Spec dokumen (requirements, design, tasks)
```

---

## Arsitektur Singkat

- **Business logic dibungkus Service**, bukan di Controller. Contoh: `StockService::deductForOrder`, `StockService::applyOpnameAdjustment`, `DiscountService::applyPromoCode`.
- **OrderObserver** men-trigger stock deduction + FCM. Daftarnya di `AppServiceProvider::boot`.
- **Discount tidak stack** — saat banyak diskon eligible per item, hanya nilai terbesar yang dipakai.
- **Status transitions** divalidasi di `StaffOrderController` API:
  `pending → processing → ready → completed`, `pending|processing → cancelled` (restore stok).

Lihat [context/design(2).md](context/design(2).md) untuk skema DB, API map, dan business logic lengkap.

---

## Skrip Berguna

```bash
php artisan migrate:fresh --seed      # Reset DB + seed dummy
php artisan route:list                # Daftar routes
php artisan route:list --name=admin   # Filter admin routes
php artisan tinker                    # REPL untuk eksplorasi model
npm run build                         # Build production assets
npm run dev                           # Vite dev (hot reload)
```

---

## Konfigurasi Produksi (opsional)

Default development pakai SQLite. Untuk produksi, switch ke PostgreSQL di `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=your-supabase-host
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=...
```

Integrasi opsional (kosongkan kalau belum dipakai):
- **Supabase Storage** — `SUPABASE_URL`, `SUPABASE_KEY`, `SUPABASE_BUCKET` (untuk foto menu)
- **Midtrans** — `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`
- **Firebase FCM** — `FIREBASE_CREDENTIALS` (path JSON), `FIREBASE_PROJECT_ID`

File-file yang **tidak boleh** ter-commit:
- `.env`
- `storage/app/firebase-credentials.json`
- `database/database.sqlite`

---

## Endpoint API (ringkas)

Base URL: `https://{host}/api`. Format response konsisten:

```json
{ "success": true, "data": { ... }, "message": "OK" }
```

Grup utama:
- `POST /auth/login`, `POST /auth/register`, `GET /auth/me`
- `GET /menus`, `GET /categories` (public)
- `POST /orders`, `GET /orders` (customer)
- `GET /staff/orders`, `PATCH /staff/orders/{id}/status` (kasir/admin)
- `GET /admin/*` — semua endpoint admin (menu, stok, diskon, analytics, staff)
- `POST /webhook/midtrans` (public, signature-verified)

Detail lengkap di [context/design(2).md](context/design(2).md) §3.

---

## Lisensi

Kode internal proyek Cofflow. Tidak untuk distribusi publik tanpa izin.

Framework Laravel di-license di bawah [MIT](https://opensource.org/licenses/MIT).
