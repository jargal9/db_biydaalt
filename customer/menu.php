<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $filtered = [];
    foreach ($items as $i => $fid) {
        $foodID = cleanInt($fid ?? null);
        $qty = cleanInt($quantities[$i] ?? null, 1, 50);
        if ($foodID && $qty) $filtered[] = ['food'=>$foodID,'qty'=>$qty];
    }

    if (!validatePostedFields($pdo, $_POST, $_SESSION['username'] ?? null)) {
        $msg = ['type'=>'error','text'=>'Оруулсан мэдээлэл зөвшөөрөгдөхгүй тэмдэгт агуулсан байна.'];
    } elseif (!empty($filtered)) {
        // Pick any available staff
        $staff = $pdo->query("SELECT user_ID FROM Users WHERE role='Staff' LIMIT 1")->fetchColumn();

        $maxOrder = $pdo->query("SELECT MAX(order_ID) FROM Orders")->fetchColumn();
        $orderID = ($maxOrder ?? 500) + 1;
        $pdo->prepare("INSERT INTO Orders VALUES (?,?,?,?,?)")
            ->execute([$orderID, $userID, $staff, date('Y-m-d H:i:s'), 'Pending']);

        $total = 0; $did = 1;
        foreach ($filtered as $item) {
            $price = $pdo->prepare("SELECT price FROM Food_Info WHERE food_ID=?");
            $price->execute([$item['food']]);
            $p = $price->fetchColumn();
            $pdo->prepare("INSERT INTO Order_Details VALUES (?,?,?,?,?)")
                ->execute([$did++, $orderID, $item['food'], $item['qty'], $p]);
            $total += $p * $item['qty'];
        }

        $maxDel = $pdo->query("SELECT MAX(delivery_ID) FROM Delivery")->fetchColumn();
        $pdo->prepare("INSERT INTO Delivery VALUES (?,?,NULL,?)")
            ->execute([($maxDel??700)+1, $orderID, 'Pending']);

        logSecurityEvent($pdo, 'order_create', $_SESSION['username'] ?? null, true, 'order_ID=' . $orderID);
        $msg = ['type'=>'success','text'=>"✓ Захиалга #$orderID бүртгэгдлээ. Төлбөр хийхийн тулд 'Миний захиалга' хэсэг рүү очно уу."];
    } else {
        $msg = ['type'=>'error','text'=>'Дор хаяж нэг хоол сонгоно уу.'];
    }
}

$menus = $pdo->query("SELECT * FROM Menu ORDER BY menu_ID")->fetchAll();
$allFoods = $pdo->query("
    SELECT f.*, m.menu_ID, m.menu_name
    FROM Food_Info f
    JOIN Menu_Items mi ON f.food_ID = mi.food_ID
    JOIN Menu m ON mi.menu_ID = m.menu_ID
    WHERE f.status='Available'
    ORDER BY m.menu_ID, f.food_ID
")->fetchAll();

// Group by menu
$grouped = [];
foreach ($allFoods as $f) {
    $grouped[$f['menu_ID']]['name'] = $f['menu_name'];
    $grouped[$f['menu_ID']]['items'][] = $f;
}

$pageTitle = 'Цэс & Захиалах';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Нүүр хуудас</a>
<a href="menu.php" class="nav-link active"><span class="icon"></span> Цэс & Захиалах</a>
<a href="my_orders.php" class="nav-link"><span class="icon"></span> Миний захиалга</a>
<a href="profile.php" class="nav-link"><span class="icon"></span> Мэдээлэл засах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Цэс</h1>
  <p>Хоолоо сонгоод захиалаарай</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<form method="POST" id="menuForm">
<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">
  <div>
    <?php foreach ($grouped as $menuID => $menu): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title" style="font-size:17px"><?= htmlspecialchars($menu['name']) ?></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px">
        <?php foreach ($menu['items'] as $f): ?>
        <div class="food-card" onclick="addToCart(<?= (int)$f['food_ID'] ?>, <?= htmlspecialchars(json_encode($f['name']), ENT_QUOTES, 'UTF-8') ?>, <?= (int)$f['price'] ?>)"
             style="padding:18px;background:#f7f2ea;border:2px solid transparent;border-radius:12px;cursor:pointer;transition:all 0.15s;user-select:none"
             onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='transparent'">
          <div style="font-weight:600;margin-bottom:4px"><?= htmlspecialchars($f['name']) ?></div>
          <div style="font-size:18px;font-family:'Playfair Display',serif;color:var(--gold)"><?= number_format($f['price']) ?>₮</div>
          <div style="margin-top:10px;font-size:12px;color:var(--warm-gray)">Tap to add →</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="position:sticky;top:24px">
    <div class="card">
      <div class="card-title">🛒 Сагс</div>
      <div id="quantityWarning" class="alert alert-error" style="display:none;margin-bottom:12px">Хоолны тоо ширхэг 50-с дээш байх боломжгүй.</div>
      <div id="cartContainer">
        <p id="emptyMsg" style="color:var(--warm-gray);font-size:14px;padding:12px 0">Хоол сонгоогүй байна...</p>
      </div>
      <div id="cartSummary" style="display:none;border-top:2px solid var(--border);margin-top:16px;padding-top:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:16px">
          <span style="font-weight:600">Нийт дүн:</span>
          <span id="cartTotal" style="font-family:'Playfair Display',serif;font-size:22px"></span>
        </div>
        <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;padding:13px;font-size:15px">
          ✓ Захиалах
        </button>
      </div>
    </div>
  </div>
</div>
<div id="hiddenInputs"></div>
</form>

<script>
let cart = {};

function showQuantityWarning() {
  const warning = document.getElementById('quantityWarning');
  warning.style.display = 'block';
}

function addToCart(id, name, price) {
  if (cart[id]) {
    if (cart[id].qty >= 50) showQuantityWarning();
    cart[id].qty = Math.min(cart[id].qty + 1, 50);
  } else {
    cart[id] = { name, price, qty: 1 };
  }
  renderCart();
}

function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) delete cart[id];
  else if (cart[id].qty > 50) {
    showQuantityWarning();
    cart[id].qty = 50;
  }
  renderCart();
}

function renderCart() {
  const container = document.getElementById('cartContainer');
  const summary = document.getElementById('cartSummary');
  const empty = document.getElementById('emptyMsg');
  const hidden = document.getElementById('hiddenInputs');

  const ids = Object.keys(cart);
  hidden.innerHTML = '';

  if (ids.length === 0) {
    empty.style.display = 'block';
    summary.style.display = 'none';
    container.innerHTML = '<p id="emptyMsg" style="color:var(--warm-gray);font-size:14px;padding:12px 0">Хоол сонгоогүй байна...</p>';
    return;
  }

  let html = '';
  let total = 0;
  ids.forEach((id, i) => {
    const item = cart[id];
    const sub = item.price * item.qty;
    total += sub;
    html += `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:14px">
      <div>
        <div style="font-weight:500">${item.name}</div>
        <div style="font-size:12px;color:var(--warm-gray)">${item.price.toLocaleString()}₮ × ${item.qty}</div>
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <button type="button" onclick="changeQty(${id},-1)" style="width:26px;height:26px;border-radius:50%;border:1px solid var(--border);background:transparent;cursor:pointer;font-size:16px">−</button>
        <span style="font-weight:600;min-width:20px;text-align:center">${item.qty}</span>
        <button type="button" onclick="changeQty(${id},1)" style="width:26px;height:26px;border-radius:50%;border:1px solid var(--border);background:transparent;cursor:pointer;font-size:16px">+</button>
      </div>
    </div>`;
    hidden.innerHTML += `<input type="hidden" name="items[]" value="${id}"><input type="hidden" name="quantities[]" value="${item.qty}">`;
  });

  container.innerHTML = html;
  document.getElementById('cartTotal').textContent = total.toLocaleString() + '₮';
  summary.style.display = 'block';
}
</script>

<?php require_once '../includes/footer.php'; ?>
