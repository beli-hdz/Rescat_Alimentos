<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_admin();

$allowed=['available','in_process','picked_up','delivered','completed','cancelled'];
$labels=[
    'available'=>'Pendiente de recoger',
    'in_process'=>'En proceso',
    'picked_up'=>'Recogida',
    'delivered'=>'Entregada',
    'completed'=>'Completada',
    'cancelled'=>'Cancelada'
];

function inventory_redirect(): void {
    $query = trim((string)($_POST['return_query'] ?? ''));
    header('Location: inventario_donaciones.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
    $action=$_POST['action']??'';

    if ($id && $action==='delete') {
        $s=$pdo->prepare('DELETE FROM donations WHERE id=?');
        $s->execute([$id]);
        flash('Donación eliminada.');
        inventory_redirect();
    }

    if ($id && $action==='update') {
        $quantity=trim($_POST['quantity']??'');
        $status=$_POST['status']??'';
        $note=trim($_POST['note']??'');

        if ($quantity!=='' && mb_strlen($quantity)<=80 && in_array($status,$allowed,true) && mb_strlen($note)<=1000) {
            $q=$pdo->prepare('SELECT user_id,food_name,status FROM donations WHERE id=?');
            $q->execute([$id]);
            $previous=$q->fetch();

            if ($previous) {
                $s=$pdo->prepare('UPDATE donations SET quantity=?,status=? WHERE id=?');
                $s->execute([$quantity,$status,$id]);

                $s=$pdo->prepare('INSERT INTO delivery_updates(donation_id,user_id,status,note) VALUES(?,?,?,?)');
                $s->execute([$id,$_SESSION['user_id'],$status,$note]);

                if ($status==='picked_up' && $previous['status']!=='picked_up') {
                    create_notification(
                        $pdo,
                        (int)$previous['user_id'],
                        'Tu donación fue recogida',
                        'La donación de '.$previous['food_name'].' ya fue recogida.',
                        'mis_pedidos.php',
                        'pickup'
                    );
                }
                flash('Donación y estado actualizados correctamente.');
            } else {
                flash('No se encontró la donación.','error');
            }
        } else {
            flash('Revisa la cantidad, el estado y la nota.','error');
        }
        inventory_redirect();
    }
}

$period=$_GET['period']??'all';
if(!in_array($period,['all','week','month'],true)) $period='all';
$search=trim($_GET['search']??'');
$dateFrom=trim($_GET['date_from']??'');
$dateTo=trim($_GET['date_to']??'');
foreach (['dateFrom','dateTo'] as $dateVar) {
    if ($$dateVar !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $$dateVar)) $$dateVar='';
}
$category=trim($_GET['category']??'all');
$statusFilter=$_GET['status']??'all';
if(!in_array($statusFilter,array_merge(['all'],$allowed),true)) $statusFilter='all';

$where=['1=1'];
$params=[];

if($period==='week') {
    $where[]="d.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND d.created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)";
}
if($period==='month') {
    $where[]="YEAR(d.created_at)=YEAR(CURDATE()) AND MONTH(d.created_at)=MONTH(CURDATE())";
}
if($dateFrom!=='') { $where[]='DATE(d.created_at)>=?'; $params[]=$dateFrom; }
if($dateTo!=='') { $where[]='DATE(d.created_at)<=?'; $params[]=$dateTo; }
if($category!=='' && $category!=='all') {
    $where[]='d.category=?';
    $params[]=$category;
}
if($statusFilter!=='all') {
    $where[]='d.status=?';
    $params[]=$statusFilter;
}
if($search!=='') {
    $where[]='(d.food_name LIKE ? OR d.category LIKE ? OR d.location LIKE ? OR u.username LIKE ?)';
    $term='%'.$search.'%';
    array_push($params,$term,$term,$term,$term);
}

$sql='SELECT d.*,u.username,
      (SELECT du.note FROM delivery_updates du WHERE du.donation_id=d.id ORDER BY du.created_at DESC,du.id DESC LIMIT 1) AS last_note,
      (SELECT du.created_at FROM delivery_updates du WHERE du.donation_id=d.id ORDER BY du.created_at DESC,du.id DESC LIMIT 1) AS last_update
      FROM donations d
      JOIN users u ON u.id=d.user_id
      WHERE '.implode(' AND ',$where).'
      ORDER BY d.created_at DESC';
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$rows=$stmt->fetchAll();

$categoryStmt=$pdo->query("SELECT DISTINCT category FROM donations WHERE category IS NOT NULL AND category<>'' ORDER BY category");
$categories=$categoryStmt->fetchAll(PDO::FETCH_COLUMN);

$counts=$pdo->query("SELECT COUNT(*) total,
    SUM(status='picked_up') picked_up,
    SUM(status='available') available,
    SUM(status='in_process') in_process
    FROM donations")->fetch();

$pageTitle='Inventario de donaciones';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell admin-page-shell">
<?php $adminActive='inventory'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="dashboard-main admin-content-main">
<section class="admin-heading inventory-hero">
    <div>
        <span class="eyebrow">RESCATALIMENTOS · CONTROL DE ALIMENTOS</span>
        <h1>Inventario e historial de donaciones</h1>
        <p class="muted">Consulta todas las donaciones, filtra por fecha, categoría o estado y actualiza si cada donación ya fue recogida.</p>
    </div>
</section>

<div class="inventory-summary">
    <div class="inventory-total"><span class="summary-icon">▤</span><strong><?= (int)$counts['total'] ?></strong><small>Donaciones registradas</small></div>
    <div class="inventory-available"><span class="summary-icon">✓</span><strong><?= (int)$counts['picked_up'] ?></strong><small>Donaciones recogidas</small></div>
    <div class="inventory-other"><span class="summary-icon">⌕</span><strong><?= (int)$counts['available'] ?></strong><small>Pendientes de recoger</small></div>
</div>

<form method="get" action="inventario_donaciones.php" class="filter-panel inventory-filters" id="inventoryFilters">
    <div class="field search-field">
        <label for="search">Buscar donación</label>
        <input type="search" id="search" name="search" value="<?=e($search)?>" placeholder="Alimento, categoría, ubicación o donante">
    </div>
    <div class="field">
        <label for="category">Categoría</label>
        <select name="category" id="category">
            <option value="all" <?=$category==='all'?'selected':''?>>Todas las categorías</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?=e($cat)?>" <?=$category===$cat?'selected':''?>><?=e($cat)?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="date_from">Desde</label>
        <input type="date" id="date_from" name="date_from" value="<?=e($dateFrom)?>" aria-label="Fecha inicial">
    </div>
    <div class="field">
        <label for="date_to">Hasta</label>
        <input type="date" id="date_to" name="date_to" value="<?=e($dateTo)?>" aria-label="Fecha final">
    </div>
    <div class="field">
        <label for="period">Periodo rápido</label>
        <select name="period" id="period">
            <option value="all" <?=$period==='all'?'selected':''?>>Todo el historial</option>
            <option value="week" <?=$period==='week'?'selected':''?>>Esta semana</option>
            <option value="month" <?=$period==='month'?'selected':''?>>Este mes</option>
        </select>
    </div>
    <div class="field">
        <label for="status">Estado de la donación</label>
        <select name="status" id="status">
            <option value="all" <?=$statusFilter==='all'?'selected':''?>>Todos los estados</option>
            <?php foreach($allowed as $st): ?>
                <option value="<?=e($st)?>" <?=$statusFilter===$st?'selected':''?>><?=e($labels[$st])?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button class="btn teal" type="submit">Aplicar filtros</button>
        <a class="btn light" href="inventario_donaciones.php">Limpiar</a>
    </div>
</form>

<section id="recogidas" class="inventory-section-block">
    <div class="inventory-list-heading">
        <div>
            <h2>Historial de donaciones</h2>
            <p>Se muestran donaciones pendientes, en proceso, recogidas, entregadas, completadas y canceladas.</p>
        </div>
        <span><?=count($rows)?> registros</span>
    </div>
    <div class="admin-table-card">
        <div class="table-wrap">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Alimento</th><th>Donante</th><th>Ubicación</th>
                        <th>Cantidad</th><th>Estado actual</th><th>Último seguimiento</th><th>Actualizar</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!$rows): ?>
                    <tr><td colspan="7"><div class="inventory-no-results"><strong>No hay donaciones que coincidan</strong><span>Prueba con otros filtros o selecciona “Limpiar” para consultar todo el historial.</span></div></td></tr>
                <?php endif; ?>
                <?php foreach($rows as $d): ?>
                    <tr>
                        <td data-label="Alimento"><b><?=e($d['food_name'])?></b><small><?=e($d['category']??'Otros')?></small><small><?=e(date('d/m/Y',strtotime($d['created_at'])))?></small></td>
                        <td data-label="Donante"><?=e($d['username'])?></td>
                        <td data-label="Ubicación"><?=e($d['location'])?></td>
                        <td data-label="Cantidad"><?=e($d['quantity'])?></td>
                        <td data-label="Estado actual"><span class="status-pill"><?=e($labels[$d['status']]??$d['status'])?></span></td>
                        <td data-label="Último seguimiento">
                            <?php if(!empty($d['last_update'])): ?>
                                <small><?=e(date('d/m/Y H:i',strtotime($d['last_update'])))?></small>
                                <small><?=e($d['last_note']?:'Sin nota')?></small>
                            <?php else: ?><small>Sin actualizaciones</small><?php endif; ?>
                        </td>
                        <td data-label="Actualizar">
                            <form method="post" class="inventory-edit">
                                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                                <input type="hidden" name="id" value="<?=(int)$d['id']?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="return_query" value="<?=e(http_build_query($_GET))?>">
                                <label class="sr-only" for="quantity-<?=(int)$d['id']?>">Cantidad</label>
                                <input id="quantity-<?=(int)$d['id']?>" name="quantity" maxlength="80" value="<?=e($d['quantity'])?>" required>
                                <label class="sr-only" for="status-<?=(int)$d['id']?>">Estado</label>
                                <select id="status-<?=(int)$d['id']?>" name="status" required>
                                    <?php foreach($allowed as $st): ?>
                                        <option value="<?=e($st)?>" <?=$d['status']===$st?'selected':''?>><?=e($labels[$st])?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="note" maxlength="1000" placeholder="Nota de seguimiento" aria-label="Nota de seguimiento">
                                <button class="btn teal" type="submit">Guardar cambios</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<div class="inventory-assignment-action">
    <p>¿Quieres consultar o asignar alimentos a un centro de donación?</p>
    <a class="btn teal" href="asignar_centros.php">Ir a Asignar centros</a>
</div>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
