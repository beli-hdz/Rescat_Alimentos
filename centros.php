<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$centros = $pdo->query("SELECT * FROM centers WHERE active=1 ORDER BY name")->fetchAll();
$mapPlaces = [];
foreach ($centros as $c) {
    if ($c['latitude'] !== null && $c['longitude'] !== null) {
        $mapPlaces[] = [
            'id' => (int)$c['id'],
            'name' => $c['name'],
            'type' => $c['center_type'] ?? 'center',
            'address' => $c['address'],
            'description' => $c['description'] ?? '',
            'phone' => $c['phone'] ?? '',
            'lat' => (float)$c['latitude'],
            'lng' => (float)$c['longitude'],
        ];
    }
}
$pageTitle = 'Centros de apoyo';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<section class="places-intro">
  <span class="eyebrow">UBICACIONES RESCATALIMENTOS</span>
  <h1>Centros de apoyo y puntos de recolección</h1>
  <p>Consulta en el mapa los lugares registrados. Selecciona un marcador para ver sus datos y abrir la ruta.</p>
</section>

<section class="places-map-section">
  <div class="places-map-heading">
    <div><h2>Mapa de ubicaciones</h2><p class="muted">Los marcadores se actualizan con los registros activos que tienen coordenadas.</p></div>
    <div class="map-legend">
      <span><i class="map-dot center-dot"></i> Centros de donaciones</span>
      <span><i class="map-dot pickup-dot"></i> Puntos de recolección</span>
    </div>
  </div>
  <div id="rescatPlacesMap" class="rescat-leaflet-map" role="region" aria-label="Mapa de centros y puntos de recolección"></div>
  <p id="mapStatus" class="map-status" aria-live="polite"></p>
  <div class="map-filter-row">
    <label><input type="checkbox" id="showCenters" checked> Mostrar centros</label>
    <label><input type="checkbox" id="showPickupPoints" checked> Mostrar puntos de recolección</label>
    <span class="muted"><?=count($mapPlaces)?> de <?=count($centros)?> lugares con coordenadas</span>
  </div>
  <p class="map-attribution">Mapa © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>, datos disponibles bajo ODbL.</p>
</section>

<div class="section-head"><div><h2>Lugares registrados</h2><p>Consulta la información de cada organización y punto de recolección.</p></div></div>
<div class="places-cards">
<?php foreach($centros as $c): ?>
<article class="card place-public-card">
  <div class="card-body">
    <span class="tag"><?=($c['center_type']??'center')==='pickup_point'?'Punto de recolección':'Centro de donaciones'?></span>
    <h3><?=e($c['name'])?></h3>
    <?php if(!empty($c['description'])): ?><p><?=e($c['description'])?></p><?php endif; ?>
    <p><b>Dirección:</b> <?=e($c['address'])?></p>
    <?php if(!empty($c['phone'])): ?><p><b>Teléfono:</b> <?=e($c['phone'])?></p><?php endif; ?>
    <?php if(!empty($c['website'])): ?><p><a href="<?=e($c['website'])?>" target="_blank" rel="noopener noreferrer">Más información</a></p><?php endif; ?>
    <?php if($c['latitude'] !== null && $c['longitude'] !== null): ?>
      <a class="btn teal" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/dir/?api=1&destination=<?=rawurlencode($c['latitude'].','.$c['longitude'])?>">Cómo llegar</a>
    <?php else: ?><span class="small">Ubicación en mapa pendiente de registrar.</span><?php endif; ?>
  </div>
</article>
<?php endforeach; ?>
<?php if(!$centros): ?><div class="empty">Todavía no hay centros ni puntos de recolección registrados.</div><?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
  const mapEl = document.getElementById('rescatPlacesMap');
  if (!mapEl || typeof L === 'undefined') {
    const status = document.getElementById('mapStatus');
    if (status) status.textContent = 'No se pudo cargar el mapa. Comprueba tu conexión a Internet y vuelve a intentarlo.';
    return;
  }

  const places = <?=json_encode($mapPlaces, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
  const fallback = [25.6732, -100.4589];
  const map = L.map(mapEl, { scrollWheelZoom: false }).setView(fallback, 11);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
  }).addTo(map);

  const centerIcon = L.divIcon({
    className: 'rescat-map-marker-wrap',
    html: '<span class="rescat-map-marker rescat-center-marker"></span>',
    iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -12]
  });
  const pickupIcon = L.divIcon({
    className: 'rescat-map-marker-wrap',
    html: '<span class="rescat-map-marker rescat-pickup-marker"></span>',
    iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -12]
  });

  const escapeHtml = value => String(value || '').replace(/[&<>"']/g, ch => ({
    '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
  }[ch]));

  const markers = [];
  const bounds = [];
  places.forEach(place => {
    const isPickup = place.type === 'pickup_point';
    const marker = L.marker([Number(place.lat), Number(place.lng)], {
      icon: isPickup ? pickupIcon : centerIcon,
      title: place.name
    });
    const destination = encodeURIComponent(place.lat + ',' + place.lng);
    const popup = '<div class="rescat-map-popup">' +
      '<strong>' + escapeHtml(place.name) + '</strong>' +
      '<p class="popup-kind">' + (isPickup ? 'Punto de recolección' : 'Centro de donaciones') + '</p>' +
      '<p>' + escapeHtml(place.address) + '</p>' +
      (place.phone ? '<p>Tel. ' + escapeHtml(place.phone) + '</p>' : '') +
      '<a href="https://www.google.com/maps/dir/?api=1&destination=' + destination + '" target="_blank" rel="noopener noreferrer">Cómo llegar</a>' +
      '</div>';
    marker.bindPopup(popup);
    marker.rescatType = place.type;
    markers.push(marker);
    bounds.push([Number(place.lat), Number(place.lng)]);
  });

  const centerLayer = L.layerGroup(markers.filter(m => m.rescatType === 'center')).addTo(map);
  const pickupLayer = L.layerGroup(markers.filter(m => m.rescatType === 'pickup_point')).addTo(map);
  if (bounds.length) {
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
    if (bounds.length === 1) map.setZoom(14);
  }

  const status = document.getElementById('mapStatus');
  if (status) {
    status.textContent = places.length
      ? 'Se muestran ' + places.length + ' ubicaciones. Selecciona un marcador para consultar sus datos.'
      : 'Aún no hay ubicaciones con coordenadas. El administrador puede agregarlas desde Administrar centros.';
  }

  document.getElementById('showCenters').addEventListener('change', event => {
    if (event.target.checked) map.addLayer(centerLayer); else map.removeLayer(centerLayer);
  });
  document.getElementById('showPickupPoints').addEventListener('change', event => {
    if (event.target.checked) map.addLayer(pickupLayer); else map.removeLayer(pickupLayer);
  });
  window.setTimeout(() => map.invalidateSize(), 150);
})();
</script>

<style>
.places-intro{margin:10px 0 25px}.places-intro h1{margin:8px 0;color:var(--navy);font-size:clamp(1.8rem,4vw,2.6rem)}.places-intro p{color:var(--muted);max-width:760px}
.places-map-section{background:#fff;border:1px solid var(--line);border-radius:20px;padding:22px;box-shadow:0 10px 28px rgba(20,42,67,.045)}
.places-map-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:15px}.places-map-heading h2{margin:0;color:var(--navy)}.places-map-heading p{margin:4px 0}
.rescat-leaflet-map{width:100%;height:440px;border-radius:15px;background:#edf2f2;overflow:hidden;z-index:1}
.map-legend{display:flex;flex-wrap:wrap;gap:12px;font-size:.82rem;color:var(--muted)}.map-legend span{display:flex;align-items:center;gap:6px;white-space:nowrap}
.map-dot{width:11px;height:11px;border-radius:50%;display:inline-block}.center-dot{background:#087f78}.pickup-dot{background:#e69a35}
.map-status{margin:10px 0 0;color:var(--muted);font-size:.88rem}.map-filter-row{display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-top:12px;font-size:.9rem}.map-filter-row label{display:flex;align-items:center;gap:7px}
.map-attribution{margin:8px 0 0;text-align:right;font-size:.72rem;color:var(--muted)}.map-attribution a{color:inherit}
.rescat-map-marker-wrap{background:transparent;border:0}.rescat-map-marker{display:block;width:18px;height:18px;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35)}.rescat-center-marker{background:#087f78}.rescat-pickup-marker{background:#e69a35}
.rescat-map-popup{min-width:190px;max-width:260px;font-family:Arial,sans-serif}.rescat-map-popup strong{display:block;color:#142a43;font-size:15px}.rescat-map-popup p{margin:6px 0;color:#536273}.rescat-map-popup .popup-kind{font-weight:700;color:#087f78}.rescat-map-popup a{color:#087f78;font-weight:700}
.places-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.place-public-card .card-body{height:100%}.place-public-card .btn{margin-top:8px}
@media(max-width:760px){.places-map-heading{flex-direction:column}.rescat-leaflet-map{height:360px}.places-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:540px){.places-cards{grid-template-columns:1fr}.rescat-leaflet-map{height:320px}.places-map-section{padding:14px}}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
