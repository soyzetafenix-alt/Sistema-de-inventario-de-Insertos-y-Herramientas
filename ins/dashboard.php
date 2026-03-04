<?php
require_once __DIR__ . '/db.php';
require_login();
$user = $_SESSION['user'];

// Count unread notifications for users
$unread_count = 0;
if ($user['role'] === 'user') {
    $notif_stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :user_id AND is_read = FALSE');
    $notif_stmt->execute(['user_id' => $user['id']]);
    $unread_count = $notif_stmt->fetch()['cnt'] ?? 0;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard - Sistema de Insertos</title>
  <link rel="stylesheet" href="styles.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
  <!-- NAVBAR -->
  <div class="navbar">
    <a href="dashboard.php" class="navbar-logo">
      <div class="navbar-logo-icon">⚙️</div>
      <span>VALMET Insertos</span>
    </a>
    <div class="navbar-user">
      <span><?=htmlspecialchars($user['username'])?> <strong>(<?=htmlspecialchars($user['role'])?>)</strong></span>
      <?php if ($user['role'] === 'user' && $unread_count > 0): ?>
        <a href="notifications.php" style="position: relative; margin-right: 10px; padding: 5px 10px; border-radius: 4px; background: #fff3cd; text-decoration: none; color: #0055b8; font-weight: 500; display: inline-flex; align-items: center; gap: 5px;">
          📬 <?=$unread_count?>
        </a>
      <?php elseif ($user['role'] === 'user'): ?>
        <a href="notifications.php" style="margin-right: 10px; text-decoration: none; color: #0055b8;">📬 Notificaciones</a>
      <?php endif; ?>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="container">
    <div class="page-header">
      <h1 class="page-title">Bienvenido, <?=htmlspecialchars($user['username'])?></h1>
      <p class="page-subtitle">Sistema de Gestión de Insertos</p>
    </div>

    <?php if ($user['role'] === 'admin'): ?>
      <div class="dashboard-grid">
        <a href="create_user.php" class="dashboard-card">
          <div class="dashboard-card-icon">👤</div>
          <div class="dashboard-card-title">Crear usuario</div>
          <div class="dashboard-card-text">Añadir nuevo usuario al sistema</div>
        </a>
        <a href="admin_users.php" class="dashboard-card">
          <div class="dashboard-card-icon">👥</div>
          <div class="dashboard-card-title">Gestionar usuarios</div>
          <div class="dashboard-card-text">Ver y activar/desactivar usuarios</div>
        </a>
        <a href="create_inserto.php" class="dashboard-card">
          <div class="dashboard-card-icon">📦</div>
          <div class="dashboard-card-title">Crear inserto</div>
          <div class="dashboard-card-text">Registrar nuevo inserto</div>
        </a>
        <a href="create_tool.php" class="dashboard-card">
          <div class="dashboard-card-icon">🛠️</div>
          <div class="dashboard-card-title">Crear herramienta</div>
          <div class="dashboard-card-text">Registrar nueva herramienta</div>
        </a>
        <a href="search.php" class="dashboard-card">
          <div class="dashboard-card-icon">🔍</div>
          <div class="dashboard-card-title">Buscar insertos / herramientas</div>
          <div class="dashboard-card-text">Gestionar inventario y stock</div>
        </a>
        <a href="requests.php" class="dashboard-card">
          <div class="dashboard-card-icon">📋</div>
          <div class="dashboard-card-title">Solicitudes</div>
          <div class="dashboard-card-text">Revisar y aprobar solicitudes</div>
        </a>
        <a href="admin_reports_module.php" class="dashboard-card">
          <div class="dashboard-card-icon">📊</div>
          <div class="dashboard-card-title">Reportes</div>
          <div class="dashboard-card-text">Reportes de solicitudes aprobadas</div>
        </a>
      </div>
    <?php else: ?>
      <div class="dashboard-grid">
        <a href="search.php" class="dashboard-card">
          <div class="dashboard-card-icon">🔍</div>
          <div class="dashboard-card-title">Buscar insertos / herramientas</div>
          <div class="dashboard-card-text">Encuentra lo que necesitas</div>
        </a>
        <a href="cart.php" class="dashboard-card">
          <div class="dashboard-card-icon">🛒</div>
          <div class="dashboard-card-title">Mi carrito</div>
          <div class="dashboard-card-text">Revisa tus solicitudes pendientes</div>
        </a>
        <a href="notifications.php" class="dashboard-card">
          <div class="dashboard-card-icon">📬</div>
          <div class="dashboard-card-title">Notificaciones</div>
          <div class="dashboard-card-text"><?=$unread_count > 0 ? $unread_count . ' sin leer' : 'Tu bandeja de entrada'?></div>
        </a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
