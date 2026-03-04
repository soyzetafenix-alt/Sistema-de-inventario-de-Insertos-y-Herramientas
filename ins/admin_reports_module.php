<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

// Get filters
$ot = $_GET['ot'] ?? '';
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';
$brand = $_GET['brand'] ?? '';
$item_type = $_GET['type'] ?? 'ambos'; // insertos, herramientas, ambos

// Build base query for insertos
$query_insertos = '';
if ($item_type === 'insertos' || $item_type === 'ambos') {
    $query_insertos = '
        SELECT 
            r.id as request_id,
            r.ot,
            r.created_at,
            r.delivery_date,
            r.approximate_return_date,
            u.username,
            u.dni,
            u.area,
            \'inserto\' as item_type,
            i.code,
            i.code as item_code,
            i.brand,
            ri.cantidad,
            ri.unit_price,
            (ri.cantidad * ri.unit_price) as subtotal,
            r.status
        FROM requests r
        JOIN users u ON r.user_id = u.id
        JOIN request_items ri ON r.id = ri.request_id
        JOIN insertos i ON ri.inserto_id = i.id
        WHERE r.status = \'accepted\'
    ';
    
    if (!empty($ot)) {
        $query_insertos .= ' AND r.ot LIKE :ot';
    }
    if (!empty($from_date)) {
        $query_insertos .= ' AND r.created_at >= :from';
    }
    if (!empty($to_date)) {
        $query_insertos .= ' AND r.created_at <= :to';
    }
    if (!empty($brand)) {
        $query_insertos .= ' AND i.brand LIKE :brand';
    }
}

// Build base query for herramientas
$query_tools = '';
if ($item_type === 'herramientas' || $item_type === 'ambos') {
    $query_tools = '
        SELECT 
            r.id as request_id,
            r.ot,
            r.created_at,
            r.delivery_date,
            r.approximate_return_date,
            u.username,
            u.dni,
            u.area,
            \'herramienta\' as item_type,
            t.code,
            t.code as item_code,
            t.brand,
            rti.cantidad,
            rti.unit_price,
            (rti.cantidad * rti.unit_price) as subtotal,
            r.status
        FROM requests r
        JOIN users u ON r.user_id = u.id
        JOIN request_tool_items rti ON r.id = rti.request_id
        JOIN tools t ON rti.tool_id = t.id
        WHERE r.status = \'accepted\'
    ';
    
    if (!empty($ot)) {
        $query_tools .= ' AND r.ot LIKE :ot';
    }
    if (!empty($from_date)) {
        $query_tools .= ' AND r.created_at >= :from';
    }
    if (!empty($to_date)) {
        $query_tools .= ' AND r.created_at <= :to';
    }
    if (!empty($brand)) {
        $query_tools .= ' AND t.brand LIKE :brand';
    }
}

// Combine queries
$combined_query = '';
if ($item_type === 'ambos') {
    $combined_query = '(' . $query_insertos . ') UNION ALL (' . $query_tools . ') ORDER BY created_at DESC';
} elseif ($item_type === 'insertos') {
    $combined_query = $query_insertos . ' ORDER BY created_at DESC';
} else {
    $combined_query = $query_tools . ' ORDER BY created_at DESC';
}

// Prepare parameters
$params = [];
if (!empty($ot)) {
    $params['ot'] = '%' . $ot . '%';
}
if (!empty($from_date)) {
    $params['from'] = $from_date . ' 00:00:00';
}
if (!empty($to_date)) {
    $params['to'] = $to_date . ' 23:59:59';
}
if (!empty($brand)) {
    $params['brand'] = '%' . $brand . '%';
}

$stmt = $pdo->prepare($combined_query);
$stmt->execute($params);
$report_items = $stmt->fetchAll();

// Calculate total
$total = 0;
foreach ($report_items as $item) {
    $total += $item['subtotal'] ?? 0;
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reportes - Sistema de Insertos</title>
  <link rel="stylesheet" href="styles.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    @media print {
      .navbar, .print-hide, button, a, .filters { display: none; }
      body { margin: 0; padding: 20px; }
      .container { margin: 0;}
      table { page-break-inside: avoid; }
    }
  </style>
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
      <h1 class="page-title">📊 Módulo de Reportes</h1>
      <p class="page-subtitle">Visualiza y analiza solicitudes aprobadas</p>
    </div>

    <!-- FILTROS -->
    <div class="card" style="margin-bottom: 20px;">
      <h3 style="margin-bottom: 15px; color: #003d7a;">Filtros</h3>
      <form id="filter-form" method="get" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; align-items: flex-end;">
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">OT</label>
          <input type="text" name="ot" value="<?=htmlspecialchars($ot)?>" placeholder="Ej: 1111, 2222" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        </div>
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Desde</label>
          <input type="date" name="from" value="<?=$from_date?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        </div>
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Hasta</label>
          <input type="date" name="to" value="<?=$to_date?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        </div>
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Marca</label>
          <input type="text" name="brand" value="<?=htmlspecialchars($brand)?>" placeholder="Ej: Sandvik, Seco" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        </div>
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Tipo</label>
          <select name="type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            <option value="ambos" <?=$item_type === 'ambos' ? 'selected' : ''?>>Ambos</option>
            <option value="insertos" <?=$item_type === 'insertos' ? 'selected' : ''?>>Solo Insertos</option>
            <option value="herramientas" <?=$item_type === 'herramientas' ? 'selected' : ''?>>Solo Herramientas</option>
          </select>
        </div>
      </form>
      <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="submit" form="filter-form" style="padding: 10px 20px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
          🔍 Filtrar
        </button>
        <a href="admin_reports_module.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Limpiar</a>
        <button onclick="window.print()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
          🖨️ Imprimir
        </button>
      </div>
    </div>

    <!-- REPORTE -->
    <div class="card">
      <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; color: #003d7a;">Resultados (<?=count($report_items)?> items)</h3>
        <?php if (!empty($report_items)): ?>
          <div style="font-size: 18px; font-weight: bold; color: #0055b8;">
            Total: $<?=number_format($total, 2)?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (empty($report_items)): ?>
        <p style="color: #666; text-align: center; padding: 40px;">No hay resultados para mostrar con los filtros seleccionados.</p>
      <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">Tipo</th>
              <th style="text-align: left; padding: 10px;">OT</th>
              <th style="text-align: left; padding: 10px;">Código</th>
              <th style="text-align: left; padding: 10px;">Marca</th>
              <th style="text-align: left; padding: 10px;">Usuario</th>
              <th style="text-align: left; padding: 10px;">Área</th>
              <th style="text-align: center; padding: 10px;">Cantidad</th>
              <th style="text-align: right; padding: 10px;">P.U.</th>
              <th style="text-align: right; padding: 10px;">Subtotal</th>
              <th style="text-align: left; padding: 10px;">Fecha Solicitud</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report_items as $item): ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;">
                  <span style="padding: 3px 8px; border-radius: 3px; background: <?=$item['item_type'] === 'inserto' ? '#0055b8' : '#c83e1d'?>; color: white; font-size: 11px; font-weight: bold;">
                    <?=$item['item_type'] === 'inserto' ? 'INSERTO' : 'HERRAMIENTA'?>
                  </span>
                </td>
                <td style="padding: 10px; font-weight: bold;"><?=htmlspecialchars($item['ot'] ?? '-')?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($item['item_code'])?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($item['brand'] ?? '-')?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($item['username'])?></td>
                <td style="padding: 10px; font-size: 12px; color: #666;"><?=htmlspecialchars($item['area'] ?? '-')?></td>
                <td style="text-align: center; padding: 10px;"><?=$item['cantidad']?></td>
                <td style="text-align: right; padding: 10px;">$<?=$item['unit_price'] !== null ? number_format($item['unit_price'], 2) : '-'?></td>
                <td style="text-align: right; padding: 10px; font-weight: bold;">$<?=number_format($item['subtotal'] ?? 0, 2)?></td>
                <td style="padding: 10px; font-size: 12px;"><?=date('d/m/Y H:i', strtotime($item['created_at']))?></td>
              </tr>
            <?php endforeach; ?>
            <tr style="border-top: 2px solid #ddd; background: #f9f9f9; font-weight: bold;">
              <td colspan="8" style="padding: 10px; text-align: right;">TOTAL:</td>
              <td style="text-align: right; padding: 10px;">$<?=number_format($total, 2)?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div style="margin-top: 20px; display: flex; gap: 10px;">
      <a href="dashboard.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver al Dashboard</a>
    </div>
  </div>
</body>
</html>
