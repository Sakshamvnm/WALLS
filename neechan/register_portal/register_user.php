<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../database/session.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'captcha') {
    $firstNumber = random_int(2, 9);
    $secondNumber = random_int(2, 9);
    $_SESSION['registration_captcha_answer'] = $firstNumber + $secondNumber;
    send_json(['question' => "{$firstNumber} + {$secondNumber}"]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Unsupported request.'], 405);
}

$submittedAnswer = filter_input(INPUT_POST, 'captcha-answer', FILTER_VALIDATE_INT);
$expectedAnswer = $_SESSION['registration_captcha_answer'] ?? null;
unset($_SESSION['registration_captcha_answer']);

if (!is_int($submittedAnswer) || !is_int($expectedAnswer) || $submittedAnswer !== $expectedAnswer) {
    send_json(['error' => 'The math answer was incorrect. A new question has been loaded.'], 422);
}

$name = trim((string) ($_POST['name'] ?? ''));
$dateOfBirth = trim((string) ($_POST['dob'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm-password'] ?? '';
$errors = [];

if ($name === '' || strlen($name) > 120) {
    $errors[] = 'Enter a name no longer than 120 characters.';
}

$dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
$dobErrors = DateTimeImmutable::getLastErrors();
if ($dobDate === false || ($dobErrors !== false && ($dobErrors['warning_count'] > 0 || $dobErrors['error_count'] > 0)) || $dobDate->format('Y-m-d') !== $dateOfBirth || $dobDate > new DateTimeImmutable('today')) {
    $errors[] = 'Enter a valid date of birth that is not in the future.';
}

if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Enter a valid email address.';
}

if (!is_string($password) || strlen($password) < 8) {
    $errors[] = 'Choose a password with at least 8 characters.';
}

if (!is_string($confirmPassword) || !is_string($password) || !hash_equals($password, $confirmPassword)) {
    $errors[] = 'The passwords do not match.';
}

if ($errors !== []) {
    send_json(['error' => implode(' ', $errors)], 422);
}

try {
    $statement = database()->prepare(
        'INSERT INTO users (name, date_of_birth, email, password_hash) VALUES (:name, :date_of_birth, :email, :password_hash)'
    );
    $statement->execute([
        'name' => $name,
        'date_of_birth' => $dateOfBirth,
        'email' => strtolower($email),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    send_json(['message' => 'Account created. You can now sign in.'], 201);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        send_json(['error' => 'An account with that email already exists.'], 409);
    }

    error_log($exception->getMessage());
    send_json(['error' => 'The account could not be saved. Check the database connection.'], 500);
}