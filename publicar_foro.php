<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $type = $_POST['post_type'] ?? 'community';
    $imagePath = null;
    if (!in_array($type, ['community','delivered'], true)) $type = 'community';
    if ($type === 'delivered' && !in_array(($_SESSION['role'] ?? 'user'), ['admin','super_admin'], true)) $error = 'Solo el administrador puede marcar una entrega como realizada.';
    elseif ($title === '' || $body === '') $error = 'Completa el título y el mensaje.';
    elseif (!empty($_FILES['post_image']['name'])) {
        $file = $_FILES['post_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) $error = 'No se pudo subir la imagen. Inténtalo de nuevo.';
        elseif ($file['size'] > 5 * 1024 * 1024) $error = 'La imagen debe pesar máximo 5 MB.';
        else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($allowed[$mime])) $error = 'La imagen debe ser JPG, PNG o WEBP.';
            else {
                $dir = __DIR__ . '/uploads/foro';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) $error = 'No se pudo guardar la imagen.';
                else $imagePath = 'uploads/foro/' . $filename;
            }
        }
    }
    if ($error === '') {
        $stmt = $pdo->prepare('INSERT INTO forum_posts(user_id,title,body,image_path,post_type) VALUES(?,?,?,?,?)');
        $stmt->execute([$_SESSION['user_id'], $title, $body, $imagePath, $type]);
        $postId = (int)$pdo->lastInsertId();
        $recipients = $pdo->prepare('SELECT id FROM users WHERE id <> ?');
        $recipients->execute([$_SESSION['user_id']]);
        foreach ($recipients->fetchAll(PDO::FETCH_COLUMN) as $recipientId) {
            create_notification($pdo, (int)$recipientId, 'Nueva publicación en el foro', 'Hay una nueva publicación: ' . $title . '.', 'foro.php#publicacion-' . $postId, 'forum');
        }
        flash('Publicación creada correctamente.');
        header('Location: foro.php'); exit;
    }
}
$pageTitle = 'Crear publicación';
require __DIR__ . '/includes/header.php';
?>
<section class="panel wide-panel form-elevated"><span class="eyebrow">Comunidad RescatAlimentos</span><h1>Crear publicación</h1><p class="muted">Comparte novedades, avances o fotografías relacionadas con las donaciones.</p>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="field"><label for="title">Título</label><input id="title" name="title" maxlength="160" required value="<?= e($_POST['title'] ?? '') ?>" placeholder="Escribe un título para tu publicación"></div>
<div class="field"><label for="body">Mensaje</label><textarea id="body" name="body" maxlength="5000" required placeholder="Describe la actualización..."><?= e($_POST['body'] ?? '') ?></textarea></div>
<div class="form-grid"><div class="field"><label for="post_image">Adjuntar imagen (opcional)</label><input class="file-input" id="post_image" name="post_image" type="file" accept="image/jpeg,image/png,image/webp"><small class="small">JPG, PNG o WEBP · máximo 5 MB.</small></div>
<div class="field"><label for="post_type">Tipo de publicación</label><select id="post_type" name="post_type"><option value="community">Actualización</option><?php if (in_array(($_SESSION['role'] ?? 'user'), ['admin','super_admin'], true)): ?><option value="delivered">Donación entregada</option><?php endif; ?></select></div></div>
<button class="btn teal" type="submit">Publicar en el foro</button></form></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
