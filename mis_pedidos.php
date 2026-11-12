<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login(); $pageTitle = 'Mi cuenta';
$stmt = $pdo->prepare('SELECT id,food_name,quantity,status,created_at FROM donations WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]); $mine = $stmt->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="panel"><span class="eyebrow">Mi cuenta</span><h1>Hola, <?= e($_SESSION['user_name'] ?? 'usuario') ?> 👋</h1><p class="muted">Desde aquí puedes revisar únicamente las donaciones que has publicado.</p><div class="actions"><a class="btn teal" href="publicar_donacion.php">Publicar donación</a><a class="btn light" href="donaciones.php">Ver mis donaciones</a></div></section>
<div class="section-head"><div><h2>Mis donaciones</h2><p>Historial de publicaciones de tu cuenta.</p></div></div>
<?php if (!$mine): ?><div class="empty">Todavía no has publicado donaciones.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Alimento</th><th>Cantidad</th><th>Estado</th><th>Fecha</th></tr></thead><tbody><?php foreach($mine as $d): ?><tr><td><?= e($d['food_name']) ?></td><td><?= e($d['quantity']) ?></td><td><?= e(['available'=>'Disponible','in_process'=>'En proceso','picked_up'=>'Recogida','delivered'=>'Entregada','completed'=>'Completada','cancelled'=>'Cancelada'][$d['status']] ?? $d['status']) ?></td><td><?= e($d['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
