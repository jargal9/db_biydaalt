<?php
require_once '../includes/auth.php';
requireRole('Admin');
require_once '../includes/db.php';

$totalPayments = $pdo->query("SELECT COUNT(*) FROM Payment")->fetchColumn();
$totalAmount = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM Payment")->fetchColumn();

$recentOrders = $pdo->query(
    "SELECT o.order_ID, ANY_VALUE(o.order_date) AS order_date, ANY_VALUE(o.status) AS order_status, 
            ANY_VALUE(p.amount) AS amount, ANY_VALUE(p.pay_method) AS pay_method,
            ANY_VALUE(u.name) AS customer_name, ANY_VALUE(d.status) AS delivery_status,
            GROUP_CONCAT(f.name, ' x', od.quantity SEPARATOR ', ') AS items
     FROM Orders o
     JOIN Payment p ON o.order_ID = p.order_ID
     JOIN Users u ON o.customer_ID = u.user_ID
     LEFT JOIN Delivery d ON o.order_ID = d.order_ID
     LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
     LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
     GROUP BY o.order_ID
     ORDER BY o.order_date DESC
     LIMIT 10"
)->fetchAll();

$pageTitle = 'Төлбөр';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link active"><span class="icon"></span> Dashboard</a>
<a href="users.php" class="nav-link"><span class="icon"></span> Хэрэглэгчид</a>
<span class="nav-label">Удирдлага</span>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Хоол</a>
<a href="payments.php" class="nav-link active"><span class="icon"></span> Төлбөр</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Төлбөрийн хяналт</h1>
  <p>Админын төлбөрийн мэдээлэл</p>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт төлбөрийн бичлэг</div>
    <div class="stat-value"><?= $totalPayments ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт орлого</div>
    <div class="stat-value" style="font-size:24px"><?= number_format($totalAmount) ?><small style="font-size:13px;color:var(--warm-gray)">₮</small></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:24px">
  <div class="card">
    <div class="card-title">
      <span>Сүүлийн төлбөрүүд</span>
    </div>
    <?php if (empty($recentOrders)): ?>
      <p style="color:var(--warm-gray);font-size:14px;padding:20px 0">Төлбөрийн бичлэг алга байна.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Харилцагч</th><th>Хоол</th><th>Төлбөр</th><th>Арга</th><th>Огноо</th><th>Захиалга</th><th>Хүргэлт</th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><strong>#<?= $o['order_ID'] ?></strong></td>
          <td><?= htmlspecialchars($o['customer_name']) ?></td>
          <td style="font-size:13px;color:var(--warm-gray)"><?= htmlspecialchars($o['items'] ?? '-') ?></td>
          <td><?= number_format($o['amount']) ?>₮</td>
          <td><?= htmlspecialchars($o['pay_method'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--warm-gray)"><?= $o['order_date'] ?></td>
          <td><span class="badge badge-<?= strtolower($o['order_status']) ?>"><?= e(statusLabel($o['order_status'])) ?></span></td>
          <td>
            <?php
            $ds = strtolower(str_replace(' ','',$o['delivery_status'] ?? ''));
            $cls = $ds === 'delivered' ? 'delivered' : ($ds === 'cancelled' ? 'cancelled' : 'pending');
            echo "<span class='badge badge-$cls'>" . e(statusLabel($o['delivery_status'] ?? '—')) . "</span>";
            ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
