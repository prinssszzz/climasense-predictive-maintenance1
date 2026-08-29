<?php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($activeNav)) $activeNav = 'dashboard';
require_once __DIR__ . '/auth.php';
cs_require_login();
if (cs_has_role('consumer') && basename($_SERVER['PHP_SELF']) !== 'consumer-dashboard.php') { header('Location: consumer-dashboard.php'); exit; }
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/helpers.php';
$fleet = cs_fleet_summary();
$authedUser = cs_current_user();
?><!DOCTYPE html>
<html lang="en">
<head>
<script>
document.documentElement.classList.add('js-anim');
try { if (localStorage.getItem('cs-theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark'); } catch (e) {}
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> · ClimaSense</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="page-loader" id="pageLoader"></div>
<div class="shell">
  <?php require __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <svg viewBox="0 0 24 24" width="20" height="20"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>

      <div class="search" id="globalSearchWrap">
        <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" fill="none"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="text" id="globalSearch" placeholder="Search units, alerts, technicians…">
        <button type="button" class="search-clear" id="searchClear" aria-label="Clear search">
          <svg viewBox="0 0 24 24" width="14" height="14"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
      </div>

      <div class="topbar-status">
        <span class="dot ok"></span><?= $fleet['healthy'] ?> healthy
        <span class="dot warn"></span><?= $fleet['warning'] ?> at risk
        <span class="dot crit"></span><?= $fleet['critical'] ?> critical
      </div>

      <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Toggle dark mode" data-tooltip="Toggle dark mode">
        <span class="theme-icon-stack">
        <svg id="themeIconSun" viewBox="0 0 24 24" width="19" height="19"><circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 2v2.5M12 19.5V22M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2 12h2.5M19.5 12H22M4.2 19.8L6 18M18 6l1.8-1.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <svg id="themeIconMoon" viewBox="0 0 24 24" width="19" height="19"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/></svg>
        </span>
      </button>

      <div class="topbar-alert-btn">
        <button class="icon-btn" id="bellBtn" aria-label="Notifications" data-tooltip="Notifications">
          <svg viewBox="0 0 24 24" width="19" height="19"><path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
          <span class="badge-count"><?= $fleet['critical'] + $fleet['warning'] ?></span>
        </button>
      </div>

      <div class="user-chip" id="userChip">
        <div class="avatar"><?= htmlspecialchars(cs_initials($authedUser['name'])) ?></div>
        <div class="user-meta">
          <strong><?= htmlspecialchars($authedUser['name']) ?></strong>
          <span><?= htmlspecialchars($authedUser['role']) ?></span>
        </div>
        <div class="user-menu" id="userMenu">
          <a href="settings.php">Account Settings</a>
          <a href="logout.php" class="danger">Log Out</a>
        </div>
      </div>
    </header>

    <main class="content">
