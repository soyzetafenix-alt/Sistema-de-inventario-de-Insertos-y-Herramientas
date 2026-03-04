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

// Add stock 
$success = '';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? 0;
    $reason = $_POST['reason'] ?? 'Reposición manual';
    
    if ($amount <= 0) {
        $errors[] = 'La cantidad debe ser mayor a 0';
    }
    
    if (empty($errors)) {
        try {
            $before_stock = $inserto['stock'];
            $after_stock = $before_stock + $amount;
            
            $pdo->prepare('UPDATE insertos SET stock = :stock WHERE id = :id')->execute(['stock' => $after_stock, 'id' => $inserto_id]);
            
            $pdo->prepare('
                INSERT INTO stock_movements (inserto_id, change_amount, before_stock, after_stock, reason, performed_by)
                VALUES (:ins_id, :change, :before, :after, :reason, :admin)
            ')->execute([
                'ins_id' => $inserto_id,
                'change' => $amount,
                'before' => $before_stock,
                'after' => $after_stock,
                'reason' => $reason,
                'admin' => $user['id']
            ]);
            
            $success = '✓ Stock actualizado correctamente';
            $inserto['stock'] = $after_stock;
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Poner Stock - <?=htmlspecialchars($inserto['code'])?></title>
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
      <h1 class="page-title">Poner Stock: <?=htmlspecialchars($inserto['code'])?></h1>
      <p class="page-subtitle">Stock actual: <strong><?=$inserto['stock']?></strong></p>
    </div>

    <div class="card">
      <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
          <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
            ❌ <?=htmlspecialchars($err)?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
          <?=$success?>
        </div>
      <?php endif; ?>

      <form method="post">
        <div style="margin-bottom: 15px;">
          <label><strong>Cantidad a añadir</strong> <span style="color: red;">*</span></label><br>
          <input type="number" name="amount" required min="1" value="<?=$_POST['amount'] ?? ''?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>

        <div style="margin-bottom: 20px;">
          <label><strong>Motivo</strong></label><br>
          <input type="text" name="reason" value="<?=$_POST['reason'] ?? 'Reposición manual'?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>

        <div style="display: flex; gap: 10px;">
          <button type="submit" style="padding: 10px 20px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer;">✓ Guardar</button>
          <a href="search.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Volver</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
