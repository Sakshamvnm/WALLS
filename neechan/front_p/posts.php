<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../database/session.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['photo'])) {
	$photoId = filter_input(INPUT_GET, 'photo', FILTER_VALIDATE_INT);
	if (!is_int($photoId) || $photoId < 1) {
		send_json(['error' => 'Invalid photo.'], 400);
	}

	$statement = database()->prepare('SELECT photo, photo_mime FROM posts WHERE id = :id AND photo IS NOT NULL');
	$statement->execute(['id' => $photoId]);
	$photo = $statement->fetch();
	if (!$photo) {
		http_response_code(404);
		exit;
	}

	header('Content-Type: ' . $photo['photo_mime']);
	header('X-Content-Type-Options: nosniff');
	echo $photo['photo'];
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
	if ($userId !== null && !isset($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	$posts = database()->query(
		'SELECT posts.id, posts.user_id, users.name AS author, posts.body, posts.photo_mime, UNIX_TIMESTAMP(posts.created_at) AS created_at
		 FROM posts
		 INNER JOIN users ON users.id = posts.user_id
		 ORDER BY posts.created_at DESC, posts.id DESC'
	)->fetchAll();

	foreach ($posts as &$post) {
		$post['id'] = (int) $post['id'];
		$post['can_delete'] = $userId !== null && (int) $post['user_id'] === $userId;
		$post['created_at'] = (int) $post['created_at'];
		$post['photo_url'] = $post['photo_mime'] !== null ? 'posts.php?photo=' . $post['id'] : null;
		unset($post['user_id'], $post['photo_mime']);
	}
	unset($post);

	send_json([
		'posts' => $posts,
		'total' => count($posts),
		'authenticated' => $userId !== null,
		'user_name' => $_SESSION['user_name'] ?? null,
		'csrf_token' => $_SESSION['csrf_token'] ?? null,
	]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	send_json(['error' => 'Unsupported request.'], 405);
}

$userId = require_user_id();
verify_csrf_token();
$action = $_POST['action'] ?? 'create';

if ($action === 'delete') {
	$postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
	if (!is_int($postId) || $postId < 1) {
		send_json(['error' => 'Invalid post.'], 400);
	}

	$statement = database()->prepare('DELETE FROM posts WHERE id = :id AND user_id = :user_id');
	$statement->execute(['id' => $postId, 'user_id' => $userId]);
	if ($statement->rowCount() === 0) {
		send_json(['error' => 'This post could not be found or does not belong to you.'], 404);
	}

	send_json(['message' => 'Post deleted.']);
}

if ($action !== 'create') {
	send_json(['error' => 'Unsupported action.'], 400);
}

$body = trim((string) ($_POST['text'] ?? ''));
$photoData = null;
$photoMime = null;
$photo = $_FILES['photo'] ?? null;

if (strlen($body) > 8000) {
	send_json(['error' => 'Posts must be no longer than 2,000 characters.'], 422);
}

if (is_array($photo) && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
	if ($photo['error'] !== UPLOAD_ERR_OK || $photo['size'] > 1.5 * 1024 * 1024) {
		send_json(['error' => 'Choose a photo smaller than 1.5 MB.'], 422);
	}

	$fileInfo = new finfo(FILEINFO_MIME_TYPE);
	$photoMime = $fileInfo->file($photo['tmp_name']);
	$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	if (!in_array($photoMime, $allowedImageTypes, true)) {
		send_json(['error' => 'Choose a JPEG, PNG, GIF, or WebP image.'], 422);
	}

	$photoData = file_get_contents($photo['tmp_name']);
	if ($photoData === false) {
		send_json(['error' => 'The selected photo could not be read.'], 422);
	}
}

if ($body === '' && $photoData === null) {
	send_json(['error' => 'Write something or attach a photo before posting.'], 422);
}

$statement = database()->prepare(
	'INSERT INTO posts (user_id, body, photo, photo_mime) VALUES (:user_id, :body, :photo, :photo_mime)'
);
$statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
$statement->bindValue(':body', $body, PDO::PARAM_STR);
$statement->bindValue(':photo', $photoData, $photoData === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
$statement->bindValue(':photo_mime', $photoMime, $photoMime === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$statement->execute();

send_json(['message' => 'Post shared with everyone.'], 201);