<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use Illuminate\Support\Carbon;

/**
 * Single source of truth for the two public legal documents (Terms of Service
 * and Privacy Policy). The same structured payload feeds the Inertia page, the
 * bot-friendly Blade fallback, and the JSON-LD meta block, so the text a
 * crawler indexes can never drift from the text a visitor reads.
 */
final readonly class LegalDocumentService
{
    /** Shared revision date for both documents. */
    public const LAST_UPDATED = '2026-09-12';

    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    /**
     * Vendor (platform) support channels used when no tenant owns the request host.
     */
    private const PLATFORM_CONTACT = [
        'email' => 'support@sidbm.id',
        'phone' => null,
        'address' => 'Kantor Dinas Pemberdayaan Masyarakat dan Desa Kabupaten — Sekretariat pengelola SIDBM Next',
    ];

    /**
     * @param  array<string, mixed>|null  $site  Tenant site payload, or null on a platform host.
     * @return array<string, mixed>
     */
    public function document(string $type, ?array $site = null): array
    {
        return match ($type) {
            self::TERMS => $this->terms($site),
            self::PRIVACY => $this->privacy($site),
            default => throw new \InvalidArgumentException("Unknown legal document [{$type}]."),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function navigation(): array
    {
        return [
            [
                'type' => self::TERMS,
                'title' => 'Syarat Layanan',
                'short_title' => 'Syarat Layanan',
                'path' => '/terms',
                'icon' => 'description',
                'route' => 'public.terms',
            ],
            [
                'type' => self::PRIVACY,
                'title' => 'Kebijakan Privasi',
                'short_title' => 'Kebijakan Privasi',
                'path' => '/privacy',
                'icon' => 'privacy_tip',
                'route' => 'public.privacy',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $site
     * @return array<string, mixed>
     */
    private function terms(?array $site): array
    {
        $operator = $this->operator($site);
        $entity = $operator['label'];

        return [
            'type' => self::TERMS,
            'title' => 'Syarat Layanan',
            'heading' => 'Syarat Layanan '.$entity,
            'lead' => 'Ketentuan yang mengatur penggunaan platform SIDBM Next — Sistem Informasi Dana Bergulir Masyarakat — untuk pengelolaan pinjaman bergulir, pembukuan, dan pelaporan BUMDesma serta Lembaga Keuangan Desa.',
            'description' => 'Syarat Layanan SIDBM Next: ketentuan akun, hak dan kewajiban pengguna, pengelolaan data finansial, pembukuan SAK Entitas Privat, batasan tanggung jawab, dan hukum yang berlaku.',
            'path' => '/terms',
            'route' => 'public.terms',
            'article_label' => 'Pasal',
            'document_kind' => 'Perjanjian Layanan',
            'last_updated' => self::LAST_UPDATED,
            'last_updated_label' => $this->humanDate(self::LAST_UPDATED),
            'publisher' => $operator['publisher'],
            'contact' => $operator['contact'],
            'sections' => [
                [
                    'id' => 'definisi',
                    'title' => 'Definisi dan Istilah',
                    'icon' => 'book',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Dalam Syarat Layanan ini, istilah-istilah berikut memiliki arti sebagai berikut:'],
                        ['type' => 'list', 'items' => [
                            '<strong>Platform</strong> adalah SIDBM Next, sistem informasi berbasis web dan aplikasi desktop milik '.$operator['publisher'].' yang menyediakan modul pengelolaan dana bergulir, pembukuan, pelaporan, dan kemitraan.',
                            '<strong>Entitas</strong> adalah unit organisasi penyewa (<em>tenant</em>) yang menggunakan Platform, meliputi BUMDesma, BUMDes Bersama, Lembaga Keuangan Desa (LKD), koperasi, atau badan usaha milik desa lainnya beserta perangkat desa pembina.',
                            '<strong>Pengguna</strong> adalah setiap orang yang memiliki akun resmi pada Entitas, termasuk administrator, petugas pembiayaan, bendahara, akuntan/operator jurnal, pengelola kelompok, perangkat desa, serta pengguna portal debitur.',
                            '<strong>Data Nasabah</strong> adalah seluruh informasi identitas, keanggotaan, pinjaman, agunan, pembayaran, dan riwayat transaksi yang diinput atau dihasilkan oleh Platform atas nama Entitas.',
                            '<strong>Konten</strong> adalah teks, angka, dokumen, laporan, gambar, logo, dan data apa pun yang diunggah, dikirim, atau dihasilkan melalui Platform.',
                            '<strong>Sistem</strong> adalah keseluruhan infrastruktur Platform termasuk basis data, antarmuka web, API, kanal notifikasi WhatsApp, dan mekanisme pencadahan.',
                        ]],
                    ],
                ],
                [
                    'id' => 'berlakunya-ketentuan',
                    'title' => 'Berlakunya Ketentuan dan Persetujuan',
                    'icon' => 'verified_user',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Dengan membuat akun, mengakses, atau menggunakan Platform, Pengguna dan Entitas dinyatakan telah membaca, memahami, dan menyetujui seluruh isi Syarat Layanan ini beserta Kebijakan Privasi yang merupakan bagian tidak terpisahkan darinya.'],
                        ['type' => 'list', 'items' => [
                            'Bagi Pengguna yang bertindak atas nama Entitas, penggunaan Platform sekaligus merupakan pernyataan bahwa yang bersangkutan berwenang mengikatkan Entitas pada ketentuan ini.',
                            'Bila Pengguna atau Entitas tidak menyetujui ketentuan ini, penghentian penggunaan Platform adalah satu-satunya jalan yang tersedia.',
                            'Ketentuan turunan seperti panduan operasional, perjanjian kerja sama, surat penetapan pengguna, dan kebijakan keamanan yang diberitahukan kemudian merupakan bagian yang melengkapi Syarat Layanan ini.',
                        ]],
                    ],
                ],
                [
                    'id' => 'ruang-lingkup-layanan',
                    'title' => 'Ruang Lingkup Layanan',
                    'icon' => 'category',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Platform disediakan sebagai perangkat pencatatan, pengolahan, dan pelaporan. Platform tidak bertindak sebagai pemberi pinjaman, penjamin, manajer investasi, akuntan publik, maupun lembaga konsultasi keuangan.'],
                        ['type' => 'subheading', 'text' => 'Lingkup fungsi yang disediakan'],
                        ['type' => 'list', 'items' => [
                            'Pendaftaran dan pengelolaan anggota serta kelompok penerima manfaat program dana bergulir.',
                            'Pengajuan, penetapan, pencairan, penjadwalan angsuran, dan pemantauan kolektibilitas pinjaman.',
                            'Pembukuan berganda sesuai SAK Entitas Privat, termasuk jurnal, buku besar, dan siklus penutupan periode.',
                            'Penyusunan laporan keuangan (neraca, laba rugi, arus kas, perubahan ekuitas, catatan atas laporan keuangan) dan laporan periodik untuk pembinaan.',
                            'Penerbitan dokumen pendukung seperti kartu angsuran, bukti penerimaan, surat tagihan, dan berkas pertanggungjawaban.',
                            'Notifikasi melalui pesan instan, portal mandiri anggota, serta dasbor konsolidasi tingkat kabupaten atau provinsi bila diaktifkan.',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Ketersediaan fungsi tertentu bergantung pada langganan, konfigurasi Entitas, dan peraturan internal yang berlaku pada Entitas tersebut.'],
                    ],
                ],
                [
                    'id' => 'akun-dan-otentikasi',
                    'title' => 'Akun, Otentikasi, dan Keamanan Akses',
                    'icon' => 'key',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Setiap Pengguna wajib memiliki akun individual. Pembagian kredensial (nama pengguna, kata sandi, token, atau kode OTP) dengan siapa pun dilarang, termasuk dengan atasan atau rekan kerja.',
                            'Pengguna menjamin keakuratan data yang diberikan saat pendaftaran dan wajib memperbaruinya segera ketika terjadi perubahan.',
                            'Kata sandi wajib dijaga kerahasiaannya, diubah berkala, serta tidak boleh menggunakan identitas orang lain atau pola yang mudah ditebak.',
                            'Pengguna bertanggung jawab penuh atas seluruh aktivitas yang terjadi pada akunnya, termasuk kesalahan input, penghapusan data, atau persetujuan transaksi yang dilakukan melalui akun tersebut.',
                            'Tanda tangan elektronik, nomor induk pegawai/NIK, dan konfirmasi digital yang dihasilkan melalui Platform dianggap sebagai pernyataan sah Pengguna yang mengirimkannya.',
                            'Pengguna wajib segera melaporkan kepada administrator Entitas dan pengelola Platform apabila mengetahui atau menduga adanya akses tidak sah atas akunnya. Permintaan penguncian akun karena dugaan penyalahgunaan akan ditindaklanjuti tanpa memerlukan persetujuan Pengguna yang bersangkutan.',
                            'Platform dapat meminta verifikasi tambahan, membatasi sesi, atau menangguhkan akses sementara demi keamanan, misalnya saat terdeteksi masuk dari perangkat atau lokasi yang tidak lazim.',
                        ]],
                    ],
                ],
                [
                    'id' => 'hak-dan-kewajiban-pengguna',
                    'title' => 'Hak dan Kewajiban Pengguna',
                    'icon' => 'balance',
                    'blocks' => [
                        ['type' => 'subheading', 'text' => 'Hak Pengguna'],
                        ['type' => 'list', 'items' => [
                            'Menggunakan Platform sesuai peran dan hak akses yang diberikan administrator Entitas.',
                            'Memperoleh bantuan teknis, dokumentasi, dan kanal pengaduan yang tersedia.',
                            'Melihat, mengoreksi, atau meminta koreksi atas data yang berkaitan dengan dirinya sesuai ketentuan yang berlaku.',
                            'Memperoleh informasi mengenai perubahan ketentuan layanan sebelum perubahan tersebut berlaku.',
                        ]],
                        ['type' => 'subheading', 'text' => 'Kewajiban Pengguna'],
                        ['type' => 'list', 'items' => [
                            'Menginput data secara benar, jujur, lengkap, dan tepat waktu. Kualitas laporan keuangan sepenuhnya bergantung pada ketelitian bukti transaksi yang diinput.',
                            'Menggunakan Platform hanya untuk kepentingan pengelolaan keuangan Entitas yang sah.',
                            'Mematuhi batas kewenangannya dan tidak mencoba mengakses modul, data Entitas lain, atau fungsi yang bukan menjadi haknya.',
                            'Menyimpan dan memutakhirkan dokumen sumber (perjanjian kredit, kuitansi, berita acara) yang menjadi dasar pencatatan.',
                            'Menghormati hak atas data pribadi debitur, anggota, dan pengguna lain.',
                            'Melaporkan dugaan kecurangan, penyimpangan, atau kegagalan sistem kepada administrator Entitas dan pengelola Platform.',
                        ]],
                    ],
                ],
                [
                    'id' => 'tata-kelola-data-finansial',
                    'title' => 'Tata Kelola Data Finansial dan Pembukuan',
                    'icon' => 'account_balance',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Platform bekerja sebagai alat bantu pencatatan. Tanggung jawab atas isi laporan keuangan tetap berada pada manajemen Entitas.'],
                        ['type' => 'list', 'items' => [
                            'Pencatatan mengacu pada SAK Entitas Privat yang diterbitkan Dewan Standar Akuntansi Keuangan Ikatan Akuntan Indonesia, dengan kebijakan akuntansi yang ruang lingkupnya diatur oleh manajemen masing-masing.',
                            'Setiap posting jurnal yang telah diposting (posted) tidak boleh diubah secara langsung. Koreksi dilakukan melalui jurnal pembalik (<em>reversal</em>) atau jurnal koreksi sehingga jejak audit (<em>audit trail</em>) tetap utuh dan dapat ditelusuri.',
                            'Penutupan periode pembukuan mengunci data periode bersangkutan. Pembukaan kembali periode yang telah ditutup memerlukan wewenang administrator dan tercatat dalam log aktivitas.',
                            'Saldo awal, kebijakan penyusutan, pengelompokan akun, dan pengakuan pendapatan jasa pinjaman ditetapkan oleh Entitas; Platform menyediakan fasilitas penerapannya.',
                            'Hasil cetak, ekspor, dan tanda tangan digital yang dihasilkan Platform merupakan salinan dari data yang tersimpan; pengguna wajib memverifikasinya dengan bukti transaksi yang sah.',
                            'Rekonsiliasi antara catatan Platform dengan kas riil, rekening bank, dan dokumen fisik merupakan kewajiban Pengelola Entitas dan bukan tanggung jawab pengelola Platform.',
                        ]],
                    ],
                ],
                [
                    'id' => 'pinjaman-dan-proses-kredit',
                    'title' => 'Pinjaman Bergulir dan Proses Kredit',
                    'icon' => 'savings',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Platform memfasilitasi pencatatan siklus kredit: pengusulan, verifikasi kelayakan, penetapan alokasi, pencairan, penjadwalan angsuran, pemantauan kolektibilitas, hingga pelunasan.',
                            'Keputusan kelayakan kredit, penetapan suku bunga/jasa, denda, tenor, dan agunan merupakan kewenangan sepenuhnya organ Entitas yang berwenang sesuai anggaran dasar dan peraturan internalnya.',
                            'Skor, simulasi, atau informasi apa pun yang ditampilkan Platform bersifat indikatif dan bukan merupakan penawaran kredit, janji pencairan, maupun nasihat keuangan.',
                            'Data debitur yang diinput ke dalam Platform menjadi milik Entitas; Pengguna dilarang menyalin, menjual, atau mempergunakan data tersebut untuk kepentingan di luar tugasnya.',
                            'Penerbitan surat keterangan melunasi, kartu angsuran, atau dokumen penagihan melalui kanal pesan instan tunduk pada peraturan internal Entitas dan peraturan perundang-undangan di bidang perlindungan konsumen.',
                        ]],
                    ],
                ],
                [
                    'id' => 'kewajiban-pengelola-platform',
                    'title' => 'Kewajiban Pengelola Platform',
                    'icon' => 'handshake',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Menyediakan layanan secara wajar sesuai tingkat layanan yang disepakati, dengan sasaran ketersediaan (uptime) 99,5% per bulan di luar jendela pemeliharaan yang diumumkan.',
                            'Menjaga kerahasiaan data Entitas dan menerapkan pemisahan basis data antar-tenant (isolasi multi-tenant) sehingga satu Entitas tidak dapat membaca data Entitas lain.',
                            'Menyimpan log aktivitas untuk keperluan audit dan investigasi serta menjadikannya tersedia bagi Entitas yang berhak apabila diminta.',
                            'Melakukan pencadahan berkala dan memberikan pemberitahuan terlebih dahulu untuk pemeliharaan terjadwal yang berpotensi mengganggu layanan.',
                            'Menindaklanjuti laporan insiden keamanan dan, apabila terjadi kegagalan pelindungan data pribadi, memberitahukan sesuai kewajiban peraturan perundang-undangan.',
                            'Memberikan bantuan operasional sesuai kanal dukungan yang diumumkan, tanpa menggantikan fungsi pembinaan teknis akuntansi dari instansi pembina.',
                        ]],
                    ],
                ],
                [
                    'id' => 'larangan-penggunaan',
                    'title' => 'Larangan Penggunaan Sistem',
                    'icon' => 'block',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Pengguna dilarang melakukan hal-hal berikut, baik sengaja maupun karena kelalaian berat:'],
                        ['type' => 'list', 'items' => [
                            'Mengakses atau mencoba mengakses akun, modul, basis data, atau lingkungan Entitas lain tanpa hak.',
                            'Memodifikasi, menghapus, atau memalsukan catatan transaksi, saldo awal, tanggal posting, atau jejak audit.',
                            'Mengunggah perangkat lunak berbahaya, melakukan pemindaian kerentanan, serangan penolakan layanan, atau upaya menghindari mekanisme keamanan dan pembatasan penggunaan.',
                            'Melakukan <em>scraping</em>, pengambilan data massal otomatis, atau penyalinan basis data untuk keperluan di luar kepentingan Entitas.',
                            'Menggunakan Platform untuk pencucian uang, penggelapan, penipuan, fiktasi kredit, pelaporan ganda, atau perbuatan melawan hukum lainnya.',
                            'Menyamarkan identitas, membuat akun ganda, atau mengklaim peran yang tidak diberikan kepadanya.',
                            'Mengunggah konten yang melanggar kesusilaan, SARA, hak kekayaan intelektual pihak lain, atau rahasia jabatan.',
                            'Memperjualbelikan akses, data, atau laporan yang dihasilkan Platform kepada pihak ketiga tanpa persetujuan tertulis Entitas dan pengelola Platform.',
                        ]],
                    ],
                ],
                [
                    'id' => 'kepemilikan-dan-kekayaan-intelektual',
                    'title' => 'Kepemilikan Data dan Kekayaan Intelektual',
                    'icon' => 'copyright',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Seluruh Konten yang diinput atau dihasilkan untuk keperluan Entitas tetap menjadi milik Entitas yang bersangkutan. Pengelola Platform hanya memperoleh lisensi terbatas untuk menyimpan, memproses, dan menampilkan data guna menjalankan layanan.',
                            'Platform, termasuk kode sumber, desain antarmuka, struktur basis data, merek, logo, dan dokumentasinya, dilindungi hukum hak cipta dan peraturan kekayaan intelektual lainnya. Tidak ada hak atas kekayaan intelektual yang dialihkan kepada Pengguna.',
                            'Pengguna tidak diperkenankan meniru, memodifikasi, membongkar kode sumber (<em>reverse engineering</em>), atau menciptakan karya turunan dari Platform tanpa izin tertulis pengelola Platform.',
                            'Laporan, ekspor, dan dokumen resmi yang dihasilkan untuk Entitas dapat difotokopi dan diperlakukan sebagai arsip resmi Entitas sesuai peraturan kearsipan.',
                        ]],
                    ],
                ],
                [
                    'id' => 'kelangsungan-layanan',
                    'title' => 'Kelangsungan Layanan, Suspensi, dan Pengakhiran',
                    'icon' => 'power_settings_new',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Pengelola Platform dapat menangguhkan atau membatasi akses akun tertentu, atau satu Entitas secara keseluruhan, apabila terjadi pelanggaran ketentuan, tunggakan biaya langganan, permintaan resmi instansi berwenang, atau keadaan yang membahayakan keamanan sistem.',
                            'Suspensi karena pelanggaran berat dapat dilakukan segera tanpa pemberitahuan terlebih dahulu; suspensi karena alasan lain diupayakan dengan pemberitahuan dan kesempatan memperbaiki yang wajar.',
                            'Pengakhiran kerja sama tidak menghapus kewajiban Entitas yang telah timbul. Pengelolaan data setelah penghentian layanan mengikuti bagian Retensi Data pada Kebijakan Privasi.',
                            'Pemeliharaan, peningkatan, atau perubahan teknis berhak dilakukan dan akan diumumkan melalui kanal yang wajar bila berpotensi memengaruhi layanan.',
                            'Gangguan akibat force majeure — bencana alam, kekacauan, padam listrik berkepanjangan, kegagalan jaringan tulang punggung, gangguan penyedia pusat data, atau tindakan otoritas — bukan merupakan wanprestasi.',
                        ]],
                    ],
                ],
                [
                    'id' => 'batasan-tanggung-jawab',
                    'title' => 'Batasan Tanggung Jawab',
                    'icon' => 'gavel',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Platform disediakan "sebagaimana adanya" dan "sebagaimana tersedia". Tidak ada jaminan bahwa fungsi tertentu bebas dari cacat, gangguan, atau ketidakakuratan teknis.',
                            'Pengelola Platform tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, terhentinya kegiatan usaha, kerugian reputasi, atau kehilangan data yang timbul dari penggunaan atau ketidakmampuan menggunakan Platform.',
                            'Kesalahan hasil laporan yang bersumber dari input Pengguna, dokumen yang tidak disampaikan, atau kebijakan akuntansi yang ditetapkan Entitas bukan merupakan tanggung jawab pengelola Platform.',
                            'Tanggung jawab pengelola Platform atas kerugian langsung, sejauh hukum mengizinkan, dibatasi pada jumlah biaya langganan yang dibayarkan Entitas untuk periode 12 (dua belas) bulan sebelum kejadian yang menimbulkan kerugian.',
                            'Batasan ini tidak berlaku untuk kerugian yang timbul dari kesengajaan atau kelalaian berat pengelola Platform, dan tidak mengurangi hak Pengguna sebagai subjek data pribadi menurut undang-undang.',
                        ]],
                    ],
                ],
                [
                    'id' => 'perubahan-ketentuan',
                    'title' => 'Perubahan Ketentuan',
                    'icon' => 'update',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Syarat Layanan dapat diperbarui karena perubahan fungsi, peraturan perundang-undangan, atau kebutuhan keamanan.',
                            'Versi terbaru dipublikasikan pada halaman ini dengan tanggal berlaku yang tertera, dan dapat diberitahukan melalui dasbor, surel, atau pesan kepada administrator Entitas.',
                            'Perubahan yang bersifat material berlaku efektif setelah diumumkan. Kelanjutan penggunaan Platform setelah tanggal berlaku merupakan persetujuan atas ketentuan yang diperbarui.',
                            'Pengguna atau Entitas yang tidak menyetujui perubahan berhak menghentikan penggunaan dan meminta penutupan akun sesuai prosedur yang tersedia.',
                        ]],
                    ],
                ],
                [
                    'id' => 'hukum-dan-penyelesaian-perselisihan',
                    'title' => 'Hukum yang Berlaku dan Penyelesaian Perselisihan',
                    'icon' => 'scale',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Syarat Layanan ini tunduk pada hukum Republik Indonesia, termasuk namun tidak terbatas pada UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi, UU No. 11 Tahun 2008 beserta perubahannya tentang Informasi dan Transaksi Elektronik, PP No. 71 Tahun 2019, serta peraturan sektoral yang berlaku bagi Entitas.',
                            'Perselisihan diselesaikan terlebih dahulu secara musyawarah untuk mufakat dalam jangka waktu 30 (tiga puluh) hari sejak pemberitahuan tertulis.',
                            'Apabila musyawarah tidak mencapai kesepakatan, para pihak memilih penyelesaian melalui jalur pengadilan yang berwenang, kecuali disepakati lain secara tertulis melalui arbitrase atau mediasi.',
                            'Pengguna yang terikat hubungan kepegawaian dengan pemerintah daerah atau perangkat desa tetap tunduk pada mekanisme pengawasan internal dan peraturan perundang-undangan kepegawaian yang berlaku.',
                        ]],
                    ],
                ],
                [
                    'id' => 'kontak-dan-kanal-bantuan',
                    'title' => 'Kontak dan Kanal Bantuan',
                    'icon' => 'support_agent',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Permintaan bantuan, pelaporan insiden keamanan, atau penyampaian keberatan atas ketentuan ini dapat ditujukan kepada kontak berikut.'],
                        ['type' => 'contact', 'contact' => [
                            'name' => $operator['contact']['name'],
                            'email' => $operator['contact']['email'],
                            'phone' => $operator['contact']['phone'],
                            'address' => $operator['contact']['address'],
                        ]],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $site
     * @return array<string, mixed>
     */
    private function privacy(?array $site): array
    {
        $operator = $this->operator($site);
        $entity = $operator['label'];

        return [
            'type' => self::PRIVACY,
            'title' => 'Kebijakan Privasi',
            'heading' => 'Kebijakan Privasi '.$entity,
            'lead' => 'Kebijakan ini menjelaskan bagaimana Platform SIDBM Next mengumpulkan, memakai, melindungi, dan menyimpan data pribadi Pengguna maupun data nasabah/anggota yang dicatat oleh Entitas dalam sistem pengelolaan dana bergulir.',
            'description' => 'Kebijakan Privasi SIDBM Next: kategori data yang dikumpulkan, dasar pemrosesan menurut UU PDP, hak subjek data, keamanan dan isolasi data multi-tenant, serta retensi dan penghapusan data.',
            'path' => '/privacy',
            'route' => 'public.privacy',
            'article_label' => 'Bagian',
            'document_kind' => 'Pemberitahuan Privasi',
            'last_updated' => self::LAST_UPDATED,
            'last_updated_label' => $this->humanDate(self::LAST_UPDATED),
            'publisher' => $operator['publisher'],
            'contact' => $operator['contact'],
            'sections' => [
                [
                    'id' => 'pendahuluan',
                    'title' => 'Pendahuluan dan Ruang Lingkup',
                    'icon' => 'policy',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Perlindungan data pribadi merupakan bagian dari tata kelola sistem keuangan desa yang akuntabel. Kebijakan Privasi ini berlaku bagi:'],
                        ['type' => 'list', 'items' => [
                            'Pengguna Platform (petugas, bendahara, operator, administrator, perangkat desa, dan pengelola Entitas);',
                            'Anggota, kelompok, dan debitur yang datanya diinput oleh Entitas ke dalam Platform;',
                            'Pengunjung situs publik Entitas maupun situs platform, termasuk pengguna formulir kontak;',
                            'Portal mandiri yang menampilkan informasi angsuran kepada anggota.',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Dalam hubungan dengan Entitas, Entitas bertindak sebagai pengendali data yang menentukan tujuan dan cara pemrosesan, sedangkan Pengelola Platform bertindak sebagai prosesor yang memproses data atas nama Entitas sesuai perjanjian. Untuk data akun, log teknis, dan operasional layanan, pengelola Platform menentukan sendiri tujuan pemrosesannya.'],
                    ],
                ],
                [
                    'id' => 'data-yang-dikumpulkan',
                    'title' => 'Data Pribadi yang Dikumpulkan',
                    'icon' => 'fact_check',
                    'blocks' => [
                        ['type' => 'subheading', 'text' => 'Data yang Anda berikan'],
                        ['type' => 'list', 'items' => [
                            '<strong>Data identitas pengguna</strong>: nama, nama pengguna, email, nomor telepon, jabatan/unit kerja, foto profil, dan kredensial akses.',
                            '<strong>Data keanggotaan dan debitur</strong>: nama lengkap, NIK atau nomor identitas lain, tempat/tanggal lahir, jenis kelamin, alamat, nomor telepon, status pernikahan, jumlah tanggungan, dan data usaha.',
                            '<strong>Data pinjaman</strong>: nomor pengajuan/SPK, pokok dan plafon, jangka waktu, agunan, riwayat pencairan, jadwal dan realisasi angsuran, tunggakan, denda, serta kolektibilitas.',
                            '<strong>Pesan dan dokumen</strong>: isi formulir kontak, lampiran yang diunggah, dan dokumen pendukung transaksi.',
                        ]],
                        ['type' => 'subheading', 'text' => 'Data yang dikumpulkan secara otomatis'],
                        ['type' => 'list', 'items' => [
                            '<strong>Data teknis sesi</strong>: alamat IP, jenis peramban dan perangkat, zona waktu, halaman yang diakses, dan identifier aplikasi desktop.',
                            '<strong>Log aktivitas</strong>: waktu masuk/keluar, entri jurnal yang dibuat atau diubah, laporan yang dicetak, serta upaya akses yang ditolak — dipakai untuk jejak audit.',
                            '<strong>Cookie dan penyimpanan peramban</strong>: token sesi, preferensi tema, dan status instalasi aplikasi (PWA).',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Platform tidak sengaja mengumpulkan data sensitif seperti data kesehatan, data biometrik, atau pandangan politik. Mohon tidak mengunggah informasi semacam itu ke dalam kolom keterangan.'],
                    ],
                ],
                [
                    'id' => 'dasar-dan-tujuan-pemrosesan',
                    'title' => 'Dasar Hukum dan Tujuan Pemrosesan',
                    'icon' => 'rule',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Pemrosesan data dilakukan dengan dasar pemenuhan kewajiban perjanjian, kepentingan sah yang tidak mengurangi hak subjek data, pemenuhan kewajiban peraturan perundang-undangan, dan perlindungan kepentingan vital, sebagaimana dimaksud UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.'],
                        ['type' => 'list', 'items' => [
                            'Menjalankan layanan inti: pencatatan keanggotaan, pengelolaan pinjaman, penagihan, dan penyaluran pembayaran.',
                            'Menyelenggarakan pembukuan dan pelaporan keuangan sesuai SAK Entitas Privat serta pertanggungjawaban kepada organ Entitas dan instansi pembina.',
                            'Menyampaikan informasi transaksi, jadwal angsuran, dan kuitansi melalui kanal komunikasi yang dipilih Entitas.',
                            'Menjaga keamanan sistem, mencegah penipuan dan penyalahgunaan, serta menyelidiki insiden.',
                            'Memberikan dukungan teknis dan pelatihan operasional kepada Pengguna.',
                            'Memenuhi permintaan resmi lembaga pengawas, pemeriksa, atau aparat penegak hukum sesuai ketentuan.',
                            'Meningkatkan kualitas layanan melalui statistik penggunaan yang disajikan secara agregat atau dianonimkan.',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Data pribadi tidak dijual kepada pengiklan atau pihak mana pun untuk kepentingan komersial di luar tujuan tersebut.'],
                    ],
                ],
                [
                    'id' => 'data-keuangan-dan-rahasia-nasabah',
                    'title' => 'Kerahasiaan Data Keuangan Nasabah',
                    'icon' => 'lock',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Rincian pinjaman, saldo, dan riwayat pembayaran anggota adalah informasi rahasia. Aksesnya dibatasi pada Pengguna yang peran dan hak aksesnya membutuhkan informasi tersebut.',
                            'Pengguna dilarang mengungkapkan data keuangan nasabah kepada pihak yang tidak berhak, termasuk antar-kelompok, tanpa dasar yang sah.',
                            'Ekspor, cetak, dan unduhan laporan dicatat dalam log aktivitas sehingga penyalahgunaan dapat ditelusuri.',
                            'Pengumuman daftar debitur atau penagihan kepada publik hanya boleh dilakukan sesuai prosedur penagihan yang sah dan tidak mempermalukan debitur.',
                            'Kartu angsuran, kuitansi, dan pesan notifikasi dirancang memuat informasi seperlunya; sebagian kanal menampilkan informasi parsial (misal nama dan nomor induk, bukan NIK lengkap).',
                        ]],
                    ],
                ],
                [
                    'id' => 'berbagi-data',
                    'title' => 'Pihak Penerima dan Berbagi Data',
                    'icon' => 'share',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Data dapat diungkapkan terbatas kepada pihak berikut, sepanjang diperlukan untuk tujuan yang sah:'],
                        ['type' => 'list', 'items' => [
                            '<strong>Entitas tempat data dicatat</strong> dan organ/pengurusnya, termasuk perangkat desa atau unsur pembinaan yang berwenang.',
                            '<strong>Pengguna internal Entitas lain yang ditugaskan</strong> sebagai pembina pada dasbor konsolidasi kabupaten/provinsi — hanya menerima data agregat yang dibatasi wilayah tugasnya.',
                            '<strong>Penyedia infrastruktur</strong> (pusat data, jasa penyimpanan, jaringan) yang terikat kewajiban kerahasiaan.',
                            '<strong>Penyedia kanal pesan</strong> untuk pengiriman notifikasi kepada nomor yang dituju.',
                            '<strong>Akuntan publik, inspektorat, APIP, atau lembaga pengawas</strong> dalam rangka audit dan pemeriksaan resmi.',
                            '<strong>Aparat penegak hukum atau pengadilan</strong> berdasarkan permintaan tertulis yang sah.',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Kepada pihak ketiga tersebut hanya diberikan data yang diperlukan, dan mereka dilarang memakainya untuk tujuan lain.'],
                    ],
                ],
                [
                    'id' => 'hak-subjek-data',
                    'title' => 'Hak Anda sebagai Subjek Data',
                    'icon' => 'badge',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Sesuai UU No. 27 Tahun 2022, Anda berhak atas hal-hal berikut. Permohonan diajukan melalui administrator Entitas Anda (untuk data nasabah/anggota) atau melalui kontak di bagian akhir kebijakan ini (untuk data akun Platform).'],
                        ['type' => 'list', 'items' => [
                            'Mengetahui dan memperoleh salinan data pribadi Anda yang diproses.',
                            'Memperbaiki atau melengkapi data yang tidak akurat.',
                            'Menghapus data pribadi, dengan catatan hak ini dapat dikesampingkan apabila data masih dibutuhkan untuk kewajiban pembukuan, perpajakan, audit, atau sengketa.',
                            'Menarik kembali persetujuan pemrosesan yang tidak didasarkan pada kewajiban perjanjian atau peraturan.',
                            'Keberatan atas pemrosesan tertentu, termasuk pengambilan keputusan otomatis.',
                            'Memindahkan data Anda ke sistem lain dalam format yang dapat dibaca mesin, sejauh secara teknis dimungkinkan.',
                            'Mengajukan pengaduan kepada lembaga yang melaksanakan fungsi pengawasan pelindungan data pribadi.',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Permohonan diverifikasi terlebih dahulu untuk memastikan identitas pemohon, dan ditindaklanjuti dalam jangka waktu paling lama 14 (empat belas) hari kerja sejak permohonan dinyatakan lengkap.'],
                    ],
                ],
                [
                    'id' => 'keamanan-data',
                    'title' => 'Keamanan dan Isolasi Data Multi-Tenant',
                    'icon' => 'shield',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Pengelola Platform menerapkan langkah teknis dan organisasi yang sesuai dengan risiko, antara lain:'],
                        ['type' => 'list', 'items' => [
                            'Pemisahan basis data per kelompok penyewa sehingga setiap Entitas hanya menjangkau datanya sendiri, diperkuat pemeriksaan tenant pada setiap lapisan aplikasi.',
                            'Enkripsi lalu lintas melalui HTTPS dan kata sandi dengan hash satu arah yang kuat.',
                            'Kontrol akses berbasis peran dan hak akses berjenjang, dengan prinsip hak minimal.',
                            'Pencatatan jejak audit atas perubahan data finansial dan pembatasan terhadap penghapusan permanen.',
                            'Pencadahan terjadwal, pemantauan keamanan, dan pembaruan berkala terhadap pustaka perangkat lunak.',
                            'Penanganan insiden terdokumentasi, termasuk pemberitahuan kepada Entitas dan — apabila memenuhi ambang hukum — kepada subjek data dan lembaga pengawas.',
                        ]],
                        ['type' => 'paragraph', 'text' => 'Tidak ada sistem yang benar-benar bebas risiko. Karena itu keamanan akun juga bergantung pada kerahasiaan kredensial yang Anda jaga dan ketepatan administrator Entitas memberikan hak akses.'],
                    ],
                ],
                [
                    'id' => 'retensi-data',
                    'title' => 'Retensi, Penghapusan, dan Pengarsipan Data',
                    'icon' => 'inventory_2',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Data pembukuan, laporan keuangan, dan bukti transaksi disimpan sepanjang masa retensi arsip yang diwajibkan peraturan (sekurang-kurangnya sesuai ketentuan perpajakan dan kearsipan), bahkan setelah akun Pengguna dinonaktifkan.',
                            'Data akun Pengguna yang berhenti aktif dinonaktifkan dan dibatasi aksesnya, lalu dihapus atau dianonimkan setelah masa retensi berakhir.',
                            'Data pada mode pelatihan atau lingkungan uji tidak berisi data nasabah sesungguhnya dan dapat dihapus kapan pun tanpa dampak hukum.',
                            'Apabila kerja sama dengan Entitas berakhir, data Entitas dapat diekspor atau dikembalikan dalam format yang wajar, kemudian dihapus dari lingkungan produksi setelah tenggang pengarsipan yang disepakati.',
                            'Log keamanan dan jejak audit disimpan sesuai kebutuhan pembuktian, dan tidak diperlakukan sebagai arsip keuangan.',
                        ]],
                    ],
                ],
                [
                    'id' => 'mode-pelatihan',
                    'title' => 'Mode Pelatihan dan Lingkungan Uji',
                    'icon' => 'school',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Entitas dapat mengaktifkan mode pelatihan untuk simulasi pencatatan. Mode ini menandai seluruh data yang dihasilkan sebagai data latihan.',
                            'Dilarang memasukkan data nasabah atau keuangan yang sebenarnya ke dalam lingkungan pelatihan.',
                            'Isi lingkungan pelatihan tidak dijamin keakuratannya, tidak dipakai sebagai dasar pelaporan resmi, dan dapat dikosongkan sewaktu-waktu.',
                        ]],
                    ],
                ],
                [
                    'id' => 'cookie-dan-teknologi-pengacakan',
                    'title' => 'Cookie dan Teknologi Serupa',
                    'icon' => 'cookie',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Cookie sesi dan token penyimpanan lokal dipakai untuk menjaga Anda tetap masuk, mengingat preferensi tema, dan menampilkan pemberitahuan.',
                            'Layanan aplikasi (PWA) menyimpan berkas di perangkat agar aplikasi dapat berjalan terbatas saat luring; berkas tersebut berisi salinan data Entitas Anda dan tersimpan di perangkat milik Anda sendiri.',
                            'Pengukuran penggunaan dilakukan secara agregat dan tidak dipakai untuk membangun profil perilaku untuk iklan.',
                            'Pengaturan peramban dapat membatasi cookie, namun fitur masuk dan luring tidak akan berfungsi semestinya.',
                        ]],
                    ],
                ],
                [
                    'id' => 'masuk-terpadu',
                    'title' => 'Masuk Terpadu (Single Sign-On) dan Pencabutan Akses',
                    'icon' => 'login',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Apabila Entitas Anda memakai masuk terpadu dari portal pembina, portal tersebut menyampaikan identitas dan peran Anda melalui token sekali pakai yang divalidasi dan langsung dimusnahkan setelah dipakai.',
                            'Token tidak menyimpan kata sandi Anda dan tidak dapat digunakan ulang.',
                            'Penghentian tugas atau mutasi Pengguna wajib segera dilaporkan agar hak akses dicabut; penyalahgunaan akun karena kelalaian pelaporan menjadi tanggung jawab Pengguna dan Entitas yang bersangkutan.',
                        ]],
                    ],
                ],
                [
                    'id' => 'anak-dan-kelompok-rentan',
                    'title' => 'Perlindungan Khusus dan Kapasitas Hukum',
                    'icon' => 'diversity_3',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Program dana bergulir menyasar keluarga dan kelompok usaha. Platform tidak secara sengaja mengumpulkan data anak di bawah umur sebagai debitur. Entitas wajib memastikan calon debitur cakap secara hukum dan pemberian pinjaman dilakukan sesuai persetujuan yang sah, termasuk bagi debitur penerima kuasa atau wali.'],
                    ],
                ],
                [
                    'id' => 'perubahan-kebijakan',
                    'title' => 'Perubahan Kebijakan Privasi',
                    'icon' => 'restart_alt',
                    'blocks' => [
                        ['type' => 'list', 'items' => [
                            'Kebijakan ini ditinjau berkala dan dapat diperbarui untuk mengikuti perubahan fungsi, teknologi, dan peraturan.',
                            'Versi terbaru selalu tersedia pada halaman ini beserta tanggal pemberlakuan.',
                            'Perubahan material diberitahukan melalui dasbor, surel, atau pesan resmi kepada administrator Entitas sebelum berlaku.',
                        ]],
                    ],
                ],
                [
                    'id' => 'kontak-privasi',
                    'title' => 'Kontak dan Pengaduan Privasi',
                    'icon' => 'alternate_email',
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => 'Untuk permohonan hak subjek data, pelaporan kebocoran data, atau pertanyaan mengenai pemrosesan data, hubungi kontak berikut. Sertakan identitas pemohon dan Entitas asal agar permohonan dapat diverifikasi.'],
                        ['type' => 'contact', 'contact' => [
                            'name' => $operator['contact']['name'],
                            'email' => $operator['contact']['email'],
                            'phone' => $operator['contact']['phone'],
                            'address' => $operator['contact']['address'],
                        ]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Resolve branding and contact channels for the current host.
     *
     * @param  array<string, mixed>|null  $site
     * @return array{label: string, publisher: string, contact: array{name: ?string, email: ?string, phone: ?string, address: ?string}}
     */
    private function operator(?array $site): array
    {
        if ($site === null) {
            return [
                'label' => 'SIDBM Next',
                'publisher' => (string) config('app.name', 'SIDBM Next'),
                'contact' => [
                    'name' => 'Tim Pengelola '.config('app.name', 'SIDBM Next'),
                    'email' => strtolower(self::PLATFORM_CONTACT['email']),
                    'phone' => self::PLATFORM_CONTACT['phone'],
                    'address' => self::PLATFORM_CONTACT['address'],
                ],
            ];
        }

        $organization = $site['organization'] ?? [];
        $settings = $site['settings'] ?? [];
        $legalName = $organization['legal_name'] ?? $organization['name'] ?? config('app.name', 'SIDBM Next');
        $name = $organization['name'] ?? $legalName;

        return [
            'label' => (string) $name,
            'publisher' => (string) $legalName,
            'contact' => [
                'name' => 'Administrator '.$name,
                'email' => $settings['contact_email'] ?? $organization['email'] ?? null,
                'phone' => $settings['contact_phone'] ?? $organization['phone'] ?? null,
                'address' => $settings['contact_address'] ?? $organization['address'] ?? null,
            ],
        ];
    }

    private function humanDate(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('j F Y');
    }
}
