<?php
require_once '../includes/auth.php';
requireRole('Staff');
require_once '../includes/db.php';

$staffID = $_SESSION['user_ID'];

$myOrders    = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE staff_ID=?");
$myOrders->execute([$staffID]);
$myTotal = $myOrders->fetchColumn();

$myPending   = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE staff_ID=? AND status='Pending'");
$myPending->execute([$staffID]);
$myPendingC = $myPending->fetchColumn();

$myCompleted = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE staff_ID=? AND status='Completed'");
$myCompleted->execute([$staffID]);
$myCompletedC = $myCompleted->fetchColumn();

$recentOrders = $pdo->prepare("
    SELECT o.order_ID, ANY_VALUE(u.name) AS customer, ANY_VALUE(o.order_date) AS order_date, ANY_VALUE(o.status) AS status, 
           COALESCE(ANY_VALUE(p.amount), SUM(od.quantity * f.price), 0) AS amount
    FROM Orders o
    JOIN Users u ON o.customer_ID = u.user_ID
    LEFT JOIN Payment p ON o.order_ID = p.order_ID
    LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
    LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
    WHERE o.staff_ID = ?
    GROUP BY o.order_ID
    ORDER BY o.order_date DESC LIMIT 8
");
$recentOrders->execute([$staffID]);
$recentOrders = $recentOrders->fetchAll();

$pageTitle = 'Нүүр хуудас';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link active"><span class="icon"></span> Нүүр хуудас</a>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="delivery.php" class="nav-link"><span class="icon"></span> Хүргэлт</a>
<a href="new_order.php" class="nav-link"><span class="icon"></span> Захиалга авах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Сайн байна уу, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>!</h1>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Миний захиалга</div>
    <div class="stat-value"><?= $myTotal ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Хүлээгдэж буй</div>
    <div class="stat-value"><?= $myPendingC ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Дууссан</div>
    <div class="stat-value"><?= $myCompletedC ?></div>
  </div>
</div>

<div class="card">
  <div class="card-title" style="justify-content:space-between">
    <span>Миний захиалгууд</span>
    <a href="new_order.php" class="btn btn-accent">+ Шинэ захиалга</a>
  </div>
  <table>
    <thead><tr><th>#</th><th>Харилцагч</th><th>Огноо</th><th>Дүн</th><th>Төлөв</th></tr></thead>
    <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><strong>#<?= $o['order_ID'] ?></strong></td>
        <td><?= htmlspecialchars($o['customer']) ?></td>
        <td style="font-size:12px;color:var(--warm-gray)"><?= $o['order_date'] ?></td>
        <td><?= $o['amount'] ? number_format($o['amount']) . '₮' : '—' ?></td>
        <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= e(statusLabel($o['status'])) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once '../includes/footer.php'; ?>
