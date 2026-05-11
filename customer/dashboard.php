<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];

$myOrders = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE customer_ID=?");
$myOrders->execute([$userID]);
$myTotal = $myOrders->fetchColumn();

$mySpent = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM Payment p JOIN Orders o ON p.order_ID=o.order_ID WHERE o.customer_ID=?");
$mySpent->execute([$userID]);
$mySpentVal = $mySpent->fetchColumn();

$recentOrders = $pdo->prepare("
    SELECT o.order_ID, o.order_date, o.status,
           COALESCE(ANY_VALUE(p.amount), SUM(od.quantity * od.unit_price)) AS amount,
           ANY_VALUE(d.status) AS delivery_status,
           GROUP_CONCAT(f.name, ' x', od.quantity SEPARATOR ', ') AS items
    FROM Orders o
    LEFT JOIN Payment p ON o.order_ID = p.order_ID
    LEFT JOIN Delivery d ON o.order_ID = d.order_ID
    LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
    LEFT JOIN Food_Info f ON od.food_ID = f.food_ID
    WHERE o.customer_ID = ?
    GROUP BY o.order_ID
    ORDER BY o.order_date DESC LIMIT 5
");
$recentOrders->execute([$userID]);
$recentOrders = $recentOrders->fetchAll();

$pageTitle = 'Нүүр хуудас';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link active"><span class="icon"></span> Нүүр хуудас</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Захиалах</a>
<a href="my_orders.php" class="nav-link"><span class="icon"></span> Миний захиалга</a>
<a href="profile.php" class="nav-link"><span class="icon"></span> Мэдээлэл засах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Тавтай морил, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>!</h1>

</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт захиалга</div>
    <div class="stat-value"><?= $myTotal ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт зарцуулсан</div>
    <div class="stat-value" style="font-size:24px"><?= number_format($mySpentVal) ?><small style="font-size:13px;color:var(--warm-gray)">₮</small></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <div class="card" style="grid-column:1/-1">
    <div class="card-title" style="justify-content:space-between">
      <span> Сүүлийн захиалгууд</span>
      <a href="menu.php" class="btn btn-accent">+ Захиалах</a>
    </div>
    <?php if (empty($recentOrders)): ?>
      <p style="color:var(--warm-gray);font-size:14px;padding:20px 0">Захиалга байхгүй байна. Хоол захиалахын тулд цэс харна уу.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Хоол</th><th>Дүн</th><th>Огноо</th><th>Захиалга</th><th>Хүргэлт</th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><strong>#<?= $o['order_ID'] ?></strong></td>
          <td style="font-size:13px;color:var(--warm-gray)"><?= htmlspecialchars($o['items'] ?? '-') ?></td>
          <td><?= $o['amount'] !== null ? number_format($o['amount']) . '₮' : '—' ?></td>
          <td style="font-size:12px;color:var(--warm-gray)"><?= $o['order_date'] ?></td>
          <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span></td>
          <td>
            <?php
            $ds = strtolower(str_replace(' ','',$o['delivery_status'] ?? ''));
            $cls = $ds === 'delivered' ? 'delivered' : ($ds === 'cancelled' ? 'cancelled' : 'pending');
            echo "<span class='badge badge-$cls'>" . ($o['delivery_status'] ?? '—') . "</span>";
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