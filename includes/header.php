<?php
// Usage: include with $pageTitle and $role set
$roleColors = [
    'Admin'    => '#c8922a',
    'Staff'    => '#4a6741',
    'Customer' => '#b94a2c',
];
$roleIcons = ['Admin' => '', 'Staff' => '', 'Customer' => ''];
$accentColor = $roleColors[$_SESSION['role']] ?? '#c8922a';
?>
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Хоол захиалгын систем') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --ink: #1a1108;
    --cream: #f7f2ea;
    --gold: #c8922a;
    --card: #fffdf8;
    --border: #e8e0d0;
    --warm-gray: #8c7e6e;
    --accent: <?= $accentColor ?>;
    --sidebar-w: 240px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: #f0ebe0;
    color: var(--ink);
    min-height: 100vh;
    display: flex;
  }

  /* ===== SIDEBAR ===== */
  .sidebar {
    width: var(--sidebar-w);
    background: linear-gradient(180deg, #1b140d 0%, #100a05 100%);
    min-height: 100vh;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    padding: 32px 0;
    z-index: 100;
    box-shadow: 4px 0 40px rgba(0,0,0,0.25);
    border-right: 1px solid rgba(255,255,255,0.08);
  }

  .sidebar-logo {
    padding: 0 24px 30px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 26px;
  }

  .sidebar-logo .mark {
    width: 46px;
    height: 46px;
    position: relative;
    background: linear-gradient(135deg, #e1b25d 0%, #c8932a 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.15), 0 14px 24px rgba(0,0,0,0.22);
  }

  .sidebar-logo .mark::before {
    content: '';
    position: absolute;
    left: 10px;
    bottom: 9px;
    width: 26px;
    height: 14px;
    background: rgba(255,255,255,0.95);
    border-radius: 0 0 16px 16px;
  }

  .sidebar-logo .mark::after {
    content: '';
    position: absolute;
    left: 16px;
    top: 8px;
    width: 4px;
    height: 16px;
    background: rgba(255,255,255,0.9);
    border-radius: 2px;
    box-shadow: 8px 0 0 rgba(255,255,255,0.7), 16px 0 0 rgba(255,255,255,0.45);
  }

  .sidebar-logo h2 {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    color: var(--cream);
    line-height: 1.2;
    letter-spacing: 0.01em;
    margin-bottom: 8px;
  }

  .sidebar-logo .role-tag {
    display: inline-block;
    margin-top: 6px;
    padding: 4px 10px;
    background: rgba(255,255,255,0.08);
    border-radius: 999px;
    font-size: 11px;
    color: var(--accent);
    letter-spacing: 0.08em;
  }

  .nav-section {
    padding: 0 18px;
    flex: 1;
  }

  .nav-label {
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.25);
    padding: 0 12px;
    margin: 24px 0 10px;
  }

  .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 12px;
    text-decoration: none;
    color: rgba(255,255,255,0.75);
    font-size: 14px;
    transition: all 0.2s ease;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.03);
  }

  .nav-link:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
  }

  .nav-link.active {
    background: rgba(255,255,255,0.14);
    color: #fff;
    box-shadow: inset 4px 0 0 var(--gold);
  }

  .nav-link .icon { font-size: 16px; width: 20px; text-align: center; }
  .nav-link .icon:empty { display: none; }

  .sidebar-footer {
    padding: 20px 24px 0;
    border-top: 1px solid rgba(255,255,255,0.08);
  }

  .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
  }

  .avatar {
    width: 34px; height: 34px;
    background: var(--accent);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    color: #fff;
    flex-shrink: 0;
  }

  .user-name { font-size: 13px; color: var(--cream); font-weight: 500; }
  .user-role { font-size: 11px; color: rgba(255,255,255,0.35); }

  .btn-logout {
    display: block;
    text-align: center;
    padding: 9px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    font-size: 13px;
    transition: all 0.15s;
  }

  .btn-logout:hover {
    background: rgba(185,74,44,0.3);
    color: #fff;
    border-color: rgba(185,74,44,0.5);
  }

  /* ===== MAIN ===== */
  .main {
    margin-left: var(--sidebar-w);
    flex: 1;
    padding: 40px;
    min-height: 100vh;
  }

  .page-header {
    margin-bottom: 32px;
  }

  .page-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: var(--ink);
    margin-bottom: 4px;
  }

  .page-header p { font-size: 14px; color: var(--warm-gray); }

  /* ===== CARDS ===== */
  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  }

  .card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* ===== STATS ===== */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }

  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  }

  .stat-label { font-size: 12px; color: var(--warm-gray); letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 8px; }
  .stat-value { font-family: 'Playfair Display', serif; font-size: 32px; color: var(--ink); line-height: 1; margin-bottom: 4px; }
  .stat-icon { font-size: 20px; margin-bottom: 10px; }

  /* ===== TABLE ===== */
  table { width: 100%; border-collapse: collapse; }
  th {
    text-align: left;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--warm-gray);
    padding: 10px 14px;
    border-bottom: 2px solid var(--border);
  }
  td { padding: 12px 14px; font-size: 14px; border-bottom: 1px solid #f0e8d8; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: rgba(200,146,42,0.04); }

  /* ===== BADGES ===== */
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.04em;
  }
  .badge-completed { background: #e8f5e3; color: #2d6a1f; }
  .badge-pending   { background: #fff3cd; color: #856404; }
  .badge-cancelled { background: #fde8e4; color: #9b2b1a; }
  .badge-processing{ background: #e0f0ff; color: #1a5fa3; }
  .badge-delivered { background: #e8f5e3; color: #2d6a1f; }
  .badge-admin     { background: rgba(200,146,42,0.15); color: #7a5010; }
  .badge-staff     { background: rgba(74,103,65,0.15); color: #2d5022; }
  .badge-customer  { background: rgba(185,74,44,0.12); color: #7a2010; }

  /* ===== BUTTONS ===== */
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    text-decoration: none;
  }
  .btn-primary { background: var(--ink); color: var(--cream); }
  .btn-primary:hover { background: var(--accent); }
  .btn-accent { background: var(--accent); color: #fff; }
  .btn-accent:hover { opacity: 0.9; }
  .btn-danger  { background: #fde8e4; color: #9b2b1a; border: 1px solid #f5c5bc; }
  .btn-danger:hover { background: #f5c5bc; }
  .btn-sm { padding: 6px 12px; font-size: 12px; }
  .btn-ghost { background: transparent; border: 1.5px solid var(--border); color: var(--ink); }
  .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

  /* ===== FORM ===== */
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
  .form-row.full { grid-template-columns: 1fr; }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.07em; color: var(--warm-gray); margin-bottom: 7px; }
  .form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--ink);
    background: #fff;
    outline: none;
    transition: border-color 0.15s;
  }
  .form-group input:focus, .form-group select:focus { border-color: var(--accent); }

  /* ===== ALERTS ===== */
  .alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .alert-success { background: #e8f5e3; border: 1px solid #b6e0a0; color: #2d6a1f; }
  .alert-error   { background: #fde8e4; border: 1px solid #f5c5bc; color: #9b2b1a; }

  @media (max-width: 768px) {
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 20px; }
    .form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="mark"></div>
    <h2>Хоол захиалгын<br>систем</h2>
    <span class="role-tag"><?= $_SESSION['role'] ?></span>
  </div>
  <div class="nav-section">
    <?= $navLinks ?? '' ?>
  </div>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['name'], 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
        <div class="user-role"><?= htmlspecialchars($_SESSION['username']) ?></div>
      </div>
    </div>
    <a href="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>logout.php" class="btn-logout">← Гарах</a>
  </div>
</nav>
<main class="main">