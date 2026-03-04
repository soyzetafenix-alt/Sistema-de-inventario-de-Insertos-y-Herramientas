<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$tool_id = $_GET['id'] ?? null;
if (!$tool_id) {
    header('Location: search.php');
    exit;
}

// Get tool
$tool_stmt = $pdo->prepare('SELECT * FROM tools WHERE id = :id');
$tool_stmt->execute(['id' => $tool_id]);
$tool = $tool_stmt->fetch();

if (!$tool) {
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
            $before_stock = $tool['stock'];
            $after_stock = $before_stock + $amount;
            
            $pdo->prepare('UPDATE tools SET stock = :stock WHERE id = :id')
                ->execute(['stock' => $after_stock, 'id' => $tool_id]);
            
            $success = '✓ Stock de herramienta actualizado correctamente';
            $tool['stock'] = $after_stock;
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
  <title>Poner Stock - <?=htmlspecialchars($tool['code'])?></title>
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
      <h1 class="page-title">Poner Stock (Herramienta): <?=htmlspecialchars($tool['code'])?></h1>
      <p class="page-subtitle">Stock actual: <strong><?=$tool['stock']?></strong></p>
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
          <a href="search.php?type=herramientas" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Volver</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>

