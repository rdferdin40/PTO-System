<div class="auth-container">
    <div class="auth-box">
        <h1>Log In</h1>

        <form method="POST" action="/login">
            <?= View::csrfField() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= View::e(View::old('email')) ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Log In</button>
        </form>

        <p class="auth-links">
            <a href="/forgot-password">Forgot password?</a>
            <?php if (CONFIG['app']['allow_self_registration'] ?? false): ?>
                | <a href="/register">Register</a>
            <?php endif; ?>
        </p>

        <?php if (CONFIG['email']['google_auth_enabled'] ?? false): ?>
            <hr>
            <a href="/auth/google" class="btn btn-secondary">Sign in with Google</a>
        <?php endif; ?>
    </div>
</div>

<style>
.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
}

.auth-box {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 400px;
}

.auth-box h1 {
    margin-bottom: 1.5rem;
    text-align: center;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.form-group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.btn {
    width: 100%;
    padding: 0.75rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary {
    background: #0066cc;
    color: white;
}

.btn-primary:hover {
    background: #0052a3;
}

.btn-secondary {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
}

.auth-links {
    margin-top: 1rem;
    text-align: center;
    font-size: 0.9rem;
}

.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
}
</style>
