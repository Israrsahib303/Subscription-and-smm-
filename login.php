<?php
require_once __DIR__ . '/includes/helpers.php';

$error_param = $_GET['error'] ?? '';

// Yeh check karta hai ke user pehle se login to nahi
if (isLoggedIn() && $error_param !== 'auth') {
    if (isAdmin()) {
        redirect(SITE_URL . '/panel/index.php');
    } else {
        redirect(SITE_URL . '/user/index.php');
    }
}

$error = '';
if ($error_param === 'auth') {
    $error = 'You must be an administrator to access that page. Please log in as an admin.';
}
$email = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];

        try {
            // Default admin login check
            if ($email === 'admin' && $password === '123456') {
                 $stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin' AND is_admin = 1");
                 $stmt->execute();
                 $user = $stmt->fetch();
                 
                 if ($user) {
                     session_regenerate_id(true);
                     $_SESSION['user_id'] = $user['id'];
                     $_SESSION['email'] = $user['email'];
                     $_SESSION['is_admin'] = $user['is_admin'];
                     $_SESSION['force_pass_change'] = true;
                     redirect(SITE_URL . '/panel/settings.php');
                 }
            }

            // Normal user login check
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                if ($user['is_admin']) {
                    redirect(SITE_URL . '/panel/index.php');
                } else {
                    redirect(SITE_URL . '/user/index.php');
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
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

            <h2 class="auth-title">Log In</h2>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="login.php<?php echo ($error_param === 'auth') ? '?error=auth' : ''; ?>" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Type here" value="<?php echo sanitize($email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Type here" required>
                </div>
                <button type="submit" class="btn btn-primary">Log In</button>
            </form>
            
            <p class="auth-switch-link">
                Don't have an account? <a href="register.php">Sign Up</a>
            </p>
        </div>
    </div>

</body>
</html>