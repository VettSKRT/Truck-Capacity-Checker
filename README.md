# Truck Capacity Checker

Aplikasi Laravel untuk mengecek kesiapan muatan truk di gudang, dengan tampilan 3D susunan karton.

## Konsep utama

- **Satu jalur = satu truk khusus ke satu tujuan.** Contoh: Jalur 1 ke Jabodetabek, Jalur 2 ke Jawa Tengah.
  Muatan satu jalur tidak dicampur dengan jalur lain (truk ke Jabodetabek tidak dialihkan ke Surabaya).
- **Satu kali unggah file order = satu kloter** (tabel `uploads`). Satu kloter berisi beberapa jalur.
- **Kesiapan kirim dinilai PER JALUR** terhadap truk jalur itu: rasio = nilai terbesar antara rasio volume dan rasio berat
  (mana yang tercapai lebih dulu). Siap dikirim jika >= 80%, melebihi kapasitas jika > 100%. Hanya karton **Status 4 (Cargo Ready)** dihitung.
- Jalur 1-7 ditentukan dari **kode area** dealer (`SA` pada `SA38`). Jalur 8 untuk dealer **Same Day** terpilih.
- Nama tujuan dan **truk default tiap jalur** diatur di menu Pengaturan (Area dan Jalur). Truk bisa diganti sementara per jalur saat melihat hasil.
- **Rencana muat** per jalur bisa dicetak untuk sopir dan gudang.

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
| Dashboard (`/`) | Jalur siap hari ini, kloter terbaru, jadwal kapal terdekat |
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

## Kendaraan & aturan muatan

| Kendaraan | Dimensi dalam (cm) | Volume | Muatan |
|---|---|---|---|
| CDE | 320 × 170 × 170 | 9,25 m³ | 3 ton |
| CDD | 440 × 200 × 190 | 16,72 m³ | 6 ton |
| Wing Box | 950 × 245 × 250 | 58,19 m³ | 17 ton |
| Impor (kontainer 40' HC, estimasi) | 1203 × 235 × 269 | 76,05 m³ | 26 ton |

- Kendaraan di atas dipilih sebagai **truk default per jalur** (bawaan: CDD, truk putih sekitar 16 m3).
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
- **Frontend**: Bootstrap 5, JavaScript biasa, Plotly.js
- **Excel Processing**: Laravel Excel
- **Database**: MySQL/PostgreSQL

## Installation

1. Clone repository
2. Install dependencies: `composer install`
3. Copy .env.example to .env
4. Configure database
5. Run migrations: `php artisan migrate`
6. Start server: `php artisan serve`

## Catatan

- Jalur pada karton (`boxes.route`) adalah data turunan dari kode dealer dan pengaturan; dihitung ulang otomatis saat pengaturan berubah.
- Kolom `uploads.status`, `ratio`, `volume_ratio`, `weight_ratio`, `binding_constraint`, dan `vehicle_id` adalah peninggalan model lama dan tidak dipakai.
- Logika frontend ada di `public/js/packing.js`.
