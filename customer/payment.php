<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];
$orderID = (int)($_GET['order_id'] ?? 0);
$msg = '';

// Get order details
$orderStmt = $pdo->prepare(
    "SELECT o.order_ID, o.order_date, o.status, u.address,
            GROUP_CONCAT(f.name, ' x', od.quantity SEPARATOR ', ') AS items,
            GROUP_CONCAT(f.name, '|', od.quantity, '|', od.unit_price SEPARATOR '~~~') AS items_detail,
            SUM(od.quantity * od.unit_price) AS total_amount
     FROM Orders o
     JOIN Users u ON o.customer_ID = u.user_ID
     LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
     LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
     WHERE o.order_ID = ? AND o.customer_ID = ?
     GROUP BY o.order_ID"
);
$orderStmt->execute([$orderID, $userID]);
$order = $orderStmt->fetch();

if (!$order || $order['status'] === 'Cancelled') {
    header('Location: my_orders.php');
    exit;
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_method'], $_POST['delivery_addr'])) {
    $payMethod = trim($_POST['pay_method']);
    $deliveryAddr = trim($_POST['delivery_addr']);

    if (!$payMethod || !$deliveryAddr) {
        $msg = ['type' => 'error', 'text' => 'Төлбөрийн арга болон хүргэлтийн хаягийг бөглөнө үү.'];
    } else {
        try {
            $payStmt = $pdo->prepare("SELECT pay_ID FROM Payment WHERE order_ID = ?");
            $payStmt->execute([$orderID]);
            $payID = $payStmt->fetchColumn();

            if ($payID) {
                $pdo->prepare("UPDATE Payment SET amount = ?, pay_date = ?, pay_method = ? WHERE pay_ID = ?")
                    ->execute([$order['total_amount'], date('Y-m-d H:i:s'), $payMethod, $payID]);
            } else {
                $maxPay = $pdo->query("SELECT MAX(pay_ID) FROM Payment")->fetchColumn();
                $pdo->prepare("INSERT INTO Payment (pay_ID, order_ID, amount, pay_date, pay_method) VALUES (?,?,?,?,?)")
                    ->execute([($maxPay ?? 900) + 1, $orderID, $order['total_amount'], date('Y-m-d H:i:s'), $payMethod]);
            }

            $pdo->prepare("UPDATE Users SET address = ? WHERE user_ID = ?")
                ->execute([$deliveryAddr, $userID]);

            $pdo->prepare("UPDATE Orders SET status = 'Processing' WHERE order_ID = ?")
                ->execute([$orderID]);

            header('Location: my_orders.php?payment_success=' . $orderID);
            exit;
        } catch (Exception $e) {
            $msg = ['type' => 'error', 'text' => 'Төлбөр төлөхдөө алдаа гарлаа: ' . $e->getMessage()];
        }
    }
}

$pageTitle = 'Төлбөр төлөх';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Нүүр хуудас</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Захиалах</a>
<a href="my_orders.php" class="nav-link active"><span class="icon"></span> Миний захиалга</a>
<a href="profile.php" class="nav-link"><span class="icon"></span> Мэдээлэл засах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Төлбөр төлөх</h1>
  <p>Захиалга #<?= $orderID ?> — <?= date('Y/m/d H:i', strtotime($order['order_date'])) ?></p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">
  <!-- Order Details -->
  <div class="card">
    <div class="card-title">Захиалгын дэлгэрэнгүй</div>
    
    <div style="margin-bottom:24px;padding:16px;background:#f7f2ea;border-radius:12px">
      <table style="width:100%">
        <thead>
          <tr style="border-bottom:2px solid var(--border)">
            <th style="text-align:left;padding:0 0 10px 0;font-size:12px;color:var(--warm-gray)">Хоол</th>
            <th style="text-align:center;padding:0 0 10px 0;font-size:12px;color:var(--warm-gray)">Тоо</th>
            <th style="text-align:right;padding:0 0 10px 0;font-size:12px;color:var(--warm-gray)">Үнэ</th>
            <th style="text-align:right;padding:0 0 10px 0;font-size:12px;color:var(--warm-gray)">Дүн</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $itemsArray = explode('~~~', $order['items_detail']);
          foreach ($itemsArray as $item):
            if (empty(trim($item))) continue;
            list($name, $qty, $price) = explode('|', $item);
            $subtotal = $qty * $price;
          ?>
          <tr style="border-bottom:1px solid #e8e0d0;padding:10px 0">
            <td style="padding:10px 0 10px 0;font-size:14px;font-weight:500"><?= htmlspecialchars($name) ?></td>
            <td style="padding:10px 0 10px 0;text-align:center;font-size:14px"><?= $qty ?></td>
            <td style="padding:10px 0 10px 0;text-align:right;font-size:13px;color:var(--warm-gray)"><?= number_format($price) ?>₮</td>
            <td style="padding:10px 0 10px 0;text-align:right;font-size:14px;font-weight:600"><?= number_format($subtotal) ?>₮</td>
          </tr>
          <?php endforeach; ?>
          <tr style="border-top:2px solid var(--border);padding:12px 0">
            <td colspan="3" style="text-align:right;padding:12px 0;font-weight:600">Нийт:</td>
            <td style="text-align:right;padding:12px 0;font-family:'Playfair Display',serif;font-size:20px;color:var(--gold)">
              <?= number_format($order['total_amount']) ?>₮
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Payment Sidebar -->
  <div style="position:sticky;top:24px">
    <div class="card">
      <div class="card-title">Төлбөр төлөх</div>
      
      <form method="POST">
        <!-- Payment Method -->
        <div class="form-group">
          <label>Төлбөрийн арга</label>
          <select name="pay_method" required style="width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--ink);background:#fff;cursor:pointer;outline:none;transition:border-color 0.15s">
            <option value="" disabled selected>Сонгоно уу...</option>
            <option value="Cash">💵 Бэлэн мөнгө</option>
            <option value="Card">Картаар</option>
            <option value="Mobile">📱 Мобайл төлбөр</option>
            <option value="Bank">🏦 Онлайн данс</option>
          </select>
        </div>

        <!-- Delivery Address -->
        <div class="form-group">
          <label>Хүргэлтийн хаяг</label>
          <textarea 
            name="delivery_addr" 
            rows="4" 
            placeholder="Уул, өрөө, дүүргийн нэр..."
            required
            style="width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--ink);background:#fff;outline:none;resize:none;transition:border-color 0.15s"
          ><?= htmlspecialchars($order['address'] ?? '') ?></textarea>
        </div>

        <!-- Amount Summary -->
        <div style="background:#f7f2ea;padding:14px;border-radius:10px;margin-bottom:16px">
          <div style="font-size:12px;color:var(--warm-gray);margin-bottom:6px;letter-spacing:0.05em;text-transform:uppercase">Төлөх дүн</div>
          <div style="font-family:'Playfair Display',serif;font-size:28px;color:var(--gold)">
            <?= number_format($order['total_amount']) ?>₮
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;padding:13px;font-size:15px;font-weight:600">
          ✓ Төлбөр төлөх
        </button>

        <!-- Back Link -->
        <div style="text-align:center;margin-top:12px">
          <a href="my_orders.php" style="color:var(--warm-gray);font-size:13px;text-decoration:none">← Буцаах</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
