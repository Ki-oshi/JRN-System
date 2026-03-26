document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const loginError = document.getElementById('login-error');

    if (!loginForm || !loginError) {
        console.error('Login form or error div not found!');
        return;
    }

    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();

        // Clear any existing error messages
        loginError.textContent = '';
        loginError.style.display = 'none';

        // Get form values
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        // Basic validation
        if (!email || !password) {
            loginError.textContent = 'Please fill in all fields.';
            loginError.style.color = '#dc3545';
            loginError.style.display = 'block';
            return;
        }

        // Disable submit button
        const submitBtn = loginForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Signing in...';

        const formData = new FormData(loginForm);

        fetch('process_user_login.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                loginError.textContent = data.message || 'Login successful!';
                loginError.style.color = '#28a745';
                loginError.style.display = 'block';

                // Keep button disabled during redirect
                submitBtn.innerHTML = 'Redirecting...';

                // Redirect to appropriate page based on role
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 500);
            } else {
                // Show error message
                loginError.textContent = data.message || 'Login failed. Please try again.';
                loginError.style.color = '#dc3545';
                loginError.style.display = 'block';

                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                // Clear error after 5 seconds
                setTimeout(() => {
                    loginError.textContent = '';
                    loginError.style.display = 'none';
                }, 5000);
            }
        })
        .catch(err => {
            console.error('Login error:', err);
            
            loginError.textContent = 'Login failed. Please check your connection and try again.';
            loginError.style.color = '#dc3545';
            loginError.style.display = 'block';

            // Re-enable button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            setTimeout(() => {
                loginError.textContent = '';
                loginError.style.display = 'none';
            }, 5000);
        });
    });

    // Optional: Clear error on input change
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    [emailInput, passwordInput].forEach(input => {
        if (input) {
            input.addEventListener('input', () => {
                if (loginError.textContent) {
                    loginError.textContent = '';
                    loginError.style.display = 'none';
                }
            });
        }
    });
});
