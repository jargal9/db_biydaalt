<?php
require_once '../includes/auth.php';
requireRole('Staff');
require_once '../includes/db.php';

$staffID = $_SESSION['user_ID'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $orderID = cleanInt($_POST['order_id'] ?? null);
    $status = cleanEnum($_POST['status'] ?? '', ORDER_STATUSES);

    if (!$orderID || !$status) {
        logSecurityEvent($pdo, 'invalid_order_update', $_SESSION['username'] ?? null, false, json_encode($_POST));
        $msg = ['type'=>'error','text'=>'Захиалгын төлөв буруу байна.'];
    } else {
        $pdo->prepare("UPDATE Orders SET status=? WHERE order_ID=? AND staff_ID=?")->execute([$status, $orderID, $staffID]);
        logSecurityEvent($pdo, 'order_status_update', $_SESSION['username'] ?? null, true, "order_ID=$orderID status=$status");
        $msg = ['type'=>'success','text'=>'✓ Захиалгын төлөв шинэчлэгдлээ.'];
    }
}

$orders = $pdo->prepare("
    SELECT o.*, 
           ANY_VALUE(c.name) AS customer_name,
           COALESCE(ANY_VALUE(p.amount), SUM(od.quantity * f.price), 0) AS amount,
           GROUP_CONCAT(f.name, ' x', od.quantity SEPARATOR ' | ') AS items
    FROM Orders o
    JOIN Users c ON o.customer_ID = c.user_ID
    LEFT JOIN Payment p ON o.order_ID = p.order_ID
    LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
    LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
    WHERE o.staff_ID = ?
    GROUP BY o.order_ID
    ORDER BY o.order_date DESC
");
$orders->execute([$staffID]);
$orders = $orders->fetchAll();

$pageTitle = 'Захиалгууд';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Dashboard</a>
<a href="orders.php" class="nav-link active"><span class="icon"></span> Захиалгууд</a>
<a href="delivery.php" class="nav-link"><span class="icon"></span> Хүргэлт</a>
<a href="new_order.php" class="nav-link"><span class="icon"></span> Захиалга авах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Миний захиалгууд</h1>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-title" style="justify-content:space-between">
    <span>Захиалгын жагсаалт</span>
    <a href="new_order.php" class="btn btn-accent">+ Шинэ захиалга</a>
  </div>
  <table>
    <thead><tr><th>#</th><th>Харилцагч</th><th>Хоол</th><th>Дүн</th><th>Огноо</th><th>Төлөв</th><th>Үйлдэл</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><strong>#<?= $o['order_ID'] ?></strong></td>
        <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <td style="font-size:12px;color:var(--warm-gray);max-width:160px"><?= htmlspecialchars($o['items'] ?? '-') ?></td>
        <td><?= $o['amount'] ? number_format($o['amount']) . '₮' : '—' ?></td>
        <td style="font-size:12px;color:var(--warm-gray)"><?= $o['order_date'] ?></td>
        <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= e(statusLabel($o['status'])) ?></span></td>
        <td>
          <?php if (!in_array($o['status'], ['Completed','Cancelled'])): ?>
          <form method="POST" style="display:flex;gap:4px">
            <input type="hidden" name="order_id" value="<?= $o['order_ID'] ?>">
            <select name="status" style="padding:5px 7px;border-radius:6px;border:1px solid var(--border);font-size:12px">
              <?php foreach(['Pending','Processing','Completed','Cancelled'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $o['status']===$s?'selected':'' ?>><?= e(statusLabel($s)) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-accent">→</button>
          </form>
          <?php else: echo '<span style="color:var(--warm-gray);font-size:12px">—</span>'; endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once '../includes/footer.php'; ?>
