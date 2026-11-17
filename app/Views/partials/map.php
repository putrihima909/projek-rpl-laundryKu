<?php
/**
 * Partial View: Interactive Visual Map (Leaflet.js + OpenStreetMap)
 * ==============================================================================
 * Komponen peta visual modular. Saat ini menggunakan Leaflet.js & OSM tile
 * provider (bebas biaya, tanpa API key). Terisolasi di partial ini sehingga mudah
 * diganti/di-upgrade ke Google Maps JavaScript SDK di masa mendatang cukup dengan
 * menyesuaikan block JavaScript renderer di file ini.
 *
 * Parameter yang didukung:
 * @var array   $markers      Daftar marker: [['lat' => float, 'lng' => float, 'label' => string, 'type' => 'store'|'courier'|'customer', 'info' => string]]
 * @var string  $mapId        ID unik elemen container DOM (default: auto-generated)
 * @var string  $mapHeight    Tinggi container CSS (default: '380px')
 * @var int     $zoom         Tingkat zoom awal (default: 13)
 * @var string  $cardTitle    Judul card header (opsional)
 * @var string  $cardSubtitle Subtitle info (opsional)
 * ==============================================================================
 */

$mapId = $mapId ?? ('map_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
$mapHeight = $mapHeight ?? '380px';
$zoom = $zoom ?? 13;
$markers = $markers ?? [];

// Hitung center awal dari marker pertama atau default Jakarta
$defaultLat = -6.2297465;
$defaultLng = 106.8164490;
if (!empty($markers) && isset($markers[0]['lat'], $markers[0]['lng'])) {
    $defaultLat = (float) $markers[0]['lat'];
    $defaultLng = (float) $markers[0]['lng'];
}
?>

<!-- Leaflet CDN Assets (Loaded once) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
/* Custom Marker Pin Styles */
.custom-map-pin {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #fff;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35);
    border: 2px solid #fff;
    font-size: 14px;
    position: relative;
    transition: transform 0.2s ease-in-out;
}
.custom-map-pin:hover {
    transform: scale(1.15);
}
.custom-map-pin.pin-store {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
}
.custom-map-pin.pin-courier {
    background: linear-gradient(135deg, #198754, #20c997);
    animation: courier-pulse 2s infinite;
}
.custom-map-pin.pin-customer {
    background: linear-gradient(135deg, #dc3545, #fd7e14);
}

@keyframes courier-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.6), 0 4px 10px rgba(0, 0, 0, 0.35);
    }
    70% {
        box-shadow: 0 0 0 12px rgba(25, 135, 84, 0), 0 4px 10px rgba(0, 0, 0, 0.35);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(25, 135, 84, 0), 0 4px 10px rgba(0, 0, 0, 0.35);
    }
}

.leaflet-popup-content-wrapper {
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}
.leaflet-popup-content {
    font-family: inherit;
    font-size: 13px;
    line-height: 1.4;
    padding: 4px;
}
</style>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <?php if (!empty($cardTitle)): ?>
        <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-map-location-dot text-primary me-2"></i><?= htmlspecialchars($cardTitle) ?>
                </h6>
                <?php if (!empty($cardSubtitle)): ?>
                    <small class="text-muted"><?= htmlspecialchars($cardSubtitle) ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                    <i class="fa-solid fa-circle text-success fa-beat-fade me-1" style="font-size: 8px;"></i> Live Position
                </span>
                <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 small">
                    Leaflet • OSM
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="card-body p-0 position-relative">
        <div id="<?= htmlspecialchars($mapId) ?>" style="width: 100%; height: <?= htmlspecialchars($mapHeight) ?>; z-index: 1;"></div>
    </div>

    <!-- Legend Footer -->
    <div class="card-footer bg-light border-0 py-2.5 px-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="d-flex align-items-center gap-1.5 text-secondary">
                    <span class="d-inline-block rounded-circle bg-primary" style="width: 10px; height: 10px;"></span> 
                    <strong>Toko Laundry</strong>
                </span>
                <span class="d-flex align-items-center gap-1.5 text-secondary">
                    <span class="d-inline-block rounded-circle bg-success" style="width: 10px; height: 10px;"></span> 
                    <strong>Kurir (Aktif)</strong>
                </span>
                <?php if (count($markers) > 2): ?>
                    <span class="d-flex align-items-center gap-1.5 text-secondary">
                        <span class="d-inline-block rounded-circle bg-danger" style="width: 10px; height: 10px;"></span> 
                        <strong>Pelanggan</strong>
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-muted small">
                <i class="fa-solid fa-arrows-to-dot me-1"></i> Klik pin marker untuk melihat rincian lokasi
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function initMap() {
        if (typeof L === 'undefined') {
            setTimeout(initMap, 50);
            return;
        }

        const mapElement = document.getElementById('<?= $mapId ?>');
        if (!mapElement) return;

        // Inisialisasi Peta Leaflet
        const map = L.map('<?= $mapId ?>', {
            zoomControl: true,
            attributionControl: true
        }).setView([<?= $defaultLat ?>, <?= $defaultLng ?>], <?= $zoom ?>);

        // Tile layer OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors'
        }).addTo(map);

        const markersData = <?= json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const bounds = [];

        function createIcon(type) {
            let iconClass = 'fa-store';
            let pinClass = 'pin-store';
            if (type === 'courier') {
                iconClass = 'fa-motorcycle';
                pinClass = 'pin-courier';
            } else if (type === 'customer') {
                iconClass = 'fa-house-user';
                pinClass = 'pin-customer';
            }

            return L.divIcon({
                className: 'custom-div-icon',
                html: `<div class="custom-map-pin ${pinClass}" style="width: 34px; height: 34px;">
                         <i class="fa-solid ${iconClass}"></i>
                       </div>`,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -18]
            });
        }

        let courierMarker = null;
        let storeMarker = null;

        markersData.forEach(function(item) {
            if (!item.lat || !item.lng) return;
            const lat = parseFloat(item.lat);
            const lng = parseFloat(item.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            bounds.push([lat, lng]);
            const icon = createIcon(item.type || 'store');
            const marker = L.marker([lat, lng], { icon: icon }).addTo(map);

            const badgeBg = item.type === 'courier' ? 'success' : (item.type === 'customer' ? 'danger' : 'primary');
            const popupHtml = `
                <div style="min-width: 180px;">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge bg-${badgeBg} rounded-pill px-2 py-0.5" style="font-size: 10px;">
                            ${(item.type || 'LOKASI').toUpperCase()}
                        </span>
                    </div>
                    <strong class="d-block text-dark" style="font-size: 13px;">${item.label || 'Lokasi'}</strong>
                    ${item.info ? `<div class="text-secondary small mt-1">${item.info}</div>` : ''}
                    <div class="text-muted mt-1 font-monospace" style="font-size: 10px;">
                        <i class="fa-solid fa-location-crosshairs me-1"></i>${lat.toFixed(6)}, ${lng.toFixed(6)}
                    </div>
                </div>
            `;
            marker.bindPopup(popupHtml);

            if (item.type === 'courier') {
                courierMarker = marker;
                marker.openPopup();
            } else if (item.type === 'store') {
                storeMarker = marker;
            }
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, {
                padding: [40, 40],
                maxZoom: 16
            });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], <?= $zoom ?>);
        }

        // Simpan helper ke window agar polling script dapat memperbarui posisi marker kurir secara live
        window['mapHelper_<?= $mapId ?>'] = {
            map: map,
            courierMarker: courierMarker,
            storeMarker: storeMarker,
            updateCourier: function(newLat, newLng, infoText, courierName) {
                if (this.courierMarker) {
                    this.courierMarker.setLatLng([newLat, newLng]);
                    const name = courierName || 'Kurir Pengantar';
                    const newHtml = `
                        <div style="min-width: 180px;">
                            <div class="d-flex align-items-center gap-1 mb-1">
                                <span class="badge bg-success rounded-pill px-2 py-0.5" style="font-size: 10px;">KURIR</span>
                            </div>
                            <strong class="d-block text-dark" style="font-size: 13px;">${name}</strong>
                            ${infoText ? `<div class="text-secondary small mt-1">${infoText}</div>` : ''}
                            <div class="text-muted mt-1 font-monospace" style="font-size: 10px;">
                                <i class="fa-solid fa-location-crosshairs me-1"></i>${newLat.toFixed(6)}, ${newLng.toFixed(6)}
                            </div>
                        </div>
                    `;
                    this.courierMarker.setPopupContent(newHtml);
                }
            }
        };
        window.activeTrackingMapHelper = window['mapHelper_<?= $mapId ?>'];

        setTimeout(function() {
            map.invalidateSize();
        }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
})();
</script>
