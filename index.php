<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_ID'] = $user['user_ID'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['username']= $user['username'];

            if ($user['role'] === 'Admin')    header('Location: admin/dashboard.php');
            elseif ($user['role'] === 'Staff') header('Location: staff/dashboard.php');
            else                               header('Location: customer/dashboard.php');
            exit;
        } else {
            $error = 'Нэвтрэх нэр эсвэл нууц үг буруу байна.';
        }
    } else {
        $error = 'Бүх талбарыг бөглөнө үү.';
    }
}

if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error = 'Та энэ хуудсанд нэвтрэх эрхгүй байна.';
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Хоол захиалгын систем — Нэвтрэх</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --ink: #1a1108;
    --cream: #f7f2ea;
    --gold: #c8922a;
    --gold-light: #e8b84b;
    --rust: #b94a2c;
    --sage: #4a6741;
    --warm-gray: #8c7e6e;
    --card: #fffdf8;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--ink);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: 
      radial-gradient(ellipse 80% 60% at 20% 80%, rgba(200,146,42,0.12) 0%, transparent 60%),
      radial-gradient(ellipse 60% 80% at 80% 20%, rgba(185,74,44,0.08) 0%, transparent 60%);
    pointer-events: none;
  }

  .noise {
    position: fixed;
    inset: 0;
    opacity: 0.04;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    pointer-events: none;
  }

  .decor-lines {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none;
    overflow: hidden;
  }

  .decor-lines::before {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    border: 1px solid rgba(200,146,42,0.08);
    border-radius: 50%;
    top: -200px; right: -200px;
  }

  .decor-lines::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border: 1px solid rgba(200,146,42,0.06);
    border-radius: 50%;
    bottom: -150px; left: -100px;
  }

  .wrapper {
    display: flex;
    width: 900px;
    max-width: 96vw;
    min-height: 560px;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 40px 120px rgba(0,0,0,0.6), 0 0 0 1px rgba(200,146,42,0.15);
    animation: rise 0.6s cubic-bezier(.22,1,.36,1) both;
  }

  @keyframes rise {
    from { opacity: 0; transform: translateY(30px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  .brand-panel {
    flex: 1;
    background: linear-gradient(145deg, #2a1f0e 0%, #1a1108 50%, #0f0a04 100%);
    padding: 60px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    text-align: center;    
    align-items: center; 
    overflow: hidden;
  }

  .brand-panel::before {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 70%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    border-radius: 50%;
  }

  .logo-mark {
    width: 52px; height: 52px;
    background: var(--gold);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 32px;
    box-shadow: 0 8px 24px rgba(200,146,42,0.3);
  }

  .brand-title {
    font-family: 'Playfair Display', serif;
    font-size: 42px;
    font-weight: 900;
    color: var(--cream);
    line-height: 1.1;
    margin-bottom: 16px;
  }

  .brand-title span { color: var(--gold); }

  .brand-desc {
    font-size: 14px;
    color: var(--warm-gray);
    line-height: 1.7;
    max-width: 260px;
  }

  .roles-preview {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .role-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(200,146,42,0.1);
    border-radius: 10px;
    font-size: 13px;
    color: var(--warm-gray);
  }

  .role-badge .dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .dot-admin { background: var(--gold); }
  .dot-staff { background: var(--sage); }
  .dot-customer { background: var(--rust); }

  .form-panel {
    width: 400px;
    background: var(--card);
    padding: 60px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .form-heading {
    font-family: 'Playfair Display', serif;
    font-size: 30px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 8px;
  }

  .form-sub {
    font-size: 14px;
    color: var(--warm-gray);
    margin-bottom: 40px;
  }

  .field-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--warm-gray);
    margin-bottom: 8px;
  }

  input[type="text"], input[type="password"] {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #e0d8cc;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    color: var(--ink);
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }

  input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 4px rgba(200,146,42,0.12);
  }

  .error-box {
    background: #fff5f3;
    border: 1px solid rgba(185,74,44,0.3);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: var(--rust);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .btn-login {
    width: 100%;
    padding: 15px;
    background: var(--ink);
    color: var(--cream);
    border: none;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.2s, transform 0.15s;
    letter-spacing: 0.02em;
  }

  .btn-login:hover {
    background: var(--gold);
    color: var(--ink);
    transform: translateY(-1px);
  }

  .hint {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid #ece6da;
    font-size: 12px;
    color: var(--warm-gray);
    line-height: 1.7;
  }

  .hint strong { color: var(--ink); }

  @media (max-width: 700px) {
    .brand-panel { display: none; }
    .form-panel { width: 100%; padding: 40px 28px; }
  }
</style>
</head>
<body>
<div class="noise"></div>
<div class="decor-lines"></div>

<div class="wrapper">
  <div class="brand-panel">
    <div>
      <!-- <div class="logo-mark"></div>-->
      <h1 class="brand-title">Хоол захиалгын<br><span>систем</span></h1>
    </div>

  </div>

  <div class="form-panel">
    <h2 class="form-heading">Нэвтрэх</h2>
  
    <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field-group">
        <label>Нэвтрэх нэр</label>
        <input type="text" name="username" placeholder="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="field-group">
        <label>Нууц үг</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">Нэвтрэх →</button>
    </form>
  </div>
</div>
</body>
</html>