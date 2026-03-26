// ===== DISCARD CHANGES MODAL =====
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('profileForm');
    const editDiv = document.getElementById('profileEdit');
    const displayDiv = document.getElementById('profileDisplay');

    // Create modal elements
    const modalOverlay = document.createElement('div');
    modalOverlay.classList.add('discard-modal-overlay');
    modalOverlay.innerHTML = `
        <div class="discard-modal">
            <h3>Discard Changes?</h3>
            <p>Are you sure you want to discard your changes?</p>
            <div class="discard-modal-buttons">
                <button class="confirm-btn">Yes</button>
                <button class="cancel-btn">No</button>
            </div>
        </div>
    `;
    document.body.appendChild(modalOverlay);

    const confirmBtn = modalOverlay.querySelector('.confirm-btn');
    const cancelModalBtn = modalOverlay.querySelector('.cancel-btn');

    // Function to check if form inputs changed
    function hasFormChanges() {
        const inputs = form.querySelectorAll('input');
        for (let input of inputs) {
            if (input.defaultValue !== input.value) return true;
        }
        return false;
    }

    // Open modal function using .active for animation
    window.openDiscardModal = (onConfirm) => {
        modalOverlay.classList.add('active');

        // Confirm discard
        const confirmHandler = () => {
            modalOverlay.classList.remove('active');
            form.reset();
            editDiv.style.display = 'none';
            displayDiv.style.display = 'block';
            if (typeof onConfirm === 'function') onConfirm();
            cleanup();
        };

        // Cancel discard
        const cancelHandler = () => {
            modalOverlay.classList.remove('active');
            cleanup();
        };

        function cleanup() {
            confirmBtn.removeEventListener('click', confirmHandler);
            cancelModalBtn.removeEventListener('click', cancelHandler);
        }

        confirmBtn.addEventListener('click', confirmHandler);
        cancelModalBtn.addEventListener('click', cancelHandler);
    };

    // Cancel button inside the profile form
    const cancelBtn = document.getElementById('cancelEditBtn');
    cancelBtn.addEventListener('click', () => {
        if (hasFormChanges()) {
            window.openDiscardModal();
        } else {
            editDiv.style.display = 'none';
            displayDiv.style.display = 'block';
        }
    });
});
