// LaundryKu - Client Script

document.addEventListener('DOMContentLoaded', function () {
    // Sidebar Toggle for Mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // Auto dismiss alerts after 5 seconds
    const flashAlerts = document.querySelectorAll('.alert-dismissible');
    flashAlerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
