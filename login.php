<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Iniciar sesión'; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $login = trim($_POST['login'] ?? '');
  $password = $_POST['password'] ?? '';
  $stmt = $pdo->prepare('SELECT id,name,username,password_hash,role FROM users WHERE email=? OR username=? LIMIT 1');
  $stmt->execute([$login,$login]); $user = $stmt->fetch();
  if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id']; $_SESSION['user_name'] = $user['name']; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role'] ?? 'user';
    header('Location: ' . (in_array($_SESSION['role'], ['admin','super_admin'], true) ? 'admin.php' : 'mis_pedidos.php')); exit;
  }
  $error = 'Usuario, correo o contraseña incorrectos.';
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap"><aside class="auth-art"><a class="brand footer-brand" href="index.php"><span class="brand-mark"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 20A7 7 0 019.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.5 2 8 0 5.5-4.8 10-10 10z"/><path d="M2 21c0-3 1.9-5.5 6-8"/></svg></span> Rescat<span>Alimentos</span></a><blockquote>Lo que hoy sobra en una mesa, mañana puede <em>llenar otra</em>.</blockquote><ul class="auth-points"><li>Publica donaciones en minutos</li><li>Entrega en puntos autorizados</li><li>Da seguimiento a cada alimento</li></ul></aside>
<section class="panel auth-panel"><h1>¡Qué bueno verte!</h1><p class="muted">Inicia sesión en RescatAlimentos.</p>
<?php if (isset($_GET['registered'])): ?><div class="notice success">Cuenta creada. Ya puedes iniciar sesión.</div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="field"><label for="login">Correo o usuario</label><input id="login" name="login" maxlength="150" required autocomplete="username" value="<?= e($_POST['login'] ?? '') ?>"></div>
<div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button class="btn teal" type="submit">Iniciar sesión</button>
</form><p class="muted">¿Aún no tienes cuenta? <a href="registro.php"><strong>Regístrate aquí</strong></a></p></section></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
