<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_admin();
if (!is_super_admin()) { http_response_code(403); exit('Esta sección es exclusiva del Super Admin.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM centers WHERE id=?');
            $stmt->execute([$id]);
            flash('Registro eliminado correctamente.');
        }
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['center_type'] ?? 'center';
        $address = trim($_POST['address'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $latitudeRaw = trim($_POST['latitude'] ?? '');
        $longitudeRaw = trim($_POST['longitude'] ?? '');
        $latitude = $latitudeRaw === '' ? null : filter_var($latitudeRaw, FILTER_VALIDATE_FLOAT);
        $longitude = $longitudeRaw === '' ? null : filter_var($longitudeRaw, FILTER_VALIDATE_FLOAT);
        // Los puntos de recolección solo requieren nombre y dirección.
        $contact = $type === 'center' ? trim($_POST['contact_person'] ?? '') : '';
        $email = $type === 'center' ? trim($_POST['email'] ?? '') : '';
        $phone = $type === 'center' ? $phone : '';
        $hours = null;
        if ($name === '' || $address === '' || !in_array($type, ['center','pickup_point'], true)) {
            flash('Nombre, tipo y dirección son obligatorios.', 'error');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Escribe un correo electrónico válido.', 'error');
        } elseif (($latitudeRaw !== '' && $latitude === false) || ($longitudeRaw !== '' && $longitude === false) ||
                  (($latitude === null) !== ($longitude === null)) ||
                  ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180))) {
            flash('Las coordenadas deben ser válidas. Captura latitud y longitud juntas.', 'error');
        } else {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE centers SET name=?,center_type=?,description=?,address=?,phone=?,website=?,contact_person=?,email=?,opening_hours=?,latitude=?,longitude=?,active=1 WHERE id=?');
                $stmt->execute([$name,$type,$description,$address,$phone ?: null,$website ?: null,$contact ?: null,$email ?: null,$hours ?: null,$latitude,$longitude,$id]);
                flash('Registro actualizado correctamente.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO centers(name,center_type,description,address,phone,website,contact_person,email,opening_hours,latitude,longitude,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,1)');
                $stmt->execute([$name,$type,$description,$address,$phone ?: null,$website ?: null,$contact ?: null,$email ?: null,$hours ?: null,$latitude,$longitude]);
                flash($type === 'center' ? 'Centro de donaciones registrado.' : 'Punto de recolección registrado.');
            }
        }
    }
    header('Location: administrar_centros.php'); exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM centers WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}
$centers = $pdo->query("SELECT * FROM centers WHERE center_type='center' ORDER BY name")->fetchAll();
$points = $pdo->query("SELECT * FROM centers WHERE center_type='pickup_point' ORDER BY name")->fetchAll();
$pageTitle = 'Administrar centros y puntos';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<div class="dashboard-shell admin-page-shell">
<?php $adminActive='centers'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="dashboard-main admin-content-main">
<section class="admin-heading"><div><span class="eyebrow">SUPER ADMIN</span><h1>Centros y puntos de recolección</h1><p class="muted">Administra por separado los centros que reciben donaciones y los puntos donde se recolectan.</p></div></section>
<section class="admin-card center-form-card">
  <div class="center-form-heading"><div><h2><?= $edit ? 'Editar registro' : 'Registrar nuevo lugar' ?></h2><p class="muted">El formulario cambia según el tipo de lugar seleccionado.</p></div></div>
  <form method="post" class="form-grid" id="placeForm">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
    <div class="field"><label for="center_type">Tipo de registro</label><select id="center_type" name="center_type"><option value="center" <?=($edit['center_type']??'center')==='center'?'selected':''?>>Centro de donaciones</option><option value="pickup_point" <?=($edit['center_type']??'')==='pickup_point'?'selected':''?>>Punto de recolección</option></select></div>
    <div class="field"><label for="name">Nombre</label><input id="name" name="name" maxlength="160" required value="<?=e($edit['name']??'')?>" placeholder="Nombre del lugar"></div>
    <div class="field full"><label for="address">Dirección completa</label><input id="address" name="address" maxlength="255" required value="<?=e($edit['address']??'')?>" placeholder="Calle, número, colonia y municipio"></div>
    <div class="field"><label for="latitude">Latitud</label><input id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="<?=e($edit['latitude']??'')?>" placeholder="Ej. 25.6732100"></div>
    <div class="field"><label for="longitude">Longitud</label><input id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="<?=e($edit['longitude']??'')?>" placeholder="Ej. -100.4589000"></div>
    <div class="field full coordinate-help">
      <p class="muted">Agrega ambas coordenadas para que el lugar aparezca en el mapa público. Puedes escribirlas o seleccionar el punto directamente en el mapa.</p>
      <div id="adminPlacePicker" class="admin-place-picker" role="region" aria-label="Selector de ubicación"></div>
      <small class="muted">Haz clic en el mapa para colocar el marcador o arrástralo para ajustar la ubicación. La latitud y longitud se llenarán automáticamente. También puedes escribir las coordenadas manualmente.</small>
    </div>
    <div class="field center-only"><label for="contact_person">Persona responsable</label><input id="contact_person" name="contact_person" maxlength="120" value="<?=e($edit['contact_person']??'')?>" placeholder="Nombre del responsable"></div>
    <div class="field center-only"><label for="phone">Teléfono de contacto</label><input id="phone" name="phone" maxlength="60" value="<?=e($edit['phone']??'')?>" placeholder="Teléfono"></div>
    <div class="field center-only"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" maxlength="150" value="<?=e($edit['email']??'')?>" placeholder="contacto@ejemplo.com"></div>
    <div class="field center-only" data-field="website"><label for="website">Sitio web</label><input id="website" type="url" name="website" maxlength="255" value="<?=e($edit['website']??'')?>" placeholder="https://"></div>
    <div class="field full center-only"><label for="description">Descripción del centro</label><textarea id="description" name="description" rows="3" placeholder="Servicios, capacidad de recepción y descripción general"><?=e($edit['description']??'')?></textarea></div>
    <div class="field full center-form-actions"><button class="btn teal" type="submit"><?= $edit ? 'Guardar cambios' : 'Registrar lugar' ?></button><?php if($edit): ?><a class="btn light" href="administrar_centros.php">Cancelar</a><?php endif; ?></div>
  </form>
</section>
<section class="admin-card place-list-card"><div class="place-list-heading"><div><h2>Centros de donaciones</h2><p class="muted">Lugares que reciben y gestionan alimentos donados.</p></div><span class="place-count"><?=count($centers)?> registrados</span></div>
<div class="table-wrap"><table class="admin-data-table"><thead><tr><th>Nombre</th><th>Dirección</th><th>Coordenadas</th><th>Responsable y contacto</th><th>Sitio web</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($centers as $c): ?><tr><td data-label="Nombre"><b><?=e($c['name'])?></b><small class="place-type-label">Centro de donaciones</small></td><td data-label="Dirección"><?=e($c['address'])?></td><td data-label="Coordenadas"><?=($c['latitude']!==null && $c['longitude']!==null)?e($c['latitude']).', '.e($c['longitude']):'Pendientes'?></td><td data-label="Responsable y contacto"><?=e($c['contact_person']?:'Sin responsable')?><small><?=e($c['phone']?:'Sin teléfono')?><?=!empty($c['email'])?' · '.e($c['email']):''?></small></td><td data-label="Sitio web"><?=!empty($c['website'])?'<a href="'.e($c['website']).'" target="_blank" rel="noopener">Visitar sitio</a>':'—'?></td><td data-label="Acciones"><div class="place-actions"><a class="btn light" href="?edit=<?=(int)$c['id']?>">Editar</a><form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar este centro?');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="btn danger" type="submit">Eliminar</button></form></div></td></tr><?php endforeach; ?>
<?php if(!$centers): ?><tr><td colspan="6">Todavía no hay centros de donaciones registrados.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="admin-card place-list-card"><div class="place-list-heading"><div><h2>Puntos de recolección</h2><p class="muted">Ubicaciones y horarios donde se reciben o recogen alimentos.</p></div><span class="place-count"><?=count($points)?> registrados</span></div>
<div class="table-wrap"><table class="admin-data-table"><thead><tr><th>Nombre</th><th>Dirección</th><th>Coordenadas</th><th>Horario</th><th>Responsable y contacto</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($points as $c): ?><tr><td data-label="Nombre"><b><?=e($c['name'])?></b><small class="place-type-label">Punto de recolección</small></td><td data-label="Dirección"><?=e($c['address'])?></td><td data-label="Coordenadas"><?=($c['latitude']!==null && $c['longitude']!==null)?e($c['latitude']).', '.e($c['longitude']):'Pendientes'?></td><td data-label="Horario"><?=e($c['opening_hours']?:'Horario por confirmar')?></td><td data-label="Responsable y contacto"><?=e($c['contact_person']?:'Sin responsable')?><small><?=e($c['phone']?:'Sin teléfono')?><?=!empty($c['email'])?' · '.e($c['email']):''?></small></td><td data-label="Acciones"><div class="place-actions"><a class="btn light" href="?edit=<?=(int)$c['id']?>">Editar</a><form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar este punto de recolección?');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="btn danger" type="submit">Eliminar</button></form></div></td></tr><?php endforeach; ?>
<?php if(!$points): ?><tr><td colspan="6">Todavía no hay puntos de recolección registrados.</td></tr><?php endif; ?></tbody></table></div></section>
</main></div>
<script>
(function(){
 const type=document.getElementById('center_type');
 const centerFields=document.querySelectorAll('.center-only');
 function updatePlaceForm(){
   const isCenter=type.value==='center';
   centerFields.forEach(field=>field.hidden=!isCenter);
 }
 type.addEventListener('change',updatePlaceForm); updatePlaceForm();
})();
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
  const el = document.getElementById('adminPlacePicker');
  if (!el || typeof L === 'undefined') return;

  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  const fallback = [25.6732, -100.4589];
  const initialLat = parseFloat(latInput.value);
  const initialLng = parseFloat(lngInput.value);
  const hasCoordinates = Number.isFinite(initialLat) && Number.isFinite(initialLng);
  const start = hasCoordinates ? [initialLat, initialLng] : fallback;

  const map = L.map(el).setView(start, hasCoordinates ? 15 : 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
  }).addTo(map);

  const placeIcon = L.divIcon({
    className: 'rescat-map-marker-wrap',
    html: '<span class="rescat-map-marker rescat-center-marker"></span>',
    iconSize: [24, 24], iconAnchor: [12, 12]
  });

  let marker = null;
  const updateCoordinates = latlng => {
    latInput.value = Number(latlng.lat).toFixed(7);
    lngInput.value = Number(latlng.lng).toFixed(7);
    if (!marker) {
      marker = L.marker(latlng, {draggable: true, icon: placeIcon}).addTo(map);
      marker.on('dragend', event => updateCoordinates(event.target.getLatLng()));
    } else {
      marker.setLatLng(latlng);
    }
  };

  if (hasCoordinates) {
    marker = L.marker(start, {draggable: true, icon: placeIcon}).addTo(map);
    marker.on('dragend', event => updateCoordinates(event.target.getLatLng()));
  }

  map.on('click', event => updateCoordinates(event.latlng));
  [latInput, lngInput].forEach(input => input.addEventListener('change', () => {
    const lat = parseFloat(latInput.value);
    const lng = parseFloat(lngInput.value);
    if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
      const point = [lat, lng];
      if (!marker) {
        marker = L.marker(point, {draggable: true, icon: placeIcon}).addTo(map);
        marker.on('dragend', event => updateCoordinates(event.target.getLatLng()));
      } else marker.setLatLng(point);
      map.setView(point, 15);
    }
  }));
  window.setTimeout(() => map.invalidateSize(), 150);
})();
</script>
<style>
.center-form-card,.place-list-card{margin-bottom:22px}.admin-place-picker{height:300px;width:100%;border-radius:14px;overflow:hidden;background:#edf2f2;margin:10px 0}.coordinate-help{padding:12px 0}.coordinate-help p{margin:0 0 8px}.center-form-heading,.place-list-heading{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:16px}.center-form-heading h2,.place-list-heading h2{margin:0 0 5px}.center-form-heading p,.place-list-heading p{margin:0}.place-count{white-space:nowrap;border-radius:999px;background:#eaf3ed;color:#205b48;padding:8px 12px;font-size:.82rem;font-weight:700}.center-form-actions,.place-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.place-actions .inline-form{display:inline-flex;margin:0}.place-type-label{display:block;margin-top:5px;color:#648075;font-size:.78rem}.admin-data-table td small{display:block;margin-top:5px;overflow-wrap:anywhere}.admin-data-table td{overflow-wrap:anywhere}.field[hidden]{display:none!important}.rescat-map-marker-wrap{background:transparent;border:0}.rescat-map-marker{display:block;width:18px;height:18px;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35)}.rescat-center-marker{background:#087f78}@media(max-width:700px){.center-form-heading,.place-list-heading{align-items:flex-start;flex-direction:column}.place-actions{align-items:stretch}.place-actions .btn{width:auto}}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
