            </div> <!-- End of main-content -->
        </div> <!-- End of content -->
    </div> <!-- End of wrapper -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Toggle sidebar
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('collapsed');
            $('.wrapper').toggleClass('collapsed');
            
            // Store sidebar state in cookie
            const isCollapsed = $('#sidebar').hasClass('collapsed');
            document.cookie = `sidebar_collapsed=${isCollapsed}; path=/; max-age=31536000`; // 1 year
        });

        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize all popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // Fix dropdown menu position on mobile
        $('.dropdown-menu').each(function() {
            $(this).css('min-width', $(this).parent().width());
        });

        // Handle nested dropdowns
        $('.dropdown-menu a.dropdown-toggle').on('click', function(e) {
            if (!$(this).next().hasClass('show')) {
                $(this).parents('.dropdown-menu').first().find('.show').removeClass('show');
            }
            var $subMenu = $(this).next('.dropdown-menu');
            $subMenu.toggleClass('show');

            $(this).parents('li.nav-item.dropdown.show').on('hidden.bs.dropdown', function(e) {
                $('.dropdown-submenu .show').removeClass('show');
            });

            return false;
        });

        // Fix sidebar submenu collapse
        $('#sidebar:not(.collapsed) a[data-bs-toggle="collapse"]').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            $(target).collapse('toggle');
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert-dismissible').fadeOut('slow');
        }, 5000);

        // Add shadow to navbar on scroll
        $(window).scroll(function() {
            if ($(window).scrollTop() > 0) {
                $('.navbar').addClass('shadow-sm');
            } else {
                $('.navbar').removeClass('shadow-sm');
            }
        });

        // Prevent form double submission
        $('form').submit(function() {
            $(this).find(':submit').attr('disabled', 'disabled');
        });

        // Add active class to sidebar menu based on current URL
        var currentUrl = window.location.pathname;
        $('#sidebar ul.components a').each(function() {
            if ($(this).attr('href') === currentUrl) {
                $(this).closest('li').addClass('active');
                var $collapse = $(this).closest('.collapse');
                if ($collapse.length) {
                    $collapse.addClass('show');
                    $collapse.prev('a').attr('aria-expanded', 'true');
                }
            }
        });

        // Initialize DataTables with common configuration
        if ($.fn.DataTable) {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    search: "",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    zeroRecords: "No matching records found",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                },
                pageLength: 25,
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                responsive: true,
                stateSave: true,
                stateDuration: 60 * 60 * 24, // 1 day
                ordering: true,
                processing: true
            });
        }

        // Handle mobile menu
        $(window).resize(function() {
            if ($(window).width() <= 768) {
                $('#sidebar').addClass('collapsed');
                $('.wrapper').addClass('collapsed');
            }
        }).trigger('resize');
    });

    // Global Functions
    function showLoading() {
        // Add loading overlay
        $('body').append('<div class="loading-overlay"><div class="spinner-border text-primary"></div></div>');
    }

    function hideLoading() {
        // Remove loading overlay
        $('.loading-overlay').remove();
    }

    function showToast(message, type = 'success') {
        // Create toast element
        const toast = $(`
            <div class="toast position-fixed top-0 end-0 m-3" role="alert">
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `);

        // Add to document and show
        $('body').append(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Remove after hidden
        toast.on('hidden.bs.toast', function() {
            toast.remove();
        });
    }

    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        hideLoading();
        showToast('An error occurred. Please try again.', 'danger');
    });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
