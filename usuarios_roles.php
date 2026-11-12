<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_admin();
if (!is_super_admin()) { http_response_code(403); exit('Esta sección es exclusiva del superadministrador.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if ($id && $id !== (int)$_SESSION['user_id']) {
        $q=$pdo->prepare('SELECT role FROM users WHERE id=?'); $q->execute([$id]); $target=$q->fetch();
        if ($target) {
            if ($action==='delete') {
                $q=$pdo->prepare('DELETE FROM users WHERE id=?'); $q->execute([$id]); flash('Usuario eliminado correctamente.');
            } elseif ($action==='role' && in_array($_POST['role'] ?? '', ['user','admin','super_admin'], true)) {
                $q=$pdo->prepare('UPDATE users SET role=? WHERE id=?'); $q->execute([$_POST['role'],$id]); flash('Rol actualizado correctamente.');
            }
        }
    }
    header('Location: usuarios_roles.php'); exit;
}
$users=$pdo->query('SELECT id,name,email,username,role,created_at FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle='Usuarios y roles'; require __DIR__.'/includes/header.php';
?>
<div class="dashboard-shell admin-page-shell">
<?php $adminActive='users'; require __DIR__.'/includes/admin_sidebar.php'; ?>
<div class="dashboard-main admin-content-main users-roles-page">
<section class="admin-heading"><div><span class="eyebrow">CONTROL DE ACCESOS</span><h1>Usuarios y roles</h1><p class="muted">Administra las cuentas y asigna permisos de acceso.</p></div><a class="btn light" href="admin.php">Volver al dashboard</a></section>
<div class="inventory-summary"><div><strong><?=count($users)?></strong><span>Usuarios registrados</span></div><div><strong><?=count(array_filter($users,fn($u)=>$u['role']==='admin'))?></strong><span>Administradores</span></div><div><strong><?=count(array_filter($users,fn($u)=>$u['role']==='super_admin'))?></strong><span>Superadministradores</span></div></div>
<div class="admin-table-card"><div class="table-wrap"><table class="admin-data-table"><thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol actual</th><th>Asignar rol</th><th>Acción</th></tr></thead><tbody>
<?php foreach($users as $u): ?><tr><td data-label="Nombre"><?=e($u['name'])?></td><td data-label="Usuario"><?=e($u['username'])?></td><td data-label="Correo"><?=e($u['email'])?></td><td data-label="Rol actual"><?=e(['user'=>'Usuario','admin'=>'Administrador','super_admin'=>'Superadministrador'][$u['role']]??$u['role'])?></td><td data-label="Asignar rol"><?php if((int)$u['id']!==(int)$_SESSION['user_id']): ?><form method="post" class="role-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><input type="hidden" name="action" value="role"><select name="role"><option value="user" <?=$u['role']==='user'?'selected':''?>>Usuario</option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Administrador</option><option value="super_admin" <?=$u['role']==='super_admin'?'selected':''?>>Superadministrador</option></select><button class="btn" type="submit">Guardar</button></form><?php else: ?>Tu cuenta actual<?php endif; ?></td><td data-label="Acción"><?php if((int)$u['id']!==(int)$_SESSION['user_id']): ?><form method="post" class="user-delete-form" onsubmit="return confirm('¿Eliminar este usuario?')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><input type="hidden" name="action" value="delete"><button class="btn danger" type="submit">Eliminar</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$users): ?><tr><td colspan="6">No hay usuarios registrados.</td></tr><?php endif; ?></tbody></table></div></div>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
