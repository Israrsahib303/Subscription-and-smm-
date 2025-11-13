<?php
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Validations
    if (empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email is already registered.';
            } else {
                // Create user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (email, password_hash, is_admin) VALUES (?, ?, 0)");
                $stmt->execute([$email, $password_hash]);
                
                $user_id = $db->lastInsertId();
                
                // Log the new user in
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['is_admin'] = 0;
                
                redirect(SITE_URL . '/user/index.php?welcome=1');
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.8">
</head>
<body class="auth-page"> <div class="auth-wrapper">
        <div class="shape-top-left"></div>
        <div class="shape-bottom-right"></div>

        <div class="auth-form-container">
            <div class="auth-logo-container">
                <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                    <img src="assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>?v=<?php echo time(); ?>" 
                         alt="<?php echo sanitize($GLOBALS['settings']['site_name']); ?> Logo" 
                         class="auth-logo">
                <?php else: ?>
                    <h1 class="auth-logo-text"><?php echo sanitize($GLOBALS['settings']['site_name']); ?></h1>
                <?php endif; ?>
                <p class="auth-tagline">We Turn Your Reach Into Reality</p>
            </div>

            <h2 class="auth-title">Sign Up</h2>
            
            <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Type here" value="<?php echo sanitize($email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Type here" required>
                </div>
                 <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Type here" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign Up</button>
            </form>
            
            <p class="auth-switch-link">
                Already have an account? <a href="login.php">Log In</a>
            </p>
        </div>
    </div>

</body>
</html>