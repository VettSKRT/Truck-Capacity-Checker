# Truck Capacity Checker

Aplikasi Laravel untuk mengecek kapasitas truk dengan visualisasi 3D real-time.

## Fitur

### 1. Upload Order (Excel/CSV)
- Dimensi karton dan berat diambil dari **master barang**, bukan dari file order.
- Kolom file order (baris 1 = header):
  - A: Cargo Destination
  - B: Customer Code (kode dealer, mis. `SA38` = area `SA` + dealer `38`)
  - C: Customer Name
  - D: Part Number
  - E: Qty (pcs)
  - F: Status (1 Order Received, 2 Picking, 3 Checking/Packing, 4 Cargo Ready)
  - G: Service Type (opsional: `same_day`, `one_day`, `emergency`; default `one_day`)
- Qty dipecah otomatis: **Packing Unit** (box penuh) dan **Packing Receh** (sisa < qty per box).

### 2. Manual Entry
- Input manual dimensi item (panjang, lebar, tinggi dalam meter)
- Pilih status item (Status 2, 3, atau 4)

### 3. 3D Visualization
- Visualisasi 3D real-time menggunakan Plotly.js
- Container 16m × 1m × 1m
- Item cards yang dapat diklik untuk highlight
- Warna berbeda berdasarkan status item

### 4. Efficiency Metrics
- Volume yang terpacking (semua status)
- Efisiensi = volume **Status 4** / 16 m³ (sama dengan aturan di halaman hasil)
- Badge kesiapan kirim: **Siap Dikirim** jika rasio Status 4 ≥ 80%
- Progress bar dengan warna berdasarkan efisiensi

## Cara Penggunaan

1. **Akses aplikasi**: `http://localhost:8000`
2. **Aplikasi akan langsung redirect ke halaman packing**
3. **Upload Excel**: Pilih file Excel dengan format yang sesuai
4. **Input Manual**: Atau input item secara manual
5. **Lihat hasil**: Visualisasi 3D akan update secara real-time
6. **Halaman laporan**: setelah upload via form biasa (`/upload`), hasil ada di `/result/{id}`

## Kendaraan & aturan muatan

| Kendaraan | Dimensi dalam (cm) | Volume | Muatan |
|---|---|---|---|
| CDE | 320 × 170 × 170 | 9,25 m³ | 3 ton |
| CDD | 440 × 200 × 190 | 16,72 m³ | 6 ton |
| Wing Box | 950 × 245 × 250 | 58,19 m³ | 17 ton |
| Impor (kontainer 40' HC, estimasi) | 1203 × 235 × 269 | 76,05 m³ | 26 ton |

- Rasio muatan = nilai terbesar antara **rasio volume** dan **rasio berat** (mana yang tercapai lebih dulu).
- Hanya box **Status 4 (Cargo Ready)** yang dihitung; siap kirim jika rasio >= 80%.
- Karton **tidak boleh diputar** (P sejajar panjang kendaraan, L lebar, T tinggi).
- Jalur 1-7 dari kode area, jalur 8 untuk Same Day dealer terpilih. **Isi mapping di `config/logistics.php`** (`area_routes`, `same_day_dealers`).

## Algoritma packing (`POST /packing/optimize`)

Parameter: `upload_id` (wajib), `mode` = `tetris` (default) atau `ratio`, `vehicle` (kode, default kendaraan upload), `only_ready` (default `true` = hanya Status 4).

- **Mode `ratio`**: hanya kubikasi dan berat. Karton dimuat berurutan (Emergency, lalu Same Day, lalu One Day) sampai volume atau berat tercapai lebih dulu. Tidak ada posisi 3D.
- **Mode `tetris`**: penyusunan 3D nyata. Karton tidak diputar, yang berat di bawah dan yang ringan di atas, dan karton hanya ditumpuk bila alasnya tertopang penuh (tanpa overhang/ruang kosong). Beberapa urutan pemuatan dicoba, lalu dipakai yang paling padat.
- Karton yang tidak termuat dilaporkan di `unloaded` dengan alasan `weight`, `volume`, `space`, atau `too_big`. Dimensi respons dalam meter.

## Setup data

```
php artisan migrate
php artisan db:seed                 # kendaraan, 25 karton, dan master barang CONTOH
php artisan products:import sample_products.csv   # import/update master barang (ganti dengan data asli)
```

File contoh: `sample_data.csv` (order) dan `sample_products.csv` (master barang:
Part Number, Name, Weight kg/pcs, No Karton, Qty per Box, Category small|heavy, Location).

## Teknologi

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Bootstrap 5, jQuery, Plotly.js
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

- Baris Excel/CSV yang kosong, berdimensi <= 0, atau berstatus selain 2/3/4 dilewati dan dilaporkan.
- Logika frontend halaman packing ada di `public/js/packing.js`.
