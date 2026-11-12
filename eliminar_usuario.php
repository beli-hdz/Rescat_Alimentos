<?php
require_once 'config/database.php'; require_once 'includes/functions.php'; require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: admin.php');exit;}
csrf_check(); $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
if(!$id){flash('Usuario inválido.','error');header('Location: admin.php');exit;}
$s=$pdo->prepare("SELECT role FROM users WHERE id=?");$s->execute([$id]);$target=$s->fetch();
if(!$target || $target['role']==='admin' || $id===(int)$_SESSION['user']['id']){
 flash('No se puede eliminar esa cuenta.','error');
}else{
 $s=$pdo->prepare("DELETE FROM users WHERE id=?");$s->execute([$id]);flash('Usuario eliminado.');
}
header('Location: admin.php');exit;
?>