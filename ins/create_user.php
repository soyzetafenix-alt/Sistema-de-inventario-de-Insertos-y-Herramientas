<?php
require_once __DIR__ . '/db.php';
require_admin();
$user = $_SESSION['user'];

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $dni = $_POST['dni'] ?? null;
    $area = $_POST['area'] ?? null;
    $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';

    if ($username === '' || $password === '') {
        $errors[] = 'Usuario y contraseña son obligatorios';
    }

    if (strlen($password) < 4) {
        $errors[] = 'La contraseña debe tener al menos 4 caracteres';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, dni, area, role) VALUES (:u, :h, :dni, :area, :role)');
        try {
            $stmt->execute(['u'=>$username,'h'=>$hash,'dni'=>$dni,'area'=>$area,'role'=>$role]);
            $success = 'Usuario creado correctamente';
            // Clear form on success
            $_POST = [];
        } catch (Exception $e) {
            $errors[] = 'Error al crear usuario: ' . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Crear usuario - Sistema de Insertos</title>
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
        <h1 class="page-title">Crear nuevo usuario</h1>
        <p class="page-subtitle">Completa el formulario para agregar un usuario al sistema</p>
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

      <?php $area_selected = $_POST['area'] ?? ''; ?>
      <form method="post">
        <div class="form-row">
          <div class="field">
            <label>usuario <sup>*</sup></label>
            <input type="text" name="username" required placeholder="ej: juan.perez" value="<?=htmlspecialchars($_POST['username'] ?? '')?>">
          </div>
          <div class="field">
            <label>contraseña <sup>*</sup></label>
            <input type="password" name="password" required placeholder="Mínimo 4 caracteres" value="<?=htmlspecialchars($_POST['password'] ?? '')?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>DNI</label>
            <input type="text" name="dni" placeholder="ej: 12345678" value="<?=htmlspecialchars($_POST['dni'] ?? '')?>">
          </div>
          <div class="field">
            <label>área <sup>*</sup></label>
            <select name="area" required>
              <option value="">-- Seleccionar área --</option>
              <?php
              $areas = [
                'ADMINISTRACIÓN','JEFE DE PLANTA','CALIDAD','SUPERVISORES Y PLANIFICACION',
                'CAPATAZ DE AREA','CADISTAS','ALMACEN','ACABADO, RECUBRIMIENTO Y','DESPACHO',
                'CONDUCTORES','SOLDADORES','ARMADORES','AYUDANTES SOLDADORES','HABILITADO',
                'MAESTRANZA','SOL INOX','MANTENIMIENTO MECANICO','ELECTRICO'
              ];
              foreach ($areas as $a) {
                $sel = ($a === $area_selected) ? 'selected' : '';
                echo "<option value=\"".htmlspecialchars($a)."\" $sel>".htmlspecialchars($a)."</option>";
              }
              ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>rol <sup>*</sup></label>
            <select name="role" required>
              <option value="user" <?=($_POST['role'] ?? 'user') === 'user' ? 'selected' : ''?>>Usuario común</option>
              <option value="admin" <?=($_POST['role'] ?? 'user') === 'admin' ? 'selected' : ''?>>Administrador</option>
            </select>
          </div>
          <div class="field" style="justify-content: flex-end;">
            <button type="submit">Crear usuario</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
