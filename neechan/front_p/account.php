<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../database/session.php';

start_app_session();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../register_portal/login-page/account.html');
	exit;
}

$statement = database()->prepare('SELECT name, email, date_of_birth, role, created_at FROM users WHERE id = :id');
$statement->execute(['id' => (int) $_SESSION['user_id']]);
$user = $statement->fetch();
if (!$user) {
	session_unset();
	header('Location: ../register_portal/login-page/account.html');
	exit;
}

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="theme-color" content="#f4f7f5">
		<title>Your account | Neechan</title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="account.css">
	</head>
	<body>
		<header class="topbar">
			<a class="brand" href="data.php"><span class="brand-mark" aria-hidden="true">n</span><span>neechan</span></a>
			<a class="text-link" href="data.php">Community wall</a>
		</header>
		<main class="account-page">
			<p class="eyebrow">YOUR ACCOUNT</p>
			<h1>About you<span>.</span></h1>
			<p class="page-intro">Your account details and sign-in controls.</p>
			<section class="profile-sheet" aria-label="Account details">
				<div class="profile-heading">
					<div class="avatar" aria-hidden="true"><?= $escape(mb_substr($user['name'], 0, 1, 'UTF-8')) ?></div>
					<div><h2><?= $escape($user['name']) ?></h2><p><?= $escape($user['email']) ?></p></div>
				</div>
				<dl class="details">
					<div><dt>Date of birth</dt><dd><?= $escape($user['date_of_birth']) ?></dd></div>
					<div><dt>Member since</dt><dd><?= $escape(date('F j, Y', strtotime($user['created_at']))) ?></dd></div>
				</dl>
				<div class="account-actions">
					<?php if ($user['role'] === 'admin'): ?>
						<a class="button button-secondary" href="admin.php">Admin dashboard</a>
					<?php endif; ?>
					<form action="logout.php" method="post">
						<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
						<button class="button button-danger" type="submit">Log out</button>
					</form>
				</div>
			</section>
		</main>
	</body>
</html>