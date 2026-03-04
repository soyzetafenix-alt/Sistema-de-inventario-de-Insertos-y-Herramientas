<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $detalle = $_POST['detalle'] ?? null;
    $brand = $_POST['brand'] ?? null;
    $cutting_conditions = $_POST['cutting_conditions'] ?? null;
    $cantidad_por_paquete = intval($_POST['cantidad_por_paquete'] ?? 1);
    $stock = intval($_POST['stock'] ?? 0);
    $photo_url = $_POST['photo_url'] ?? null;
    $price = $_POST['price'] !== '' ? floatval($_POST['price']) : null;

    if ($code === '') {
        $errors[] = 'El código es obligatorio';
    }

    if ($brand === null || trim($brand) === '') {
        $errors[] = 'La marca es obligatoria';
    }

    if ($cantidad_por_paquete < 1) {
        $errors[] = 'La cantidad por paquete debe ser mayor a 0';
    }

    if ($price === null || $price <= 0) {
        $errors[] = 'El precio debe ser mayor a 0';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO insertos (code, descripcion, detalle, brand, cutting_conditions, cantidad_por_paquete, stock, photo_url, price) VALUES (:code, :desc, :detalle, :brand, :cc, :cpp, :stock, :photo, :price)');
        try {
            $stmt->execute([
                'code' => $code,
                'desc' => $detalle,
                'detalle' => $detalle,
                'brand' => $brand,
                'cc' => $cutting_conditions,
                'cpp' => $cantidad_por_paquete,
                'stock' => $stock,
                'photo' => $photo_url,
                'price' => $price,
            ]);
            $success = 'Inserto creado correctamente';
            $_POST = [];
        } catch (Exception $e) {
            $errors[] = 'Error al crear inserto: ' . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Crear inserto - Sistema de Insertos</title>
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
        <h1 class="page-title">Crear nuevo inserto</h1>
        <p class="page-subtitle">Completa el formulario con los detalles del inserto</p>
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
            <input type="text" name="code" required placeholder="ej: INS-001" value="<?=htmlspecialchars($_POST['code'] ?? '')?>">
          </div>
          <div class="field">
            <label>marca <sup>*</sup></label>
            <input type="text" name="brand" required placeholder="ej: Sandvik, Seco, etc." value="<?=htmlspecialchars($_POST['brand'] ?? '')?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>detalle</label>
            <input type="text" name="detalle" placeholder="ej: Inserto de corte, medidas, etc." value="<?=htmlspecialchars($_POST['detalle'] ?? '')?>">
          </div>
          <div class="field">
            <label>precio (por unidad o paquete) <sup>*</sup></label>
            <input type="number" name="price" step="0.01" min="0.01" required placeholder="ej: 10.50" value="<?=htmlspecialchars($_POST['price'] ?? '')?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>cutting conditions</label>
            <input type="text" name="cutting_conditions" placeholder="ej: 100 m/min" value="<?=htmlspecialchars($_POST['cutting_conditions'] ?? '')?>">
          </div>
          <div class="field">
            <label>cantidad por paquete <sup>*</sup></label>
            <input type="number" name="cantidad_por_paquete" value="<?=htmlspecialchars($_POST['cantidad_por_paquete'] ?? '1')?>" min="1" required>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>stock inicial</label>
            <input type="number" name="stock" value="<?=htmlspecialchars($_POST['stock'] ?? '0')?>" min="0">
          </div>
          <div class="field">
            <label>photo URL</label>
            <input type="text" name="photo_url" placeholder="https://ejemplo.com/imagen.jpg" value="<?=htmlspecialchars($_POST['photo_url'] ?? '')?>">
          </div>
        </div>

        <div class="flex flex-end gap-12">
          <button type="submit">Crear inserto</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
