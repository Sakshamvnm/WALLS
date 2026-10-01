<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/session.php';

start_app_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit('Method not allowed.');
}

verify_csrf_token();
$_SESSION = [];
$cookieParams = session_get_cookie_params();
setcookie(session_name(), '', [
	'expires' => time() - 42000,
	'path' => $cookieParams['path'],
	'domain' => $cookieParams['domain'],
	'secure' => $cookieParams['secure'],
	'httponly' => $cookieParams['httponly'],
	'samesite' => 'Lax',
]);
session_destroy();

header('Location: data.php');
exit;