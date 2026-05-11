<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name) {
        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE Users SET name=?, contact=?, address=?, password=? WHERE user_ID=?");
            $stmt->execute([$name, $contact, $address, $password, $userID]);
        } else {
            $stmt = $pdo->prepare("UPDATE Users SET name=?, contact=?, address=? WHERE user_ID=?");
            $stmt->execute([$name, $contact, $address, $userID]);
        }

        $_SESSION['name'] = $name;
        $msg = ['type' => 'success', 'text' => 'Таны мэдээлэл амжилттай шинэчлэгдлээ.'];
    } else {
        $msg = ['type' => 'error', 'text' => 'Нэрийг бөглөнө үү.'];
    }
}

$user = $pdo->prepare("SELECT name, username, contact, address FROM Users WHERE user_ID = ?");
$user->execute([$userID]);
$user = $user->fetch();

$pageTitle = 'Миний мэдээлэл';
$navLinks = '
<span class="nav-label">Үндсэн</span>
<a href="dashboard.php" class="nav-link"><span class="icon"></span> Нүүр хуудас</a>
<a href="menu.php" class="nav-link"><span class="icon"></span> Цэс & Захиалах</a>
<a href="my_orders.php" class="nav-link"><span class="icon"></span> Миний захиалга</a>
<a href="profile.php" class="nav-link active"><span class="icon"></span> Мэдээлэл засах</a>
';
require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Миний мэдээлэл</h1>
  <p>Өөрийн мэдээллийг шинэчилнэ үү</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<div class="card" style="max-width:560px">
  <div class="card-title">Хувийн мэдээлэл</div>
  <form method="POST">
    <div class="form-group">
      <label>Нэр</label>
      <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Нэвтрэх нэр</label>
      <input type="text" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
    </div>
    <div class="form-group">
      <label>Утас</label>
      <input type="text" name="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Хаяг</label>
      <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Нууц үг (шинэ нууц үг оруулах бол)</label>
      <input type="password" name="password" placeholder="Шинэ нууц үг">
    </div>
    <button type="submit" class="btn btn-primary">Хадгалах</button>
  </form>
</div>

<?php require_once '../includes/footer.php'; ?>