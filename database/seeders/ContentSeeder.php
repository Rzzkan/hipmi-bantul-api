<?php

namespace Database\Seeders;

use App\Models\BoardMember;
use App\Models\Event;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Konten contoh agar landing page langsung tampil. Semua teks & nama bisa diubah dari /admin.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->globals();
        $this->programs();
        $this->events();
        $this->posts();
        $this->board();
        $this->partners();
        $this->pages();
    }

    protected function link(string $label, string $url, string $appearance = 'default'): array
    {
        return ['label' => $label, 'url' => $url, 'appearance' => $appearance, 'newTab' => false];
    }

    protected function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }

    protected function globals(): void
    {
        Setting::put('site', [
            'name' => 'BPC HIPMI Bantul',
            'tagline' => 'Himpunan Pengusaha Muda Indonesia — Badan Pengurus Cabang Kabupaten Bantul',
            'email' => 'sekretariat@hipmibantul.com',
            'phone' => null,
            'whatsapp' => null,
            'address' => 'Kabupaten Bantul, Daerah Istimewa Yogyakarta',
            'maps_url' => null,
            'socials' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/'],
            ],
        ]);

        Setting::put('header', [
            'nav_items' => [
                $this->link('Beranda', '/'),
                $this->link('Tentang', '/tentang'),
                $this->link('Program', '/program'),
                $this->link('Agenda', '/agenda'),
                $this->link('Berita', '/berita'),
            ],
            'cta' => $this->link('Daftar Anggota', '/daftar'),
        ]);

        Setting::put('footer', [
            'about' => 'Wadah pengusaha muda Bantul untuk bertumbuh, berjejaring, dan bersinergi membangun perekonomian daerah.',
            'nav_items' => [
                $this->link('Tentang Kami', '/tentang'),
                $this->link('Program', '/program'),
                $this->link('Agenda', '/agenda'),
                $this->link('Berita', '/berita'),
                $this->link('Daftar Anggota', '/daftar'),
            ],
            'copyright' => '© '.now()->year.' BPC HIPMI Bantul. All rights reserved.',
        ]);
    }

    protected function programs(): void
    {
        $items = [
            ['Pendampingan UMKM Naik Kelas', 'umkm', true, 'Mentoring 8 minggu bersama anggota HIPMI untuk UMKM Bantul: legalitas, pembukuan, branding, dan akses pasar digital.',
                ['Mentor dari pengusaha anggota HIPMI', 'Kelas legalitas & perizinan (NIB, PIRT, Halal)', 'Klinik foto produk & branding', 'Kesempatan business matching']],
            ['HIPMI Bantul Berbagi', 'sosial', false, 'Program charity berkolaborasi dengan komunitas & lembaga non-profit di Bantul.',
                ['Penyaluran bantuan tepat sasaran', 'Kolaborasi dengan komunitas lokal', 'Laporan transparan untuk donatur']],
            ['Business Matching & Sinergi Anggota', 'networking', true, 'Forum rutin mempertemukan anggota untuk kolaborasi, supply chain lokal, dan peluang investasi.',
                ['Sesi pitching usaha anggota', 'Direktori kebutuhan & penawaran antar anggota', 'Akses ke mitra pemerintah & perbankan']],
            ['Kaderisasi Pengusaha Muda', 'okk', false, 'Jalur kaderisasi untuk calon anggota: orientasi organisasi, kepemimpinan, dan mentalitas wirausaha.',
                ['Orientasi organisasi HIPMI', 'Pelatihan kepemimpinan', 'Sertifikat kaderisasi']],
        ];

        foreach ($items as $i => [$title, $cat, $featured, $summary, $benefits]) {
            Program::updateOrCreate(['slug' => Str::slug($title)], [
                'title' => $title,
                'category' => $cat,
                'summary' => $summary,
                'description' => "<p>{$summary}</p><p>Detail program dapat diperbarui oleh pengurus melalui CMS.</p>",
                'benefits' => array_map(fn ($b) => ['item' => $b], $benefits),
                'is_featured' => $featured,
                'registration_open' => true,
                'sort_order' => $i,
                'status' => 'published',
            ]);
        }
    }

    protected function events(): void
    {
        $items = [
            ['HIPMI Bantul Business Gathering', 21, 'Ngopi bareng & business matching antar anggota dan calon anggota.', 50000, 80],
            ['Kelas UMKM: Foto Produk Pakai HP', 35, 'Workshop praktik foto produk estetik dengan smartphone untuk pelaku UMKM.', 0, 40],
            ['Orientasi Calon Anggota Baru', 49, 'Pengenalan organisasi, program kerja, dan jalur kaderisasi HIPMI Bantul.', 0, null],
        ];

        foreach ($items as [$title, $days, $excerpt, $price, $quota]) {
            $start = now()->addDays($days)->setTime(19, 0);
            Event::updateOrCreate(['slug' => Str::slug($title)], [
                'title' => $title,
                'excerpt' => $excerpt,
                'description' => "<p>{$excerpt}</p><h3>Yang akan didapat</h3><ul><li>Relasi baru sesama pengusaha muda</li><li>Insight praktis dari narasumber</li></ul>",
                'start_at' => $start,
                'end_at' => $start->copy()->addHours(3),
                'location' => 'Bantul, Yogyakarta',
                'price' => $price,
                'quota' => $quota,
                'registration_open' => true,
                'status' => 'published',
            ]);
        }
    }

    protected function posts(): void
    {
        $items = [
            ['Pengurus BPC HIPMI Bantul Siapkan Program Pendampingan UMKM', 'berita'],
            ['Kenapa Pengusaha Muda Perlu Berjejaring Sejak Awal', 'opini'],
            ['Pendaftaran Anggota Baru HIPMI Bantul Dibuka', 'pengumuman'],
        ];

        foreach ($items as $i => [$title, $cat]) {
            Post::updateOrCreate(['slug' => Str::slug($title)], [
                'title' => $title,
                'category' => $cat,
                'excerpt' => 'Contoh artikel. Ganti isi ini melalui menu Berita di CMS.',
                'content' => '<p>Ini adalah contoh konten berita. Silakan ubah atau hapus dari dashboard admin.</p><h2>Sub judul</h2><p>Konten mendukung <strong>format teks</strong>, daftar, kutipan, dan gambar.</p>',
                'author_name' => 'Humas HIPMI Bantul',
                'status' => 'published',
                'published_at' => now()->subDays(($i + 1) * 3),
            ]);
        }
    }

    protected function board(): void
    {
        $items = [
            ['Nama Ketua Umum', 'Ketua Umum', 'inti'],
            ['Nama Sekretaris Umum', 'Sekretaris Umum', 'inti'],
            ['Nama Bendahara Umum', 'Bendahara Umum', 'inti'],
            ['Aditya', 'Ketua Bidang OKK', 'okk'],
            ['Nama Ketua Bidang', 'Ketua Bidang UMKM & Ekraf', 'umkm'],
            ['Nama Ketua Bidang', 'Ketua Bidang Humas & Media', 'humas'],
        ];

        foreach ($items as $i => [$name, $position, $division]) {
            BoardMember::updateOrCreate(['position' => $position], [
                'name' => $name, 'division' => $division, 'sort_order' => $i, 'is_active' => true,
            ]);
        }
    }

    protected function partners(): void
    {
        foreach (['Partner Contoh A', 'Partner Contoh B', 'Media Partner Contoh'] as $i => $name) {
            Partner::updateOrCreate(['name' => $name], [
                'tier' => $i === 2 ? 'media' : 'partner', 'sort_order' => $i, 'is_active' => true,
            ]);
        }
    }

    protected function pages(): void
    {
        Page::updateOrCreate(['slug' => 'home'], [
            'title' => 'Beranda',
            'status' => 'published',
            'meta_description' => 'BPC HIPMI Bantul — wadah pengusaha muda Bantul untuk tumbuh, berjejaring, dan bersinergi.',
            'hero' => [
                'type' => 'highImpact',
                'eyebrow' => 'BPC HIPMI Bantul',
                'richText' => '<h2>Pengusaha Muda Bantul, Tumbuh Bareng &amp; Saling Menguatkan</h2><p>Bergabung dengan jejaring pengusaha muda untuk kolaborasi, pendampingan UMKM, dan kontribusi nyata bagi ekonomi Bantul.</p>',
                'links' => [$this->link('Daftar Jadi Anggota', '/daftar'), $this->link('Lihat Program', '/program', 'outline')],
            ],
            'layout' => [
                $this->block('stats', [
                    'introContent' => null,
                    'items' => [
                        ['value' => '17', 'label' => 'Kapanewon terjangkau'],
                        ['value' => '4', 'label' => 'Program unggulan'],
                        ['value' => '12+', 'label' => 'Agenda per tahun'],
                    ],
                ]),
                $this->block('content', [
                    'background' => 'default',
                    'columns' => [
                        ['size' => 'half', 'richText' => '<h2>Tentang HIPMI Bantul</h2>', 'enableLink' => false],
                        ['size' => 'half', 'richText' => '<p>Badan Pengurus Cabang HIPMI Bantul adalah rumah bagi pengusaha muda di Kabupaten Bantul. Kami fokus pada tiga hal: <strong>kaderisasi</strong> pengusaha muda, <strong>pendampingan UMKM</strong>, dan <strong>sinergi antar anggota</strong> untuk menumbuhkan ekonomi daerah.</p>', 'enableLink' => true, 'link' => $this->link('Kenali kami', '/tentang', 'outline')],
                    ],
                ]),
                $this->block('archive', ['introContent' => '<h2>Program Kami</h2><p>Program yang bisa kamu ikuti, baik sebagai anggota maupun pelaku UMKM.</p>', 'relationTo' => 'programs', 'limit' => 4]),
                $this->block('archive', ['introContent' => '<h2>Agenda Terdekat</h2>', 'relationTo' => 'events', 'limit' => 3]),
                $this->block('archive', ['introContent' => '<h2>Kabar Terbaru</h2>', 'relationTo' => 'posts', 'limit' => 3]),
                $this->block('partners', ['introContent' => '<h2>Didukung Oleh</h2>']),
                $this->block('cta', [
                    'richText' => '<h3>Siap tumbuh bareng pengusaha muda Bantul?</h3><p>Daftar sekarang dan tim OKK akan menghubungi kamu untuk tahap selanjutnya.</p>',
                    'links' => [$this->link('Daftar Anggota', '/daftar')],
                ]),
            ],
        ]);

        Page::updateOrCreate(['slug' => 'tentang'], [
            'title' => 'Tentang Kami',
            'status' => 'published',
            'hero' => ['type' => 'lowImpact', 'eyebrow' => 'Tentang Kami', 'richText' => '<h2>Mengenal BPC HIPMI Bantul</h2><p>Visi, misi, dan orang-orang di balik organisasi.</p>', 'links' => []],
            'layout' => [
                $this->block('content', [
                    'background' => 'default',
                    'columns' => [
                        ['size' => 'half', 'richText' => '<h3>Visi</h3><p>Isi visi organisasi di sini melalui CMS.</p>', 'enableLink' => false],
                        ['size' => 'half', 'richText' => '<h3>Misi</h3><ol><li>Mencetak pengusaha muda yang tangguh dan berdaya saing.</li><li>Mendampingi UMKM Bantul naik kelas.</li><li>Membangun sinergi antar anggota untuk pertumbuhan ekonomi daerah.</li></ol>', 'enableLink' => false],
                    ],
                ]),
                $this->block('team', ['introContent' => '<h2>Struktur Pengurus</h2>', 'division' => null]),
                $this->block('cta', ['richText' => '<h3>Mau berkontribusi bersama kami?</h3>', 'links' => [$this->link('Daftar Anggota', '/daftar')]]),
            ],
        ]);

        Page::updateOrCreate(['slug' => 'daftar'], [
            'title' => 'Daftar Anggota',
            'status' => 'published',
            'hero' => ['type' => 'lowImpact', 'eyebrow' => 'Pendaftaran', 'richText' => '<h2>Bergabung dengan HIPMI Bantul</h2><p>Isi formulir berikut. Bidang OKK akan menghubungi kamu via WhatsApp.</p>', 'links' => []],
            'layout' => [
                $this->block('form', ['formType' => 'membership', 'introContent' => null, 'successMessage' => 'Terima kasih! Pendaftaranmu sudah kami terima. Tim OKK akan menghubungimu maksimal 3x24 jam.']),
                $this->block('faq', [
                    'introContent' => '<h2>Pertanyaan Umum</h2>',
                    'items' => [
                        ['question' => 'Siapa yang bisa menjadi anggota?', 'answer' => 'Pengusaha muda yang berdomisili atau memiliki usaha di Kabupaten Bantul, sesuai ketentuan organisasi.'],
                        ['question' => 'Apakah usaha saya harus sudah besar?', 'answer' => 'Tidak. Yang terpenting kamu sudah menjalankan usaha dan punya semangat untuk tumbuh bersama.'],
                        ['question' => 'Bagaimana proses setelah mendaftar?', 'answer' => 'Tim OKK akan memverifikasi data, menghubungi via WhatsApp, lalu mengundang ke orientasi calon anggota.'],
                    ],
                ]),
            ],
        ]);
    }
}
