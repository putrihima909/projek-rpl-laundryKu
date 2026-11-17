<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Store;

class HomeController extends Controller
{
    public function index(): void
    {
        // Attempt to load featured stores from DB (toko dengan layanan terbanyak)
        $featuredStores = [];
        try {
            $storeModel     = new Store();
            $featuredStores = $storeModel->getFeaturedStores(4);
        } catch (\Throwable $e) {
            // DB not available — fallback below
        }

        // Static fallback data jika DB kosong / belum ada toko
        $featuredServices = !empty($featuredStores)
            ? array_map(fn($s) => [
                'name'  => $s['name'],
                'icon'  => 'fa-store',
                'price' => $s['min_price'] ? 'Mulai Rp ' . number_format((float)$s['min_price'], 0, ',', '.') : 'Lihat Detail',
                'desc'  => $s['description'] ?? 'Layanan laundry profesional dengan ' . ($s['service_count'] ?? 0) . ' jenis layanan tersedia.',
                'id'    => $s['id'],
            ], $featuredStores)
            : [
                ['name' => 'Cuci Komplit Reguler',       'icon' => 'fa-shirt',     'price' => 'Rp 6.000 / kg',  'desc' => 'Cuci, kering, dan setrika rapi dengan pewangi harum tahan lama.', 'id' => null],
                ['name' => 'Cuci Kilat / Express 3 Jam', 'icon' => 'fa-bolt',      'price' => 'Rp 12.000 / kg', 'desc' => 'Selesai dalam 3 jam! Cocok untuk kebutuhan darurat Anda.',         'id' => null],
                ['name' => 'Dry Cleaning & Suit Care',   'icon' => 'fa-user-tie',  'price' => 'Rp 35.000 / pcs','desc' => 'Perawatan khusus jas, gaun pesta, dan pakaian berbahan sensitif.','id' => null],
                ['name' => 'Layanan Antar Jemput Kurir', 'icon' => 'fa-truck-fast','price' => 'Gratis s.d 3 km','desc' => 'Kurir siap mengambil dan mengantar laundry langsung ke pintu rumah Anda.','id' => null],
            ];

        $data = [
            'pageTitle'       => 'LaundryKu - Bersih, Cepat & Cerdas',
            'isLoggedIn'      => Auth::check(),
            'user'            => Auth::user(),
            'featuredServices'=> $featuredServices,
            'fromDb'          => !empty($featuredStores),
        ];

        $this->view('home.index', $data, 'main');
    }
}
