<?php

namespace App\Services;

/**
 * Service: Distance & Duration Calculator
 * ==============================================================================
 * Layanan terpusat untuk perhitungan jarak dan estimasi waktu tempuh kurir
 * antar dua koordinat GPS (Toko, Kurir, Pelanggan).
 *
 * Menggunakan pendekatan Haversine formula (bebas biaya, tanpa dependensi API key).
 * Terstruktur rapi di service ini sehingga mudah dimigrasikan ke Google Distance
 * Matrix API saat GOOGLE_MAPS_API_KEY dikonfigurasi di file .env.
 * ==============================================================================
 */
class DistanceCalculator
{
    public const EARTH_RADIUS_KM = 6371.0;
    public const DEFAULT_COURIER_SPEED_KMH = 30.0; // Kecepatan rata-rata motor kurir dalam kota (km/jam)
    public const DETOUR_FACTOR = 1.25;             // Faktor koreksi rute jalan perkotaan (rute belokan)

    /**
     * Menghitung jarak garis lengkung bumi (great-circle distance) antar dua koordinat dalam kilometer.
     *
     * @param float $lat1 Latitude titik awal
     * @param float $lng1 Longitude titik awal
     * @param float $lat2 Latitude titik tujuan
     * @param float $lng2 Longitude titik tujuan
     * @return float Jarak dalam kilometer (dibulatkan 2 desimal)
     */
    public static function calculateHaversine(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        if (abs($lat1 - $lat2) < 0.000001 && abs($lng1 - $lng2) < 0.000001) {
            return 0.0;
        }

        $lat1Rad = deg2rad($lat1);
        $lng1Rad = deg2rad($lng1);
        $lat2Rad = deg2rad($lat2);
        $lng2Rad = deg2rad($lng2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLng = $lng2Rad - $lng1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt(min(1.0, max(0.0, $a))), sqrt(min(1.0, max(0.0, 1.0 - $a))));

        return round(self::EARTH_RADIUS_KM * $c, 2);
    }

    /**
     * Menghitung estimasi durasi tempuh perjalanan kurir dalam menit.
     *
     * @param float $distanceKm Jarak tempuh dalam kilometer
     * @param float $speedKmH Kecepatan rata-rata (default: 30 km/jam)
     * @return int Durasi tempuh dalam menit (minimal 1 menit)
     */
    public static function calculateDurationMinutes(
        float $distanceKm,
        float $speedKmH = self::DEFAULT_COURIER_SPEED_KMH
    ): int {
        if ($distanceKm <= 0.0) {
            return 1;
        }

        $effectiveDistance = $distanceKm * self::DETOUR_FACTOR;
        $hours = $effectiveDistance / max(1.0, $speedKmH);
        $minutes = (int) round($hours * 60);

        return max(1, $minutes);
    }

    /**
     * Mendapatkan estimasi komprehensif jarak dan durasi dalam array terstruktur.
     *
     * @param float $lat1 Latitude awal
     * @param float $lng1 Longitude awal
     * @param float $lat2 Latitude tujuan
     * @param float $lng2 Longitude tujuan
     * @param float $speedKmH Kecepatan kendaraan kurir (default: 30 km/jam)
     * @return array [
     *     'distance_km' => float,
     *     'duration_minutes' => int,
     *     'formatted_distance' => string,
     *     'formatted_duration' => string,
     *     'provider' => 'haversine'|'google_distance_matrix'
     * ]
     */
    public static function estimate(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        float $speedKmH = self::DEFAULT_COURIER_SPEED_KMH
    ): array {
        // Jika kelak GOOGLE_MAPS_API_KEY terisi, adapter Google Distance Matrix API dapat dipanggil di sini
        $distanceKm = self::calculateHaversine($lat1, $lng1, $lat2, $lng2);
        $durationMinutes = self::calculateDurationMinutes($distanceKm, $speedKmH);

        $formattedDistance = ($distanceKm < 1.0)
            ? (int)round($distanceKm * 1000) . ' m'
            : number_format($distanceKm, 1, ',', '.') . ' km';

        $formattedDuration = ($durationMinutes < 60)
            ? $durationMinutes . ' menit'
            : floor($durationMinutes / 60) . ' jam ' . ($durationMinutes % 60) . ' menit';

        return [
            'distance_km'        => $distanceKm,
            'duration_minutes'   => $durationMinutes,
            'formatted_distance' => $formattedDistance,
            'formatted_duration' => $formattedDuration,
            'provider'           => 'haversine'
        ];
    }
}
