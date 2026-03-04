<?php
require_once __DIR__ . '/db.php';
require_login();
$user = $_SESSION['user'];

if ($user['role'] !== 'user') {
    header('Location: dashboard.php');
    exit;
}

$cart_id = null;
$cart_items = [];
$cart_tool_items = [];
$errors = [];
$success = '';

// Get user's cart
$cart_stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
$cart_stmt->execute(['user_id' => $user['id']]);
$cart = $cart_stmt->fetch();

if ($cart) {
    $cart_id = $cart['id'];
    
    // Get insertos in cart
    $items_stmt = $pdo->prepare('
        SELECT ci.id as cart_item_id, ci.inserto_id, ci.cantidad, i.code, COALESCE(i.detalle, i.descripcion) AS detalle, i.stock, i.cantidad_por_paquete, i.price
        FROM cart_items ci
        JOIN insertos i ON ci.inserto_id = i.id
        WHERE ci.cart_id = :cart_id
        ORDER BY i.code ASC
    ');
    $items_stmt->execute(['cart_id' => $cart_id]);
    $cart_items = $items_stmt->fetchAll();

    // Get tools in cart
    $tools_stmt = $pdo->prepare('
        SELECT cti.id as cart_item_id, cti.tool_id, cti.cantidad, t.code, t.brand, t.stock, t.price
        FROM cart_tool_items cti
        JOIN tools t ON cti.tool_id = t.id
        WHERE cti.cart_id = :cart_id
        ORDER BY t.code ASC
    ');
    $tools_stmt->execute(['cart_id' => $cart_id]);
    $cart_tool_items = $tools_stmt->fetchAll();
}

// Remove item from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['remove_inserto', 'remove_tool'], true)) {
    $item_id = $_POST['item_id'] ?? null;
    $action = $_POST['action'];
    if ($item_id) {
        if ($action === 'remove_inserto') {
            $delete = $pdo->prepare('DELETE FROM cart_items WHERE id = :id');
        } else {
            $delete = $pdo->prepare('DELETE FROM cart_tool_items WHERE id = :id');
        }
        $delete->execute(['id' => $item_id]);
        header('Location: cart.php');
        exit;
    }
}

// Save request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_request') {
    $delivery_date = $_POST['delivery_date'] ?? null;
    $return_date = $_POST['return_date'] ?? null;
    $ot = $_POST['ot'] ?? null;
    
    if (!$delivery_date || !$return_date) {
        $errors[] = 'Debe completar las fechas de entrega y devolución';
    }

    if ($ot === null || trim($ot) === '') {
        $errors[] = 'Debe indicar la OT para el pedido';
    }
    
    // Check quantities
    foreach ($cart_items as $item) {
        $qty = $_POST['qty_' . $item['cart_item_id']] ?? $item['cantidad'];
        if ($qty > $item['stock']) {
            $errors[] = "Cantidad de {$item['code']} excede stock disponible ({$item['stock']})";
        }
    }

    foreach ($cart_tool_items as $item) {
        $qty = $_POST['qty_tool_' . $item['cart_item_id']] ?? $item['cantidad'];
        if ($qty > $item['stock']) {
            $errors[] = "Cantidad de herramienta {$item['code']} excede stock disponible ({$item['stock']})";
        }
    }
    
    if (empty($errors)) {
        try {
            // Create request
            $req_stmt = $pdo->prepare('
                INSERT INTO requests (user_id, status, delivery_date, approximate_return_date, ot)
                VALUES (:user_id, :status, :del_date, :ret_date, :ot)
            ');
            $req_stmt->execute([
                'user_id' => $user['id'],
                'status' => 'pending',
                'del_date' => $delivery_date,
                'ret_date' => $return_date,
                'ot' => $ot,
            ]);
            $request_id = $pdo->lastInsertId();
            
            // Add inserto items to request
            foreach ($cart_items as $item) {
                $qty = $_POST['qty_' . $item['cart_item_id']] ?? $item['cantidad'];
                $req_item = $pdo->prepare('
                    INSERT INTO request_items (request_id, inserto_id, cantidad, unit_price)
                    VALUES (:req_id, :ins_id, :qty, :unit_price)
                ');
                $req_item->execute([
                    'req_id' => $request_id,
                    'ins_id' => $item['inserto_id'],
                    'qty' => $qty,
                    'unit_price' => $item['price'],
                ]);
            }

            // Add tool items to request
            foreach ($cart_tool_items as $item) {
                $qty = $_POST['qty_tool_' . $item['cart_item_id']] ?? $item['cantidad'];
                $req_item = $pdo->prepare('
                    INSERT INTO request_tool_items (request_id, tool_id, cantidad, unit_price)
                    VALUES (:req_id, :tool_id, :qty, :unit_price)
                ');
                $req_item->execute([
                    'req_id' => $request_id,
                    'tool_id' => $item['tool_id'],
                    'qty' => $qty,
                    'unit_price' => $item['price'],
                ]);
            }
            
            // Empty cart
            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cart_id]);
            $pdo->prepare('DELETE FROM cart_tool_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cart_id]);
            
            // Create notification for user
            $notif_stmt = $pdo->prepare('
                INSERT INTO notifications (user_id, type, message)
                VALUES (:user_id, :type, :msg)
            ');
            $notif_stmt->execute([
                'user_id' => $user['id'],
                'type' => 'request_created',
                'msg' => 'Tu solicitud #' . $request_id . ' ha sido enviada. Esperando aprobación.'
            ]);
            
            $success = '✓ Solicitud creada correctamente. Esperando aprobación del administrador.';
            
            // Refresh
            header('Location: cart.php?success=1');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al crear solicitud: ' . $e->getMessage();
        }
    }
}

// Check if user has pending orders
$pending_stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM requests WHERE user_id = :user_id AND status = :status');
$pending_stmt->execute(['user_id' => $user['id'], 'status' => 'pending']);
$has_pending = $pending_stmt->fetch()['cnt'] > 0;

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Mi Carrito</title>
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
      <a href="search.php">← Volver</a>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <div class="container">
    <div class="page-header">
      <h1 class="page-title">Mi Carrito</h1>
      <p class="page-subtitle">Revisa y confirma tu solicitud</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        ✓ Solicitud creada correctamente. El administrador la revisará pronto.
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <?php foreach ($errors as $err): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
          ❌ <?=htmlspecialchars($err)?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($cart_items) && empty($cart_tool_items)): ?>
      <div class="card">
        <p style="text-align: center; color: #666; padding: 40px;">
          Tu carrito está vacío. <a href="search.php" style="color: #0055b8;">Busca insertos / herramientas →</a>
        </p>
      </div>
    <?php else: ?>
      <div class="card" style="margin-bottom: 20px;">
        <h2 style="margin-bottom: 15px">Insertos en carrito (<?=count($cart_items)?>)</h2>
        
        <?php if ($has_pending): ?>
          <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
            ⚠️ Tienes una solicitud pendiente. Completa la actual o espera aprobación.
          </div>
        <?php endif; ?>
        
        <?php if (empty($cart_items)): ?>
          <p style="color: #666;">No hay insertos en el carrito.</p>
        <?php else: ?>
          <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
              <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                <th style="text-align: left; padding: 10px;">Código</th>
                <th style="text-align: left; padding: 10px;">Detalle</th>
                <th style="text-align: center; padding: 10px;">Stock</th>
                <th style="text-align: center; padding: 10px;">Cantidad</th>
                <th style="text-align: center; padding: 10px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cart_items as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                  <td style="padding: 10px;"><strong><?=htmlspecialchars($item['code'])?></strong></td>
                  <td style="padding: 10px;"><?=htmlspecialchars($item['detalle'] ?? '-')?></td>
                  <td style="text-align: center; padding: 10px;">
                    <span style="color: <?=$item['stock'] > 0 ? 'green' : 'red'?>">
                      <?=$item['stock']?>
                    </span>
                  </td>
                  <td style="text-align: center; padding: 10px;">
                    <input type="number" name="qty_<?=$item['cart_item_id']?>" value="<?=$item['cantidad']?>" min="1" max="<?=$item['stock']?>" style="width: 70px; padding: 5px; text-align: center;">
                  </td>
                  <td style="text-align: center; padding: 10px;">
                    <form method="post" style="display: inline;">
                      <input type="hidden" name="action" value="remove_inserto">
                      <input type="hidden" name="item_id" value="<?=$item['cart_item_id']?>">
                      <button type="submit" onclick="return confirm('¿Eliminar del carrito?');" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Quitar
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="card" style="margin-bottom: 20px;">
        <h2 style="margin-bottom: 15px">Herramientas en carrito (<?=count($cart_tool_items)?>)</h2>
        <?php if (empty($cart_tool_items)): ?>
          <p style="color: #666;">No hay herramientas en el carrito.</p>
        <?php else: ?>
          <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
              <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                <th style="text-align: left; padding: 10px;">Código</th>
                <th style="text-align: left; padding: 10px;">Marca</th>
                <th style="text-align: center; padding: 10px;">Stock</th>
                <th style="text-align: center; padding: 10px;">Cantidad</th>
                <th style="text-align: center; padding: 10px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cart_tool_items as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                  <td style="padding: 10px;"><strong><?=htmlspecialchars($item['code'])?></strong></td>
                  <td style="padding: 10px;"><?=htmlspecialchars($item['brand'] ?? '-')?></td>
                  <td style="text-align: center; padding: 10px;">
                    <span style="color: <?=$item['stock'] > 0 ? 'green' : 'red'?>">
                      <?=$item['stock']?>
                    </span>
                  </td>
                  <td style="text-align: center; padding: 10px;">
                    <input type="number" name="qty_tool_<?=$item['cart_item_id']?>" value="<?=$item['cantidad']?>" min="1" max="<?=$item['stock']?>" style="width: 70px; padding: 5px; text-align: center;">
                  </td>
                  <td style="text-align: center; padding: 10px;">
                    <form method="post" style="display: inline;">
                      <input type="hidden" name="action" value="remove_tool">
                      <input type="hidden" name="item_id" value="<?=$item['cart_item_id']?>">
                      <button type="submit" onclick="return confirm('¿Eliminar del carrito?');" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Quitar
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Dates form -->
      <form method="post" style="display: none;" id="cart_form">
        <input type="hidden" name="action" value="save_request">
        <?php foreach ($cart_items as $item): ?>
          <input type="hidden" name="qty_<?=$item['cart_item_id']?>" value="">
        <?php endforeach; ?>
      </form>

      <div class="card">
        <h2 style="margin-bottom: 15px">Completar Solicitud</h2>
        <form method="post">
          <input type="hidden" name="action" value="save_request">
          <?php foreach ($cart_items as $item): ?>
            <input type="hidden" name="qty_<?=$item['cart_item_id']?>" value="<?=$item['cantidad']?>">
          <?php endforeach; ?>
          <?php foreach ($cart_tool_items as $item): ?>
            <input type="hidden" name="qty_tool_<?=$item['cart_item_id']?>" value="<?=$item['cantidad']?>">
          <?php endforeach; ?>

          <div style="margin-bottom: 15px;">
            <label>OT <span style="color: red;">*</span></label><br>
            <input type="text" name="ot" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 260px;" placeholder="Número o código de OT">
          </div>

          <div style="margin-bottom: 15px;">
            <label>Fecha de entrega <span style="color: red;">*</span></label><br>
            <input type="date" name="delivery_date" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
          </div>

          <div style="margin-bottom: 20px;">
            <label>Fecha aproximada de devolución <span style="color: red;">*</span></label><br>
            <input type="date" name="return_date" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
          </div>

          <div style="display: flex; gap: 10px;">
            <button type="submit" style="padding: 10px 20px; background: #0055b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
              ✓ Guardar solicitud
            </button>
            <a href="search.php" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; text-decoration: none; display: inline-block;">
              ✗ Cancelar
            </a>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
