<?php
require_once '../includes/auth.php';
requireRole('Admin');
require_once '../includes/db.php';

$currentUserId = $_SESSION['user_ID'];
$msg = '';

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validatePostedFields($pdo, $_POST, $_SESSION['username'] ?? null)) {
        $msg = ['type'=>'error', 'text'=>'Оруулсан мэдээлэл зөвшөөрөгдөхгүй тэмдэгт агуулсан байна.'];
    } elseif ($_POST['action'] === 'add') {
        $name = cleanText($_POST['name'] ?? '', 100);
        $contact = cleanText($_POST['contact'] ?? '', 50, true);
        $address = cleanText($_POST['address'] ?? '', 255, true);
        $username = cleanText($_POST['username'] ?? '', 50);
        $password = trim($_POST['password'] ?? '');
        $role = cleanEnum($_POST['role'] ?? '', ROLES);

        if (!$name || !$username || !$role || $password === '' || strlen($password) > 255) {
            $msg = ['type'=>'error', 'text'=>'Хэрэглэгчийн мэдээллийг зөв бөглөнө үү.'];
        } else {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
            $exists->execute([$username]);
            if ((int)$exists->fetchColumn() > 0) {
                $msg = ['type'=>'error', 'text'=>'Энэ нэвтрэх нэр бүртгэлтэй байна.'];
            } else {
                $maxID = $pdo->query("SELECT MAX(user_ID) FROM Users")->fetchColumn();
                $newID = ($maxID ?? 0) + 1;
                $stmt = $pdo->prepare(
                    "INSERT INTO Users (user_ID, name, contact, address, username, password, role)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->execute([$newID, $name, $contact, $address, $username, hashUserPassword($password), $role]);
                logSecurityEvent($pdo, 'user_add', $username, true, 'role=' . $role);
                $msg = ['type'=>'success', 'text'=>'✓ Хэрэглэгч нэмэгдлээ.'];
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['del_id'])) {
        $delID = cleanInt($_POST['del_id'] ?? null);
        if (!$delID || $delID === (int)$currentUserId) {
            $msg = ['type'=>'error', 'text'=>'Энэ хэрэглэгчийг устгах боломжгүй.'];
        } else {
            $pdo->prepare("DELETE FROM Users WHERE user_ID = ?")->execute([$delID]);
            logSecurityEvent($pdo, 'user_delete', $_SESSION['username'] ?? null, true, 'deleted user_ID=' . $delID);
            $msg = ['type'=>'success', 'text'=>'✓ Хэрэглэгч устгагдлаа.'];
        }
    } elseif ($_POST['action'] === 'edit') {
        $userID = cleanInt($_POST['user_id'] ?? null);
        $name = cleanText($_POST['name'] ?? '', 100);
        $contact = cleanText($_POST['contact'] ?? '', 50, true);
        $address = cleanText($_POST['address'] ?? '', 255, true);
        $newRole = cleanEnum($_POST['role'] ?? '', ROLES);

        if (!$userID || !$name || !$newRole) {
            $msg = ['type'=>'error', 'text'=>'Хэрэглэгчийн мэдээллийг зөв бөглөнө үү.'];
        } else {
            $roleStmt = $pdo->prepare("SELECT role FROM Users WHERE user_ID = ?");
            $roleStmt->execute([$userID]);
            $currentRole = $roleStmt->fetchColumn();

            if (!$currentRole || ((int)$userID === (int)$currentUserId && $newRole !== 'Admin')) {
                $msg = ['type'=>'error', 'text'=>'Өөрийн admin эрхийг өөрчлөх боломжгүй.'];
            } else {
                $stmt = $pdo->prepare("UPDATE Users SET name=?, contact=?, address=?, role=? WHERE user_ID=?");
                $stmt->execute([$name, $contact, $address, $newRole, $userID]);
                logSecurityEvent($pdo, 'user_role_update', $_SESSION['username'] ?? null, true, "user_ID=$userID role=$newRole");
                $msg = ['type'=>'success', 'text'=>'✓ Мэдээлэл шинэчлэгдлээ.'];
            }
        }
    }
}

$users = $pdo->query("SELECT * FROM Users ORDER BY role, user_ID")->fetchAll();

$pageTitle = 'Хэрэглэгч удирдлага';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Dashboard</a>
<a href="users.php" class="nav-link active"><span class="icon"></span> Хэрэглэгчид</a>
<span class="nav-label">Удирдлага</span>
<a href="orders.php" class="nav-link"><span class="icon"></span> Захиалгууд</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Хоол</a>
<a href="payments.php" class="nav-link"><span class="icon"></span> Төлбөр</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Хэрэглэгчид</h1>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-title" style="justify-content:space-between">
    <span>Хэрэглэгчдийн жагсаалт</span>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">+ Нэмэх</button>
  </div>
  <table>
    <thead><tr><th>ID</th><th>Нэр</th><th>Нэвтрэх нэр</th><th>Холбоо барих</th><th>Роль</th><th>Үйлдэл</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= $u['user_ID'] ?></td>
        <td><?= htmlspecialchars($u['name']) ?></td>
        <td><code style="font-size:12px;background:#f0ebe0;padding:2px 6px;border-radius:4px"><?= e($u['username']) ?></code></td>
        <td><?= e($u['contact']) ?></td>
        <td><span class="badge badge-<?= strtolower(e($u['role'])) ?>"><?= e($u['role']) ?></span></td>
        <td style="display:flex;gap:6px">
          <button onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" class="btn btn-sm btn-ghost">Засах</button>
          <form method="POST" onsubmit="return confirm('Устгах уу?')" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="del_id" value="<?= $u['user_ID'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">✕</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border-radius:16px;padding:32px;width:480px;max-width:94vw;max-height:90vh;overflow-y:auto">
    <h3 style="font-family:'Playfair Display',serif;font-size:20px;margin-bottom:20px">Хэрэглэгч нэмэх</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label>Нэр</label><input name="name" required></div>
        <div class="form-group"><label>Утас</label><input name="contact" required></div>
      </div>
      <div class="form-group"><label>Хаяг</label><input name="address"></div>
      <div class="form-row">
        <div class="form-group"><label>Нэвтрэх нэр</label><input name="username" required></div>
        <div class="form-group"><label>Нууц үг</label><input name="password" required></div>
      </div>
      <div class="form-group">
        <label>Роль</label>
        <select name="role">
          <option>Admin</option>
          <option>Staff</option>
          <option selected>Customer</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Хадгалах</button>
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-ghost">Болих</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border-radius:16px;padding:32px;width:480px;max-width:94vw">
    <h3 style="font-family:'Playfair Display',serif;font-size:20px;margin-bottom:20px">Засах</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" id="edit_id">
      <div class="form-row">
        <div class="form-group"><label>Нэр</label><input name="name" id="edit_name" required></div>
        <div class="form-group"><label>Утас</label><input name="contact" id="edit_contact"></div>
      </div>
      <div class="form-group"><label>Хаяг</label><input name="address" id="edit_address"></div>
      <div class="form-group">
        <label>Роль</label>
        <select name="role" id="edit_role">
          <option>Admin</option>
          <option>Staff</option>
          <option>Customer</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Хадгалах</button>
        <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-ghost">Болих</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(u) {
  document.getElementById('edit_id').value = u.user_ID;
  document.getElementById('edit_name').value = u.name;
  document.getElementById('edit_contact').value = u.contact;
  document.getElementById('edit_address').value = u.address;
  document.getElementById('edit_role').value = u.role;
  document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
