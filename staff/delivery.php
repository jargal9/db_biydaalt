<?php
require_once '../includes/auth.php';
requireRole('Staff');
require_once '../includes/db.php';

$staffID = $_SESSION['user_ID'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_id'], $_POST['status'])) {
    $deliveryID = cleanInt($_POST['delivery_id'] ?? null);
    $newStatus = cleanEnum($_POST['status'] ?? '', DELIVERY_STATUSES);

    if (!$deliveryID || !$newStatus) {
        logSecurityEvent($pdo, 'invalid_delivery_update', $_SESSION['username'] ?? null, false, json_encode($_POST));
        $msg = ['type'=>'error','text'=>'Хүргэлтийн төлөв буруу байна.'];
    } else {
        $delivDate = $newStatus === 'Delivered' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare(
            "UPDATE Delivery d
             JOIN Orders o ON d.order_ID = o.order_ID
             SET d.status=?, d.delivery_date=?
             WHERE d.delivery_ID=? AND o.staff_ID=?"
        )->execute([$newStatus, $delivDate, $deliveryID, $staffID]);
        logSecurityEvent($pdo, 'delivery_status_update', $_SESSION['username'] ?? null, true, "delivery_ID=$deliveryID status=$newStatus");
        $msg = ['type'=>'success','text'=>'✓ Хүргэлтийн төлөв шинэчлэгдлээ.'];
    }
}

$deliveries = $pdo->prepare("
    SELECT d.*, o.order_date, c.name AS customer_name, c.address, c.contact
    FROM Delivery d
    JOIN Orders o ON d.order_ID = o.order_ID
    JOIN Users c ON o.customer_ID = c.user_ID
    WHERE o.staff_ID = ?
    ORDER BY d.delivery_ID DESC
");
$deliveries->execute([$staffID]);
$deliveries = $deliveries->fetchAll();

$pageTitle = 'Хүргэлт';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Dashboard</a>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="delivery.php" class="nav-link active"><span class="icon"></span> Хүргэлт</a>
<a href="new_order.php" class="nav-link"><span class="icon"></span> Захиалга авах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Хүргэлт</h1>
  <p>Хүргэлтийн төлөвийг хянах, шинэчлэх</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Хүргэлтийн жагсаалт</div>
  <table>
    <thead><tr><th>Хүргэлт #</th><th>Захиалга #</th><th>Харилцагч</th><th>Хаяг</th><th>Утас</th><th>Төлөв</th><th>Хүргэсэн огноо</th><th>Үйлдэл</th></tr></thead>
    <tbody>
      <?php foreach ($deliveries as $d): ?>
      <tr>
        <td><strong>#<?= $d['delivery_ID'] ?></strong></td>
        <td>#<?= $d['order_ID'] ?></td>
        <td><?= htmlspecialchars($d['customer_name']) ?></td>
        <td style="font-size:12px;color:var(--warm-gray)"><?= htmlspecialchars($d['address']) ?></td>
        <td><?= e($d['contact']) ?></td>
        <td>
          <?php
          $sc = strtolower(str_replace(' ','',$d['status']));
          $cls = $sc === 'delivered' ? 'completed' : ($sc === 'cancelled' ? 'cancelled' : ($sc === 'inprogress' ? 'processing' : 'pending'));
          echo "<span class='badge badge-$cls'>" . e(statusLabel($d['status'])) . "</span>";
          ?>
        </td>
        <td style="font-size:12px;color:var(--warm-gray)"><?= $d['delivery_date'] ?? '—' ?></td>
        <td>
          <?php if ($d['status'] !== 'Delivered' && $d['status'] !== 'Cancelled'): ?>
          <form method="POST" style="display:flex;gap:4px">
            <input type="hidden" name="delivery_id" value="<?= $d['delivery_ID'] ?>">
            <select name="status" style="padding:5px 7px;border-radius:6px;border:1px solid var(--border);font-size:12px">
              <?php foreach(['Pending','In Progress','Delivered','Cancelled'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $d['status']===$s?'selected':'' ?>><?= e(statusLabel($s)) ?></option>
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
