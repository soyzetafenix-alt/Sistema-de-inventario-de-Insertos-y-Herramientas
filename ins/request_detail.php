<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$request_id = $_GET['id'] ?? null;
if (!$request_id) {
    header('Location: requests.php');
    exit;
}

// Get request details
$req_stmt = $pdo->prepare('
    SELECT r.id, r.user_id, u.username, u.area, u.dni, r.status, r.created_at, r.delivery_date, r.approximate_return_date, r.admin_comment, r.ot
    FROM requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.id = :req_id
');
$req_stmt->execute(['req_id' => $request_id]);
$request = $req_stmt->fetch();

if (!$request) {
    header('Location: requests.php');
    exit;
}

// Get inserto items
$items_stmt = $pdo->prepare('
    SELECT ri.cantidad, i.id, i.code, COALESCE(i.detalle, i.descripcion) AS detalle, i.stock, i.cutting_conditions, ri.unit_price
    FROM request_items ri
    JOIN insertos i ON ri.inserto_id = i.id
    WHERE ri.request_id = :req_id
');
$items_stmt->execute(['req_id' => $request_id]);
$items = $items_stmt->fetchAll();

// Get tool items
$tool_items_stmt = $pdo->prepare('
    SELECT rti.cantidad, t.id, t.code, t.brand, t.stock, rti.unit_price
    FROM request_tool_items rti
    JOIN tools t ON rti.tool_id = t.id
    WHERE rti.request_id = :req_id
');
$tool_items_stmt->execute(['req_id' => $request_id]);
$tool_items = $tool_items_stmt->fetchAll();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Detalle de Solicitud</title>
  <link rel="stylesheet" href="styles.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">
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
      <h1 class="page-title">Solicitud #<?=$request_id?></h1>
      <p class="page-subtitle">Detalles y seguimiento</p>
    </div>

    <div class="card" style="margin-bottom: 20px;">
      <h2 style="margin-bottom: 15px;">Información del Usuario</h2>
      <table style="width: 100%; border-collapse: collapse;">
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px; width: 150px;"><strong>Usuario:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($request['username'])?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>DNI:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($request['dni'] ?? '-')?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>Área:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($request['area'] ?? '-')?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>Estado:</strong></td>
          <td style="padding: 10px;">
            <span style="padding: 5px 10px; border-radius: 4px; background: 
              <?=$request['status'] === 'pending' ? '#ffc107' : ($request['status'] === 'accepted' ? '#28a745' : '#dc3545')?>; 
              color: white; font-weight: bold;">
              <?=strtoupper($request['status'])?>
            </span>
          </td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>Solicitud creada:</strong></td>
          <td style="padding: 10px;"><?=date('d/m/Y H:i', strtotime($request['created_at']))?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>OT:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($request['ot'] ?? '-')?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>Fecha entrega:</strong></td>
          <td style="padding: 10px;"><?=$request['delivery_date'] ? date('d/m/Y', strtotime($request['delivery_date'])) : '-'?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>Fecha devolución aprox:</strong></td>
          <td style="padding: 10px;"><?=$request['approximate_return_date'] ? date('d/m/Y', strtotime($request['approximate_return_date'])) : '-'?></td>
        </tr>
        <?php if ($request['admin_comment']): ?>
          <tr>
            <td style="padding: 10px;"><strong>Comentario:</strong></td>
            <td style="padding: 10px;"><?=htmlspecialchars($request['admin_comment'])?></td>
          </tr>
        <?php endif; ?>
      </table>
    </div>

    <div class="card">
      <h2 style="margin-bottom: 15px;">Insertos Solicitados (<?=count($items)?>)</h2>
      
      <?php if (empty($items)): ?>
        <p style="color: #666;">No hay insertos en esta solicitud.</p>
      <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">Código</th>
              <th style="text-align: left; padding: 10px;">Detalle</th>
              <th style="text-align: left; padding: 10px;">Cutting Conditions</th>
              <th style="text-align: center; padding: 10px;">Cantidad</th>
              <th style="text-align: center; padding: 10px;">Precio Unitario</th>
              <th style="text-align: center; padding: 10px;">Subtotal</th>
              <th style="text-align: center; padding: 10px;">Stock Disponible</th>
            </tr>
          </thead>
          <tbody>
            <?php $total = 0; ?>
            <?php foreach ($items as $item): ?>
              <?php
                $unit = $item['unit_price'] !== null ? $item['unit_price'] : 0;
                $sub = $unit * $item['cantidad'];
                $total += $sub;
              ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><strong><?=htmlspecialchars($item['code'])?></strong></td>
                <td style="padding: 10px;"><?=htmlspecialchars($item['detalle'] ?? '-')?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($item['cutting_conditions'] ?? '-')?></td>
                <td style="text-align: center; padding: 10px;"><?=$item['cantidad']?></td>
                <td style="text-align: center; padding: 10px;"><?= $unit > 0 ? number_format($unit, 2) : '-' ?></td>
                <td style="text-align: center; padding: 10px;"><?= $sub > 0 ? number_format($sub, 2) : '-' ?></td>
                <td style="text-align: center; padding: 10px; color: <?=$item['stock'] >= $item['cantidad'] ? 'green' : 'red'?>;">
                  <?=$item['stock']?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-bottom: 15px;">Herramientas Solicitadas (<?=count($tool_items)?>)</h2>
      <?php if (empty($tool_items)): ?>
        <p style="color: #666;">No hay herramientas en esta solicitud.</p>
      <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">Código</th>
              <th style="text-align: left; padding: 10px;">Marca</th>
              <th style="text-align: center; padding: 10px;">Cantidad</th>
              <th style="text-align: center; padding: 10px;">Precio Unitario</th>
              <th style="text-align: center; padding: 10px;">Subtotal</th>
              <th style="text-align: center; padding: 10px;">Stock Disponible</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tool_items as $t): ?>
              <?php
                $unit = $t['unit_price'] !== null ? $t['unit_price'] : 0;
                $sub = $unit * $t['cantidad'];
                $total += $sub;
              ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><strong><?=htmlspecialchars($t['code'])?></strong></td>
                <td style="padding: 10px;"><?=htmlspecialchars($t['brand'] ?? '-')?></td>
                <td style="text-align: center; padding: 10px;"><?=$t['cantidad']?></td>
                <td style="text-align: center; padding: 10px;"><?= $unit > 0 ? number_format($unit, 2) : '-' ?></td>
                <td style="text-align: center; padding: 10px;"><?= $sub > 0 ? number_format($sub, 2) : '-' ?></td>
                <td style="text-align: center; padding: 10px; color: <?=$t['stock'] >= $t['cantidad'] ? 'green' : 'red'?>;">
                  <?=$t['stock']?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-bottom: 15px;">Resumen económico</h2>
      <p><strong>Total de la solicitud:</strong> <?=isset($total) ? number_format($total, 2) : '0.00'?></p>
    </div>

    <div style="margin-top: 20px;">
      <a href="requests.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver</a>
    </div>
  </div>
</body>
</html>
