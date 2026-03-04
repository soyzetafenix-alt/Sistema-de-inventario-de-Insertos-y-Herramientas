<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$tab = $_GET['tab'] ?? 'pending';
$filters = [];
$requests = [];

// Get requests based on tab
if ($tab === 'pending') {
    $query = 'SELECT r.id, r.user_id, u.username, u.area, r.status, r.created_at, r.delivery_date, r.approximate_return_date FROM requests r JOIN users u ON r.user_id = u.id WHERE r.status = :status ORDER BY r.created_at DESC';
} elseif ($tab === 'accepted') {
    $query = 'SELECT r.id, r.user_id, u.username, u.area, r.status, r.created_at, r.delivery_date, r.approximate_return_date FROM requests r JOIN users u ON r.user_id = u.id WHERE r.status = :status ORDER BY r.created_at DESC';
} else {
    $query = 'SELECT r.id, r.user_id, u.username, u.area, r.status, r.created_at, r.delivery_date, r.approximate_return_date FROM requests r JOIN users u ON r.user_id = u.id WHERE r.status = :status ORDER BY r.created_at DESC';
}

$stmt = $pdo->prepare($query);
$stmt->execute(['status' => $tab]);
$requests = $stmt->fetchAll();

// Handle accept/reject
$action_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $request_id = $_POST['request_id'] ?? null;
    
    if ($request_id) {
        if ($action === 'accept') {
            try {
                // Call the stored procedure
                $call = $pdo->prepare('SELECT accept_request(:req_id, :admin_id)');
                $call->execute(['req_id' => $request_id, 'admin_id' => $user['id']]);
                $action_result = 'success:Solicitud aceptada correctamente';
            } catch (Exception $e) {
                $action_result = 'error:' . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $reason = $_POST['reason'] ?? '';
            try {
                $call = $pdo->prepare('SELECT reject_request(:req_id, :admin_id, :reason)');
                $call->execute(['req_id' => $request_id, 'admin_id' => $user['id'], 'reason' => $reason]);
                $action_result = 'success:Solicitud rechazada';
            } catch (Exception $e) {
                $action_result = 'error:' . $e->getMessage();
            }
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Solicitudes - Panel de Admin</title>
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
      <h1 class="page-title">Solicitudes de Insertos</h1>
      <p class="page-subtitle">Panel de administración - Aprobación y seguimiento</p>
    </div>

    <?php if (strpos($action_result, 'success:') === 0): ?>
      <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        ✓ <?=htmlspecialchars(substr($action_result, 8))?>
      </div>
      <?php header('Refresh: 2'); ?>
    <?php elseif (strpos($action_result, 'error:') === 0): ?>
      <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        ❌ <?=htmlspecialchars(substr($action_result, 6))?>
      </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
      <a href="?tab=pending" style="padding: 10px 20px; background: <?=$tab === 'pending' ? '#0055b8' : '#ddd'?>; color: <?=$tab === 'pending' ? 'white' : '#333'?>; text-decoration: none; border-radius: 4px; border: none; cursor: pointer;">
        ⏳ Pendientes
      </a>
      <a href="?tab=accepted" style="padding: 10px 20px; background: <?=$tab === 'accepted' ? '#0055b8' : '#ddd'?>; color: <?=$tab === 'accepted' ? 'white' : '#333'?>; text-decoration: none; border-radius: 4px; border: none; cursor: pointer;">
        ✓ Aceptadas
      </a>
      <a href="?tab=rejected" style="padding: 10px 20px; background: <?=$tab === 'rejected' ? '#0055b8' : '#ddd'?>; color: <?=$tab === 'rejected' ? 'white' : '#333'?>; text-decoration: none; border-radius: 4px; border: none; cursor: pointer;">
        ✗ Rechazadas
      </a>
    </div>

    <?php if (empty($requests)): ?>
      <div class="card">
        <p style="text-align: center; color: #666; padding: 40px;">
          No hay solicitudes en esta categoría.
        </p>
      </div>
    <?php else: ?>
      <div class="card">
        <h2 style="margin-bottom: 15px">Total: <?=count($requests)?> solicitudes</h2>
        
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">#ID</th>
              <th style="text-align: left; padding: 10px;">Usuario</th>
              <th style="text-align: left; padding: 10px;">Área</th>
              <th style="text-align: left; padding: 10px;">Fecha Solicitud</th>
              <th style="text-align: left; padding: 10px;">Entrega</th>
              <th style="text-align: left; padding: 10px;">Devolución</th>
              <th style="text-align: center; padding: 10px;">Items</th>
              <th style="text-align: center; padding: 10px;">Debe</th>
              <th style="text-align: center; padding: 10px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $req): ?>
              <?php
              // Get request items
              $items_stmt = $pdo->prepare('SELECT ri.cantidad, ri.returned, i.code FROM request_items ri JOIN insertos i ON ri.inserto_id = i.id WHERE ri.request_id = :req_id');
              $items_stmt->execute(['req_id' => $req['id']]);
              $items = $items_stmt->fetchAll();
              
              // Check ALL accepted requests for this user to see if they have outstanding items
              $user_outstanding_stmt = $pdo->prepare('
                SELECT COUNT(*) as cnt FROM request_items ri
                JOIN requests r ON ri.request_id = r.id
                WHERE r.user_id = :user_id AND r.status = :status AND ri.returned = FALSE
              ');
              $user_outstanding_stmt->execute(['user_id' => $req['user_id'], 'status' => 'accepted']);
              $user_outstanding_count = $user_outstanding_stmt->fetch()['cnt'];
              $debe = $user_outstanding_count > 0 ? 'Sí' : 'No';
              ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><strong>#<?=$req['id']?></strong></td>
                <td style="padding: 10px;"><?=htmlspecialchars($req['username'])?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($req['area'] ?? '-')?></td>
                <td style="padding: 10px;"><?=date('d/m/Y', strtotime($req['created_at']))?></td>
                <td style="padding: 10px;"><?=$req['delivery_date'] ? date('d/m/Y', strtotime($req['delivery_date'])) : '-'?></td>
                <td style="padding: 10px;"><?=$req['approximate_return_date'] ? date('d/m/Y', strtotime($req['approximate_return_date'])) : '-'?></td>
                <td style="text-align: center; padding: 10px;"><?=count($items)?></td>
                <td style="text-align: center; padding: 10px;">
                  <span style="color: <?=$debe === 'Sí' ? '#dc3545' : '#28a745'?>; font-weight: bold;">
                    <?=$debe?>
                  </span>
                </td>
                <td style="text-align: center; padding: 10px;">
                  <a href="request_detail.php?id=<?=$req['id']?>" style="color: #0055b8; text-decoration: none; margin-right: 5px;">👁️ Ver</a>
                  <?php if ($req['status'] === 'pending'): ?>
                    <button onclick="openAccept(<?=$req['id']?>)" style="color: green; background: none; border: none; cursor: pointer; margin-right: 5px;">✓ Aceptar</button>
                    <button onclick="openReject(<?=$req['id']?>)" style="color: red; background: none; border: none; cursor: pointer;">✗ Rechazar</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Modal Aceptar -->
  <div id="modal_accept" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 400px;">
      <h2 style="margin-bottom: 15px;">Confirmación</h2>
      <p style="margin-bottom: 20px;">¿Está seguro de que desea aceptar esta solicitud?</p>
      <form method="post" style="display: flex; gap: 10px;">
        <input type="hidden" name="action" value="accept">
        <input type="hidden" name="request_id" id="modal_req_id" value="">
        <button type="submit" style="flex: 1; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Sí, aceptar</button>
        <button type="button" onclick="closeModals()" style="flex: 1; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
      </form>
    </div>
  </div>

  <!-- Modal Rechazar -->
  <div id="modal_reject" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 400px;">
      <h2 style="margin-bottom: 15px;">Rechazar Solicitud</h2>
      <form method="post">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="request_id" id="modal_req_id_reject" value="">
        
        <label style="display: block; margin-bottom: 10px;">Motivo del rechazo <span style="color: red;">*</span></label>
        <textarea name="reason" required style="width: 100%; height: 80px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;"></textarea>
        
        <div style="display: flex; gap: 10px;">
          <button type="submit" style="flex: 1; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Rechazar</button>
          <button type="button" onclick="closeModals()" style="flex: 1; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAccept(reqId) {
      document.getElementById('modal_req_id').value = reqId;
      document.getElementById('modal_accept').style.display = 'flex';
    }

    function openReject(reqId) {
      document.getElementById('modal_req_id_reject').value = reqId;
      document.getElementById('modal_reject').style.display = 'flex';
    }

    function closeModals() {
      document.getElementById('modal_accept').style.display = 'none';
      document.getElementById('modal_reject').style.display = 'none';
    }
  </script>
</body>
</html>
