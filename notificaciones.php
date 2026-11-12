<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$userId = (int)$_SESSION['user_id'];

if (isset($_GET['read']) && ctype_digit((string)$_GET['read'])) {
    $mark = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');
    $mark->execute([(int)$_GET['read'], $userId]);
    $allowed = ['index.php','foro.php','mis_pedidos.php','inventario_donaciones.php','admin.php','asignar_centros.php'];
    $target = basename((string)($_GET['to'] ?? 'notificaciones.php'));
    if (!in_array($target, $allowed, true)) $target = 'notificaciones.php';
    header('Location: ' . $target); exit;
}
if (isset($_POST['mark_all'])) {
    verify_csrf();
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$userId]);
    flash('Todas las notificaciones se marcaron como leídas.');
    header('Location: notificaciones.php'); exit;
}
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 100');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
$unreadCount = count(array_filter($notifications, fn($n) => empty($n['is_read'])));
$pageTitle = 'Notificaciones';
$isAdmin = in_array(($_SESSION['role'] ?? 'user'), ['admin','super_admin'], true);
require __DIR__ . '/includes/header.php';
if ($isAdmin) { ?>
<div class="dashboard-shell admin-page-shell">
  <?php $adminActive='notifications'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <div class="dashboard-main admin-content-main">
    <section class="admin-heading notifications-admin-heading">
      <div><span class="eyebrow">RESCATALIMENTOS · CENTRO DE AVISOS</span><h1>Notificaciones</h1><p class="muted">Mantente al tanto de nuevas donaciones, publicaciones del foro y movimientos de la comunidad.</p></div>
      <div class="notification-total"><?=count($notifications)?> avisos</div>
    </section>
    <section class="notifications-page notifications-admin-page">
      <?php if ($notifications): ?>
        <div class="notifications-toolbar"><span><?= $unreadCount ?> sin leer</span><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="btn light" type="submit" name="mark_all" value="1" <?= $unreadCount===0?'disabled':'' ?>>Marcar todas como leídas</button></form></div>
        <div class="notification-list">
          <?php foreach ($notifications as $n):
            $target = basename((string)(parse_url($n['url'] ?? '', PHP_URL_PATH) ?: 'notificaciones.php'));
            $allowed = ['index.php','foro.php','mis_pedidos.php','inventario_donaciones.php','admin.php','asignar_centros.php'];
            if (!in_array($target, $allowed, true)) $target='notificaciones.php';
            $date = strtotime($n['created_at'] ?? '');
          ?>
          <article class="notification-item <?=empty($n['is_read'])?'unread':'read'?>">
            <span class="notification-dot" aria-hidden="true"></span>
            <div class="notification-content"><div class="notification-title-row"><strong><?=e($n['title'])?></strong><span class="notification-state"><?=empty($n['is_read'])?'Nueva':'Leída'?></span></div><p><?=e($n['message'])?></p><small><?= $date ? e(date('d/m/Y · H:i', $date)) : 'Fecha no disponible' ?></small></div>
            <a class="btn light notification-action" href="notificaciones.php?read=<?=(int)$n['id']?>&amp;to=<?=e($target)?>">Ver detalle</a>
          </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="notifications-empty"><span aria-hidden="true">♧</span><strong>Aún no tienes notificaciones</strong><p>Cuando llegue una nueva donación o se publique un foro, aparecerá aquí.</p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php } else { ?>
<section class="panel wide-panel notifications-page">
  <div class="notifications-heading"><div><span class="eyebrow">ACTUALIZACIONES</span><h1>Notificaciones</h1><p class="muted">Consulta los avisos sobre donaciones, entregas y publicaciones de la comunidad.</p></div><span class="notification-total"><?=count($notifications)?> avisos</span></div>
  <?php if ($notifications): ?>
    <div class="notifications-toolbar"><span><?= $unreadCount ?> sin leer</span><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="btn light" type="submit" name="mark_all" value="1" <?= $unreadCount===0?'disabled':'' ?>>Marcar todas como leídas</button></form></div>
    <div class="notification-list">
      <?php foreach ($notifications as $n):
        $target = basename((string)(parse_url($n['url'] ?? '', PHP_URL_PATH) ?: 'notificaciones.php'));
        $allowed = ['index.php','foro.php','mis_pedidos.php','inventario_donaciones.php','admin.php','asignar_centros.php'];
        if (!in_array($target, $allowed, true)) $target='notificaciones.php';
        $date = strtotime($n['created_at'] ?? '');
      ?>
      <article class="notification-item <?=empty($n['is_read'])?'unread':'read'?>"><span class="notification-dot" aria-hidden="true"></span><div class="notification-content"><div class="notification-title-row"><strong><?=e($n['title'])?></strong><span class="notification-state"><?=empty($n['is_read'])?'Nueva':'Leída'?></span></div><p><?=e($n['message'])?></p><small><?= $date ? e(date('d/m/Y · H:i', $date)) : 'Fecha no disponible' ?></small></div><a class="btn light notification-action" href="notificaciones.php?read=<?=(int)$n['id']?>&amp;to=<?=e($target)?>">Ver detalle</a></article>
      <?php endforeach; ?>
    </div>
  <?php else: ?><div class="notifications-empty"><span aria-hidden="true">♧</span><strong>Aún no tienes notificaciones</strong><p>Cuando haya novedades sobre tus donaciones o el foro, aparecerán aquí.</p></div><?php endif; ?>
</section>
<?php }
require __DIR__ . '/includes/footer.php';
