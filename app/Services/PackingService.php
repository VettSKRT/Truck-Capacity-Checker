<?php

namespace App\Services;

/**
 * Algoritma pemuatan. Dua mode:
 *
 *  - "ratio"  : hanya hitung kubikasi & berat. Unit dimuat berurutan sampai salah satu
 *               (volume efektif atau muatan maksimum) tercapai lebih dulu.
 *  - "tetris" : penyusunan 3D nyata dengan aturan domestik:
 *               * karton TIDAK diputar (P // panjang, L // lebar, T // tinggi kendaraan)
 *               * yang berat di bawah, yang ringan di atas
 *               * tidak ada ruang kosong antar susunan: karton hanya boleh ditumpuk
 *                 bila seluruh alasnya ditopang karton di bawahnya (tanpa overhang)
 *
 * Kelas ini murni (tanpa database) agar mudah dites.
 *
 * Unit  : ['id','l','w','h' (mm),'weight' (kg),'status','route','service_type',...]
 * Kendaraan: ['length_mm','width_mm','height_mm','max_payload_kg','volume_m3' (efektif)]
 */
class PackingService
{
    public const MODES = ['ratio', 'tetris'];

    private const EPS = 1e-9;

    private const SERVICE_PRIORITY = ['emergency' => 0, 'same_day' => 1, 'one_day' => 2];

    public function __construct(private LoadCalculator $calculator)
    {
    }

    /**
     * Perluas baris Box (box_count karton identik) menjadi unit per karton.
     *
     * @param iterable<\App\Models\Box> $boxes
     */
    public function unitsFromBoxes(iterable $boxes): array
    {
        $units = [];
        foreach ($boxes as $box) {
            $count = max(1, (int) $box->box_count);
            $weight = $box->weight_kg / $count;

            for ($i = 0; $i < $count; $i++) {
                $units[] = [
                    'id' => $box->id,
                    'l' => (int) $box->panjang,
                    'w' => (int) $box->lebar,
                    'h' => (int) $box->tinggi,
                    'weight' => $weight,
                    'status' => $box->status,
                    'route' => $box->route,
                    'service_type' => $box->service_type,
                    'part_number' => $box->part_number,
                    'packing_type' => $box->packing_type,
                    'category' => $box->category,
                ];
            }
        }

        return $units;
    }

    /**
     * @return array{mode:string, loaded:array, unloaded:array, summary:array}
     */
    public function pack(array $units, array $vehicle, string $mode = 'tetris'): array
    {
        foreach ($units as $i => $u) {
            $units[$i]['_i'] = $i;
            $units[$i]['volume'] = $u['l'] * $u['w'] * $u['h'] / 1e9;
        }

        $result = $mode === 'ratio'
            ? $this->packByRatio($units, $vehicle)
            : $this->packTetris($units, $vehicle);

        $result['mode'] = $mode === 'ratio' ? 'ratio' : 'tetris';
        $result['summary'] = $this->summary($result, $vehicle);

        return $result;
    }

    /* ----------------------------- Mode Rasio ----------------------------- */

    private function packByRatio(array $units, array $vehicle): array
    {
        // Prioritas layanan (emergency > same day > one day), stabil terhadap urutan file
        usort($units, fn ($a, $b) =>
            (self::SERVICE_PRIORITY[$a['service_type'] ?? ''] ?? 3) <=> (self::SERVICE_PRIORITY[$b['service_type'] ?? ''] ?? 3)
            ?: $a['_i'] <=> $b['_i']);

        $loaded = $unloaded = [];
        $volume = $weight = 0.0;

        foreach ($units as $u) {
            $reason = $this->rejectReason($u, $vehicle, $weight, $volume);
            if ($reason !== null) {
                $unloaded[] = $u + ['reason' => $reason];
                continue;
            }
            $volume += $u['volume'];
            $weight += $u['weight'];
            $loaded[] = $u;
        }

        return ['loaded' => $loaded, 'unloaded' => $unloaded, 'stack_count' => null];
    }

    /* ---------------------------- Mode Tetris ----------------------------- */

    /**
     * Coba beberapa urutan pemuatan lalu pakai hasil terpadat. Aturan fisik (tanpa rotasi,
     * tertopang penuh, ringan di atas berat) selalu ditegakkan saat penumpukan, apa pun urutannya.
     */
    private function packTetris(array $units, array $vehicle): array
    {
        $area = fn ($u) => $u['l'] * $u['w'];

        $strategies = [
            // berat -> ringan (berat jadi dasar)
            'weight' => fn ($a, $b) => $b['weight'] <=> $a['weight'] ?: $area($b) <=> $area($a) ?: $b['h'] <=> $a['h'],
            // alas terbesar dulu
            'area' => fn ($a, $b) => $area($b) <=> $area($a) ?: $b['weight'] <=> $a['weight'] ?: $b['h'] <=> $a['h'],
            // lebar (kedalaman baris) terbesar dulu agar baris rata
            'width' => fn ($a, $b) => $b['w'] <=> $a['w'] ?: $b['l'] <=> $a['l'] ?: $b['weight'] <=> $a['weight'] ?: $b['h'] <=> $a['h'],
            // panjang terbesar dulu
            'length' => fn ($a, $b) => $b['l'] <=> $a['l'] ?: $b['w'] <=> $a['w'] ?: $b['weight'] <=> $a['weight'] ?: $b['h'] <=> $a['h'],
        ];

        $best = null;
        $bestVolume = -1.0;

        foreach ($strategies as $name => $cmp) {
            $sorted = $units;
            usort($sorted, fn ($a, $b) => $cmp($a, $b) ?: $a['_i'] <=> $b['_i']);

            $result = $this->packTetrisOnce($sorted, $vehicle);
            $volume = array_sum(array_column($result['loaded'], 'volume'));

            if ($volume > $bestVolume + self::EPS) {
                $bestVolume = $volume;
                $best = $result + ['strategy' => $name];
            }
        }

        return $best;
    }

    private function packTetrisOnce(array $units, array $vehicle): array
    {
        $maxL = $vehicle['length_mm'];
        $maxW = $vehicle['width_mm'];
        $maxH = $vehicle['height_mm'];

        $stacks = [];   // tiap stack: x, y, l, w (alas karton teratas), top (z puncak), weight (berat karton teratas)
        $rows = [];     // baris lantai: y, depth, x (kursor)
        $loaded = $unloaded = [];
        $volume = $weight = 0.0;

        foreach ($units as $u) {
            $reason = $this->rejectReason($u, $vehicle, $weight, $volume);
            if ($reason !== null) {
                $unloaded[] = $u + ['reason' => $reason];
                continue;
            }

            // 1) Tumpuk di stack yang alasnya sepenuhnya menopang & paling pas sisa tingginya
            $best = null;
            $bestLeft = PHP_INT_MAX;
            $bestWaste = PHP_INT_MAX;
            foreach ($stacks as $si => $s) {
                if ($u['l'] > $s['l'] || $u['w'] > $s['w'] || $u['weight'] > $s['weight'] + self::EPS) {
                    continue;
                }
                $left = $maxH - $s['top'] - $u['h'];
                if ($left < 0) {
                    continue;
                }
                $waste = $s['l'] * $s['w'] - $u['l'] * $u['w'];
                if ($left < $bestLeft || ($left === $bestLeft && $waste < $bestWaste)) {
                    $best = $si;
                    $bestLeft = $left;
                    $bestWaste = $waste;
                }
            }

            if ($best !== null) {
                $s = &$stacks[$best];
                $loaded[] = $u + ['x' => $s['x'], 'y' => $s['y'], 'z' => $s['top']];
                $s['top'] += $u['h'];
                $s['l'] = $u['l'];
                $s['w'] = $u['w'];
                $s['weight'] = $u['weight'];
                unset($s);
            } else {
                // 2) Buka stack baru di lantai
                $pos = $this->placeOnFloor($rows, $u['l'], $u['w'], $maxL, $maxW);
                if ($pos === null) {
                    $unloaded[] = $u + ['reason' => 'space'];
                    continue;
                }
                $stacks[] = [
                    'x' => $pos[0], 'y' => $pos[1],
                    'l' => $u['l'], 'w' => $u['w'],
                    'top' => $u['h'], 'weight' => $u['weight'],
                ];
                $loaded[] = $u + ['x' => $pos[0], 'y' => $pos[1], 'z' => 0];
            }

            $volume += $u['volume'];
            $weight += $u['weight'];
        }

        return ['loaded' => $loaded, 'unloaded' => $unloaded, 'stack_count' => count($stacks)];
    }

    /**
     * Penempatan lantai bergaya rak: baris sepanjang panjang kendaraan, baris-baris
     * berurutan sepanjang lebar. Baris terakhir boleh melebar bila perlu.
     *
     * @return array{0:int,1:int}|null [x, y]
     */
    private function placeOnFloor(array &$rows, int $l, int $w, int $maxL, int $maxW): ?array
    {
        $last = count($rows) - 1;

        foreach ($rows as $i => &$row) {
            if ($row['x'] + $l > $maxL) {
                continue;
            }
            if ($w <= $row['depth']) {
                $pos = [$row['x'], $row['y']];
                $row['x'] += $l;

                return $pos;
            }
            if ($i === $last && $row['y'] + $w <= $maxW) {
                $row['depth'] = $w;
                $pos = [$row['x'], $row['y']];
                $row['x'] += $l;

                return $pos;
            }
        }
        unset($row);

        $y = $last >= 0 ? $rows[$last]['y'] + $rows[$last]['depth'] : 0;
        if ($y + $w > $maxW || $l > $maxL) {
            return null;
        }
        $rows[] = ['y' => $y, 'depth' => $w, 'x' => $l];

        return [0, $y];
    }

    /* ------------------------------ Umum ---------------------------------- */

    /** Alasan unit tidak bisa dimuat: too_big | weight | volume, atau null bila lolos. */
    private function rejectReason(array $u, array $vehicle, float $weight, float $volume): ?string
    {
        if ($u['l'] > $vehicle['length_mm'] || $u['w'] > $vehicle['width_mm'] || $u['h'] > $vehicle['height_mm']) {
            return 'too_big';
        }
        if ($weight + $u['weight'] > $vehicle['max_payload_kg'] + self::EPS) {
            return 'weight';
        }
        if ($volume + $u['volume'] > $vehicle['volume_m3'] + self::EPS) {
            return 'volume';
        }

        return null;
    }

    private function summary(array $result, array $vehicle): array
    {
        $volume = array_sum(array_column($result['loaded'], 'volume'));
        $weight = array_sum(array_column($result['loaded'], 'weight'));
        $physical = $vehicle['length_mm'] * $vehicle['width_mm'] * $vehicle['height_mm'] / 1e9;

        $reasons = [];
        foreach ($result['unloaded'] as $u) {
            $reasons[$u['reason']] = ($reasons[$u['reason']] ?? 0) + 1;
        }

        return $this->calculator->summarize($volume, $weight, $vehicle['volume_m3'], $vehicle['max_payload_kg']) + [
            'loaded_count' => count($result['loaded']),
            'unloaded_count' => count($result['unloaded']),
            'unloaded_reasons' => $reasons,
            'loaded_volume_m3' => $volume,
            'loaded_weight_kg' => $weight,
            'space_utilization' => $physical > 0 ? $volume / $physical : 0.0,
            'stack_count' => $result['stack_count'],
            'strategy' => $result['strategy'] ?? null,
        ];
    }
}
