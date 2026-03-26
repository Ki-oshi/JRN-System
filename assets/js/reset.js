// reset.js
document.addEventListener('DOMContentLoaded', () => {
    const forgotForm = document.getElementById('forgot-form');
    const resetForm = document.getElementById('reset-form');
    const modal = document.getElementById('reset-modal');
    const loader = document.getElementById('reset-loader');
    const message = document.getElementById('reset-message');

    function showModal(text) {
        message.textContent = text;
        loader.style.display = 'none';
        modal.style.display = 'flex';
    }

    function startModalLoading() {
        message.textContent = '';
        loader.style.display = 'block';
        modal.style.display = 'flex';
    }

    // Forgot Password
    if (forgotForm) {
        forgotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            startModalLoading();

            const formData = new FormData(forgotForm);
            const res = await fetch('process_forgot_password.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            showModal(data.message);
        });
    }

    // Reset Password
    if (resetForm) {
        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const pwd = resetForm.password.value;
            const confirm = resetForm.confirm_password.value;

            if (pwd !== confirm) {
                showModal('Passwords do not match.');
                return;
            }

            startModalLoading();
            const formData = new FormData(resetForm);
            const res = await fetch('process_reset_password.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            showModal(data.message);
        });
    }
});
