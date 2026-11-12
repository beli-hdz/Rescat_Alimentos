<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Inicio';
$latestDonations = $pdo->query("SELECT id,food_name,quantity,category,notes,image_path,status,created_at FROM donations WHERE status='available' ORDER BY created_at DESC LIMIT 6")->fetchAll();
try{$impact=$pdo->query("SELECT COUNT(*) t, COALESCE(SUM(status IN ('delivered','completed')),0) d FROM donations")->fetch();$centersCount=(int)$pdo->query("SELECT COUNT(*) FROM centers WHERE active=1 AND center_type='center'")->fetchColumn();}catch(Throwable $e){$impact=['t'=>0,'d'=>0];$centersCount=0;}
require __DIR__ . '/includes/header.php';
?>
<section class="hero about-hero">
  <div><span class="eyebrow">Una comunidad que comparte</span>
    <h1>Donar, aprovechar y <em>ayudar</em>.</h1>
    <p>Somos una iniciativa comunitaria que busca conectar a personas con alimentos que pueden compartirse con quienes los necesitan. Aquí no se vende nada: las publicaciones son donaciones y el objetivo es reducir el desperdicio alimentario.</p>
    <div class="actions"><a class="btn teal" href="<?= logged_in() ? 'publicar_donacion.php' : 'registro.php' ?>">Quiero donar alimentos</a><a class="btn light" href="centros.php">Ver centros de donación</a></div>
  </div><div class="hero-photo"><img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1100&q=85" alt="Frutas y verduras frescas en un mercado"><div class="hero-badge"><i>🌱</i><div><b><?= (int)$impact['d'] ?></b>donaciones entregadas</div></div></div>
</section>
<div class="impact-strip"><div><strong><?= (int)$impact['t'] ?></strong><span>donaciones<br>registradas</span></div><div><strong><?= (int)$impact['d'] ?></strong><span>entregas<br>completadas</span></div><div><strong><?= $centersCount ?></strong><span>centros de<br>donación activos</span></div></div>
<div class="ticker" aria-hidden="true"><div class="ticker-track"><span>🥦 Verduras</span><span>🍎 Frutas</span><span>🥖 Pan</span><span>🥫 Conservas</span><span>🌾 Granos</span><span>🥛 Lácteos</span><span>📦 Empacados</span><span>🍲 Comida preparada</span><span>🥦 Verduras</span><span>🍎 Frutas</span><span>🥖 Pan</span><span>🥫 Conservas</span><span>🌾 Granos</span><span>🥛 Lácteos</span><span>📦 Empacados</span><span>🍲 Comida preparada</span></div></div>
<section class="about-section"><div class="section-head"><div><span class="eyebrow">Quiénes somos</span><h2>Alimentos que pueden hacer la diferencia</h2></div></div><p>RescatAlimentos promueve el aprovechamiento responsable de alimentos en buen estado. Facilitamos que las personas registren lo que desean donar, indiquen su cantidad y seleccionen uno de los puntos de entrega autorizados. Cada publicación ayuda a organizar mejor la donación y a dar seguimiento a su estado.</p>
<div class="stats"><div class="stat"><strong>Donar</strong><span class="muted">Comparte alimentos aptos para consumo.</span></div><div class="stat"><strong>Organizar</strong><span class="muted">Clasifica las donaciones por tipo de alimento.</span></div><div class="stat"><strong>Entregar</strong><span class="muted">Usa los puntos de entrega autorizados.</span></div></div></section>
<section class="about-section"><div class="section-head"><div><span class="eyebrow">Cómo funciona</span><h2>Un proceso sencillo y solidario</h2></div></div><div class="grid">
<article class="card"><span class="step-num">1</span><img class="process-photo" src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=800&q=80" alt="Persona preparando alimentos frescos"><div class="card-body"><h3>Registra tu donación</h3><p class="muted">Inicia sesión, describe el alimento, elige su categoría y agrega una fotografía opcional.</p></div></article>
<article class="card"><span class="step-num">2</span><img class="process-photo" src="https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&w=800&q=80" alt="Voluntarios organizando donaciones"><div class="card-body"><h3>Elige un punto autorizado</h3><p class="muted">Selecciona uno de los puntos de entrega autorizados registrados por el administrador.</p></div></article>
<article class="card"><span class="step-num">3</span><img class="process-photo" src="https://images.unsplash.com/photo-1594708767771-a7502209ff51?auto=format&fit=crop&w=800&q=80" alt="Entrega de alimentos a una comunidad"><div class="card-body"><h3>Da seguimiento</h3><p class="muted">Consulta el estado de tus propias donaciones desde Mi cuenta.</p></div></article>
</div></section>
<section class="about-section category-section"><div class="section-head"><div><span class="eyebrow">Clasificación</span><h2>¿Qué tipo de alimentos puedes donar?</h2><p>Selecciona la categoría correspondiente al publicar.</p></div></div><div class="category-chips"><span>🥦 Verduras</span><span>🍎 Frutas</span><span>🥖 Pan y panadería</span><span>🥫 Enlatados y conservas</span><span>🌾 Granos y cereales</span><span>🥛 Lácteos</span><span>📦 Alimentos empacados</span><span>🍲 Comida preparada</span><span>✨ Otros</span></div><p class="notice">Por seguridad, dona únicamente alimentos en condiciones adecuadas para consumo. Revisa su conservación y fecha de caducidad antes de entregarlos.</p></section>
<section class="cta-panel"><div><h2>Una pequeña donación puede ser de gran ayuda.</h2><p>Registra alimentos que estén en buen estado y contribuye a una comunidad con menos desperdicio.</p></div><a class="btn teal" href="<?= logged_in() ? 'publicar_donacion.php' : 'registro.php' ?>">Comenzar</a></section>

<section class="about-section" id="donaciones-disponibles">
  <div class="section-head"><div><span class="eyebrow">COMUNIDAD</span><h2>Donaciones disponibles</h2><p>Alimentos compartidos recientemente por la comunidad.</p></div><a class="btn light" href="donaciones.php">Ver mis donaciones</a></div>
  <?php if ($latestDonations): ?><div class="grid donation-listing">
    <?php foreach ($latestDonations as $d): ?><article class="card">
      <img class="donation-photo" src="<?=e(donation_image_src($d['image_path'] ?? null, $d['food_name'] ?? '', $d['category'] ?? ''))?>" alt="Imagen de <?=e($d['food_name'])?>" loading="lazy">
      <div class="card-body"><span class="tag"><?=e($d['category'] ?: 'Alimento')?></span><h3><?=e($d['food_name'])?></h3><p class="muted"><?=e($d['notes'] ?: 'Alimento compartido para apoyar a la comunidad.')?></p><div class="price-row"><strong><?=e($d['quantity'])?></strong><span class="tag">Disponible</span></div></div>
    </article><?php endforeach; ?>
  </div><?php else: ?><div class="empty">Todavía no hay donaciones disponibles. ¡Sé la primera persona en compartir alimentos!</div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>