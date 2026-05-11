<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];
$msg = '';

if (isset($_GET['payment_success'])) {
    $paidID = cleanInt($_GET['payment_success'] ?? null) ?? '';
    $msg = ['type' => 'success', 'text' => "✓ Захиалга #" . e($paidID) . " төлөгдлөө."];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $orderID = cleanInt($_POST['cancel_order_id'] ?? null);
    if (!$orderID) {
        logSecurityEvent($pdo, 'invalid_order_cancel', $_SESSION['username'] ?? null, false, json_encode($_POST));
        $msg = ['type' => 'error', 'text' => 'Захиалгын ID буруу байна.'];
    } else {
    $statusStmt = $pdo->prepare("SELECT status FROM Orders WHERE order_ID = ? AND customer_ID = ?");
    $statusStmt->execute([$orderID, $userID]);
    $currentStatus = $statusStmt->fetchColumn();

    if ($currentStatus && $currentStatus !== 'Completed' && $currentStatus !== 'Cancelled') {
        $pdo->prepare("UPDATE Orders SET status = 'Cancelled' WHERE order_ID = ?")->execute([$orderID]);
        $pdo->prepare("UPDATE Delivery SET status = 'Cancelled' WHERE order_ID = ?")->execute([$orderID]);
        logSecurityEvent($pdo, 'order_cancel', $_SESSION['username'] ?? null, true, 'order_ID=' . $orderID);
        $msg = ['type' => 'success', 'text' => "✓ Захиалга #$orderID амжилттай цуцлагдлаа."];
    } else {
        $msg = ['type' => 'error', 'text' => 'Энэ захиалгыг цуцлах боломжгүй байна.'];
    }
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
    <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
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
        <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= e(statusLabel($o['status'])) ?></span></td>
        <td>
          <?php
          $delivery = strtolower(str_replace(' ', '', $o['delivery_status'] ?? 'Pending'));
          $deliveryClass = in_array($delivery, ['delivered','cancelled','pending']) ? $delivery : 'pending';
          echo "<span class='badge badge-$deliveryClass'>" . e(statusLabel($o['delivery_status'] ?? 'Pending')) . "</span>";
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
              <form method="POST" class="cancel-order-form" data-order-id="<?= e($o['order_ID']) ?>" style="display:inline">
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

<style>
  .app-modal-backdrop {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(26, 17, 8, 0.46);
    z-index: 1000;
  }

  .app-modal-backdrop.is-open {
    display: flex;
  }

  .app-modal {
    width: min(420px, 94vw);
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 24px 70px rgba(0,0,0,0.22);
    padding: 24px;
  }

  .app-modal h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    margin-bottom: 8px;
    color: var(--ink);
  }

  .app-modal p {
    color: var(--warm-gray);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 22px;
  }

  .app-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
</style>

<div class="app-modal-backdrop" id="cancelOrderModal" aria-hidden="true">
  <div class="app-modal" role="dialog" aria-modal="true" aria-labelledby="cancelOrderTitle">
    <h2 id="cancelOrderTitle">Захиалга цуцлах</h2>
    <p id="cancelOrderMessage">Та энэ захиалгыг цуцлахдаа итгэлтэй байна уу?</p>
    <div class="app-modal-actions">
      <button type="button" class="btn btn-ghost" id="cancelOrderNo">Болих</button>
      <button type="button" class="btn btn-danger" id="cancelOrderYes">Цуцлах</button>
    </div>
  </div>
</div>

<script>
let pendingCancelForm = null;
const cancelModal = document.getElementById('cancelOrderModal');
const cancelMessage = document.getElementById('cancelOrderMessage');

document.querySelectorAll('.cancel-order-form').forEach(form => {
  form.addEventListener('submit', event => {
    event.preventDefault();
    pendingCancelForm = form;
    cancelMessage.textContent = `#${form.dataset.orderId} захиалгыг цуцлахдаа итгэлтэй байна уу?`;
    cancelModal.classList.add('is-open');
    cancelModal.setAttribute('aria-hidden', 'false');
  });
});

document.getElementById('cancelOrderNo').addEventListener('click', () => {
  pendingCancelForm = null;
  cancelModal.classList.remove('is-open');
  cancelModal.setAttribute('aria-hidden', 'true');
});

document.getElementById('cancelOrderYes').addEventListener('click', () => {
  if (pendingCancelForm) {
    pendingCancelForm.submit();
  }
});

cancelModal.addEventListener('click', event => {
  if (event.target === cancelModal) {
    document.getElementById('cancelOrderNo').click();
  }
});
</script>

<?php require_once '../includes/footer.php'; ?>
