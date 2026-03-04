<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: admin_users.php');
    exit;
}

// Get user details
$user_stmt = $pdo->prepare('SELECT id, username, dni, area, role, is_active, created_at FROM users WHERE id = :id');
$user_stmt->execute(['id' => $user_id]);
$profile_user = $user_stmt->fetch();

if (!$profile_user) {
    header('Location: admin_users.php');
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all'; // all, devuelto, no_devuelto

// Get accepted requests for this user
$query = '
    SELECT r.id, r.created_at, r.delivery_date, r.approximate_return_date
    FROM requests r
    WHERE r.user_id = :user_id AND r.status = :status
    ORDER BY r.created_at DESC
';
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $user_id, 'status' => 'accepted']);
$requests = $stmt->fetchAll();

// Mark item as returned/not returned
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_returned') {
        $item_id = $_POST['item_id'] ?? null;
        $item_type = $_POST['item_type'] ?? 'inserto'; // inserto or tool
        if ($item_id) {
            try {
                // Get current status
                $table = $item_type === 'tool' ? 'request_tool_items' : 'request_items';
                
                // Toggle the returned status and update the returned_date accordingly
                $update = $pdo->prepare("
                    UPDATE $table 
                    SET returned = NOT returned, 
                        returned_date = CASE WHEN NOT returned THEN NOW() ELSE NULL END 
                    WHERE id = :id
                ");
                $update->execute(['id' => $item_id]);
                
                header('Location: admin_user_detail.php?id=' . $user_id . '&filter=' . $filter);
                exit;
            } catch (Exception $e) {
                // Error silently
            }
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Gestión de Devoluciones - <?=htmlspecialchars($profile_user['username'])?></title>
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
      <h1 class="page-title">📦 Devoluciones - <?=htmlspecialchars($profile_user['username'])?></h1>
      <p class="page-subtitle">Gestiona el estado de devolución de insertos</p>
    </div>

    <!-- Información del usuario -->
    <div class="card" style="margin-bottom: 20px;">
      <h2 style="margin-bottom: 15px; color: #003d7a;">Información del Usuario</h2>
      <table style="width: 100%; border-collapse: collapse;">
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px; width: 150px;"><strong>Usuario:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($profile_user['username'])?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>DNI:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($profile_user['dni'] ?? '-')?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 10px;"><strong>Área:</strong></td>
          <td style="padding: 10px;"><?=htmlspecialchars($profile_user['area'] ?? '-')?></td>
        </tr>
        <tr>
          <td style="padding: 10px;"><strong>Estado:</strong></td>
          <td style="padding: 10px;">
            <span style="padding: 3px 8px; border-radius: 3px; background: <?=$profile_user['is_active'] ? '#28a745' : '#6c757d'?>; color: white; font-size: 12px; font-weight: bold;">
              <?=$profile_user['is_active'] ? 'Activo' : 'Inactivo'?>
            </span>
          </td>
        </tr>
      </table>
    </div>

    <!-- Filtros -->
    <div class="card" style="margin-bottom: 20px;">
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="admin_user_detail.php?id=<?=$user_id?>&filter=all" style="padding: 8px 16px; border-radius: 4px; background: <?=$filter === 'all' ? '#0055b8' : '#f0f0f0'?>; color: <?=$filter === 'all' ? 'white' : '#333'?>; text-decoration: none; font-weight: 500;">
          📋 Todos
        </a>
        <a href="admin_user_detail.php?id=<?=$user_id?>&filter=no_devuelto" style="padding: 8px 16px; border-radius: 4px; background: <?=$filter === 'no_devuelto' ? '#dc3545' : '#f0f0f0'?>; color: <?=$filter === 'no_devuelto' ? 'white' : '#333'?>; text-decoration: none; font-weight: 500;">
          ⏳ No Devuelto
        </a>
        <a href="admin_user_detail.php?id=<?=$user_id?>&filter=devuelto" style="padding: 8px 16px; border-radius: 4px; background: <?=$filter === 'devuelto' ? '#28a745' : '#f0f0f0'?>; color: <?=$filter === 'devuelto' ? 'white' : '#333'?>; text-decoration: none; font-weight: 500;">
          ✓ Devuelto
        </a>
      </div>
    </div>

    <!-- Insertos y Herramientas por solicitud -->
    <div class="card">
      <?php if (empty($requests)): ?>
        <p style="color: #999; text-align: center; padding: 20px;">Este usuario no tiene solicitudes aceptadas.</p>
      <?php else: ?>
        <?php foreach ($requests as $req): ?>
          <?php
          // Get insertos for this request
          $insertos_stmt = $pdo->prepare('
            SELECT ri.id, ri.cantidad, ri.returned, ri.returned_date, i.code, COALESCE(i.detalle, i.descripcion) AS detalle, i.cutting_conditions
            FROM request_items ri
            JOIN insertos i ON ri.inserto_id = i.id
            WHERE ri.request_id = :req_id
            ORDER BY i.code ASC
          ');
          $insertos_stmt->execute(['req_id' => $req['id']]);
          $insertos = $insertos_stmt->fetchAll();

          // Get herramientas for this request
          $tools_stmt = $pdo->prepare('
            SELECT rti.id, rti.cantidad, rti.returned, rti.returned_date, t.code, t.brand, t.detalles
            FROM request_tool_items rti
            JOIN tools t ON rti.tool_id = t.id
            WHERE rti.request_id = :req_id
            ORDER BY t.code ASC
          ');
          $tools_stmt->execute(['req_id' => $req['id']]);
          $tools = $tools_stmt->fetchAll();

          // Filter insertos based on filter
          $filtered_insertos = [];
          foreach ($insertos as $item) {
            if ($filter === 'all') {
              $filtered_insertos[] = $item;
            } elseif ($filter === 'devuelto' && $item['returned']) {
              $filtered_insertos[] = $item;
            } elseif ($filter === 'no_devuelto' && !$item['returned']) {
              $filtered_insertos[] = $item;
            }
          }

          // Filter herramientas based on filter
          $filtered_tools = [];
          foreach ($tools as $item) {
            if ($filter === 'all') {
              $filtered_tools[] = $item;
            } elseif ($filter === 'devuelto' && $item['returned']) {
              $filtered_tools[] = $item;
            } elseif ($filter === 'no_devuelto' && !$item['returned']) {
              $filtered_tools[] = $item;
            }
          }

          // Skip this request if no items match filter
          if (empty($filtered_insertos) && empty($filtered_tools) && $filter !== 'all') {
            continue;
          }
          ?>

          <div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #eee;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
              <h3 style="margin: 0; color: #003d7a;">Solicitud #<?=$req['id']?></h3>
              <div style="font-size: 12px; color: #666;">
                <span>Fecha: <?=date('d/m/Y', strtotime($req['created_at']))?></span>
                <span style="margin-left: 15px;">Entrega: <?=$req['delivery_date'] ? date('d/m/Y', strtotime($req['delivery_date'])) : '-'?></span>
              </div>
            </div>

            <!-- INSERTOS SECTION -->
            <?php if (!empty($insertos)): ?>
              <div style="margin-bottom: 20px;">
                <h4 style="color: #0055b8; margin-bottom: 10px; font-size: 14px;">📌 INSERTOS</h4>
                <table style="width: 100%; border-collapse: collapse;">
                  <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                      <th style="text-align: left; padding: 10px;">Código</th>
                      <th style="text-align: left; padding: 10px;">Detalle</th>
                      <th style="text-align: left; padding: 10px;">Condiciones</th>
                      <th style="text-align: center; padding: 10px;">Cantidad</th>
                      <th style="text-align: center; padding: 10px;">Estado</th>
                      <th style="text-align: center; padding: 10px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($filtered_insertos as $item): ?>
                      <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;"><?=htmlspecialchars($item['code'])?></td>
                        <td style="padding: 10px;"><?=htmlspecialchars($item['detalle'] ?? '-')?></td>
                        <td style="padding: 10px; font-size: 12px; color: #666;"><?=htmlspecialchars(substr($item['cutting_conditions'] ?? '', 0, 30))?></td>
                        <td style="text-align: center; padding: 10px;"><?=$item['cantidad']?></td>
                        <td style="text-align: center; padding: 10px;">
                          <span style="padding: 4px 10px; border-radius: 3px; background: <?=$item['returned'] ? '#28a745' : '#dc3545'?>; color: white; font-size: 11px; font-weight: bold;">
                            <?=$item['returned'] ? '✓ DEVUELTO' : '⏳ NO DEVUELTO'?>
                          </span>
                        </td>
                        <td style="text-align: center; padding: 10px;">
                          <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_returned">
                            <input type="hidden" name="item_id" value="<?=$item['id']?>">
                            <input type="hidden" name="item_type" value="inserto">
                            <button type="submit" style="background: none; border: none; color: <?=$item['returned'] ? '#dc3545' : '#28a745'?>; cursor: pointer; text-decoration: underline; font-weight: 500; font-size: 12px;">
                              <?=$item['returned'] ? 'Marcar No Devuelto' : 'Marcar Devuelto'?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php if (empty($filtered_insertos) && $filter !== 'all'): ?>
                  <p style="color: #999; font-size: 12px; padding: 10px 0;">No hay insertos con esta condición.</p>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- HERRAMIENTAS SECTION -->
            <?php if (!empty($tools)): ?>
              <div style="margin-bottom: 0;">
                <h4 style="color: #c83e1d; margin-bottom: 10px; font-size: 14px;">🔧 HERRAMIENTAS</h4>
                <table style="width: 100%; border-collapse: collapse;">
                  <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                      <th style="text-align: left; padding: 10px;">Código</th>
                      <th style="text-align: left; padding: 10px;">Marca</th>
                      <th style="text-align: left; padding: 10px;">Detalles</th>
                      <th style="text-align: center; padding: 10px;">Cantidad</th>
                      <th style="text-align: center; padding: 10px;">Estado</th>
                      <th style="text-align: center; padding: 10px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($filtered_tools as $item): ?>
                      <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;"><?=htmlspecialchars($item['code'])?></td>
                        <td style="padding: 10px;"><?=htmlspecialchars($item['brand'] ?? '-')?></td>
                        <td style="padding: 10px; font-size: 12px; color: #666;"><?=htmlspecialchars(substr($item['detalles'] ?? '', 0, 50))?></td>
                        <td style="text-align: center; padding: 10px;"><?=$item['cantidad']?></td>
                        <td style="text-align: center; padding: 10px;">
                          <span style="padding: 4px 10px; border-radius: 3px; background: <?=$item['returned'] ? '#28a745' : '#dc3545'?>; color: white; font-size: 11px; font-weight: bold;">
                            <?=$item['returned'] ? '✓ DEVUELTO' : '⏳ NO DEVUELTO'?>
                          </span>
                        </td>
                        <td style="text-align: center; padding: 10px;">
                          <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_returned">
                            <input type="hidden" name="item_id" value="<?=$item['id']?>">
                            <input type="hidden" name="item_type" value="tool">
                            <button type="submit" style="background: none; border: none; color: <?=$item['returned'] ? '#dc3545' : '#28a745'?>; cursor: pointer; text-decoration: underline; font-weight: 500; font-size: 12px;">
                              <?=$item['returned'] ? 'Marcar No Devuelto' : 'Marcar Devuelto'?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php if (empty($filtered_tools) && $filter !== 'all'): ?>
                  <p style="color: #999; font-size: 12px; padding: 10px 0;">No hay herramientas con esta condición.</p>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (empty($insertos) && empty($tools)): ?>
              <p style="color: #999; text-align: center; padding: 20px;">Esta solicitud no tiene insertos ni herramientas.</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if (empty($filtered_items) && $filter !== 'all'): ?>
          <p style="color: #999; text-align: center; padding: 20px;">No hay items con esta condición.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div style="margin-top: 20px; display: flex; gap: 10px;">
      <a href="admin_users.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver a Usuarios</a>
    </div>
  </div>
</body>
</html>
