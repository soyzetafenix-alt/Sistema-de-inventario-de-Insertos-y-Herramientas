<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $detalles = $_POST['detalles'] ?? null;
    $photo_url = $_POST['photo_url'] ?? null;
    $stock = intval($_POST['stock'] ?? 0);
    $price = $_POST['price'] !== '' ? floatval($_POST['price']) : null;

    if ($code === '') {
        $errors[] = 'El código es obligatorio';
    }

    if ($brand === '') {
        $errors[] = 'La marca es obligatoria';
    }

    if ($price === null || $price <= 0) {
        $errors[] = 'El precio debe ser mayor a 0';
    }

    if ($stock < 0) {
        $errors[] = 'El stock inicial no puede ser negativo';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO tools 
            (code, brand, detalles, photo_url, price, stock)
            VALUES
            (:code, :brand, :detalles, :photo_url, :price, :stock)
        ');
        try {
            $stmt->execute([
                'code' => $code,
                'brand' => $brand,
                'detalles' => $detalles,
                'photo_url' => $photo_url,
                'price' => $price,
                'stock' => $stock,
            ]);
            $success = 'Herramienta creada correctamente';
            $_POST = [];
        } catch (Exception $e) {
            $errors[] = 'Error al crear herramienta: ' . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Crear herramienta - Sistema de Insertos</title>
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
      <span><?=htmlspecialchars($user['username'])?></span>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="container">
    <div class="page-header flex">
      <div>
        <h1 class="page-title">Crear nueva herramienta</h1>
        <p class="page-subtitle">Completa el formulario con los detalles de la herramienta</p>
      </div>
      <a href="dashboard.php" class="btn btn-secondary">← Volver</a>
    </div>

    <div class="card">
      <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger">
            <span>❌</span>
            <span><?=htmlspecialchars($err)?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success">
          <span>✓</span>
          <span><?=htmlspecialchars($success)?></span>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="form-row">
          <div class="field">
            <label>código <sup>*</sup></label>
            <input type="text" name="code" required placeholder="ej: HERR-001" value="<?=htmlspecialchars($_POST['code'] ?? '')?>">
          </div>
          <div class="field">
            <label>marca <sup>*</sup></label>
            <input type="text" name="brand" required placeholder="ej: Sandvik, Seco, etc." value="<?=htmlspecialchars($_POST['brand'] ?? '')?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field" style="flex: 1 1 100%;">
            <label>detalles</label>
            <textarea name="detalles" placeholder="Base, tornillo, placa, torniplaca, llave, etc." style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?=htmlspecialchars($_POST['detalles'] ?? '')?></textarea>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>photo URL</label>
            <input type="text" name="photo_url" placeholder="https://ejemplo.com/imagen.jpg" value="<?=htmlspecialchars($_POST['photo_url'] ?? '')?>">
          </div>
          <div class="field">
            <label>precio <sup>*</sup></label>
            <input type="number" name="price" step="0.01" min="0.01" required placeholder="ej: 50.00" value="<?=htmlspecialchars($_POST['price'] ?? '')?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>stock inicial</label>
            <input type="number" name="stock" value="<?=htmlspecialchars($_POST['stock'] ?? '0')?>" min="0">
          </div>
        </div>

        <div class="flex flex-end gap-12">
          <button type="submit">Crear herramienta</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>

