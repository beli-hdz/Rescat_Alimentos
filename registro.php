<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Crear cuenta'; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
      !preg_match('/^[a-zA-Z0-9]{4,30}$/', $username) ||
      !preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{8,64}$/', $password)) {
    $error = 'Revisa los datos. Usuario: 4-30 letras o números. Contraseña: mínimo 8 caracteres, con letras y números, sin símbolos.';
  } else {
    try {
      $stmt = $pdo->prepare('INSERT INTO users (name,email,username,password_hash) VALUES (?,?,?,?)');
      $stmt->execute([$name,$email,$username,password_hash($password, PASSWORD_DEFAULT)]);
      header('Location: login.php?registered=1'); exit;
    } catch (PDOException $ex) { $error = 'El correo o nombre de usuario ya está registrado.'; }
  }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap"><aside class="auth-art"><a class="brand footer-brand" href="index.php"><span class="brand-mark"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 20A7 7 0 019.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.5 2 8 0 5.5-4.8 10-10 10z"/><path d="M2 21c0-3 1.9-5.5 6-8"/></svg></span> Rescat<span>Alimentos</span></a><blockquote>Lo que hoy sobra en una mesa, mañana puede <em>llenar otra</em>.</blockquote><ul class="auth-points"><li>Publica donaciones en minutos</li><li>Entrega en puntos autorizados</li><li>Da seguimiento a cada alimento</li></ul></aside>
<section class="panel auth-panel"><h1>Crear tu cuenta</h1><p class="muted">Únete para gestionar tus pedidos y publicar donaciones.</p>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="field"><label for="name">Nombre completo</label><input id="name" name="name" maxlength="100" required value="<?= e($_POST['name'] ?? '') ?>"></div>
<div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" maxlength="150" required value="<?= e($_POST['email'] ?? '') ?>"></div>
<div class="field"><label for="username">Nombre de usuario</label><input id="username" name="username" data-alphanumeric minlength="4" maxlength="30" pattern="[A-Za-z0-9]{4,30}" required value="<?= e($_POST['username'] ?? '') ?>"><small class="small">De 4 a 30 letras y números, sin espacios.</small></div>
<div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" minlength="8" maxlength="64" pattern="(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,64}" required><small class="small">Mínimo 8 caracteres, incluyendo letras y números.</small></div>
<button class="btn teal" type="submit">Crear cuenta</button>
</form><p class="muted">¿Ya tienes cuenta? <a href="login.php"><strong>Inicia sesión</strong></a></p></section></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
