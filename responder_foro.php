<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: foro.php'); exit; }
verify_csrf();
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
$body = trim($_POST['body'] ?? '');
if (!$postId || $body === '' || mb_strlen($body) > 2000) { flash('Escribe una respuesta válida.','error'); header('Location: foro.php'); exit; }
$check=$pdo->prepare('SELECT id,title FROM forum_posts WHERE id=?'); $check->execute([$postId]); $post=$check->fetch();
if (!$post) { flash('La publicación ya no existe.','error'); header('Location: foro.php'); exit; }
$stmt=$pdo->prepare('INSERT INTO forum_replies(post_id,user_id,body) VALUES(?,?,?)');
$stmt->execute([$postId,$_SESSION['user_id'],$body]);
$role=$_SESSION['role']??'user';
$label=in_array($role,['admin','super_admin'],true)?'RescatAlimentos respondió':'Nueva respuesta en el foro';
$others=$pdo->prepare("SELECT id FROM users WHERE id<>?");
$others->execute([$_SESSION['user_id']]);
foreach($others->fetchAll(PDO::FETCH_COLUMN) as $uid) create_notification($pdo,(int)$uid,$label,'Hay una nueva respuesta en: '.$post['title'],'foro.php#publicacion-'.$postId,'forum');
flash('Tu respuesta se publicó correctamente.');
header('Location: foro.php#publicacion-'.$postId); exit;
