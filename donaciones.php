<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pageTitle = 'Mis donaciones';
$stmt = $pdo->prepare('SELECT * FROM donations WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$donations = $stmt->fetchAll();
require __DIR__ . '/includes/header.php';
$isAdminView = in_array(($_SESSION['role'] ?? 'user'), ['admin','super_admin'], true);
?>

<div class="section-head"><div><span class="eyebrow">Tu espacio personal</span><h1>Mis donaciones</h1><p>Solo tú puedes ver las donaciones que publicaste. El inventario completo es exclusivo del administrador.</p></div><a class="btn teal" href="publicar_donacion.php">+ Publicar donación</a></div>
<?php if (isset($_GET['published'])): ?><div class="notice success">Tu donación se publicó correctamente.</div><?php endif; ?>
<?php if (!$donations): ?><div class="empty"><h2>Aún no has publicado donaciones</h2><p>Cuando publiques alimentos, aparecerán aquí y solo estarán asociados a tu cuenta.</p><a class="btn teal" href="publicar_donacion.php">Publicar mi primera donación</a></div><?php else: ?>
<div class="grid"><?php foreach ($donations as $d): ?><article class="card">
<img class="donation-photo" src="<?= e(donation_image_src($d['image_path'] ?? null, $d['food_name'] ?? '', $d['category'] ?? '')) ?>" alt="Imagen de <?= e($d['food_name']) ?>" loading="lazy">
<div class="card-body"><span class="tag"><?= e($d['category'] ?? 'Otros') ?></span><h3><?= e($d['food_name']) ?></h3><p><b>Cantidad:</b> <?= e($d['quantity']) ?></p><p class="muted"><b>Punto de entrega:</b> <?= e($d['location']) ?></p><?php if (!empty($d['contact_phone'])): ?><p class="muted"><b>Teléfono:</b> <?= e($d['contact_phone']) ?></p><?php endif; ?><?php if (!empty($d['delivery_date'])): ?><p class="muted"><b>Entrega programada:</b> <?= e(date('d/m/Y', strtotime($d['delivery_date']))) ?></p><?php endif; ?><?php if (!empty($d['expiry_date'])): ?><p class="muted">Consumir preferentemente antes de: <?= e($d['expiry_date']) ?></p><?php endif; ?><?php if (!empty($d['latitude']) && !empty($d['longitude'])): ?><p><a target="_blank" rel="noopener noreferrer" href="<?=e('https://www.google.com/maps?q='.$d['latitude'].','.$d['longitude'])?>">Ver ubicación compartida</a></p><?php endif; ?>
<?php $attStmt=$pdo->prepare('SELECT file_path,original_name,mime_type FROM donation_attachments WHERE donation_id=?'); $attStmt->execute([(int)$d['id']]); $attachments=$attStmt->fetchAll(); ?>
<?php if ($attachments): ?><p><b>Archivos adjuntos:</b></p><ul><?php foreach ($attachments as $att): ?><li><a href="<?=e($att['file_path'])?>" target="_blank" rel="noopener noreferrer"><?=e($att['original_name'])?></a></li><?php endforeach; ?></ul><?php endif; ?><p class="muted"><?= e($d['notes'] ?? '') ?></p><p><b>Estado:</b> <?= e(['available'=>'Disponible','in_process'=>'En proceso','picked_up'=>'Pedido recogido','delivered'=>'Entregado','completed'=>'Completado','cancelled'=>'Cancelado'][$d['status']] ?? $d['status']) ?></p><small class="small">Publicada: <?= e($d['created_at']) ?></small></div></article><?php endforeach; ?></div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
