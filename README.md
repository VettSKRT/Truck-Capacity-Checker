# Truck Capacity Checker

Aplikasi Laravel untuk mengecek kapasitas truk dengan visualisasi 3D real-time.

## Fitur

### 1. Upload Excel Data
- Upload file Excel (.xlsx, .xls) dengan data cargo
- Format Excel yang diharapkan:
  - Kolom A: Cargo Destination
  - Kolom B: Customer Code  
  - Kolom C: Customer Name
  - Kolom D: Panjang (mm)
  - Kolom E: Lebar (mm)
  - Kolom F: Tinggi (mm)
  - Kolom G: Status (2, 3, atau 4)

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

## Format Excel

| Cargo Destination | Customer Code | Customer Name | Panjang (mm) | Lebar (mm) | Tinggi (mm) | Status |
|------------------|---------------|---------------|--------------|------------|-------------|--------|
| Jakarta          | CUST001       | PT ABC        | 1000         | 500        | 300         | 4      |
| Surabaya         | CUST002       | PT XYZ        | 800          | 400        | 250         | 3      |

## Status Items

- **Status 2**: Prioritas rendah (kuning)
- **Status 3**: Prioritas menengah (biru)
- **Status 4**: Prioritas tinggi (hijau)

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
