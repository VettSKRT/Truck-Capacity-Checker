<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Logistik Truck Capacity Checker
|--------------------------------------------------------------------------
| Semua aturan bisnis yang bisa berubah dikumpulkan di sini supaya tidak
| di-hardcode di controller / view.
*/

return [

    // Kendaraan default saat upload tidak menyebutkan kendaraan (kode di tabel vehicles).
    'default_vehicle' => env('LOGISTICS_DEFAULT_VEHICLE', 'CDD'),

    // Batas rasio (volume ATAU berat, mana yang lebih besar) agar muatan dianggap siap kirim.
    'ready_ratio' => 0.8,

    // Status order. Hanya status "cargo_ready" yang dihitung ke muatan siap kirim.
    'statuses' => [
        1 => 'Order Received',
        2 => 'Picking Process',
        3 => 'Checking / Packing',
        4 => 'Cargo Ready',
    ],
    'cargo_ready_status' => 4,

    // Tipe layanan. Emergency & One Day sama dari sisi waktu kirim, beda di proses gudang.
    'service_types' => [
        'same_day' => 'Same Day (order sebelum jam 12, Jalur 8)',
        'one_day' => 'One Day (order setelah jam 12)',
        'emergency' => 'Emergency',
    ],
    'default_service_type' => 'one_day',
    'same_day_cutoff' => '12:00',

    // Jalur 8 = Same Day untuk dealer terpilih.
    'same_day_route' => 8,

    /*
     | Mapping KODE AREA -> JALUR (1-7).
     | Kode dealer berformat <KODE AREA><NOMOR DEALER>, contoh "SA38":
     | "SA" = kode area, "38" = kode dealer.
     |
     | TODO: isi sesuai data asli, contoh:
     |   'SA' => 1,
     |   'SB' => 1,
     |   'BD' => 2,
     */
    'area_routes' => [
        // 'SA' => 1,
    ],

    /*
     | Daftar kode dealer (lengkap, mis. "SA38") yang boleh memakai Jalur 8 Same Day.
     | TODO: isi sesuai data asli.
     */
    'same_day_dealers' => [
        // 'SA38',
    ],

    // Nama jalur (opsional, untuk tampilan).
    'route_labels' => [
        1 => 'Jalur 1', 2 => 'Jalur 2', 3 => 'Jalur 3', 4 => 'Jalur 4',
        5 => 'Jalur 5', 6 => 'Jalur 6', 7 => 'Jalur 7', 8 => 'Jalur 8 (Same Day)',
    ],

    // Kategori barang.
    'categories' => [
        'small' => 'Barang kecil (6 jalur pengeluaran)',
        'heavy' => 'Barang berat / besar (bemper, kap mobil; 2 jalur pengeluaran)',
    ],
];
