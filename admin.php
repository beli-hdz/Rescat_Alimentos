<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_admin();
$super = is_super_admin();

$stats = $pdo->query("SELECT COUNT(*) total,
    COALESCE(SUM(status='available'),0) available,
    COALESCE(SUM(status IN ('in_process','picked_up')),0) process,
    COALESCE(SUM(status IN ('delivered','completed')),0) delivered,
    COALESCE(SUM(status='cancelled'),0) cancelled
    FROM donations")->fetch();

$monthly = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') period, COUNT(*) total
    FROM donations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY period")->fetchAll();

// La gráfica representa exclusivamente centros de donaciones.
// Los puntos de recolección se guardan en pickup_point_id y nunca se contabilizan aquí.
$destinations = $pdo->query("SELECT c.id, c.name AS destination,
    COUNT(DISTINCT CASE WHEN d.status <> 'cancelled' THEN d.id END) AS total
    FROM centers c
    LEFT JOIN donations d ON d.center_id=c.id
    WHERE c.active=1 AND c.center_type='center'
    GROUP BY c.id, c.name
    ORDER BY c.name ASC")->fetchAll();

// Nueva gráfica: donaciones por categoría de alimento (sin canceladas)
$categoriesData = $pdo->query("SELECT COALESCE(NULLIF(category,''),'Otros') AS category, COUNT(*) AS total
    FROM donations WHERE status <> 'cancelled'
    GROUP BY COALESCE(NULLIF(category,''),'Otros') ORDER BY total DESC, category ASC LIMIT 8")->fetchAll();
$categoryLabels = json_encode(array_column($categoriesData, 'category'), JSON_UNESCAPED_UNICODE);
$categoryValues = json_encode(array_map('intval', array_column($categoriesData, 'total')));

// Nuevo panel: alimentos disponibles que caducan pronto (próximos 7 días o ya vencidos)
$expiring = $pdo->query("SELECT id, food_name, quantity, expiry_date, DATEDIFF(expiry_date, CURDATE()) AS days_left
    FROM donations WHERE status='available' AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY expiry_date ASC LIMIT 5")->fetchAll();

$monthNames = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
$monthMap = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("first day of -$i month"));
    $monthMap[$key] = 0;
}
foreach ($monthly as $m) { $monthMap[$m['period']] = (int)$m['total']; }
$chartLabels = json_encode(array_map(fn($p) => ($monthNames[substr($p,5,2)] ?? '') . ' ' . substr($p,0,4), array_keys($monthMap)), JSON_UNESCAPED_UNICODE);
$chartValues = json_encode(array_values($monthMap));
$destinationLabels = json_encode(array_column($destinations, 'destination'), JSON_UNESCAPED_UNICODE);
$destinationValues = json_encode(array_map('intval', array_column($destinations, 'total')));
$statusValues = json_encode([(int)$stats['available'], (int)$stats['process'], (int)$stats['delivered'], (int)$stats['cancelled']]);
$allDonations = max(1, (int)$stats['total']);
$hr=(int)date('G');$greet=$hr<12?'Buenos días':($hr<19?'Buenas tardes':'Buenas noches');$firstName=explode(' ',trim($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'equipo'))[0];
$pageTitle = 'Dashboard administrativo';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
  <?php $adminActive='dashboard'; require __DIR__ . '/includes/admin_sidebar.php'; ?>

  <div class="dashboard-main">
    <header class="dashboard-hero">
      <div class="dashboard-hero-copy">
        <span class="dashboard-kicker">RESCATALIMENTOS <i></i> ADMINISTRACIÓN</span>
        <h1><?= $greet ?>, <em><?= e($firstName) ?></em></h1>
        <p>Consulta indicadores, actividad reciente y el impacto de RescatAlimentos.</p>
      </div>
      <div class="dashboard-hero-tools">
        <div class="dashboard-date"><span class="date-dot"></span><span>Hoy</span><strong><?= e(date('d/m/Y')) ?></strong></div>
        <a class="dashboard-primary-link" href="inventario_donaciones.php">Ver inventario <span>→</span></a>
      </div>
    </header>

    <section class="dashboard-metrics" aria-label="Indicadores principales">
      <article class="metric-card metric-dark"><div class="metric-top"><span>Donaciones registradas</span><span class="metric-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg></span></div><strong><?= (int)$stats['total'] ?></strong><small>Registros en la plataforma</small></article>
      <article class="metric-card"><div class="metric-top"><span>Disponibles</span><span class="metric-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span></div><strong><?= (int)$stats['available'] ?></strong><small>Listas para solicitar</small></article>
      <article class="metric-card"><div class="metric-top"><span>En proceso</span><span class="metric-icon orange"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span></div><strong><?= (int)$stats['process'] ?></strong><small>Con entrega pendiente</small></article>
      <article class="metric-card"><div class="metric-top"><span>Entregadas</span><span class="metric-icon gold"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span></div><strong><?= (int)$stats['delivered'] ?></strong><small>Entrega completada</small></article>
    </section>

    <section class="dashboard-charts">
      <article class="dashboard-widget span-8 widget-monthly"><div class="widget-heading"><div><h2>Actividad de donaciones</h2><p>Donaciones registradas durante los últimos seis meses</p></div><span class="widget-label">Por mes</span></div><div class="chart-area"><canvas id="monthlyChart" aria-label="Donaciones registradas por mes"></canvas></div></article>

      <article class="dashboard-widget span-4 widget-status"><div class="widget-heading"><div><h2>Estado de las donaciones</h2><p>Distribución actual</p></div></div><div class="status-chart-wrap"><canvas id="statusChart" aria-label="Gráfica de estados de donaciones"></canvas><div class="status-chart-center"><strong><?= (int)$stats['total'] ?></strong><small>Total</small></div></div><div class="status-legend"><span><i class="legend-green"></i>Disponibles <b><?= (int)$stats['available'] ?></b></span><span><i class="legend-orange"></i>En proceso <b><?= (int)$stats['process'] ?></b></span><span><i class="legend-blue"></i>Entregadas <b><?= (int)$stats['delivered'] ?></b></span><span><i class="legend-gray"></i>Canceladas <b><?= (int)$stats['cancelled'] ?></b></span></div></article>

      <article class="dashboard-widget span-8 widget-destinations"><div class="widget-heading"><div><h2>¿A dónde llegan más donaciones?</h2><p>Donaciones asignadas exclusivamente a centros de donaciones</p></div><span class="widget-label">Por destino</span></div><?php if ($destinations): ?><div class="chart-area destination-chart-area" style="min-height:<?= max(230, count($destinations) * 42) ?>px"><canvas id="destinationChart" aria-label="Donaciones registradas por destino"></canvas></div><?php else: ?><div class="chart-empty"><span>▧</span><strong>Aún no hay entregas registradas</strong><p>Registra centros de donaciones y asigna donaciones desde «Asignar centros».</p></div><?php endif; ?></article>

      <article class="dashboard-widget span-4 widget-progress"><div class="widget-heading"><div><h2>Seguimiento de donaciones</h2><p>Avance por cada etapa</p></div></div><div class="progress-list">
        <?php foreach ([['Disponibles','available','linear-gradient(90deg,#3d8967,#7fb98f)'],['En proceso','process','linear-gradient(90deg,#e8a044,#f3c77e)'],['Entregadas','delivered','linear-gradient(90deg,#477fbd,#7fa8d6)'],['Canceladas','cancelled','#c5cdc6']] as $item): $count=(int)$stats[$item[1]]; $pct=(int)round($count/$allDonations*100); ?>
          <div class="progress-item"><div class="progress-meta"><span><?= $item[0] ?></span><b><?= $count ?> <small>(<?= $pct ?>%)</small></b></div><div class="progress-track"><i style="width:<?= $pct ?>%;background:<?= $item[2] ?>"></i></div></div>
        <?php endforeach; ?>
      </div><a class="dashboard-action" href="inventario_donaciones.php">Administrar inventario <span>→</span></a></article>

      <article class="dashboard-widget span-7 widget-categories"><div class="widget-heading"><div><h2>Tipos de alimento donados</h2><p>Categorías con más donaciones (sin canceladas)</p></div><span class="widget-label">Por categoría</span></div><?php if ($categoriesData): ?><div class="chart-area category-chart-area"><canvas id="categoryChart" aria-label="Donaciones por categoría de alimento"></canvas></div><?php else: ?><div class="chart-empty"><span>▧</span><strong>Aún no hay donaciones</strong><p>Cuando se publiquen donaciones verás aquí qué tipos de alimento se donan más.</p></div><?php endif; ?></article>

      <article class="dashboard-widget span-5 widget-expiring"><div class="widget-heading"><div><h2>Próximas a caducar</h2><p>Disponibles que vencen en los próximos 7 días</p></div><span class="widget-label widget-label-warn"><?= count($expiring) ?> alerta<?= count($expiring)===1?'':'s' ?></span></div>
        <?php if ($expiring): ?><ul class="expiring-list">
          <?php foreach ($expiring as $x): $d=(int)$x['days_left']; $cls=$d<0?'is-expired':($d<=2?'is-urgent':'is-soon'); $txt=$d<0?'Vencida':($d===0?'Vence hoy':($d===1?'Mañana':"En $d días")); ?>
          <li><div class="expiring-info"><strong><?= e($x['food_name']) ?></strong><small><?= e($x['quantity']) ?> · <?= e(date('d/m/Y', strtotime($x['expiry_date']))) ?></small></div><span class="expiring-badge <?= $cls ?>"><?= $txt ?></span></li>
          <?php endforeach; ?>
        </ul><a class="dashboard-action" href="inventario_donaciones.php">Ver inventario <span>→</span></a>
        <?php else: ?><div class="chart-empty"><span>✓</span><strong>Todo al día</strong><p>No hay donaciones disponibles próximas a caducar.</p></div><?php endif; ?>
      </article>
    </section>

  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const dark = document.documentElement.dataset.theme === 'dark';
const C = {forest:'#174b3b', leaf:'#3d8967', lime:'#b9d36c', gold:'#e8a044', blue:'#477fbd', gray: dark ? '#4a5d52' : '#c5cdc6',
  text: dark ? '#b8c9bd' : '#6b7a70', grid: dark ? 'rgba(255,255,255,.07)' : 'rgba(23,75,59,.07)'};
Chart.defaults.font.family = '"Plus Jakarta Sans", system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = C.text;
Chart.defaults.animation.duration = 900;
Chart.defaults.animation.easing = 'easeOutQuart';
const tip = {backgroundColor:'#12372c', titleColor:'#fff', bodyColor:'#d6e6da', padding:12, cornerRadius:12, displayColors:false, titleFont:{weight:'700'}};
const grad = (ctx, top, bottom) => { const g = ctx.createLinearGradient(0,0,0,ctx.canvas.height); g.addColorStop(0, top); g.addColorStop(1, bottom); return g; };

// 1) Actividad mensual: área suave con degradado
(() => {
  const el = document.getElementById('monthlyChart'); const ctx = el.getContext('2d');
  new Chart(el, {type:'line', data:{labels:<?= $chartLabels ?>, datasets:[{label:'Donaciones', data:<?= $chartValues ?>,
    borderColor:C.leaf, borderWidth:3, tension:.4, fill:true, backgroundColor:grad(ctx,'rgba(61,137,103,.35)','rgba(185,211,108,0)'),
    pointRadius:5, pointHoverRadius:8, pointBackgroundColor:dark?'#101d17':'#fff', pointBorderColor:C.leaf, pointBorderWidth:3}]},
    options:{responsive:true, maintainAspectRatio:false, interaction:{intersect:false, mode:'index'},
      plugins:{legend:{display:false}, tooltip:{...tip, callbacks:{label:i => i.parsed.y + (i.parsed.y===1?' donación':' donaciones')}}},
      scales:{x:{grid:{display:false}, border:{display:false}}, y:{beginAtZero:true, border:{display:false}, ticks:{precision:0, maxTicksLimit:5}, grid:{color:C.grid}}}}});
})();

// 2) Estados: dona con segmentos redondeados (anillo gris si no hay datos)
(() => {
  const data = <?= $statusValues ?>; const empty = data.every(v => v === 0);
  new Chart(document.getElementById('statusChart'), {type:'doughnut', data:{labels:['Disponibles','En proceso','Entregadas','Canceladas'],
    datasets:[{data: empty ? [1] : data, backgroundColor: empty ? [C.gray] : [C.leaf, C.gold, C.blue, C.gray], borderWidth:0, borderRadius:empty?0:10, spacing:empty?0:4, hoverOffset:8}]},
    options:{responsive:true, maintainAspectRatio:false, cutout:'76%', plugins:{legend:{display:false}, tooltip:{...tip, enabled:!empty, callbacks:{label:i => ' ' + i.label + ': ' + i.parsed}}}}});
})();

<?php if ($categoriesData): ?>
// 4) Categorías: barras verticales con colores de la marca
(() => {
  const el = document.getElementById('categoryChart');
  const palette = [C.forest, C.leaf, '#7fb98f', C.lime, C.gold, '#f3c77e', C.blue, '#7fa8d6'];
  new Chart(el, {type:'bar', data:{labels:<?= $categoryLabels ?>, datasets:[{label:'Donaciones', data:<?= $categoryValues ?>, backgroundColor:palette, borderRadius:10, borderSkipped:false, maxBarThickness:42}]},
    options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}, tooltip:{...tip, callbacks:{label:i => i.parsed.y + (i.parsed.y===1?' donación':' donaciones')}}},
      scales:{x:{grid:{display:false}, border:{display:false}, ticks:{maxRotation:0, autoSkip:false, font:{size:11}, callback(v){const l=this.getLabelForValue(v); return l.length>12 ? l.slice(0,11)+'…' : l;}}}, y:{beginAtZero:true, border:{display:false}, ticks:{precision:0, maxTicksLimit:5}, grid:{color:C.grid}}}}});
})();
<?php endif; ?>

<?php if ($destinations): ?>
// 3) Destinos: barras horizontales con degradado y valor al final
(() => {
  const el = document.getElementById('destinationChart'); const ctx = el.getContext('2d');
  const valueLabels = {id:'valueLabels', afterDatasetsDraw(chart){const {ctx:c} = chart; c.save(); c.font = '700 12px "Plus Jakarta Sans"'; c.fillStyle = dark ? '#e4eee5' : C.forest; c.textBaseline = 'middle';
    chart.getDatasetMeta(0).data.forEach((bar,i) => c.fillText(chart.data.datasets[0].data[i], bar.x + 8, bar.y)); c.restore();}};
  const g = ctx.createLinearGradient(0,0,el.width||400,0); g.addColorStop(0, C.forest); g.addColorStop(1, C.leaf);
  new Chart(el, {type:'bar', plugins:[valueLabels], data:{labels:<?= $destinationLabels ?>, datasets:[{label:'Entregas', data:<?= $destinationValues ?>, backgroundColor:g, borderRadius:{topRight:10,bottomRight:10}, borderSkipped:false, maxBarThickness:22}]},
    options:{indexAxis:'y', responsive:true, maintainAspectRatio:false, layout:{padding:{right:28}}, plugins:{legend:{display:false}, tooltip:{...tip, callbacks:{label:i => i.parsed.x + ' donaciones asignadas'}}},
      scales:{x:{beginAtZero:true, border:{display:false}, ticks:{precision:0, maxTicksLimit:5}, grid:{color:C.grid}}, y:{grid:{display:false}, border:{display:false}, ticks:{autoSkip:false, color:dark?'#cfe0d1':'#3f5247', font:{weight:'600'}}}}}});
})();
<?php endif; ?>
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
