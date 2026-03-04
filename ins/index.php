<?php
require_once __DIR__ . '/db.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Sistema de Insertos - VALMET</title>
  <link rel="stylesheet" href="styles.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
  <!-- NAVBAR -->
  <div class="navbar">
    <a href="#" class="navbar-logo">
      <div class="navbar-logo-icon">⚙️</div>
      <span>VALMET Insertos</span>
    </a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="container">
    <div style="max-width: 600px; margin: 60px auto; text-align: center;">
      <div class="card">
        <div style="font-size: 64px; margin-bottom: 20px;">⚙️</div>
        <h1 class="page-title">Sistema de Gestión de Insertos</h1>
        <p class="page-subtitle" style="margin-bottom: 24px;">VALMET SAC - Solución Empresarial</p>
        
        <p class="text-muted" style="margin-bottom: 32px;">
          Base de datos inicializada correctamente.
        </p>

        <a href="login.php" class="btn" style="display: inline-block;">
          Iniciar sesión →
        </a>
      </div>
    </div>
  </div>
</body>
</html>
