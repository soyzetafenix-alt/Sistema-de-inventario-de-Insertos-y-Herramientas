<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$inserto_id = $_GET['id'] ?? null;
if (!$inserto_id) {
    header('Location: search.php');
    exit;
}

// Get inserto
$ins_stmt = $pdo->prepare('SELECT * FROM insertos WHERE id = :id');
$ins_stmt->execute(['id' => $inserto_id]);
$inserto = $ins_stmt->fetch();

if (!$inserto) {
    header('Location: search.php');
    exit;
}

// Get filters
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

// Build query
$query = 'SELECT * FROM stock_movements WHERE inserto_id = :ins_id';
$params = ['ins_id' => $inserto_id];

if (!empty($from_date)) {
    $query .= ' AND created_at >= :from';
    $params['from'] = $from_date . ' 00:00:00';
}

if (!empty($to_date)) {
    $query .= ' AND created_at <= :to';
    $params['to'] = $to_date . ' 23:59:59';
}

$query .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Historial de Stock - <?=htmlspecialchars($inserto['code'])?></title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="navbar">
    <a href="dashboard.php" class="navbar-logo">
      <div class="navbar-logo-icon">⚙️</div>
      <span>VALMET Insertos</span>
    </a>
    <div class="navbar-user">
      <span><?=htmlspecialchars($user['username'])?></span>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <div class="container">
    <div class="page-header">
      <h1 class="page-title">Historial de Stock: <?=htmlspecialchars($inserto['code'])?></h1>
      <p class="page-subtitle">Stock actual: <strong><?=$inserto['stock']?></strong></p>
    </div>

    <div class="card" style="margin-bottom: 20px;">
      <h3 style="margin-bottom: 15px;">Filtros</h3>
      <form method="get" style="display: flex; gap: 10px; align-items: flex-end;">
        <input type="hidden" name="id" value="<?=$inserto_id?>">
        <div>
          <label>Desde</label><br>
          <input type="date" name="from" value="<?=$from_date?>" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div>
          <label>Hasta</label><br>
          <input type="date" name="to" value="<?=$to_date?>" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <button type="submit" style="padding: 8px 15px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Filtrar</button>
        <a href="?id=<?=$inserto_id?>" style="padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Limpiar</a>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-bottom: 15px;">Movimientos (<?=count($movements)?>)</h3>
      
      <?php if (empty($movements)): ?>
        <p style="color: #666;">No hay movimientos registrados.</p>
      <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">Fecha</th>
              <th style="text-align: left; padding: 10px;">Motivo</th>
              <th style="text-align: center; padding: 10px;">Cambio</th>
              <th style="text-align: center; padding: 10px;">Antes</th>
              <th style="text-align: center; padding: 10px;">Después</th>
              <th style="text-align: left; padding: 10px;">Realizado por</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movements as $mov): ?>
              <?php
              $performed_by = '-';
              if ($mov['performed_by']) {
                $user_stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id');
                $user_stmt->execute(['id' => $mov['performed_by']]);
                $performed_by = $user_stmt->fetch()['username'] ?? '-';
              }
              ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><?=date('d/m/Y H:i', strtotime($mov['created_at']))?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($mov['reason'] ?? '-')?></td>
                <td style="text-align: center; padding: 10px; color: <?=$mov['change_amount'] > 0 ? 'green' : 'red'?>;">
                  <?=($mov['change_amount'] > 0 ? '+' : '').$mov['change_amount']?>
                </td>
                <td style="text-align: center; padding: 10px;"><?=$mov['before_stock']?></td>
                <td style="text-align: center; padding: 10px;"><?=$mov['after_stock']?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($performed_by)?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div style="margin-top: 20px;">
      <a href="search.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver</a>
    </div>
  </div>
</body>
</html>
