<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Logistik Truck Capacity Checker
|--------------------------------------------------------------------------
| Semua aturan bisnis yang bisa berubah dikumpulkan di sini supaya tidak
| di-hardcode di controller / view.
|
| CATATAN: 'area_routes', 'area_regions', dan 'same_day_dealers' hanyalah DATA AWAL
| yang disalin ke database saat migrasi. Setelah itu pengaturannya diubah lewat menu
| Pengaturan (Area dan Jalur) dan Dealer dan MOQ, bukan lewat file ini.
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
        // CONTOH untuk data sample. Satu jalur = satu truk ke satu tujuan.
        // TODO: ganti dengan kode area asli lewat menu Pengaturan, Area dan Jalur.
        'SA' => 1,  // Jabodetabek
        'SC' => 2,  // Jawa Tengah
        'SB' => 3,  // Jawa Barat
        'SH' => 3,  // Cirebon (Jawa Barat)
        'SD' => 4,  // Jawa Timur
        'SE' => 4,
        'SF' => 5,  // Sumatra
        'SG' => 5,
        'SJ' => 6,  // Luar pulau (pelabuhan)
        'SK' => 6,
        'SL' => 6,
    ],

    /*
     | Daftar kode dealer (lengkap, mis. "SA38") yang boleh memakai Jalur 8 Same Day.
     | TODO: isi sesuai data asli.
     */
    'same_day_dealers' => [
        'SH09', // CONTOH untuk sample_data_lengkap.csv; ganti dengan dealer terpilih yang asli
    ],

    /*
     | WILAYAH dan JENIS JADWAL.
     | Wilayah ditentukan dari kode area dealer (mis. "SA" pada "SA38").
     |  - Jawa dan Sumatra (kecuali Aceh): pengiriman HARIAN (daily).
     |  - Aceh dan luar pulau: mengikuti JADWAL KAPAL (weekly), harus memenuhi MOQ dealer.
     */
    'regions' => [
        'jawa' => 'Jawa',
        'sumatra' => 'Sumatra (selain Aceh)',
        'aceh' => 'Aceh',
        'luar_pulau' => 'Luar pulau',
    ],
    'region_schedule' => [
        'jawa' => 'daily',
        'sumatra' => 'daily',
        'aceh' => 'kapal',
        'luar_pulau' => 'kapal',
    ],

    /*
     | Mapping KODE AREA -> WILAYAH.
     | CONTOH untuk data sample; TODO: ganti dengan kode area asli.
     | Kode area yang tidak terdaftar tampil sebagai "Wilayah belum diatur".
     */
    'area_regions' => [
        'SA' => 'jawa',
        'SB' => 'jawa',
        'SC' => 'jawa',
        'SD' => 'jawa',
        'SE' => 'jawa',
        'SH' => 'jawa',
        'SF' => 'sumatra',
        'SG' => 'sumatra',
        'SJ' => 'luar_pulau',
        'SK' => 'luar_pulau',
        'SL' => 'aceh',
    ],

    // Kirim harian: Same Day berangkat hari yang sama, selain itu (One Day dan Emergency) keesokan harinya.
    'daily_lead_days' => ['same_day' => 0, 'one_day' => 1, 'emergency' => 1],

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
