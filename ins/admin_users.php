<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$search = $_GET['search'] ?? '';
$users = [];

if ($search !== '') {
    $search_term = '%' . $search . '%';
    $stmt = $pdo->prepare('
        SELECT id, username, dni, area, role, is_active, created_at
        FROM users
        WHERE username ILIKE :search OR dni ILIKE :search
        ORDER BY username ASC
    ');
    $stmt->execute(['search' => $search_term]);
    $users = $stmt->fetchAll();
}

// Toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
    $user_id = $_POST['user_id'] ?? null;
    if ($user_id && $user_id != $user['id']) {
        $toggle_stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = :id');
        $toggle_stmt->execute(['id' => $user_id]);
        header('Location: admin_users.php?search=' . urlencode($search));
        exit;
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Gestionar Usuarios - Panel de Admin</title>
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
      <h1 class="page-title">Gestionar Usuarios</h1>
      <p class="page-subtitle">Busca y controla el estado de los usuarios</p>
    </div>

    <div class="card" style="margin-bottom: 20px;">
      <form method="get" style="display: flex; gap: 10px;">
        <input type="text" name="search" placeholder="Buscar por usuario o DNI..." value="<?=htmlspecialchars($search)?>" style="flex-grow: 1;">
        <button type="submit" style="padding: 10px 20px;">🔍 Buscar</button>
      </form>
    </div>

    <?php if ($search === ''): ?>
      <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: #999;">Ingresa un usuario o DNI para buscar</p>
      </div>
    <?php elseif (empty($users)): ?>
      <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: #999;">No se encontraron usuarios</p>
      </div>
    <?php else: ?>
      <div class="card">
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <th style="text-align: left; padding: 10px;">Usuario</th>
              <th style="text-align: left; padding: 10px;">DNI</th>
              <th style="text-align: left; padding: 10px;">Área</th>
              <th style="text-align: center; padding: 10px;">Rol</th>
              <th style="text-align: center; padding: 10px;">Estado</th>
              <th style="text-align: center; padding: 10px;">Gestión</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px; font-weight: bold;"><?=htmlspecialchars($u['username'])?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($u['dni'] ?? '-')?></td>
                <td style="padding: 10px;"><?=htmlspecialchars($u['area'] ?? '-')?></td>
                <td style="text-align: center; padding: 10px;">
                  <span style="padding: 3px 8px; border-radius: 3px; background: <?=$u['role'] === 'admin' ? '#ffc107' : '#17a2b8'?>; color: white; font-size: 11px; font-weight: bold;">
                    <?=strtoupper($u['role'])?>
                  </span>
                </td>
                <td style="text-align: center; padding: 10px;">
                  <span style="padding: 3px 10px; border-radius: 3px; background: <?=$u['is_active'] ? '#28a745' : '#6c757d'?>; color: white; font-size: 11px; font-weight: bold;">
                    <?=$u['is_active'] ? 'Activo' : 'Inactivo'?>
                  </span>
                </td>
                <td style="text-align: center; padding: 10px;">
                  <a href="admin_user_detail.php?id=<?=$u['id']?>" style="color: #0055b8; text-decoration: none; margin-right: 10px; font-weight: 500;">📦 Devoluciones</a>
                  <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?=$u['id']?>">
                    <button type="submit" style="background: none; border: none; color: <?=$u['is_active'] ? '#dc3545' : '#28a745'?>; cursor: pointer; text-decoration: underline; font-size: 12px; font-weight: 500;">
                      <?=$u['is_active'] ? 'Desactivar' : 'Activar'?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
      <a href="dashboard.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver</a>
    </div>
  </div>
</body>
</html>
