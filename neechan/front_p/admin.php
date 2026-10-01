<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../database/session.php';

start_app_session();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../register_portal/login-page/account.html');
	exit;
}

$statement = database()->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
$statement->execute(['id' => (int) $_SESSION['user_id']]);
$admin = $statement->fetch();
if (!$admin) {
	session_unset();
	header('Location: ../register_portal/login-page/account.html');
	exit;
}

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($admin['role'] !== 'admin') {
	http_response_code(403);
	$forbidden = true;
} else {
	$forbidden = false;

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		verify_csrf_token();
		$action = $_POST['action'] ?? '';
		if ($action === 'delete_post') {
			$postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
			if (!is_int($postId) || $postId < 1) {
				header('Location: admin.php?status=invalid');
				exit;
			}
			$statement = database()->prepare('DELETE FROM posts WHERE id = :id');
			$statement->execute(['id' => $postId]);
			header('Location: admin.php?status=post-deleted');
			exit;
		}

		if ($action === 'set_role') {
			$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
			$role = $_POST['role'] ?? '';
			if (!is_int($userId) || $userId < 1 || !in_array($role, ['user', 'admin'], true) || ($userId === (int) $admin['id'] && $role !== 'admin')) {
				header('Location: admin.php?status=invalid');
				exit;
			}
			$statement = database()->prepare('UPDATE users SET role = :role WHERE id = :id');
			$statement->execute(['role' => $role, 'id' => $userId]);
			header('Location: admin.php?status=role-updated');
			exit;
		}

		header('Location: admin.php?status=invalid');
		exit;
	}

	$userCount = (int) database()->query('SELECT COUNT(*) FROM users')->fetchColumn();
	$postCount = (int) database()->query('SELECT COUNT(*) FROM posts')->fetchColumn();
	$users = database()->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 50')->fetchAll();
	$posts = database()->query(
		'SELECT posts.id, posts.body, posts.photo_mime, posts.created_at, users.name AS author
		 FROM posts INNER JOIN users ON users.id = posts.user_id
		 ORDER BY posts.created_at DESC, posts.id DESC LIMIT 50'
	)->fetchAll();
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$statusMessages = [
	'post-deleted' => 'Post removed.',
	'role-updated' => 'Account role updated.',
	'invalid' => 'That action could not be completed.',
];
$statusMessage = $statusMessages[$_GET['status'] ?? ''] ?? '';
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="theme-color" content="#f4f7f5">
		<title>Admin dashboard | Neechan</title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="account.css">
		<link rel="stylesheet" href="admin.css">
	</head>
	<body>
		<header class="topbar">
			<a class="brand" href="data.php"><span class="brand-mark" aria-hidden="true">n</span><span>neechan</span></a>
			<nav class="admin-nav" aria-label="Admin navigation">
				<a class="text-link" href="account.php">My account</a>
				<form action="logout.php" method="post">
					<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token'] ?? '') ?>">
					<button class="text-link" type="submit">Log out</button>
				</form>
			</nav>
		</header>
		<main class="admin-page">
			<p class="eyebrow">NEECHAN ADMINISTRATION</p>
			<h1>Dashboard<span>.</span></h1>
			<p class="page-intro">Manage community accounts and shared posts.</p>

			<?php if ($forbidden): ?>
				<section class="admin-notice" role="alert">
					<h2>Admin access required</h2>
					<p>This account does not have permission to open the dashboard.</p>
					<a class="button button-secondary" href="account.php">Return to your account</a>
				</section>
			<?php else: ?>
				<?php if ($statusMessage !== ''): ?><p class="notice" role="status"><?= $escape($statusMessage) ?></p><?php endif; ?>
				<section class="metrics" aria-label="Site totals">
					<div><span>Accounts</span><strong><?= $userCount ?></strong></div>
					<div><span>Shared posts</span><strong><?= $postCount ?></strong></div>
				</section>

				<section class="admin-section" aria-labelledby="users-heading">
					<div class="section-heading"><h2 id="users-heading">Accounts</h2><span>Latest <?= count($users) ?></span></div>
					<div class="table-scroll">
						<table>
							<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Action</th></tr></thead>
							<tbody>
								<?php foreach ($users as $user): ?>
									<tr>
										<td><?= $escape($user['name']) ?></td>
										<td><?= $escape($user['email']) ?></td>
										<td><span class="role-label <?= $user['role'] === 'admin' ? 'role-admin' : '' ?>"><?= $escape($user['role']) ?></span></td>
										<td><?= $escape(date('M j, Y', strtotime($user['created_at']))) ?></td>
										<td>
											<?php if ((int) $user['id'] !== (int) $admin['id']): ?>
												<form class="inline-form" method="post">
													<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
													<input type="hidden" name="action" value="set_role">
													<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
													<input type="hidden" name="role" value="<?= $user['role'] === 'admin' ? 'user' : 'admin' ?>">
													<button class="table-action" type="submit"><?= $user['role'] === 'admin' ? 'Remove admin' : 'Make admin' ?></button>
												</form>
											<?php else: ?>
												<span class="muted">You</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

				<section class="admin-section" aria-labelledby="posts-heading">
					<div class="section-heading"><h2 id="posts-heading">Recent posts</h2><span>Latest <?= count($posts) ?></span></div>
					<div class="moderation-list">
						<?php foreach ($posts as $post): ?>
							<article class="moderation-item">
								<div class="moderation-meta"><strong><?= $escape($post['author']) ?></strong><time><?= $escape(date('M j, Y g:i a', strtotime($post['created_at']))) ?></time></div>
								<p><?= $escape($post['body'] !== '' ? $post['body'] : 'Photo post') ?></p>
								<div class="moderation-actions">
									<?php if ($post['photo_mime'] !== null): ?><a class="table-action" href="posts.php?photo=<?= (int) $post['id'] ?>" target="_blank" rel="noopener">View photo</a><?php endif; ?>
									<form class="inline-form" method="post" onsubmit="return confirm('Remove this post for everyone?')">
										<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
										<input type="hidden" name="action" value="delete_post">
										<input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
										<button class="table-action danger-action" type="submit">Remove post</button>
									</form>
								</div>
							</article>
						<?php endforeach; ?>
						<?php if ($posts === []): ?><p class="empty-note">No posts yet.</p><?php endif; ?>
					</div>
				</section>
			<?php endif; ?>
		</main>
	</body>
</html>