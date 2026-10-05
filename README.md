# CMS HIPMI Bantul — API (Laravel)

Backend headless CMS untuk landing page **BPC HIPMI Bantul**. Admin panel memakai **Filament 5** (pengalaman mirip Payload CMS: koleksi, globals, layout *blocks*, draft/publish), dan menyediakan **REST API** yang dikonsumsi frontend Next.js di repo terpisah [`hipmi-bantul-web`](../hipmi-bantul-web).

| | |
|---|---|
| Framework | Laravel 13 · PHP ≥ 8.3 |
| Admin | Filament 5 → `/admin` |
| API | `/api/v1/*` (JSON, publik read-only + 1 endpoint POST pendaftaran) |
| DB | SQLite (default) · MySQL/MariaDB/Postgres siap pakai |

## Instalasi lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # atau set DB_* untuk MySQL
php artisan migrate --seed              # buat tabel + admin + konten contoh
php artisan storage:link                # agar gambar upload bisa diakses
php artisan serve                       # http://localhost:8000
```

Login admin: `http://localhost:8000/admin` → **admin@hipmibantul.id / password**
(ubah lewat `ADMIN_EMAIL` & `ADMIN_PASSWORD` di `.env` sebelum seeding, dan **ganti password setelah login pertama**).

## Konsep (padanan Payload CMS)

| Payload | Di repo ini |
|---|---|
| Collection `pages` + `layout` blocks | `Page` + Filament `Builder` (`app/Filament/Resources/Pages/Schemas/PageForm.php`) |
| Collections | `Post` (Berita), `Event` (Agenda), `Program`, `BoardMember` (Pengurus), `Partner`, `Registration` (Pendaftar) |
| Globals Header/Footer | `Setting` key `site`, `header`, `footer` → menu **Pengaturan Situs** |
| Drafts & `publishedAt` | trait `HasPublishing` (status draft/published + jadwal terbit) |
| `afterChange` revalidate hook | `App\Support\FrontendRevalidator` → `POST {FRONTEND}/api/revalidate` |

### Layout blocks yang tersedia
`content` (kolom), `mediaBlock`, `cta`, `stats`, `archive` (Berita/Agenda/Program otomatis), `team` (pengurus), `partners`, `form` (pendaftaran anggota/event/program), `faq`.

API mengubah data Filament `[{type, data}]` menjadi bentuk Payload `[{blockType, id, ...}]` dan **mem-populate** relasi (mis. block `archive` langsung berisi `docs`) — lihat `app/Support/BlockSerializer.php`.

**Menambah block baru:** (1) tambahkan `Block::make('namaBlock')` di `PageForm`, (2) tambahkan `case` di `BlockSerializer::block()`, (3) buat komponen di repo web `src/blocks/` lalu daftarkan di `RenderBlocks.tsx`.

## Endpoint API

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/v1/globals` | site, header, footer |
| GET | `/api/v1/pages` · `/pages/{slug}` | halaman (detail berisi `hero` + `layout`) |
| GET | `/api/v1/posts?category=&page=&limit=` · `/posts/{slug}` | berita (paginated) |
| GET | `/api/v1/events?when=upcoming\|past\|all` · `/events/{slug}` | agenda + sisa kuota |
| GET | `/api/v1/programs?category=` · `/programs/{slug}` | program |
| GET | `/api/v1/board-members?division=` | pengurus |
| GET | `/api/v1/partners` | partner & sponsor |
| POST | `/api/v1/registrations` | pendaftaran `membership` / `event` / `program` (rate limit 5/menit/IP, honeypot, cek kuota & duplikat) |

Hanya konten berstatus **published** (dan tanggal terbit sudah lewat) yang keluar dari API. HTML dari rich editor disanitasi sebelum dikirim.

## Fitur admin untuk OKK
- **Pendaftar**: badge jumlah yang menunggu, tombol WA langsung, terima/tolak, catatan internal, **export CSV**.
- **Dashboard**: pendaftar menunggu, agenda terdekat, estimasi pemasukan event berbayar.
- Agenda dengan harga tiket & kuota (pendaftaran otomatis tertutup saat penuh/lewat).

## Koneksi ke frontend (.env)

```env
FRONTEND_URL=https://hipmibantul.id
FRONTEND_REVALIDATE_URL=https://hipmibantul.id/api/revalidate
FRONTEND_REVALIDATE_SECRET=string-acak-yang-sama-dengan-REVALIDATE_SECRET-di-web
CORS_ALLOWED_ORIGINS=https://hipmibantul.id
```

## Testing
```bash
php artisan test
```

## Deploy ke `https://api.hipmibantul.com`

1. **DNS**: A record `api` → IP server.
2. Server: PHP 8.3-FPM (+ `intl`, `gd`, `zip`, `pdo_mysql`), Composer, MySQL/MariaDB, Nginx.
3. Clone repo ke `/var/www/hipmi-bantul-api`, lalu:
   ```bash
   cp .env.production.example .env      # isi bagian <ISI>
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force --seed   # --seed hanya saat pertama kali
   php artisan storage:link
   php artisan filament:optimize && php artisan optimize
   sudo chown -R www-data:www-data storage bootstrap/cache
   ```
4. Nginx: salin `deploy/nginx-api.hipmibantul.com.conf` ke `/etc/nginx/sites-available/`, aktifkan, lalu `sudo certbot --nginx -d api.hipmibantul.com`.
5. Update berikutnya cukup: `bash deploy/deploy.sh`.

Cek: `https://api.hipmibantul.com/api/v1/globals` (JSON) dan `https://api.hipmibantul.com/admin` (login).

### Hosting di Hostinger (shared hosting / hPanel)

Di Hostinger, file subdomain ada di `domains/api.hipmibantul.com/public_html` dan **document root-nya adalah folder project, bukan `public/`**. File `.htaccess` di root repo ini sudah meneruskan semua request ke `public/`, jadi cukup upload/clone seluruh repo ke `public_html`.

1. hPanel → **Databases → MySQL**: buat database + user (nama berawalan `u344886479_`).
2. Upload repo ke `public_html` (Git di hPanel atau File Manager), lalu via **SSH** di folder itu:
   ```bash
   cp .env.production.example .env        # isi DB_DATABASE/DB_USERNAME/DB_PASSWORD dari langkah 1
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan filament:optimize && php artisan optimize
   ```
   > Pastikan `DB_CONNECTION=mysql`. Jika `.env` masih `sqlite`, API akan error 500 ("Database file … database.sqlite does not exist").
3. hPanel → **Website → CDN → Purge cache** agar respons 404 lama tidak tersimpan.
4. Cek `https://api.hipmibantul.com/api/v1/globals` harus berisi JSON, dan `https://api.hipmibantul.com/storage/logs/laravel.log` harus **403/404** (tidak boleh terbuka).

> `FRONTEND_REVALIDATE_SECRET` harus sama persis dengan `REVALIDATE_SECRET` di website. `CORS_ALLOWED_ORIGINS` sudah berisi `web.hipmibantul.com` & `www.hipmibantul.com` agar formulir pendaftaran bisa submit.
