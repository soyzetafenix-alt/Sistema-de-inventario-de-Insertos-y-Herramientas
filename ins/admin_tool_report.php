<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$tool_id = $_GET['id'] ?? null;
if (!$tool_id) {
    header('Location: search.php?type=herramientas');
    exit;
}

// Get tool
$tool_stmt = $pdo->prepare('SELECT * FROM tools WHERE id = :id');
$tool_stmt->execute(['id' => $tool_id]);
$tool = $tool_stmt->fetch();

if (!$tool) {
    header('Location: search.php?type=herramientas');
    exit;
}

// Get filters
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Build query for users who requested this tool
$query = '
    SELECT DISTINCT u.id, u.username, u.area, u.dni,
           r.id as request_id, r.status, r.created_at, r.delivery_date, rti.cantidad
    FROM request_tool_items rti
    JOIN requests r ON rti.request_id = r.id
    JOIN users u ON r.user_id = u.id
    WHERE rti.tool_id = :tool_id
';
$params = ['tool_id' => $tool_id];

if (!empty($from_date)) {
    $query .= ' AND r.created_at >= :from';
    $params['from'] = $from_date . ' 00:00:00';
}

if (!empty($to_date)) {
    $query .= ' AND r.created_at <= :to';
    $params['to'] = $to_date . ' 23:59:59';
}

if ($status_filter !== 'all') {
    $query .= ' AND r.status = :status';
    $params['status'] = $status_filter;
}

$query .= ' ORDER BY r.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$report = $stmt->fetchAll();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reporte Herramienta - <?=htmlspecialchars($tool['code'])?></title>
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
      <h1 class="page-title">Reporte de Uso (Herramienta): <?=htmlspecialchars($tool['code'])?></h1>
      <p class="page-subtitle">Quién usó esta herramienta y cuándo</p>
    </div>

    <div class="card" style="margin-bottom: 20px;">
      <h3 style="margin-bottom: 15px;">Filtros</h3>
      <form method="get" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="id" value="<?=$tool_id?>">
        <div>
          <label>Desde</label><br>
          <input type="date" name="from" value="<?=$from_date?>" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div>
          <label>Hasta</label><br>
          <input type="date" name="to" value="<?=$to_date?>" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div>
          <label>Estado</label><br>
          <select name="status" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="all" <?=$status_filter === 'all' ? 'selected' : ''?>>Todos</option>
            <option value="pending" <?=$status_filter === 'pending' ? 'selected' : ''?>>Pendiente</option>
            <option value="accepted" <?=$status_filter === 'accepted' ? 'selected' : ''?>>Aceptado</option>
            <option value="rejected" <?=$status_filter === 'rejected' ? 'selected' : ''?>>Rechazado</option>
          </select>
        </div>
        <button type="submit" style="padding: 8px 15px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Filtrar</button>
        <a href="?id=<?=$tool_id?>" style="padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Limpiar</a>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-bottom: 15px;">Registros (<?=count($report)?>)</h3>
      
      <?php if (empty($report)): ?>
        <p style="color: #666;">No hay registros para mostrar.</p>
      <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">Usuario</th>
              <th style="text-align: left; padding: 10px;">DNI</th>
              <th style="text-align: left; padding: 10px;">Área</th>
              <th style="text-align: center; padding: 10px;">Cantidad</th>
              <th style="text-align: left; padding: 10px;">Fecha Solicitud</th>
              <th style="text-align: center; padding: 10px;">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report as $row): ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><?=htmlspecialchars($row['username'])?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($row['dni'] ?? '-')?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($row['area'] ?? '-')?></td>
                <td style="text-align: center; padding: 10px;"><?=$row['cantidad']?></td>
                <td style="padding: 10px;"><?=date('d/m/Y H:i', strtotime($row['created_at']))?></td>
                <td style="text-align: center; padding: 10px;">
                  <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; background: 
                    <?=$row['status'] === 'pending' ? '#ffc107' : ($row['status'] === 'accepted' ? '#28a745' : '#dc3545')?>; 
                    color: white;">
                    <?=strtoupper($row['status'])?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div style="margin-top: 20px;">
      <a href="search.php?type=herramientas" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver</a>
    </div>
  </div>
</body>
</html>

