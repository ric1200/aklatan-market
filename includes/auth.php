<?php
/** Customers, administrators, sessions and login throttling. */

function current_user(): ?array
{
    static $user = null;
    static $loadedFor = null;

    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

    // The cache is keyed on the session's user id, so signing in mid-request
    // (which is exactly what complete_login() does) is picked up immediately.
    if ($loadedFor === $id) { return $user; }
    $loadedFor = $id;

    if ($id <= 0) { $user = null; return null; }

    $row = fetch_one('SELECT id, first_name, last_name, email, phone, role, status, created_at, last_login_at
                      FROM users WHERE id = :id', ['id' => $id]);
    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        return null;
    }
    $user = $row;
    return $user;
}

function current_user_id(): ?int
{
    $user = current_user();
    return $user ? (int) $user['id'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function user_full_name(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) { return 'Guest'; }
    return trim($user['first_name'] . ' ' . $user['last_name']);
}

/** Remember where the visitor wanted to go, then send them to the login page. */
function require_login(string $message = 'Please sign in to continue.'): array
{
    $user = current_user();
    if ($user) { return $user; }
    $_SESSION['after_login'] = current_relative_url();
    flash_info($message);
    redirect('login.php');
}

/** Every admin page starts with this. Never rely on hiding links in the UI. */
function require_admin(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = current_relative_url();
        flash_info('Please sign in with an administrator account.');
        redirect('login.php');
    }
    if ($user['role'] !== 'admin') {
        log_error('Blocked non-admin user #' . $user['id'] . ' from admin area: ' . ($_SERVER['REQUEST_URI'] ?? '?'));
        aklatan_abort(403, 'Administrator access only',
            'Your account does not have permission to open the management area. If you believe this is a mistake, contact the store owner.');
    }
    return $user;
}

/* ------------------------------------------------------------ registration */

/** @return array{0:bool,1:string,2:?int} success, message, new user id */
function register_customer(array $input): array
{
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = normalize_phone((string) ($input['phone'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['password_confirm'] ?? '');
    $agree = !empty($input['agree']);

    if ($firstName === '' || $lastName === '') { return [false, 'Please enter your first and last name.', null]; }
    if (strlen($firstName) > 60 || strlen($lastName) > 60) { return [false, 'Names must be 60 characters or fewer.', null]; }
    if (!is_valid_email($email)) { return [false, 'Please enter a valid e-mail address (for example, juan@example.com).', null]; }
    if (strlen($email) > 190) { return [false, 'That e-mail address is too long.', null]; }
    if ($phone !== '' && !is_valid_phone($phone)) { return [false, 'Please enter a valid mobile number, for example 09171234567.', null]; }
    if (strlen($password) < 8) { return [false, 'Choose a password with at least 8 characters.', null]; }
    if ($password !== $confirm) { return [false, 'The two passwords do not match.', null]; }
    if (!$agree) { return [false, 'Please accept the privacy notice before creating an account.', null]; }

    $exists = fetch_value('SELECT id FROM users WHERE email = :email', ['email' => $email]);
    if ($exists) { return [false, 'An account with that e-mail address already exists. Try signing in instead.', null]; }

    $id = db_insert('users', [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone !== '' ? $phone : null,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'customer',
        'status' => 'active',
    ]);

    audit_log('customer.registered', 'user', $id, 'New customer account: ' . $email);
    return [true, 'Your account is ready.', $id];
}

/* -------------------------------------------------------------- login flows */

/** @return array{0:bool,1:string} */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    // Throttling slows down password guessing. It must never lock somebody out
    // of their own account, so the stored password is still checked even when
    // the limit has been reached - only the wording of a FAILED attempt changes.
    $throttled = login_throttled($email);

    $user = fetch_one('SELECT * FROM users WHERE email = :email', ['email' => $email]);

    // Always run a hash comparison so response time does not reveal whether the
    // e-mail address exists.
    $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $ok = password_verify($password, $hash);

    if (!$user || !$ok) {
        record_login_attempt($email, false);
        audit_log('auth.login_failed', 'user', $user['id'] ?? null, 'Failed sign-in for ' . $email);
        if ($throttled) {
            // A short pause makes automated guessing noticeably slower without
            // needing anything more than ordinary PHP.
            usleep(250000);
            return [false, 'Too many failed attempts from this device. Please wait '
                . (int) LOGIN_LOCKOUT_MINUTES . ' minutes and try again.'];
        }
        return [false, 'That e-mail address and password combination was not recognised. Please check your details and try again.'];
    }

    if ($user['status'] !== 'active') {
        record_login_attempt($email, false);
        return [false, 'This account is disabled. Please contact the store for help.'];
    }

    // Transparently upgrade the stored hash if PHP's default cost changed.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id', $user['id']);
    }

    // Correct credentials: forget the failed guesses made from this device for
    // this account so a legitimate shopper is never nagged by an old typo.
    clear_login_failures($email);
    record_login_attempt($email, true);
    complete_login($user);
    return [true, 'Welcome back, ' . $user['first_name'] . '!'];
}

/** Establish a fresh, authenticated session and merge any guest cart. */
function complete_login(array $user): void
{
    $guestCart = $_SESSION['guest_cart'] ?? [];
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();
    unset($_SESSION['csrf_token']); // fresh token for the new session id
    csrf_token();

    q('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);

    if (is_array($guestCart) && $guestCart) {
        merge_guest_cart_into_account((int) $user['id'], $guestCart);
        unset($_SESSION['guest_cart']);
    }
    audit_log('auth.login', 'user', $user['id'], 'Signed in');
}

function logout_user(): void
{
    $id = current_user_id();
    if ($id) { audit_log('auth.logout', 'user', $id, 'Signed out'); }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), true);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/* --------------------------------------------------------- login throttling */

function login_throttled(string $email): bool
{
    $minutes = (int) LOGIN_LOCKOUT_MINUTES;
    $max = (int) LOGIN_MAX_ATTEMPTS;
    try {
        $byEmail = (int) fetch_value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND email = :email AND attempted_at > (NOW() - INTERVAL ' . $minutes . ' MINUTE)',
            ['email' => $email], 0);
        $byIp = (int) fetch_value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND ip_address = :ip AND attempted_at > (NOW() - INTERVAL ' . $minutes . ' MINUTE)',
            ['ip' => client_ip()], 0);
    } catch (Throwable $ex) {
        log_error('throttle check failed: ' . $ex->getMessage());
        return false;
    }
    return $byEmail >= $max || $byIp >= ($max * 3);
}

/** Remove this device's failed attempts for one account after a real sign-in. */
function clear_login_failures(string $email): void
{
    try {
        q('DELETE FROM login_attempts WHERE success = 0 AND email = :email AND ip_address = :ip',
          ['email' => substr($email, 0, 190), 'ip' => client_ip()]);
    } catch (Throwable $ex) {
        log_error('clear_login_failures failed: ' . $ex->getMessage());
    }
}

function record_login_attempt(string $email, bool $success): void
{
    try {
        db_insert('login_attempts', [
            'email' => substr($email, 0, 190),
            'ip_address' => client_ip(),
            'user_agent' => client_user_agent(),
            'success' => $success ? 1 : 0,
        ]);
        if (random_int(1, 20) === 1) {
            q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
        }
    } catch (Throwable $ex) {
        log_error('record_login_attempt failed: ' . $ex->getMessage());
    }
}

function change_password(int $userId, string $current, string $new, string $confirm): array
{
    if (strlen($new) < 8) { return [false, 'The new password must be at least 8 characters long.']; }
    if ($new !== $confirm) { return [false, 'The new passwords do not match.']; }
    $user = fetch_one('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);
    if (!$user || !password_verify($current, $user['password_hash'])) {
        return [false, 'Your current password is not correct.'];
    }
    db_update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id', $userId);
    audit_log('user.password_changed', 'user', $userId, 'Password updated');
    return [true, 'Your password has been changed.'];
}
