<?php
require_once '../includes/auth.php';
requireRole('Admin');
require_once '../includes/db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!validatePostedFields($pdo, $_POST, $_SESSION['username'] ?? null)) {
        $msg = ['type'=>'error','text'=>'Оруулсан мэдээлэл зөвшөөрөгдөхгүй тэмдэгт агуулсан байна.'];
    } elseif ($action === 'add_food') {
        $name = cleanText($_POST['name'] ?? '', 100);
        $price = cleanInt($_POST['price'] ?? null, 0, 100000000);
        if (!$name || $price === null) {
            $msg = ['type'=>'error','text'=>'Хоолны нэр болон үнийг зөв оруулна уу.'];
        } else {
        $maxID = $pdo->query("SELECT MAX(food_ID) FROM Food_Info")->fetchColumn();
        $newID = ($maxID ?? 100) + 1;
        $pdo->prepare("INSERT INTO Food_Info VALUES (?,?,?,?)")
            ->execute([$newID, $name, 'Available', $price]);
        logSecurityEvent($pdo, 'food_add', $_SESSION['username'] ?? null, true, 'food_ID=' . $newID);
        $msg = ['type'=>'success','text'=>'✓ Хоол нэмэгдлээ.'];
        }
    }

    if ($action === 'toggle_food') {
        $foodID = cleanInt($_POST['food_id'] ?? null);
        if (!$foodID) {
            $msg = ['type'=>'error','text'=>'Хоолны ID буруу байна.'];
        } else {
        $food = $pdo->prepare("SELECT status FROM Food_Info WHERE food_ID=?");
        $food->execute([$foodID]);
        $cur = $food->fetchColumn();
        $new = $cur === 'Available' ? 'Unavailable' : 'Available';
        $pdo->prepare("UPDATE Food_Info SET status=? WHERE food_ID=?")->execute([$new, $foodID]);
        logSecurityEvent($pdo, 'food_status_toggle', $_SESSION['username'] ?? null, true, "food_ID=$foodID status=$new");
        $msg = ['type'=>'success','text'=>'✓ Өөрчлөгдлөө.'];
        }
    }

    if ($action === 'update_price') {
        $foodID = cleanInt($_POST['food_id'] ?? null);
        $price = cleanInt($_POST['price'] ?? null, 0, 100000000);
        if (!$foodID || $price === null) {
            $msg = ['type'=>'error','text'=>'Үнэ эсвэл хоолны ID буруу байна.'];
        } else {
        $pdo->prepare("UPDATE Food_Info SET price=? WHERE food_ID=?")->execute([$price, $foodID]);
        logSecurityEvent($pdo, 'food_price_update', $_SESSION['username'] ?? null, true, "food_ID=$foodID");
        $msg = ['type'=>'success','text'=>'✓ Үнэ шинэчлэгдлээ.'];
        }
    }
}

$foods = $pdo->query("SELECT * FROM Food_Info ORDER BY food_ID")->fetchAll();
$menus = $pdo->query("
    SELECT m.menu_ID, m.menu_name, m.details,
           GROUP_CONCAT(f.name SEPARATOR ', ') AS items
    FROM Menu m
    LEFT JOIN Menu_Items mi ON m.menu_ID = mi.menu_ID
    LEFT JOIN Food_Info f ON mi.food_ID = f.food_ID
    GROUP BY m.menu_ID
")->fetchAll();

$pageTitle = 'Цэс & Хоол';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Dashboard</a>
<a href="users.php" class="nav-link"><span class="icon"></span> Хэрэглэгчид</a>
<span class="nav-label">Удирдлага</span>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="menu.php" class="nav-link active"><span class="icon"></span> Цэс & Хоол</a>
<a href="payments.php" class="nav-link"><span class="icon"></span> Төлбөр</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Цэс & Хоол</h1>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<!-- Menus -->
<div class="card">
  <div class="card-title">📖 Цэсүүд</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <?php foreach ($menus as $m): ?>
    <div style="padding:20px;background:#f7f2ea;border-radius:12px;border:1px solid #e8e0d0">
      <div style="font-weight:600;font-size:16px;margin-bottom:4px"><?= htmlspecialchars($m['menu_name']) ?></div>
      <div style="font-size:12px;color:var(--warm-gray);margin-bottom:12px">🕐 <?= e($m['details']) ?></div>
      <div style="font-size:13px;color:#555"><?= htmlspecialchars($m['items'] ?? '-') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Foods -->
<div class="card">
  <div class="card-title" style="justify-content:space-between">
    <span>🍲 Хоолны жагсаалт</span>
    <button onclick="document.getElementById('addFoodModal').style.display='flex'" class="btn btn-primary">+ Хоол нэмэх</button>
  </div>
  <table>
    <thead><tr><th>ID</th><th>Нэр</th><th>Үнэ</th><th>Төлөв</th><th>Үйлдэл</th></tr></thead>
    <tbody>
      <?php foreach ($foods as $f): ?>
      <tr>
        <td><?= $f['food_ID'] ?></td>
        <td><?= htmlspecialchars($f['name']) ?></td>
        <td id="price_<?= $f['food_ID'] ?>"><strong><?= number_format($f['price']) ?>₮</strong></td>
        <td>
          <?php if ($f['status'] === 'Available'): ?>
            <span class="badge badge-completed">✓ Available</span>
          <?php else: ?>
            <span class="badge badge-cancelled">✗ Unavailable</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:6px;flex-wrap:wrap">
          <button onclick="editPrice(<?= $f['food_ID'] ?>, <?= $f['price'] ?>)" class="btn btn-sm btn-ghost">Үнийн мэдээлэл засах</button>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle_food">
            <input type="hidden" name="food_id" value="<?= $f['food_ID'] ?>">
            <button type="submit" class="btn btn-sm btn-ghost">Төлөв өөрчлөх</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add Food Modal -->
<div id="addFoodModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border-radius:16px;padding:32px;width:400px;max-width:94vw">
    <h3 style="font-family:'Playfair Display',serif;font-size:20px;margin-bottom:20px">Хоол нэмэх</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_food">
      <div class="form-group"><label>Хоолны нэр</label><input name="name" required></div>
      <div class="form-group"><label>Үнэ (₮)</label><input name="price" type="number" min="0" required></div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Нэмэх</button>
        <button type="button" onclick="document.getElementById('addFoodModal').style.display='none'" class="btn btn-ghost">Болих</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Price Modal -->
<div id="priceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border-radius:16px;padding:32px;width:360px;max-width:94vw">
    <h3 style="font-family:'Playfair Display',serif;font-size:20px;margin-bottom:20px">Үнэ засах</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_price">
      <input type="hidden" name="food_id" id="pm_food_id">
      <div class="form-group"><label>Шинэ үнэ (₮)</label><input name="price" id="pm_price" type="number" min="0" required></div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Хадгалах</button>
        <button type="button" onclick="document.getElementById('priceModal').style.display='none'" class="btn btn-ghost">Болих</button>
      </div>
    </form>
  </div>
</div>

<script>
function editPrice(id, price) {
  document.getElementById('pm_food_id').value = id;
  document.getElementById('pm_price').value = price;
  document.getElementById('priceModal').style.display = 'flex';
}
</script>
<?php require_once '../includes/footer.php'; ?>
