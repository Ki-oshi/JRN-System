// assets/js/logout-modal.js

document.addEventListener("DOMContentLoaded", () => {
    const logoutBtn = document.getElementById("logout-btn");
    const overlay = document.getElementById("logout-modal-overlay");
    const cancelBtn = document.getElementById("logout-cancel");
    const confirmBtn = document.getElementById("logout-confirm");

    if (logoutBtn) {
        logoutBtn.addEventListener("click", (e) => {
            e.preventDefault();
            overlay.classList.add("active");
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener("click", () => {
            overlay.classList.remove("active");
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener("click", () => {
            window.location.href = "./logout.php";
        });
    }

    // Close modal when clicking outside the box
    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
            overlay.classList.remove("active");
        }
    });
});
