<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];
$msg = '';

if (isset($_GET['payment_success'])) {
    $msg = ['type' => 'success', 'text' => "✓ Захиалга #" . htmlspecialchars($_GET['payment_success']) . " төлөгдлөө. Ажилтан үүнийг боловсруулна."];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $orderID = (int)$_POST['cancel_order_id'];
    $statusStmt = $pdo->prepare("SELECT status FROM Orders WHERE order_ID = ? AND customer_ID = ?");
    $statusStmt->execute([$orderID, $userID]);
    $currentStatus = $statusStmt->fetchColumn();

    if ($currentStatus && $currentStatus !== 'Completed' && $currentStatus !== 'Cancelled') {
        $pdo->prepare("UPDATE Orders SET status = 'Cancelled' WHERE order_ID = ?")->execute([$orderID]);
        $pdo->prepare("UPDATE Delivery SET status = 'Cancelled' WHERE order_ID = ?")->execute([$orderID]);
        $msg = ['type' => 'success', 'text' => "✓ Захиалга #$orderID амжилттай цуцлагдлаа."];
    } else {
        $msg = ['type' => 'error', 'text' => 'Энэ захиалгыг цуцлах боломжгүй байна.'];
    }
}

$orders = $pdo->prepare(
    "SELECT o.order_ID, o.order_date, o.status, ANY_VALUE(p.amount) AS amount,
            ANY_VALUE(p.pay_date) AS pay_date,
            SUM(od.quantity * od.unit_price) AS total_amount,
            ANY_VALUE(d.status) AS delivery_status,
            GROUP_CONCAT(f.name, ' x', od.quantity SEPARATOR ', ') AS items
     FROM Orders o
     LEFT JOIN Payment p ON o.order_ID = p.order_ID
     LEFT JOIN Delivery d ON o.order_ID = d.order_ID
     LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
     LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
     WHERE o.customer_ID = ?
     GROUP BY o.order_ID
     ORDER BY o.order_date DESC"
);
$orders->execute([$userID]);
$orders = $orders->fetchAll();

$pageTitle = 'Миний захиалга';
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
  <h1>Миний захиалга</h1>
</div>

<div class="card">
  <div class="card-title">Захиалгын жагсаалт</div>
  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
  <?php endif; ?>
  <?php if (empty($orders)): ?>
    <p style="color:var(--warm-gray);font-size:14px;padding:16px 0">Танд захиалга байхгүй байна. "Цэс" дээр дарж хоол захиалаарай.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Хоол</th>
        <th>Төлөх дүн</th>
        <th>Огноо</th>
        <th>Захиалга</th>
        <th>Хүргэлт</th>
        <th>Төлбөр</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <?php
        $isPaid = !empty($o['pay_date']);
        $displayAmount = $isPaid ? $o['amount'] : $o['total_amount'];
      ?>
      <tr>
        <td><strong>#<?= $o['order_ID'] ?></strong></td>
        <td style="font-size:13px;color:var(--warm-gray);max-width:240px"><?= htmlspecialchars($o['items'] ?? '-') ?></td>
        <td><strong><?= number_format($displayAmount) ?>₮</strong></td>
        <td style="font-size:12px;color:var(--warm-gray)"><?= $o['order_date'] ?></td>
        <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span></td>
        <td>
          <?php
          $delivery = strtolower(str_replace(' ', '', $o['delivery_status'] ?? 'Pending'));
          $deliveryClass = in_array($delivery, ['delivered','cancelled','pending']) ? $delivery : 'pending';
          echo "<span class='badge badge-$deliveryClass'>" . htmlspecialchars($o['delivery_status'] ?? 'Pending') . "</span>";
          ?>
        </td>
        <td>
          <?php if ($isPaid): ?>
            <span class="badge badge-completed">Төлөгдсөн</span>
          <?php elseif ($o['status'] === 'Cancelled'): ?>
            <span class="badge badge-cancelled">Цуцлагдсан</span>
          <?php else: ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <a href="payment.php?order_id=<?= $o['order_ID'] ?>" class="btn btn-sm btn-accent">Төлөх</a>
              <form method="POST" onsubmit="return confirm('Захиалгыг цуцлах уу?');" style="display:inline">
                <input type="hidden" name="cancel_order_id" value="<?= $o['order_ID'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Цуцлах</button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
