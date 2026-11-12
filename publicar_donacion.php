<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pageTitle = 'Publicar donación';
$error = '';
$categories = ['Verduras','Frutas','Pan y panadería','Enlatados y conservas','Granos y cereales','Lácteos','Alimentos empacados','Comida preparada','Otros'];
$locations = $pdo->query("SELECT id,name FROM centers WHERE active=1 AND center_type='pickup_point' ORDER BY name")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $food = trim($_POST['food_name'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $pickupPointId = filter_input(INPUT_POST, 'pickup_point_id', FILTER_VALIDATE_INT) ?: 0;
    $location = '';
    if ($pickupPointId) {
        $locationStmt = $pdo->prepare("SELECT name FROM centers WHERE id=? AND active=1 AND center_type='pickup_point'");
        $locationStmt->execute([$pickupPointId]);
        $location = (string)($locationStmt->fetchColumn() ?: '');
    }
    $phone = trim($_POST['contact_phone'] ?? '');
    $delivery = $_POST['delivery_date'] ?? '';
    $expiry = $_POST['expiry_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $imagePath = null;
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
    if ($latitude === false || $latitude < -90 || $latitude > 90) $latitude = null;
    if ($longitude === false || $longitude < -180 || $longitude > 180) $longitude = null;
    $uploadedAttachments = [];
    if ($food === '' || $quantity === '' || $category === '' || $location === '' || $phone === '' || $delivery === '') $error = 'Completa los datos del alimento, teléfono y fecha de entrega.';
    elseif (!preg_match('/^[0-9+()\s-]{8,25}$/', $phone)) $error = 'Escribe un número de teléfono válido.';
    elseif ($delivery < date('Y-m-d')) $error = 'La fecha de entrega no puede estar en el pasado.';
    elseif (!in_array($category, $categories, true)) $error = 'Selecciona una categoría válida.';
    elseif ($location === '') $error = 'Selecciona un punto de entrega autorizado vigente.';
    elseif ($expiry !== '' && $expiry < date('Y-m-d')) $error = 'La fecha de caducidad no puede estar en el pasado.';
    else {
        $files = $_FILES['attachments'] ?? null;
        if ($files && is_array($files['name'])) {
            $count = count(array_filter($files['name'], fn($n) => $n !== ''));
            if ($count > 8) $error = 'Puedes adjuntar máximo 8 archivos.';
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
            $uploadDir = __DIR__ . '/uploads/donaciones';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            for ($i=0; $i<count($files['name']) && $error===''; $i++) {
                if ($files['name'][$i] === '') continue;
                if ($files['error'][$i] !== UPLOAD_ERR_OK) { $error = 'Uno de los archivos no se pudo subir.'; break; }
                if ($files['size'][$i] > 10 * 1024 * 1024) { $error = 'Cada archivo debe pesar máximo 10 MB.'; break; }
                $mime = $finfo->file($files['tmp_name'][$i]);
                if (!isset($allowed[$mime])) { $error = 'Solo se permiten imágenes JPG, PNG, WEBP y documentos PDF.'; break; }
                $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($files['tmp_name'][$i], $uploadDir . '/' . $filename)) { $error = 'No se pudo guardar un archivo adjunto.'; break; }
                $path = 'uploads/donaciones/' . $filename;
                $uploadedAttachments[] = ['path'=>$path, 'name'=>basename($files['name'][$i]), 'mime'=>$mime];
                if ($mime !== 'application/pdf' && $imagePath === null) $imagePath = $path;
            }
        }
    }
    if ($error === '') {
        $stmt = $pdo->prepare('INSERT INTO donations (user_id,food_name,quantity,category,image_path,location,contact_phone,delivery_date,pickup_point_id,center_id,expiry_date,notes,status,latitude,longitude) VALUES (?,?,?,?,?,?,?,?,?,NULL,?,?,?,?,?)');
        $stmt->execute([$_SESSION['user_id'],$food,$quantity,$category,$imagePath,$location,$phone,$delivery,$pickupPointId,$expiry ?: null,$notes ?: null,'available',$latitude,$longitude]);
        $donationId = (int)$pdo->lastInsertId();
        if ($uploadedAttachments) {
            $attachmentStmt = $pdo->prepare('INSERT INTO donation_attachments (donation_id,file_path,original_name,mime_type) VALUES (?,?,?,?)');
            foreach ($uploadedAttachments as $attachment) $attachmentStmt->execute([$donationId,$attachment['path'],$attachment['name'],$attachment['mime']]);
        }
        notify_admins($pdo, 'Nueva donación recibida', 'Se publicó una nueva donación: ' . $food . '.', 'inventario_donaciones.php', 'donation');
        header('Location: donaciones.php?published=1'); exit;
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="panel wide-panel"><span class="eyebrow">Compartir ayuda</span><h1>Publicar alimentos para donar</h1><p class="muted">RescatAlimentos no vende productos. Publica alimentos en buen estado para que puedan aprovecharse y reducir el desperdicio.</p>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="form-grid">
<div class="field"><label for="food_name">Nombre del alimento</label><input id="food_name" name="food_name" maxlength="120" required value="<?= e($_POST['food_name'] ?? '') ?>" placeholder="Ej. Zanahorias frescas"></div>
<div class="field"><label for="category">Clasificación del alimento</label><select id="category" name="category" required><option value="">Elige una categoría</option><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="quantity">Cantidad</label><input id="quantity" name="quantity" maxlength="80" required value="<?= e($_POST['quantity'] ?? '') ?>" placeholder="Ej. 3 kg o 10 piezas"></div>
<div class="field"><label for="location">Punto de entrega autorizado</label><select id="location" name="pickup_point_id" required><option value="">Selecciona un punto de recolección</option><?php foreach ($locations as $loc): ?><option value="<?=(int)$loc['id']?>" <?=((int)($_POST['pickup_point_id']??0)===(int)$loc['id'])?'selected':''?>><?=e($loc['name'])?></option><?php endforeach; ?></select><?php if (!$locations): ?><small class="small">No hay puntos de recolección activos. Solicita al administrador que registre uno.</small><?php else: ?><small class="small">Solo puedes elegir lugares registrados y autorizados.</small><?php endif; ?></div>
<div class="field full"><label for="attachments">Fotografías y documentos (opcional)</label><input class="file-input" id="attachments" name="attachments[]" type="file" accept="image/*,application/pdf" capture="environment" multiple><small class="small">Puedes tomar una foto con tu celular o adjuntar imágenes y PDF. Máximo 8 archivos de 10 MB cada uno.</small><div id="attachment-preview" class="attachment-preview" aria-live="polite"></div></div>
<div class="field full geo-panel"><label>Ubicación para facilitar la entrega (opcional)</label><p class="small">Comparte tu ubicación actual. Solo se guardará si presionas el botón y envías la donación.</p><input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude"><button class="btn light" type="button" id="share-location">Compartir mi ubicación</button><span id="location-status" class="small" role="status">No has compartido tu ubicación.</span></div>
<div class="field"><label for="contact_phone">Teléfono de contacto</label><input id="contact_phone" name="contact_phone" type="tel" maxlength="25" required value="<?= e($_POST['contact_phone'] ?? '') ?>" placeholder="Ej. 81 1234 5678"></div>
<div class="field"><label for="delivery_date">Día programado para entregar</label><input id="delivery_date" name="delivery_date" type="date" min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['delivery_date'] ?? '') ?>"></div>
<div class="field"><label for="expiry_date">Caducidad o consumo preferente</label><input id="expiry_date" name="expiry_date" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['expiry_date'] ?? '') ?>"></div>
<div class="field full"><label for="notes">Detalles adicionales</label><textarea id="notes" name="notes" maxlength="2000" placeholder="Estado del alimento, empaque, conservación y horario sugerido..."><?= e($_POST['notes'] ?? '') ?></textarea></div>
</div><button class="btn teal" type="submit">Publicar donación</button></form></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
