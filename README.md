# Truck Capacity Checker

Aplikasi Laravel untuk mengecek kesiapan muatan truk di gudang, dengan tampilan 3D susunan karton.

## Tampilan

Antarmuka memakai **tema gelap gaya dashboard** sesuai panduan desain di `dashboard-design.md`
(panduan aslinya untuk aplikasi lain; yang diterapkan di sini adalah sistem visualnya):

- Latar `#121212`, kartu `#1E1E1E` (radius 16px, padding 20px, garis `#2C2C2E`), spasi kelipatan 8px, grid 12 kolom.
- **Cian `#00E5FF`** untuk data utama, KPI, dan tombol aksi. **Hijau `#32D74B`** = siap kirim / normal,
  **kuning `#FFD60A`** = belum siap, **merah `#FF453A`** = melebihi kapasitas dan peringatan.
- Huruf **Inter** untuk teks dan **JetBrains Mono** untuk angka (KPI, tabel, persentase).
- Dashboard: 3 KPI di atas, grafik area muatan 14 hari (8 kolom), panel peringatan (4 kolom).
- Halaman **Rencana muat** tetap dicetak hitam di atas putih (aturan `@media print`).
- **Animasi loading**: garis progres di atas saat pindah halaman, lalu kartu animasi (truk diisi karton) bila lebih dari 450 ms.
  Area 3D punya overlay sendiri saat menyusun dan menggambar, dan kartu jalur tampil sebagai kerangka (skeleton) selagi dimuat.
- **Modal konfirmasi** (elemen `<dialog>`) untuk aksi yang berdampak besar: hapus kloter, area, atau jadwal; lepas kloter dari jadwal;
  simpan pengaturan jalur atau area; impor master barang. Cukup tambahkan `data-confirm="pesan"` (opsional `data-confirm-title`,
  `data-confirm-ok`, `data-confirm-variant` = `danger`/`warn`/`info`) pada `<form>` atau tombol submit.
- **Kontrol form kustom** (`public/js/controls.js`): dropdown bergaya kaca gelap (kotak cari bila lebih dari 8 pilihan,
  keterangan kecil lewat `<option data-label="..." data-hint="...">`), kalender untuk `input[type=date]` (Senin di depan,
  tombol Hari ini/Hapus), dan kotak pilih file. Elemen asli tetap ada dan tetap dikirim bersama formulir; bisa dipakai penuh
  dengan keyboard. Tambahkan `data-native` pada elemen yang ingin tetap memakai tampilan bawaan browser.
- Semua gaya ada di `public/css/packing.css` (token warna di `:root`); warna tampilan 3D ada di `THEME` pada `public/js/packing.js`.

## Konsep utama

- **Satu jalur = satu truk khusus ke satu tujuan.** Contoh: Jalur 1 ke Jabodetabek, Jalur 2 ke Jawa Tengah.
  Muatan satu jalur tidak dicampur dengan jalur lain (truk ke Jabodetabek tidak dialihkan ke Surabaya).
- **Satu kali unggah file order = satu kloter** (tabel `uploads`). Satu kloter berisi beberapa jalur.
- **Kesiapan kirim dinilai PER JALUR** terhadap truk jalur itu: rasio = nilai terbesar antara rasio volume dan rasio berat
  (mana yang tercapai lebih dulu). Siap dikirim jika >= 80%, melebihi kapasitas jika > 100%. Hanya karton **Status 4 (Cargo Ready)** dihitung.
- Jalur 1-7 ditentukan dari **kode area** dealer (`SA` pada `SA38`). Jalur 8 untuk dealer **Same Day** terpilih.
- Nama tujuan dan **truk default tiap jalur** diatur di menu Pengaturan (Area dan Jalur). Truk bisa diganti sementara per jalur saat melihat hasil.
- **Rencana muat** per jalur bisa dicetak untuk sopir dan gudang.
- Di tampilan 3D, **arahkan mouse ke sebuah karton** untuk melihat ringkasannya dan **klik** untuk detail lengkap: barang, dealer, isi karton (unit atau receh), nomor dan ukuran karton, berat, lokasi gudang, layanan, dan posisinya di truk. Karton terpilih disorot merah.

## Upload order (Excel/CSV)

Dimensi karton dan berat diambil dari **master barang**, bukan dari file order. Kolom (baris 1 = header):

| Kolom | Isi |
|---|---|
| A | Cargo Destination |
| B | Customer Code (kode dealer, mis. `SA38` = area `SA` + dealer `38`) |
| C | Customer Name |
| D | Part Number |
| E | Qty (pcs) |
| F | Status: 1 Order Received, 2 Picking, 3 Checking/Packing, 4 Cargo Ready |
| G | Service Type (opsional: `same_day`, `one_day`, `emergency`; default `one_day`) |

Qty dipecah otomatis: **Packing Unit** (box penuh) dan **Packing Receh** (sisa < qty per box).
Baris kosong, qty <= 0, status di luar 1-4, atau part yang tidak ada di master barang dilewati dan dilaporkan.

## Menu aplikasi

| Menu | Fungsi |
|---|---|
| Dashboard (`/`) | KPI hari ini (kloter, jalur siap, jalur perlu perhatian), grafik volume Cargo Ready 14 hari, panel peringatan (jalur melebihi truk, karton tanpa jalur, kloter menunggu jadwal kapal), jadwal kapal terdekat, kloter terbaru |
| Cek Kapasitas (`/packing`) | Unggah file order; hasil per jalur dengan tampilan 3D |
| Riwayat Kloter (`/riwayat`) | Semua kloter, filter (nama, tanggal, status, jalur), buka ulang hasil, hapus |
| Detail kloter (`/riwayat/{id}`) | Kartu jalur, 3D per jalur, jadwal pengiriman, cetak rencana muat |
| Rencana muat (`/riwayat/{id}/jalur/{n}/rencana-muat`) | Halaman cetak per jalur: dealer, part, karton, berat, tanda tangan |
| Penjadwalan (`/penjadwalan`) | Jadwal kapal (manual), kloter menunggu jadwal, pengiriman harian |
| Dealer dan MOQ (`/dealer`) | MOQ per dealer, penanda Same Day, wilayah |
| Master Barang (`/master-barang`) | Lihat, cari, dan impor master barang |
| Kendaraan dan Karton (`/referensi`) | Data acuan kendaraan dan 25 ukuran karton |
| Area dan Jalur (`/pengaturan/area`) | Tujuan dan truk tiap jalur, kode area ke jalur dan wilayah |

## Pengaturan (tanpa edit file)

- **Area dan Jalur** (`/pengaturan/area`): kode area (huruf di depan kode dealer, `SA` pada `SA38`) dipetakan ke **jalur 1-7** dan **wilayah**
  (Jawa, Sumatra, Aceh, luar pulau). Kode area dari data dealer yang belum terdaftar terdeteksi otomatis.
- **Dealer Same Day** (Jalur 8): centang di menu Dealer dan MOQ, atau tambah dealer manual.
- Perubahan **langsung diterapkan ke semua kloter** (jalur adalah data turunan), jadi riwayat selalu mengikuti pengaturan terbaru.
- Nilai awal berasal dari `config/logistics.php` dan disalin ke database saat `php artisan migrate`. Setelah itu ubah lewat menu, bukan file config.

## Penjadwalan

- **Wilayah** ditentukan dari kode area dealer (`SA` pada `SA38`) lewat `area_regions` di `config/logistics.php`.
  Jawa dan Sumatra (selain Aceh) = **pengiriman harian**. Aceh dan luar pulau = **jadwal kapal**.
- **Harian:** Same Day berangkat hari yang sama, One Day dan Emergency keesokan harinya (`daily_lead_days`).
- **Jadwal kapal** (menu Penjadwalan) diinput manual: tanggal berangkat, tujuan, kapasitas kontainer, dan status
  (Direncanakan, Sudah berangkat, Dibatalkan). Tanggal **tentatif** bisa maju atau mundur.
  Satu kloter ditautkan ke satu jadwal; satu jadwal boleh berisi beberapa kloter.
- **MOQ per dealer** (menu Dealer dan MOQ) dalam m3, diisi manual. Pada halaman jadwal, volume Cargo Ready tiap dealer
  dibanding MOQ-nya: Memenuhi, Kurang sekian m3, atau MOQ belum diatur.
- Dealer baru otomatis terdaftar saat file order diunggah.
- File contoh `sample_data_kapal.csv` (dealer luar pulau dan Aceh) dan `DealerSeeder` (MOQ contoh) untuk mencoba.

### Mencoba jadwal kapal dengan `sample_data_kapal.csv`

File ini **diunggah seperti file order biasa**, tidak ada input manual per baris. Langkahnya:

1. `php artisan migrate`, lalu `php artisan db:seed --class=DealerSeeder` (mengisi MOQ contoh; atau isi sendiri di menu Dealer dan MOQ).
2. **Cek Kapasitas**: unggah `sample_data_kapal.csv`. Kloter baru berisi 4 dealer luar pulau dan Aceh, semuanya di Jalur 6.
3. **Penjadwalan**, **Tambah Jadwal Kapal**: isi tanggal berangkat, tujuan, dan kapasitas (bawaan: kontainer Impor), lalu Simpan.
4. Di tabel **Kloter menunggu jadwal kapal**, pilih jadwal tadi lalu klik **Tautkan**.
5. Buka jadwalnya. MOQ per dealer: SJ10 memenuhi, SK05 **kurang 0,808 m3**, SL02 memenuhi, SJ21 **MOQ belum diatur**.

Isi MOQ SJ21 (atau turunkan MOQ SK05) di menu Dealer dan MOQ, lalu buka jadwalnya lagi untuk melihat statusnya berubah.

## Kendaraan & aturan muatan

| Kendaraan | Dimensi dalam (cm) | Volume | Muatan |
|---|---|---|---|
| CDE | 320 × 170 × 170 | 9,25 m³ | 3 ton |
| CDD | 440 × 200 × 190 | 16,72 m³ | 6 ton |
| Wing Box | 950 × 245 × 250 | 58,19 m³ | 17 ton |
| Impor (kontainer 40' HC, estimasi) | 1203 × 235 × 269 | 76,05 m³ | 26 ton |

- Kendaraan di atas dipilih sebagai **truk default per jalur**: jalur kecil (1 sampai 6) memakai CDD (truk putih sekitar 16 m3), **jalur akhir (7 dan 8) memakai truk besar Wing Box**. Keduanya hanya default, bisa diubah per jalur di Pengaturan (`big_truck_routes` dan `big_vehicle` di `config/logistics.php`).
- Karton **tidak boleh diputar** (P sejajar panjang kendaraan, L lebar, T tinggi).
- Karton harus muat di truk jalurnya; yang tidak muat dilaporkan saat unggah dan ditandai `too_big`.

## Algoritma packing (`POST /packing/optimize`)

Menyusun **satu jalur (satu truk)**. Parameter: `upload_id` (wajib), `route` (1-8, atau `none` untuk karton tanpa jalur), `mode` = `tetris` (default) atau `ratio`, `vehicle` (kode truk, menimpa truk default jalur dan tidak disimpan), `only_ready` (default `true` = hanya Status 4).
Ringkasan semua jalur sebuah kloter: `GET /riwayat/{id}/jalur`.

- **Mode `ratio`**: hanya kubikasi dan berat. Karton dimuat berurutan (Emergency, lalu Same Day, lalu One Day) sampai volume atau berat tercapai lebih dulu. Tidak ada posisi 3D.
- **Mode `tetris`**: penyusunan 3D nyata. Karton tidak diputar, yang berat di bawah dan yang ringan di atas, dan karton hanya ditumpuk bila alasnya tertopang penuh (tanpa overhang/ruang kosong). Beberapa urutan pemuatan dicoba, lalu dipakai yang paling padat.
- Karton yang tidak termuat dilaporkan di `unloaded` dengan alasan `weight`, `volume`, `space`, atau `too_big`. Dimensi respons dalam meter.

## Setup data

```
php artisan migrate
php artisan db:seed                 # kendaraan, 25 karton, dan master barang CONTOH
php artisan products:import sample_products.csv   # import/update master barang (ganti dengan data asli)
```

File contoh (semua dipakai oleh tes agar tetap valid):
- `sample_data_lengkap.csv`: order lengkap untuk demo (30 baris, 11 dealer, status 1 sampai 4). Memperlihatkan semua kondisi jalur:
  Jalur 1, 2, dan 5 **siap** (Jalur 2 dibatasi **berat**), Jalur 3 dan 8 **belum siap**, Jalur 4 **melebihi** truk.
- `sample_data_kapal.csv`: dealer luar pulau dan Aceh (Jalur 6, jadwal kapal) untuk mencoba penjadwalan dan MOQ.
- `sample_products.csv`: master barang (6 barang), sama dengan data contoh `ProductSeeder`.

Kode area sample (SA sampai SL), nama tujuan jalur, dan dealer Same Day `SH09` adalah **CONTOH**; ganti lewat menu Pengaturan.

Format master barang: Part Number, Name, Weight kg/pcs, No Karton, Qty per Box, Category (small atau heavy), Location.

## Teknologi

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Bootstrap 5 (hanya CSS, mode gelap), JavaScript biasa tanpa Bootstrap JS (`public/js/app.js`),
  Plotly.js bundel `plotly-gl3d` untuk 3D (dimuat saat dibutuhkan), grafik dashboard SVG sendiri (`public/js/area-chart.js`),
  font Inter dan JetBrains Mono (Google Fonts)
- **Excel Processing**: Laravel Excel
- **Database**: MySQL/PostgreSQL

## Instalasi

1. Clone repository
2. `composer install`
3. Salin `.env.example` menjadi `.env`, lalu `php artisan key:generate`
4. Atur koneksi database di `.env` (`DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
5. `php artisan migrate` lalu `php artisan db:seed` (data contoh)
6. `php artisan serve`, buka `http://127.0.0.1:8000`

> **Jangan commit `.env`** (berisi `APP_KEY` dan kata sandi database). File ini sudah ada di `.gitignore`;
> yang di-commit hanya `.env.example` tanpa rahasia.

## Performa

- **Halaman tampil dulu, Plotly menyusul.** Plotly tidak dipasang sebagai `<script>`; `packing.js` mengunduh bundel `plotly-gl3d`
  (sekitar 1,7 MB, bukan 4,5 MB bundel penuh) di latar belakang saat file dipilih atau saat detail kloter dibuka.
- **Dashboard tanpa Plotly**: grafik area digambar sebagai SVG (sekitar 8 KB, dulu sekitar 1 MB).
- **Tanpa Bootstrap JS**: tab, menu, dan modal ditangani `public/js/app.js` (sekitar 9 KB).
- CSS dan JS dipanggil dengan `?v=<waktu ubah file>`; `public/.htaccess` menyetel cache panjang dan kompresi untuk Apache.
- **Aktifkan OPcache di PHP.** Tanpa OPcache, setiap permintaan menghabiskan sekitar 0,25 sampai 0,8 detik hanya untuk memuat PHP.
  Dengan OPcache, dashboard turun dari sekitar 0,4 sampai 0,6 detik menjadi sekitar 0,06 sampai 0,14 detik (diukur di mesin pengembangan).
  Tambahkan di `php.ini`, lalu jalankan ulang server:

  ```ini
  zend_extension=opcache
  opcache.enable=1
  opcache.enable_cli=1   ; perlu untuk php artisan serve
  ```

## Menjalankan tes

Tes **selalu** memakai SQLite in-memory: `phpunit.xml` memaksa (`force="true"`) `APP_ENV=testing`,
`DB_CONNECTION=sqlite`, dan `DB_DATABASE=:memory:`, sehingga variabel lingkungan lama di terminal/IDE
(misalnya `DB_CONNECTION=mysql`) tidak bisa membuat `RefreshDatabase` mengosongkan database asli.

Butuh ekstensi PHP `pdo_sqlite`. Bila belum aktif di `php.ini`, aktifkan sementara per perintah:

```
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit
```

Bila ekstensi tidak ada, tes gagal dengan pesan `could not find driver`; database MySQL tidak tersentuh.

## Catatan

- Tabel bawaan Laravel yang tidak dipakai aplikasi ini (`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`) **sengaja tidak dibuat**. Sesi dan cache memakai file (`SESSION_DRIVER=file`, `CACHE_STORE=file`) dan antrean memakai `sync`. Bila database lama masih memilikinya, tabel itu aman dihapus setelah `.env` memakai driver di atas.

- Jalur pada karton (`boxes.route`) adalah data turunan dari kode dealer dan pengaturan; dihitung ulang otomatis saat pengaturan berubah.
- Kolom `uploads.status`, `ratio`, `volume_ratio`, `weight_ratio`, `binding_constraint`, dan `vehicle_id` adalah peninggalan model lama dan tidak dipakai.
- Logika frontend ada di `public/js/packing.js`.
