<?php
require_once '../includes/auth.php';
requireRole('Admin');
require_once '../includes/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $orderID = cleanInt($_POST['order_id'] ?? null);
    $status = cleanEnum($_POST['status'] ?? '', ORDER_STATUSES);

    if (!$orderID || !$status) {
        logSecurityEvent($pdo, 'invalid_order_update', $_SESSION['username'] ?? null, false, json_encode($_POST));
        $msg = ['type'=>'error','text'=>'Захиалгын төлөв буруу байна.'];
    } else {
        $pdo->prepare("UPDATE Orders SET status=? WHERE order_ID=?")->execute([$status, $orderID]);
        $pdo->prepare("UPDATE Delivery SET status=? WHERE order_ID=?")->execute([
            $status === 'Completed' ? 'Delivered' : $status,
            $orderID
        ]);
        logSecurityEvent($pdo, 'order_status_update', $_SESSION['username'] ?? null, true, "order_ID=$orderID status=$status");
        $msg = ['type'=>'success','text'=>'✓ Захиалгын төлөв шинэчлэгдлээ.'];
    }
}

$orders = $pdo->query("
    SELECT o.*, 
           c.name AS customer_name, s.name AS staff_name,
           COALESCE(SUM(od.quantity * f.price), ANY_VALUE(p.amount), 0) AS amount,
           ANY_VALUE(d.status) AS delivery_status,
           GROUP_CONCAT(f.name, ' x', od.quantity SEPARATOR ' | ') AS items
    FROM Orders o
    JOIN Users c ON o.customer_ID = c.user_ID
    LEFT JOIN Users s ON o.staff_ID = s.user_ID
    LEFT JOIN Payment p ON o.order_ID = p.order_ID
    LEFT JOIN Delivery d ON o.order_ID = d.order_ID
    LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
    LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
    GROUP BY o.order_ID
    ORDER BY o.order_date DESC
")->fetchAll();

$pageTitle = 'Захиалгууд';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Dashboard</a>
<a href="users.php" class="nav-link"><span class="icon"></span> Хэрэглэгчид</a>
<span class="nav-label">Удирдлага</span>
<a href="orders.php" class="nav-link active"><span class="icon"></span> Захиалгууд</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Хоол</a>
<a href="payments.php" class="nav-link"><span class="icon"></span> Төлбөр</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Захиалгууд</h1>
  <p>Бүх захиалгыг хянах, төлөв өөрчлөх</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Бүх захиалга</div>
  <div style="overflow-x:auto; overflow-y:auto; max-height:620px; padding-right:6px;">
    <table>
      <thead><tr><th>#</th><th>Харилцагч</th><th>Ажилтан</th><th>Хоол</th><th>Дүн</th><th>Огноо</th><th>Төлөв</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><strong>#<?= $o['order_ID'] ?></strong></td>
        <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <td><?= htmlspecialchars($o['staff_name'] ?? '-') ?></td>
        <td style="font-size:12px;color:var(--warm-gray);max-width:180px"><?= htmlspecialchars($o['items'] ?? '-') ?></td>
        <td><strong><?= number_format($o['amount']) ?>₮</strong></td>
        <td style="font-size:12px;color:var(--warm-gray)"><?= $o['order_date'] ?></td>
        <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span></td>
        <td>
          <form method="POST" style="display:flex;gap:4px;align-items:center">
            <input type="hidden" name="order_id" value="<?= $o['order_ID'] ?>">
            <select name="status" style="padding:6px 8px;border-radius:6px;border:1px solid var(--border);font-size:12px">
              <?php foreach(['Pending','Processing','Completed','Cancelled'] as $s): ?>
              <option <?= $o['status']===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-accent">→</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once '../includes/footer.php'; ?>
