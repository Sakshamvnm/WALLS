<?php
declare(strict_types=1);

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../database/session.php';

start_app_session();
$message = 'Email or password is incorrect.';
$status = 422;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$message = 'Submit the sign-in form to continue.';
	$status = 405;
} else {
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$password = $_POST['password'] ?? '';

	if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && is_string($password) && strlen($password) >= 8) {
		try {
			$statement = database()->prepare('SELECT id, name, password_hash, role FROM users WHERE email = :email LIMIT 1');
			$statement->execute(['email' => $email]);
			$user = $statement->fetch();

			if ($user && password_verify($password, $user['password_hash'])) {
				session_regenerate_id(true);
				$_SESSION['user_id'] = (int) $user['id'];
				$_SESSION['user_name'] = $user['name'];
				$_SESSION['user_role'] = $user['role'];
				$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
				header('Location: ../../front_p/data.php');
				exit;
			}
		} catch (PDOException $exception) {
			error_log($exception->getMessage());
			$message = 'The sign-in service is unavailable. Check the database connection.';
			$status = 503;
		}
	}
}

http_response_code($status);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Sign-in failed</title>
	<style>
		body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; box-sizing: border-box; color: #253b36; background: #f1f6ef; font-family: system-ui, sans-serif; }
		main { width: min(100%, 460px); padding: 32px; border: 1px solid #dce5dc; border-radius: 8px; background: #fff; }
		h1 { margin-top: 0; font-size: 25px; }
		p { line-height: 1.6; }
		a { color: #1c5542; font-weight: 600; }
	</style>
</head>
<body>
	<main>
		<h1>Could not sign in</h1>
		<p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
		<a href="account.html">Return to sign in</a>
		<span aria-hidden="true"> · </span>
		<a href="../register/index.html">Create an account</a>
	</main>
</body>
</html>
