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

Login admin: `http://localhost:8000/admin` → **okkhipmibantul@gmail.com / okkbergerak**
(dibuat oleh `AdminUserSeeder`; bisa diubah lewat `ADMIN_EMAIL` & `ADMIN_PASSWORD` di `.env` sebelum seeding).
Ganti password kapan saja lewat menu **Profil** (klik avatar di kanan atas admin).

Membuat akun admin saja (tanpa konten contoh), misalnya di server production:
```bash
php artisan db:seed --class=AdminUserSeeder --force
```

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
| GET | `/api/v1/board-members?division={id}` | struktur pengurus: `inti` + `divisions[]` (Ketua/Wakil bidang + `compartments[].members`), tiap pengurus punya `socials` (Instagram/TikTok/LinkedIn) |
| GET | `/api/v1/partners` | partner & sponsor |
| POST | `/api/v1/registrations` | pendaftaran `membership` / `event` / `program` (rate limit 5/menit/IP, honeypot, cek kuota & duplikat) |

Hanya konten berstatus **published** (dan tanggal terbit sudah lewat) yang keluar dari API. HTML dari rich editor disanitasi sebelum dikirim.

## Struktur pengurus

- **Organisasi → Bidang & Kompartemen**: 12 bidang sudah terisi (Bidang 1 Organisasi, Keanggotaan, dan Kaderisasi … Bidang 12 Investasi dan Kerjasama antar Daerah). Tambah satu atau lebih **kompartemen** per bidang langsung di form bidang.
- **Pengurus Inti**: Ketua Umum, Sekretaris Umum (+ Wakil Sekretaris Umum, boleh lebih dari satu), Bendahara (+ Wakil Bendahara, boleh lebih dari satu). Tidak ada Wakil Ketua Umum. Bagan dikelompokkan otomatis dari teks jabatan (mengandung "Sekretaris" / "Bendahara" / "Wakil").
- **Organisasi → Pengurus**: pilih *Tingkat* (Pengurus Inti / Pimpinan Bidang / Kompartemen) → *Bidang* → *Kompartemen*, isi jabatan, foto, dan Instagram/TikTok/LinkedIn (boleh `@username` atau link).
- Halaman **Tentang Kami** (block *Pengurus*) otomatis menampilkan bagan Pengurus Inti + kartu tiap bidang beserta kompartemennya.

## Penyimpanan gambar — Cloudflare R2

Semua gambar CMS (foto pengurus, sampul berita/agenda/program, logo partner, gambar di dalam artikel) disimpan di disk yang dipilih lewat `MEDIA_DISK`:

| `MEDIA_DISK` | Lokasi | Kapan dipakai |
|---|---|---|
| `public` (default) | `storage/app/public` di server | development lokal |
| `r2` | bucket Cloudflare R2 | **production** — cepat, tanpa biaya bandwidth, aman walau server pindah |

Driver `r2` (`app/Support/R2Filesystem.php`) memakai S3 API dengan region `auto` dan **tidak mengirim header ACL** (R2 tidak mendukung ACL; driver `s3` bawaan Laravel selalu mengirimnya). Akses publik diatur di level bucket.

### Setup di Cloudflare (sekali saja)
1. **R2 → Create bucket**, mis. `hipmi-bantul` (boleh juga memakai bucket Katalog Bisnis — file CMS masuk folder `cms/` lewat `R2_ROOT`).
2. **Bucket → Settings → Public access**:
   - **Custom domain** (disarankan): hubungkan `media.hipmibantul.com` (domain harus memakai DNS Cloudflare), atau
   - **r2.dev subdomain**: aktifkan untuk uji coba (dibatasi Cloudflare, tidak untuk production).
3. **Bucket → Settings → CORS policy** — agar preview gambar di form admin bisa dimuat:
   ```json
   [{ "AllowedOrigins": ["https://api.hipmibantul.com"], "AllowedMethods": ["GET", "HEAD"], "AllowedHeaders": ["*"], "MaxAgeSeconds": 86400 }]
   ```
4. **R2 → Manage API tokens → Create API token**: izin *Object Read & Write*, batasi ke bucket tadi. Catat **Access Key ID**, **Secret Access Key**, dan endpoint `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`.

### Aktifkan di server
```bash
# .env
MEDIA_DISK=r2
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_BUCKET=hipmi-bantul
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_PUBLIC_URL=https://media.hipmibantul.com
R2_ROOT=cms
```
```bash
composer install --no-dev -o            # memasang aws-sdk (league/flysystem-aws-s3-v3)
php artisan config:clear
php artisan media:migrate-to-r2 --dry-run   # cek dulu: berapa file & konten yang akan diubah
php artisan media:migrate-to-r2             # salin gambar lama ke R2 + perbarui URL di artikel/halaman
php artisan optimize
```
Perintah migrasi aman dijalankan berulang (file yang sudah ada di R2 dilewati). Path gambar di database tidak berubah; hanya URL absolut di dalam konten rich text yang diperbarui.

Di website (Vercel), set `CMS_MEDIA_CDN_URL` ke URL publik yang sama (`https://media.hipmibantul.com`) supaya `next/image` mengizinkan domain tersebut (`*.r2.dev` sudah diizinkan otomatis).

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
