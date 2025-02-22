document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    document.getElementById('sidebarCollapse').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Add active class to current menu item
    const currentLocation = window.location.pathname;
    const menuItems = document.querySelectorAll('#sidebar a');
    menuItems.forEach(item => {
        if (item.getAttribute('href') === currentLocation.split('/').pop()) {
            item.parentElement.classList.add('active');
            // If item is in submenu, expand the parent
            const submenu = item.closest('ul.collapse');
            if (submenu) {
                submenu.classList.add('show');
                submenu.previousElementSibling.classList.add('active');
            }
        }
    });
});
