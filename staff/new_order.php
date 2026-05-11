<?php
require_once '../includes/auth.php';
requireRole('Staff');
require_once '../includes/db.php';

$staffID = $_SESSION['user_ID'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID = cleanInt($_POST['customer_id'] ?? null);
    $customerName = cleanText($_POST['customer_name'] ?? '', 100, true);
    $customerContact = cleanText($_POST['customer_contact'] ?? '', 50, true);
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $validItems = [];

    foreach ($items as $i => $foodID) {
        $foodID = cleanInt($foodID ?? null);
        $qty = cleanInt($quantities[$i] ?? null, 1, 50);
        if ($foodID && $qty) {
            $validItems[] = ['food' => $foodID, 'qty' => $qty];
        }
    }

    if (!validatePostedFields($pdo, $_POST, $_SESSION['username'] ?? null)) {
        $msg = ['type'=>'error','text'=>'Оруулсан мэдээлэл зөвшөөрөгдөхгүй тэмдэгт агуулсан байна.'];
    } elseif (empty($validItems)) {
        $msg = ['type'=>'error','text'=>'Дор хаяж нэг хоол сонгож, тоо ширхэгийг 1-50 хооронд оруулна уу.'];
    } else {
    try {
    $pdo->beginTransaction();

    // If no existing customer selected, create new one
    if (!$customerID && $customerName) {
        $maxID = $pdo->query("SELECT MAX(user_ID) FROM Users")->fetchColumn();
        $customerID = ($maxID ?? 0) + 1;
        $pdo->prepare("INSERT INTO Users VALUES (?,?,?,?,?,?,?)")
            ->execute([$customerID, $customerName, $customerContact, '', 'temp_' . time(), hashUserPassword(bin2hex(random_bytes(8))), 'Customer']);
    }

    if ($customerID) {
        // Create order
        $maxOrder = $pdo->query("SELECT MAX(order_ID) FROM Orders")->fetchColumn();
        $orderID = ($maxOrder ?? 500) + 1;

        $pdo->prepare("INSERT INTO Orders VALUES (?,?,?,?,?)")
            ->execute([$orderID, $customerID, $staffID, date('Y-m-d H:i:s'), 'Pending']);

        $total = 0;
        $detailID = 1;
        foreach ($validItems as $item) {
            $priceRow = $pdo->prepare("SELECT price FROM Food_Info WHERE food_ID=? AND status='Available' AND price > 0");
            $foodID = $item['food'];
            $qty = $item['qty'];
            $priceRow->execute([$foodID]);
            $price = $priceRow->fetchColumn();
            if ($price === false) continue;
            $pdo->prepare("INSERT INTO Order_Details VALUES (?,?,?,?,?)")
                ->execute([$detailID++, $orderID, $foodID, $qty, $price]);
            $total += $price * $qty;
        }

        if ($detailID === 1 || $total <= 0) {
            throw new RuntimeException('No valid order items.');
        }

        // Payment
        $maxPay = $pdo->query("SELECT MAX(pay_ID) FROM Payment")->fetchColumn();
        $pdo->prepare("INSERT INTO Payment (pay_ID, order_ID, amount, pay_date) VALUES (?,?,?,?)")
            ->execute([($maxPay ?? 900)+1, $orderID, $total, date('Y-m-d H:i:s')]);

        // Delivery
        $maxDel = $pdo->query("SELECT MAX(delivery_ID) FROM Delivery")->fetchColumn();
        $pdo->prepare("INSERT INTO Delivery VALUES (?,?,NULL,?)")
            ->execute([($maxDel ?? 700)+1, $orderID, 'Pending']);

        logSecurityEvent($pdo, 'staff_order_create', $_SESSION['username'] ?? null, true, 'order_ID=' . $orderID);
        $pdo->commit();
        $msg = ['type'=>'success','text'=>"✓ Захиалга #$orderID амжилттай үүслээ! Нийт дүн: " . number_format($total) . "₮"];
    } else {
        $msg = ['type'=>'error','text'=>'Харилцагч сонгох эсвэл шинэ харилцагч нэмэх, мөн дор хаяж нэг хоол сонгоно уу.'];
        $pdo->rollBack();
    }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Staff order create failed: ' . $e->getMessage());
        $msg = ['type'=>'error','text'=>'Захиалга үүсгэхэд алдаа гарлаа. Хоолны тоо ширхэгийг 1-50 хооронд оруулна уу.'];
    }
    }
}

$customers = $pdo->query("SELECT user_ID, name FROM Users WHERE role='Customer' ORDER BY name")->fetchAll();
$foods = $pdo->query("SELECT * FROM Food_Info WHERE status='Available' ORDER BY food_ID")->fetchAll();

$pageTitle = 'Шинэ захиалга';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Dashboard</a>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="delivery.php" class="nav-link"><span class="icon"></span> Хүргэлт</a>
<a href="new_order.php" class="nav-link active"><span class="icon"></span> Захиалга авах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Шинэ захиалга</h1>
  <p>Харилцагчийн захиалга бүртгэх</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">
  <form method="POST" id="orderForm">
    <div class="card">
      <div class="card-title">Харилцагч</div>
      <div style="margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
          <input type="radio" name="customer_mode" value="existing" checked onchange="toggleCustomerMode()">
          Бүртгэлтэй харилцагч сонгох
        </label>
      </div>
      <div class="form-group" id="existingCustomerGroup">
        <label>Харилцагч</label>
        <select name="customer_id" id="customer_id">
          <option value="">— сонгоно уу —</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= $c['user_ID'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin:16px 0">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
          <input type="radio" name="customer_mode" value="new" onchange="toggleCustomerMode()">
          Шинэ харилцагч нэмэх
        </label>
      </div>
      <div class="form-group" id="newCustomerGroup" style="display:none">
        <label>Нэр</label>
        <input type="text" name="customer_name" placeholder="Харилцагчийн нэр">
        <label style="margin-top:10px">Утас</label>
        <input type="text" name="customer_contact" placeholder="Утасны дугаар">
      </div>
    </div>

    <div class="card">
      <div class="card-title" style="justify-content:space-between">
        <span>Хоол сонгох</span>
        <button type="button" onclick="addRow()" class="btn btn-ghost btn-sm">+ Мөр нэмэх</button>
      </div>
      <div id="itemsContainer">
        <div class="item-row" style="display:grid;grid-template-columns:1fr 100px 36px;gap:10px;margin-bottom:10px;align-items:center">
          <select name="items[]" style="padding:10px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:14px" onchange="calcTotal()">
            <option value="">— хоол —</option>
            <?php foreach ($foods as $f): ?>
            <option value="<?= $f['food_ID'] ?>" data-price="<?= $f['price'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= number_format($f['price']) ?>₮)</option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="quantities[]" value="1" min="1" max="50" style="padding:10px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:14px;text-align:center" onchange="calcTotal()">
          <button type="button" onclick="removeRow(this)" style="padding:8px;background:#fde8e4;border:none;border-radius:8px;cursor:pointer;font-size:16px;color:#9b2b1a">✕</button>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:15px">
      Захиалга бүртгэх
    </button>
  </form>

  <div>
    <div class="card" style="position:sticky;top:24px">
      <div class="card-title">Дүн</div>
      <div id="summary" style="min-height:80px">
        <p style="color:var(--warm-gray);font-size:14px">Хоол сонгоно уу...</p>
      </div>
      <div style="border-top:2px solid var(--border);margin-top:16px;padding-top:16px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:600">Нийт дүн:</span>
        <span id="totalDisplay" style="font-family:'Playfair Display',serif;font-size:24px;color:var(--ink)">0₮</span>
      </div>
    </div>
  </div>
</div>

<script>
function toggleCustomerMode() {
  const mode = document.querySelector('input[name="customer_mode"]:checked').value;
  const existingGroup = document.getElementById('existingCustomerGroup');
  const newGroup = document.getElementById('newCustomerGroup');
  const customerId = document.getElementById('customer_id');
  
  if (mode === 'existing') {
    existingGroup.style.display = 'block';
    newGroup.style.display = 'none';
    customerId.required = true;
  } else {
    existingGroup.style.display = 'none';
    newGroup.style.display = 'block';
    customerId.required = false;
  }
}

const foodPrices = {
  <?php foreach ($foods as $f): ?>
  "<?= (int)$f['food_ID'] ?>": { name: <?= json_encode($f['name']) ?>, price: <?= (int)$f['price'] ?> },
  <?php endforeach; ?>
};

function addRow() {
  const container = document.getElementById('itemsContainer');
  const row = container.querySelector('.item-row').cloneNode(true);
  row.querySelector('select').value = '';
  row.querySelector('input').value = 1;
  row.querySelector('select').addEventListener('change', calcTotal);
  row.querySelector('input').addEventListener('change', calcTotal);
  container.appendChild(row);
}

function removeRow(btn) {
  const rows = document.querySelectorAll('.item-row');
  if (rows.length > 1) { btn.closest('.item-row').remove(); calcTotal(); }
}

function calcTotal() {
  const rows = document.querySelectorAll('.item-row');
  let total = 0;
  let summaryHTML = '';
  rows.forEach(row => {
    const sel = row.querySelector('select');
    const qtyInput = row.querySelector('input');
    let qty = parseInt(qtyInput.value) || 0;
    if (qty > 50) {
      qty = 50;
      qtyInput.value = 50;
    }
    if (sel.value && foodPrices[sel.value]) {
      const food = foodPrices[sel.value];
      const sub = food.price * qty;
      total += sub;
      summaryHTML += `<div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
        <span>${food.name} × ${qty}</span>
        <span style="font-weight:500">${sub.toLocaleString()}₮</span>
      </div>`;
    }
  });
  document.getElementById('summary').innerHTML = summaryHTML || '<p style="color:var(--warm-gray);font-size:14px">Хоол сонгоно уу...</p>';
  document.getElementById('totalDisplay').textContent = total.toLocaleString() + '₮';
}

document.querySelectorAll('select[name="items[]"]').forEach(el => el.addEventListener('change', calcTotal));
document.querySelectorAll('input[name="quantities[]"]').forEach(el => el.addEventListener('change', calcTotal));
</script>

<?php require_once '../includes/footer.php'; ?>
