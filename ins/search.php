<?php
require_once __DIR__ . '/db.php';
require_login();
$user = $_SESSION['user'];

// Get search parameters
$search_code = $_GET['code'] ?? '';
$search_brand = $_GET['brand'] ?? '';
$search_conditions = $_GET['conditions'] ?? '';
$show_stock_only = $_GET['stock_only'] ?? 0;
$search_type = $_GET['type'] ?? 'insertos'; // insertos | herramientas
if (!in_array($search_type, ['insertos', 'herramientas'], true)) {
    $search_type = 'insertos';
}

// Build query depending on type
$params = [];
if ($search_type === 'insertos') {
    $query = 'SELECT id, code, COALESCE(detalle, descripcion) AS detalle, brand, cutting_conditions, cantidad_por_paquete, photo_url, stock, price FROM insertos WHERE 1=1';

    if (!empty($search_code)) {
        $query .= ' AND LOWER(code) LIKE LOWER(:code)';
        $params['code'] = '%' . $search_code . '%';
    }

    if (!empty($search_brand)) {
        $query .= ' AND LOWER(brand) LIKE LOWER(:brand)';
        $params['brand'] = '%' . $search_brand . '%';
    }

    if (!empty($search_conditions)) {
        $query .= ' AND LOWER(cutting_conditions) LIKE LOWER(:conditions)';
        $params['conditions'] = '%' . $search_conditions . '%';
    }

    if ($show_stock_only) {
        $query .= ' AND stock > 0';
    }

    $query .= ' ORDER BY code ASC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} else {
    // herramientas
    $query = 'SELECT id, code, brand, detalles, photo_url, stock, price FROM tools WHERE 1=1';

    if (!empty($search_code)) {
        $query .= ' AND LOWER(code) LIKE LOWER(:code)';
        $params['code'] = '%' . $search_code . '%';
    }

    if (!empty($search_brand)) {
        $query .= ' AND LOWER(brand) LIKE LOWER(:brand)';
        $params['brand'] = '%' . $search_brand . '%';
    }

    if ($show_stock_only) {
        $query .= ' AND stock > 0';
    }

    $query .= ' ORDER BY code ASC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
}

// Get cart count for user
$cart_count = 0;
if ($user['role'] === 'user') {
    $cart_stmt = $pdo->prepare('
        SELECT 
          (SELECT COUNT(*) FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.user_id = :user_id) +
          (SELECT COUNT(*) FROM cart_tool_items cti JOIN carts c2 ON cti.cart_id = c2.id WHERE c2.user_id = :user_id) 
          AS cnt
    ');
    $cart_stmt->execute(['user_id' => $user['id']]);
    $cart_count = $cart_stmt->fetch()['cnt'] ?? 0;
}

// Handle add to cart (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'user') {
    header('Content-Type: application/json');
    
    $inserto_id = $_POST['inserto_id'] ?? null;
    $tool_id = $_POST['tool_id'] ?? null;
    $cantidad = $_POST['cantidad'] ?? 1;
    
    if (!$inserto_id && !$tool_id) {
        echo json_encode(['ok' => false, 'msg' => 'Item no especificado']);
        exit;
    }
    
    try {
        // Get or create cart
        $cart_stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
        $cart_stmt->execute(['user_id' => $user['id']]);
        $cart = $cart_stmt->fetch();
        
        if (!$cart) {
            $cart_insert = $pdo->prepare('INSERT INTO carts (user_id) VALUES (:user_id)');
            $cart_insert->execute(['user_id' => $user['id']]);
            $cart_id = $pdo->lastInsertId();
        } else {
            $cart_id = $cart['id'];
        }
        
        if ($inserto_id) {
            // INSERTOS
            $item_check = $pdo->prepare('SELECT id, cantidad FROM cart_items WHERE cart_id = :cart_id AND inserto_id = :inserto_id');
            $item_check->execute(['cart_id' => $cart_id, 'inserto_id' => $inserto_id]);
            $existing = $item_check->fetch();

            if ($existing) {
                $update = $pdo->prepare('UPDATE cart_items SET cantidad = cantidad + :cant WHERE id = :id');
                $update->execute(['cant' => $cantidad, 'id' => $existing['id']]);
            } else {
                $insert = $pdo->prepare('INSERT INTO cart_items (cart_id, inserto_id, cantidad) VALUES (:cart_id, :inserto_id, :cant)');
                $insert->execute(['cart_id' => $cart_id, 'inserto_id' => $inserto_id, 'cant' => $cantidad]);
            }
        } else {
            // HERRAMIENTAS
            $item_check = $pdo->prepare('SELECT id, cantidad FROM cart_tool_items WHERE cart_id = :cart_id AND tool_id = :tool_id');
            $item_check->execute(['cart_id' => $cart_id, 'tool_id' => $tool_id]);
            $existing = $item_check->fetch();

            if ($existing) {
                $update = $pdo->prepare('UPDATE cart_tool_items SET cantidad = cantidad + :cant WHERE id = :id');
                $update->execute(['cant' => $cantidad, 'id' => $existing['id']]);
            } else {
                $insert = $pdo->prepare('INSERT INTO cart_tool_items (cart_id, tool_id, cantidad) VALUES (:cart_id, :tool_id, :cant)');
                $insert->execute(['cart_id' => $cart_id, 'tool_id' => $tool_id, 'cant' => $cantidad]);
            }
        }
        
        echo json_encode(['ok' => true, 'msg' => 'Añadido al carrito']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Búsqueda de insertos / herramientas</title>
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
      <?php if ($user['role'] === 'user'): ?>
        <a href="cart.php">🛒 Carrito (<?=$cart_count?>)</a>
      <?php endif; ?>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <div class="container">
    <div class="page-header">
      <h1 class="page-title">Búsqueda de Insertos / Herramientas</h1>
      <p class="page-subtitle">Selecciona qué buscar y filtra por código, marca y condiciones</p>
    </div>

    <div class="card">
      <form method="get" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <div>
          <label>Tipo</label>
          <select name="type" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="insertos" <?=$search_type === 'insertos' ? 'selected' : ''?>>Insertos</option>
            <option value="herramientas" <?=$search_type === 'herramientas' ? 'selected' : ''?>>Herramientas</option>
          </select>
        </div>
        <div style="flex: 1; min-width: 200px;">
          <label>Buscar por código</label>
          <input type="text" name="code" placeholder="ej: INS-343" value="<?=htmlspecialchars($search_code)?>">
        </div>
        <div style="flex: 1; min-width: 200px;">
          <label>Buscar por marca</label>
          <input type="text" name="brand" placeholder="ej: marca" value="<?=htmlspecialchars($search_brand)?>">
        </div>
        <?php if ($search_type === 'insertos'): ?>
          <div style="flex: 1; min-width: 200px;">
            <label>Buscar por cutting conditions</label>
            <input type="text" name="conditions" placeholder="ej: 100m/min" value="<?=htmlspecialchars($search_conditions)?>">
          </div>
        <?php endif; ?>
        <label style="display: flex; gap: 5px; align-items: center;">
          <input type="checkbox" name="stock_only" value="1" <?=$show_stock_only ? 'checked' : ''?>>
          Solo con stock
        </label>
        <button type="submit">Buscar</button>
      </form>
    </div>

    <div class="card" style="margin-top: 20px;">
      <h2 style="margin-bottom: 15px">Resultados (<?=count($items)?> encontrados)</h2>
      
      <?php if (empty($items)): ?>
        <p style="color: #666;">No se encontraron resultados.</p>
      <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
              <?php if ($search_type === 'insertos'): ?>
                <th style="text-align: center; padding: 10px; width: 80px;">Imagen</th>
              <?php endif; ?>
              <th style="text-align: left; padding: 10px;">Código</th>
              <th style="text-align: left; padding: 10px;">Marca</th>
              <?php if ($search_type === 'insertos'): ?>
                <th style="text-align: left; padding: 10px;">Detalle</th>
                <th style="text-align: left; padding: 10px;">Cutting Conditions</th>
                <th style="text-align: center; padding: 10px;">Qty/Pkg</th>
              <?php else: ?>
                <th style="text-align: left; padding: 10px;">Detalle</th>
              <?php endif; ?>
              <th style="text-align: center; padding: 10px;">Stock</th>
              <th style="text-align: center; padding: 10px;">Precio</th>
              <?php if ($user['role'] === 'admin'): ?>
                <th style="text-align: center; padding: 10px;">Acciones</th>
              <?php else: ?>
                <th style="text-align: center; padding: 10px;">Cantidad</th>
                <th style="text-align: center; padding: 10px;">Acción</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr style="border-bottom: 1px solid #eee;">
                <?php if ($search_type === 'insertos'): ?>
                  <td style="text-align: center; padding: 10px;">
                    <?php if ($item['photo_url']): ?>
                      <a href="<?=htmlspecialchars($item['photo_url'])?>" target="_blank" style="text-decoration: none; color: #0055b8; font-weight: bold;">🖼️</a>
                    <?php else: ?>
                      <span style="color: #999;">-</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td style="padding: 10px;"><strong><?=htmlspecialchars($item['code'])?></strong></td>
                <td style="padding: 10px;"><?=htmlspecialchars($item['brand'] ?? '-')?></td>
                <?php if ($search_type === 'insertos'): ?>
                  <td style="padding: 10px;"><?=htmlspecialchars($item['detalle'] ?? '-')?></td>
                  <td style="padding: 10px;"><?=htmlspecialchars($item['cutting_conditions'] ?? '-')?></td>
                  <td style="text-align: center; padding: 10px;"><?=$item['cantidad_por_paquete']?></td>
                <?php else: ?>
                  <td style="padding: 10px;">
                    <?=htmlspecialchars($item['detalles'] ?? '-')?>
                  </td>
                <?php endif; ?>
                <td style="text-align: center; padding: 10px; font-weight: bold;">
                  <span style="color: <?=$item['stock'] > 0 ? 'green' : 'red'?>">
                    <?=$item['stock']?>
                  </span>
                </td>
                <td style="text-align: center; padding: 10px;">
                  <?= $item['price'] !== null ? number_format($item['price'], 2) : '-' ?>
                </td>
                <?php if ($user['role'] === 'admin'): ?>
                  <td style="text-align: center; padding: 10px;">
                    <?php if ($search_type === 'insertos'): ?>
                      <a href="admin_stock.php?id=<?=$item['id']?>" style="text-decoration: none; color: #0055b8;">📊 Stock</a> |
                      <a href="admin_history.php?id=<?=$item['id']?>" style="text-decoration: none; color: #0055b8;">📜 Historial</a>
                    <?php else: ?>
                      <a href="admin_tool_stock.php?id=<?=$item['id']?>" style="text-decoration: none; color: #0055b8;">📊 Stock</a> |
                      <a href="admin_tool_history.php?id=<?=$item['id']?>" style="text-decoration: none; color: #0055b8;">📜 Historial</a>
                    <?php endif; ?>
                  </td>
                <?php else: ?>
                  <td style="text-align: center; padding: 10px;">
                    <input type="number" id="cant_<?=$search_type === 'insertos' ? 'i_'.$item['id'] : 't_'.$item['id']?>" value="1" min="1" style="width: 60px; padding: 5px;">
                  </td>
                  <td style="text-align: center; padding: 10px;">
                    <?php if ($search_type === 'insertos'): ?>
                      <button onclick="addInsertoToCart(<?=$item['id']?>)" style="padding: 5px 15px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Añadir
                      </button>
                    <?php else: ?>
                      <button onclick="addToolToCart(<?=$item['id']?>)" style="padding: 5px 15px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Añadir
                      </button>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($user['role'] === 'user'): ?>
  <script>
    function addInsertoToCart(insertoId) {
      const cantidad = document.getElementById('cant_i_' + insertoId).value;
      const formData = new FormData();
      formData.append('inserto_id', insertoId);
      formData.append('cantidad', cantidad);

      fetch('search.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          alert('✓ ' + data.msg);
          location.reload();
        } else {
          alert('✗ ' + data.msg);
        }
      })
      .catch(e => alert('Error: ' + e));
    }

    function addToolToCart(toolId) {
      const cantidad = document.getElementById('cant_t_' + toolId).value;
      const formData = new FormData();
      formData.append('tool_id', toolId);
      formData.append('cantidad', cantidad);

      fetch('search.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          alert('✓ ' + data.msg);
          location.reload();
        } else {
          alert('✗ ' + data.msg);
        }
      })
      .catch(e => alert('Error: ' + e));
    }
  </script>
  <?php endif; ?>
</body>
</html>
