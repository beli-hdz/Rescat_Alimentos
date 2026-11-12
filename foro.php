<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$posts = $pdo->query("SELECT f.*,u.username,u.role FROM forum_posts f JOIN users u ON u.id=f.user_id ORDER BY f.created_at DESC")->fetchAll();
$replyStmt = $pdo->prepare("SELECT r.*,u.username,u.role FROM forum_replies r JOIN users u ON u.id=r.user_id WHERE r.post_id=? ORDER BY r.created_at ASC");
$pageTitle = 'Foro de entregas'; require __DIR__ . '/includes/header.php';
$isAdminView = in_array(($_SESSION['role'] ?? 'user'), ['admin','super_admin'], true);
$types = ['community'=>'Actualización','status'=>'Seguimiento','delivered'=>'Donación entregada'];
?>
<section class="forum-heading"><span class="eyebrow">Comunidad RescatAlimentos</span><h1>Foro de entregas y novedades</h1><p>Comparte avances, responde a otros usuarios y coordina entregas.</p><?php if (logged_in()): ?><a class="btn teal" href="publicar_foro.php">Crear publicación</a><?php endif; ?></section>
<?php if (!$posts): ?><div class="empty">Aún no hay publicaciones. ¡Sé la primera persona en compartir una actualización!</div><?php endif; ?>
<div class="forum-list"><?php foreach ($posts as $p): $replyStmt->execute([(int)$p['id']]); $replies=$replyStmt->fetchAll(); ?>
<article class="card forum-post" id="publicacion-<?= (int)$p['id'] ?>"><div class="card-body">
<span class="tag"><?= e($types[$p['post_type']] ?? 'Actualización') ?></span><h2><?= e($p['title']) ?></h2><p><?= nl2br(e($p['body'])) ?></p>
<?php if (!empty($p['image_path']) && is_file(__DIR__ . '/' . $p['image_path'])): ?><img class="forum-image" src="<?= e($p['image_path']) ?>" alt="Imagen adjunta a la publicación"><?php endif; ?>
<small class="small">Publicado por <?= e($p['username']) ?> · <?= e($p['created_at']) ?></small>
<section class="forum-replies"><h3>Respuestas (<?= count($replies) ?>)</h3>
<?php foreach($replies as $r): ?><div class="forum-reply <?= in_array($r['role'],['admin','super_admin'],true)?'admin-reply':'' ?>">
<strong><?= in_array($r['role'],['admin','super_admin'],true)?'RescatAlimentos respondió':e($r['username']) ?></strong>
<p><?= nl2br(e($r['body'])) ?></p><small class="small"><?=e($r['created_at'])?></small></div><?php endforeach; ?>
<?php if(logged_in()): ?><form method="post" action="responder_foro.php" class="forum-reply-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="post_id" value="<?=(int)$p['id']?>"><label for="reply-<?= (int)$p['id'] ?>">Escribe una respuesta</label><textarea id="reply-<?= (int)$p['id'] ?>" name="body" maxlength="2000" required placeholder="Comparte tu respuesta..."></textarea><button class="btn teal" type="submit">Responder</button></form><?php else: ?><p class="small">Inicia sesión para responder a esta publicación.</p><?php endif; ?>
</section></div></article><?php endforeach; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
