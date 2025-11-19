<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? View::e($title) . ' - ' : '' ?>TimeOff Management</title>
    <link rel="stylesheet" href="<?= View::asset('css/style.css') ?>">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo">TimeOff Management</a>

            <?php if (Session::isAuthenticated()): ?>
                <ul class="nav-menu">
                    <li><a href="/calendar">Calendar</a></li>
                    <li><a href="/requests">Requests</a></li>
                    <li><a href="/users">Team</a></li>
                    <?php if (Session::isAdmin()): ?>
                        <li><a href="/settings/company">Settings</a></li>
                    <?php endif; ?>
                    <li><a href="/logout">Logout</a></li>
                </ul>
            <?php endif; ?>
        </div>
    </nav>

    <main class="container">
        <?php if (!empty($_flashes['success'])): ?>
            <div class="alert alert-success">
                <?= View::e($_flashes['success']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_flashes['error'])): ?>
            <div class="alert alert-error">
                <?= View::e($_flashes['error']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $field => $fieldErrors): ?>
                        <?php foreach ($fieldErrors as $error): ?>
                            <li><?= View::e($error) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> TimeOff Management System - PHP 8.2</p>
        </div>
    </footer>

    <script src="<?= View::asset('js/app.js') ?>"></script>
</body>
</html>
