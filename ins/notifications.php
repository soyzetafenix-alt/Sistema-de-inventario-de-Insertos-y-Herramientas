<?php
require_once __DIR__ . '/db.php';
require_login();
$user = $_SESSION['user'];

if ($user['role'] !== 'user') {
    header('Location: dashboard.php');
    exit;
}

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read') {
        $notif_id = $_POST['notif_id'] ?? null;
        if ($notif_id) {
            $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id')
                ->execute(['id' => $notif_id, 'user_id' => $user['id']]);
        }
    } elseif ($_POST['action'] === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE')
            ->execute(['user_id' => $user['id']]);
    } elseif ($_POST['action'] === 'delete') {
        $notif_id = $_POST['notif_id'] ?? null;
        if ($notif_id) {
            $pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :user_id')
                ->execute(['id' => $notif_id, 'user_id' => $user['id']]);
        }
    }
    header('Location: notifications.php');
    exit;
}

// Get all notifications
$notif_stmt = $pdo->prepare('
    SELECT id, type, message, is_read, created_at
    FROM notifications
    WHERE user_id = :user_id
    ORDER BY created_at DESC
');
$notif_stmt->execute(['user_id' => $user['id']]);
$notifications = $notif_stmt->fetchAll();

// Count unread
$unread_stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :user_id AND is_read = FALSE');
$unread_stmt->execute(['user_id' => $user['id']]);
$unread_count = $unread_stmt->fetch()['cnt'];

// Icon map for notification types
$type_icons = [
    'request_accepted' => '✓',
    'request_rejected' => '✗',
    'request_pending' => '📋',
    'request_created' => '📝',
    'info' => 'ℹ️'
];

$type_colors = [
    'request_accepted' => '#28a745',
    'request_rejected' => '#dc3545',
    'request_pending' => '#ffc107',
    'request_created' => '#0055b8',
    'info' => '#6c757d'
];

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Notificaciones - Sistema de Insertos</title>
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
      <h1 class="page-title">📬 Mis Notificaciones</h1>
      <p class="page-subtitle">Tu bandeja de entrada de solicitudes</p>
    </div>

    <?php if ($unread_count > 0): ?>
      <div style="margin-bottom: 15px; text-align: right;">
        <p style="display: inline; color: #666; margin-right: 10px;">
          <strong><?=$unread_count?></strong> sin leer
        </p>
        <form method="post" style="display: inline;">
          <input type="hidden" name="action" value="mark_all_read">
          <button type="submit" style="background: none; border: none; color: #0055b8; cursor: pointer; text-decoration: underline; font-weight: 500;">
            Marcar todas como leídas
          </button>
        </form>
      </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
      <div class="card" style="text-align: center; padding: 40px;">
        <p style="font-size: 13px; color: #999;">No tienes notificaciones</p>
      </div>
    <?php else: ?>
      <div class="card">
        <?php foreach ($notifications as $notif): ?>
          <?php
          $icon = $type_icons[$notif['type']] ?? 'ℹ️';
          $color = $type_colors[$notif['type']] ?? '#6c757d';
          $bg_opacity = $notif['is_read'] ? '#f9f9f9' : '#f0f7ff';
          $border_left = $notif['is_read'] ? '#ddd' : $color;
          ?>
          <div style="display: flex; gap: 15px; padding: 15px; border-bottom: 1px solid #eee; border-left: 4px solid <?=$border_left?>; background: <?=$bg_opacity?>; align-items: flex-start;">
            <div style="font-size: 24px; min-width: 30px; text-align: center;">
              <?=$icon?>
            </div>
            <div style="flex-grow: 1;">
              <p style="margin: 0; font-size: 13px; color: #999; font-weight: 500;">
                <?=date('d/m/Y H:i', strtotime($notif['created_at']))?>
                <?php if (!$notif['is_read']): ?>
                  <span style="display: inline-block; width: 8px; height: 8px; background: #0055b8; border-radius: 50%; margin-left: 10px; vertical-align: middle;"></span>
                <?php endif; ?>
              </p>
              <p style="margin: 5px 0 0 0; font-size: 14px; color: #333; line-height: 1.4;">
                <?=htmlspecialchars($notif['message'])?>
              </p>
            </div>
            <div style="display: flex; gap: 8px;">
              <?php if (!$notif['is_read']): ?>
                <form method="post" style="display: inline;">
                  <input type="hidden" name="action" value="mark_read">
                  <input type="hidden" name="notif_id" value="<?=$notif['id']?>">
                  <button type="submit" style="background: none; border: none; color: #0055b8; cursor: pointer; font-size: 12px; text-decoration: underline;">
                    Leer
                  </button>
                </form>
              <?php endif; ?>
              <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="notif_id" value="<?=$notif['id']?>">
                <button type="submit" style="background: none; border: none; color: #999; cursor: pointer; font-size: 12px; text-decoration: underline;">
                  Eliminar
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
      <a href="dashboard.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Volver</a>
    </div>
  </div>
</body>
</html>
