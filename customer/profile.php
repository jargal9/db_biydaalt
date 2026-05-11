<?php
require_once '../includes/auth.php';
requireRole('Customer');
require_once '../includes/db.php';

$userID = $_SESSION['user_ID'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanText($_POST['name'] ?? '', 100);
    $contact = cleanPhone($_POST['contact'] ?? '', true);
    $address = cleanText($_POST['address'] ?? '', 255, true);
    $password = trim($_POST['password'] ?? '');

    if (!validatePostedFields($pdo, $_POST, $_SESSION['username'] ?? null)) {
        $msg = ['type' => 'error', 'text' => 'Оруулсан мэдээлэл зөвшөөрөгдөхгүй тэмдэгт агуулсан байна.'];
    } elseif (!$name) {
        $msg = ['type' => 'error', 'text' => 'Нэрийг 1-100 тэмдэгтээр зөв бөглөнө үү.'];
    } elseif ($contact === null) {
        $msg = ['type' => 'error', 'text' => 'Утасны дугаарыг 8 оронтой, эсвэл +976XXXXXXXX хэлбэрээр оруулна уу.'];
    } elseif (!validPasswordLength($password, true)) {
        $msg = ['type' => 'error', 'text' => 'Шинэ нууц үг оруулах бол 6-аас дээш тэмдэгттэй байна.'];
    } else {
        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE Users SET name=?, contact=?, address=?, password=? WHERE user_ID=?");
            $stmt->execute([$name, $contact, $address, hashUserPassword($password), $userID]);
        } else {
            $stmt = $pdo->prepare("UPDATE Users SET name=?, contact=?, address=? WHERE user_ID=?");
            $stmt->execute([$name, $contact, $address, $userID]);
        }

        $_SESSION['name'] = $name;
        logSecurityEvent($pdo, 'profile_update', $_SESSION['username'] ?? null, true, 'customer profile updated');
        $msg = ['type' => 'success', 'text' => 'Таны мэдээлэл амжилттай шинэчлэгдлээ.'];
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
      <input type="tel" name="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>" maxlength="12" pattern="(\+976)?[0-9]{8}|976[0-9]{8}" title="8 оронтой дугаар эсвэл +976XXXXXXXX хэлбэрээр оруулна уу">
    </div>
    <div class="form-group">
      <label>Хаяг</label>
      <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Нууц үг (шинэ нууц үг оруулах бол)</label>
      <input type="password" name="password" placeholder="Шинэ нууц үг" minlength="6" maxlength="255">
    </div>
    <button type="submit" class="btn btn-primary">Хадгалах</button>
  </form>
</div>

<?php require_once '../includes/footer.php'; ?>
