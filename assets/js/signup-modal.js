// signup-modal.js
document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.querySelector('form[action="register_process.php"]');
    const modal = document.getElementById('signup-modal');
    const loader = document.getElementById('signup-loader');
    const message = document.getElementById('signup-message');

    if (message) {
        message.style.textAlign = 'center';
    }

    // ✅ Handle email verification messages (auth.php?verified=1 or 0)
    const urlParams = new URLSearchParams(window.location.search);
    const verifiedStatus = urlParams.get('verified');

    if (modal && loader && message && verifiedStatus !== null) {
        modal.style.display = 'flex';
        loader.style.display = 'none';

        if (verifiedStatus === '1') {
            message.textContent = 'Your email has been verified. You can now log in.';
        } else if (verifiedStatus === '0') {
            message.textContent = 'Verification failed or expired. Please try again.';
        }

        // Auto-close modal after 3s & clean URL
        setTimeout(() => {
            modal.style.display = 'none';
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }, 3000);
    }

    // ✅ Handle signup form submission (original behavior)
    if (signupForm && modal && loader && message) {
        signupForm.addEventListener('submit', (e) => {
            e.preventDefault();

            // Show modal with loader & initial message
            modal.style.display = 'flex';
            loader.style.display = 'block';
            message.textContent = 'A verification link has been sent to your email. Please check your inbox.';

            const formData = new FormData(signupForm);

            fetch('register_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const email = signupForm.querySelector('input[name="email"]').value;

                    // Poll every 3 seconds to check if verified
                    const interval = setInterval(() => {
                        fetch(`verify_status.php?email=${encodeURIComponent(email)}`)
                            .then(res => res.json())
                            .then(status => {
                                if (status.verified) {
                                    clearInterval(interval);
                                    loader.style.display = 'none';
                                    message.textContent = 'Email verified successfully! You can now log in.';
                                    setTimeout(() => {
                                        modal.style.display = 'none';
                                    }, 3000);
                                }
                            })
                            .catch(err => console.error(err));
                    }, 3000);

                } else {
                    loader.style.display = 'none';
                    message.textContent = 'Signup failed: ' + (data.error || 'Unknown error');
                    setTimeout(() => {
                        modal.style.display = 'none';
                    }, 3000);
                }
            })
            .catch(err => {
                loader.style.display = 'none';
                message.textContent = 'Signup failed. Please try again.';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 3000);
                console.error(err);
            });
        });
    }
});
