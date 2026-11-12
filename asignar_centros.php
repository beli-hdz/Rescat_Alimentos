<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT);
    if ($id) {
        $check = $pdo->prepare("SELECT id FROM donations WHERE id=? AND status NOT IN ('cancelled')");
        $check->execute([$id]);
        $centerCheck = $pdo->prepare("SELECT id FROM centers WHERE id=? AND active=1 AND center_type='center'");
        $centerCheck->execute([$centerId]);
        if ($check->fetchColumn() && $centerId && $centerCheck->fetchColumn()) {
            $update = $pdo->prepare('UPDATE donations SET center_id=? WHERE id=?');
            $update->execute([$centerId, $id]);
            flash('Producto asignado al centro correctamente.');
        } else {
            flash('No se pudo realizar la asignación. Verifica el estado del producto y que el centro esté activo.', 'error');
        }
    }
    header('Location: asignar_centros.php');
    exit;
}
$filter = $_GET['assignment'] ?? 'all';
if (!in_array($filter, ['all','unassigned','assigned'], true)) $filter='unassigned';
$status = $_GET['status'] ?? 'assignable';
if (!in_array($status, ['assignable','all','available','in_process','picked_up','delivered','completed'], true)) $status='assignable';
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
if ($status==='assignable') $where[]="d.status <> 'cancelled'";
elseif ($status!=='all') { $where[]='d.status=?'; $params[]=$status; }
if ($filter==='unassigned') $where[]='d.center_id IS NULL';
if ($filter==='assigned') $where[]='d.center_id IS NOT NULL';
if ($search!=='') { $where[]='(d.food_name LIKE ? OR d.category LIKE ? OR d.location LIKE ?)'; $term='%'.$search.'%'; array_push($params,$term,$term,$term); }
$sql='SELECT d.id,d.food_name,d.quantity,d.category,d.location,d.status,d.center_id,c.name AS center_name FROM donations d LEFT JOIN centers c ON c.id=d.center_id WHERE '.implode(' AND ',$where).' ORDER BY d.created_at DESC LIMIT 100';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $products=$stmt->fetchAll();
$centers=$pdo->query("SELECT id,name FROM centers WHERE active=1 AND center_type='center' ORDER BY name")->fetchAll();
$pageTitle='Asignar centros'; require __DIR__.'/includes/header.php';
?>
<div class="dashboard-shell admin-page-shell">
<?php $adminActive='assign'; require __DIR__.'/includes/admin_sidebar.php'; ?>
<div class="dashboard-main admin-content-main">
<section class="admin-heading"><div><span class="eyebrow">DISTRIBUCIÓN DE ALIMENTOS</span><h1>Asignar centros</h1><p class="muted">Selecciona el centro de destino de cada donación. Puedes cambiar la asignación mientras el registro no esté cancelado.</p></div><a class="btn light" href="admin.php">Dashboard</a></section>
<div class="inventory-summary"><div><strong><?=count($products)?></strong><span>Productos encontrados</span></div><div><strong><?=count(array_filter($products,fn($p)=>empty($p['center_id'])))?></strong><span>Sin asignar</span></div><div><strong><?=count($centers)?></strong><span>Centros activos</span></div></div>
<form method="get" class="filter-panel inventory-filters">
<div class="field search-field"><label for="search">Buscar producto</label><input id="search" type="search" name="search" value="<?=e($search)?>" placeholder="Nombre, categoría o ubicación"></div>
<div class="field"><label for="assignment">Asignación</label><select id="assignment" name="assignment"><option value="unassigned" <?=$filter==='unassigned'?'selected':''?>>Sin asignar</option><option value="assigned" <?=$filter==='assigned'?'selected':''?>>Asignados</option><option value="all" <?=$filter==='all'?'selected':''?>>Todos</option></select></div>
<div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="assignable" <?=$status==='assignable'?'selected':''?>>Todas las donaciones asignables</option><option value="all" <?=$status==='all'?'selected':''?>>Todos los estados</option><option value="available" <?=$status==='available'?'selected':''?>>Solo disponibles</option><option value="in_process" <?=$status==='in_process'?'selected':''?>>En proceso</option><option value="picked_up" <?=$status==='picked_up'?'selected':''?>>Recogidas</option><option value="delivered" <?=$status==='delivered'?'selected':''?>>Entregadas</option><option value="completed" <?=$status==='completed'?'selected':''?>>Completadas</option></select></div>
<div class="filter-actions"><button class="btn teal" type="submit">Aplicar filtros</button><a class="btn light" href="asignar_centros.php">Limpiar</a></div>
</form>
<div class="admin-table-card"><div class="table-wrap"><table class="admin-data-table assign-data-table"><thead><tr><th>Producto</th><th>Cantidad / categoría</th><th>Ubicación</th><th>Estado</th><th>Centro de donaciones</th><th>Asignar / cambiar centro</th></tr></thead><tbody>
<?php if(!$products): ?><tr><td colspan="6"><div class="inventory-no-results"><strong>No hay productos que coincidan</strong><span>Prueba con otros filtros o revisa el inventario.</span></div></td></tr><?php endif; ?>
<?php foreach($products as $product): ?><tr><td><b><?=e($product['food_name'])?></b></td><td><?=e($product['quantity'])?><small><?=e($product['category'])?></small></td><td><?=e($product['location'])?></td><td><span class="status-pill <?=in_array($product['status'],['available','in_process'],true)?'is-available':'is-unavailable'?>"><?=e(['available'=>'Disponible','in_process'=>'En proceso','picked_up'=>'Pedido recogido','delivered'=>'Entregado','completed'=>'Completado','cancelled'=>'Cancelado'][$product['status']] ?? $product['status'])?></span></td><td><?=e($product['center_name']?:'Sin asignar')?></td><td>
<?php if($centers): ?><form method="post" class="assign-center-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$product['id']?>"><select name="center_id" required aria-label="Centro para <?=e($product['food_name'])?>"><option value="">Seleccionar centro</option><?php foreach($centers as $center): ?><option value="<?=(int)$center['id']?>" <?=(int)$product['center_id']===(int)$center['id']?'selected':''?>><?=e($center['name'])?></option><?php endforeach; ?></select><button class="btn teal" type="submit">Guardar</button></form><?php else: ?><span class="muted">No hay centros de donaciones activos</span><?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div></div>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
