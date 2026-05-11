<?php
require_once '../includes/auth.php';
requireRole('Admin');
require_once '../includes/db.php';

// Stats
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn();
$totalOrders   = $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn();
$totalRevenue  = $pdo->query("SELECT SUM(amount) FROM Payment WHERE amount > 0")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM Orders WHERE status = 'Pending'")->fetchColumn();

// Recent orders
$recentOrders = $pdo->query("
    SELECT o.order_ID, u.name AS customer, o.order_date, o.status,
           COALESCE(ANY_VALUE(p.amount), SUM(od.quantity * od.unit_price)) AS amount
    FROM Orders o
    JOIN Users u ON o.customer_ID = u.user_ID
    LEFT JOIN Payment p ON o.order_ID = p.order_ID
    LEFT JOIN Order_Details od ON o.order_ID = od.order_ID
    GROUP BY o.order_ID
    ORDER BY o.order_date DESC LIMIT 8
")->fetchAll();

// Users by role
$userRoles = $pdo->query("SELECT role, COUNT(*) as cnt FROM Users GROUP BY role")->fetchAll();

$pageTitle = 'Нүүр хуудас';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link active"><span class="icon"></span>Нүүр хуудас</a>
<a href="users.php" class="nav-link"><span class="icon"></span> Хэрэглэгчид</a>
<span class="nav-label">Удирдлага</span>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Хоол</a>
<a href="payments.php" class="nav-link"><span class="icon"></span> Төлбөр</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Нүүр хуудас</h1>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт хэрэглэгч</div>
    <div class="stat-value"><?= $totalUsers ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт захиалга</div>
    <div class="stat-value"><?= $totalOrders ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Нийт орлого</div>
    <div class="stat-value"><?= number_format($totalRevenue) ?><small style="font-size:14px;color:var(--warm-gray)">₮</small></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-label">Хүлээгдэж буй</div>
    <div class="stat-value"><?= $pendingOrders ?></div>
  </div>
</div>

<div class="card">
  <div class="card-title">Сүүлийн захиалгууд</div>
  <table>
    <thead>
      <tr><th>#</th><th>Харилцагч</th><th>Огноо</th><th>Төлөв</th><th>Дүн</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><strong>#<?= $o['order_ID'] ?></strong></td>
        <td><?= htmlspecialchars($o['customer']) ?></td>
        <td style="color:var(--warm-gray);font-size:13px"><?= $o['order_date'] ?></td>
        <td>
          <?php
          $sc = strtolower($o['status']);
          echo "<span class='badge badge-$sc'>" . e(statusLabel($o['status'])) . "</span>";
          ?>
        </td>
        <td><strong><?= $o['amount'] !== null ? number_format($o['amount']) . '₮' : '—' ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <div class="card-title">Хэрэглэгчийн бүтэц</div>
  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <?php foreach ($userRoles as $r):
      $cls = strtolower($r['role']);
    ?>
    <div style="flex:1;min-width:140px;padding:20px;background:#f7f2ea;border-radius:12px;text-align:center">
      <div style="font-size:28px;font-weight:700;font-family:'Playfair Display',serif"><?= $r['cnt'] ?></div>
      <div style="margin-top:6px"><span class="badge badge-<?= $cls ?>"><?= $r['role'] ?></span></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
