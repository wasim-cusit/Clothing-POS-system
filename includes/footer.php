<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<script>
// Global functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all Bootstrap components
    initializeBootstrapComponents();
    
    // Initialize custom functionality
    initializeCustomFunctionality();
    
    // Initialize mobile sidebar
    initializeMobileSidebar();
    
    // Initialize sidebar toggles
    initializeSidebarToggles();
    
    // Initialize mobile enhancements
    initializeMobileEnhancements();
    
    // Initialize responsive utilities
    initializeResponsiveUtilities();
    
    // Performance optimization: Debounce resize events
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Recalculate layouts after resize
            if (window.innerWidth <= 1199.98) {
                // Use requestAnimationFrame to prevent forced reflow
                requestAnimationFrame(() => {
                    // Any resize-specific logic can go here
                });
            }
        }, 150);
    }, { passive: true });
    
    // Performance optimization: Use passive event listeners where possible
    document.addEventListener('scroll', function() {
        // Handle scroll events efficiently
    }, { passive: true });
    
    // Performance optimization: Optimize touch events
    if ('ontouchstart' in window) {
        // Touch device optimizations
        document.addEventListener('touchmove', function() {
            // Handle touch move events efficiently
        }, { passive: true });
    }
});

function initializeBootstrapComponents() {
    // Initialize all Bootstrap dropdowns
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    dropdownElementList.forEach(dropdownToggleEl => {
        try {
            new bootstrap.Dropdown(dropdownToggleEl);
        } catch (error) {
            // Fallback: add manual dropdown functionality
            addManualDropdownFunctionality(dropdownToggleEl);
        }
    });
    
    // Initialize all Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        try {
            new bootstrap.Tooltip(tooltipTriggerEl);
        } catch (error) {
            // Silent fail
        }
    });
    
    // Initialize all Bootstrap popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(popoverTriggerEl => {
        try {
            new bootstrap.Popover(popoverTriggerEl);
        } catch (error) {
            // Silent fail
        }
    });
}

function addManualDropdownFunctionality(dropdownToggle) {
    const dropdownMenu = dropdownToggle.nextElementSibling;
    if (!dropdownMenu) return;
    
    dropdownToggle.addEventListener('click', function(e) {
        e.preventDefault();
        dropdownMenu.classList.toggle('show');
    });
    
    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    });
}

function initializeCustomFunctionality() {
    // Auto-hide all alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                try {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } catch (error) {
                    // Fallback: remove alert manually
                    alert.remove();
                }
            }
        }, 5000);
    });
    
    // Profile dropdown enhancement
    enhanceProfileDropdown();
    
    // Add fallback profile functionality
    addFallbackProfileFunctionality();
    
    // Make all clickable elements more responsive
    enhanceClickableElements();
}

function addFallbackProfileFunctionality() {
    // Add fallback for profile dropdown if Bootstrap fails
    const profileDropdown = document.getElementById('profileDropdown');
    if (!profileDropdown) return;
    
    // Add keyboard navigation
    profileDropdown.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
        }
    });
    
    // Add fallback click handler
    profileDropdown.addEventListener('click', function(e) {
        // If Bootstrap dropdown is not working, manually toggle
        setTimeout(() => {
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && !dropdownMenu.classList.contains('show')) {
                // Bootstrap failed, manually show dropdown
                dropdownMenu.classList.add('show');
            }
        }, 100);
    });
    
    // Ensure profile links work
    const profileLinks = document.querySelectorAll('[data-profile-link="true"]');
    profileLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Ensure the link works
            if (!this.href || this.href === '#' || this.href === window.location.href) {
                e.preventDefault();
            }
        });
    });
    
    // Ensure logout links work
    const logoutLinks = document.querySelectorAll('[data-logout-link="true"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Ensure the link works
            if (!this.href || this.href === '#' || this.href === window.location.href) {
                e.preventDefault();
            }
        });
    });
}

function enhanceProfileDropdown() {
    const profileDropdown = document.getElementById('profileDropdown');
    if (!profileDropdown) {
        return;
    }
    
    // Ensure dropdown works with both click and hover
    profileDropdown.addEventListener('click', function(e) {
        e.preventDefault();
        
        try {
            // Get the Bootstrap dropdown instance
            const dropdown = bootstrap.Dropdown.getInstance(this);
            if (dropdown) {
                dropdown.toggle();
            } else {
                // Fallback: manually toggle dropdown
                const dropdownMenu = this.nextElementSibling;
                if (dropdownMenu) {
                    dropdownMenu.classList.toggle('show');
                }
            }
        } catch (error) {
            // Fallback: manually toggle dropdown
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu) {
                dropdownMenu.classList.toggle('show');
            }
        }
    });
    
    // Handle dropdown menu items
    const dropdownMenu = profileDropdown.nextElementSibling;
    if (dropdownMenu) {
        const dropdownItems = dropdownMenu.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                
                try {
                    // Close dropdown after item click
                    const dropdown = bootstrap.Dropdown.getInstance(profileDropdown);
                    if (dropdown) {
                        dropdown.hide();
                    }
                } catch (error) {
                    // Fallback: manually hide dropdown
                    if (dropdownMenu) {
                        dropdownMenu.classList.remove('show');
                    }
                }
                
                // If it's a logout link, confirm first
                if (this.href.includes('logout.php')) {
                    if (!confirm('Are you sure you want to logout?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
            try {
                const dropdown = bootstrap.Dropdown.getInstance(profileDropdown);
                if (dropdown) {
                    dropdown.hide();
                }
            } catch (error) {
                // Fallback: manually hide dropdown
                if (dropdownMenu) {
                    dropdownMenu.classList.remove('show');
                }
            }
        }
    });
}

function enhanceClickableElements() {
    // Enhance all buttons with better click feedback
    const buttons = document.querySelectorAll('.btn, .nav-link, .dropdown-item');
    buttons.forEach(button => {
        // Add click feedback
        button.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.classList.add('ripple-effect');
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(255, 255, 255, 0.3)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple 0.6s linear';
            ripple.style.left = (e.clientX - this.offsetLeft) + 'px';
            ripple.style.top = (e.clientY - this.offsetTop) + 'px';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                if (ripple.parentNode) {
                    ripple.remove();
                }
            }, 600);
        });
        
        // Add hover effects with requestAnimationFrame
        button.addEventListener('mouseenter', function() {
            requestAnimationFrame(() => {
                this.style.transform = 'translateY(-1px)';
                this.style.transition = 'transform 0.2s ease';
            });
        });
        
        button.addEventListener('mouseleave', function() {
            requestAnimationFrame(() => {
                this.style.transform = 'translateY(0)';
            });
        });
    });
    
    // Enhance form inputs
    const formInputs = document.querySelectorAll('input, select, textarea');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            requestAnimationFrame(() => {
                this.parentElement.classList.add('focused');
            });
        });
        
        input.addEventListener('blur', function() {
            requestAnimationFrame(() => {
                this.parentElement.classList.remove('focused');
            });
        });
    });
}

function initializeMobileSidebar() {
    // Get sidebar elements once to avoid repeated DOM queries
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    const sidebarToggle = document.querySelector('.mobile-nav-toggle');
    
    if (!sidebar || !sidebarOverlay || !sidebarToggle) {
        return; // Exit if elements don't exist
    }

    // Mobile sidebar toggle functionality
    sidebarToggle.addEventListener('click', function() {
        // Use requestAnimationFrame to prevent forced reflow
        requestAnimationFrame(() => {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            if (sidebar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
    });

    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', function() {
        // Use requestAnimationFrame to prevent forced reflow
        requestAnimationFrame(() => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        });
    });

    // Close sidebar when pressing Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            // Use requestAnimationFrame to prevent forced reflow
            requestAnimationFrame(() => {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    });
    
    // Touch gesture support for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    // Check if touch listeners are already added to prevent duplicates
    if (!document.body.hasAttribute('data-touch-gestures-added')) {
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        // Mark as enhanced to prevent duplicate listeners
        document.body.setAttribute('data-touch-gestures-added', 'true');
    }
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const swipeDistance = touchEndX - touchStartX;
        
        // Swipe right to open sidebar
        if (swipeDistance > swipeThreshold && touchStartX < 50 && window.innerWidth <= 1199.98) {
            // Use requestAnimationFrame to prevent forced reflow
            requestAnimationFrame(() => {
                sidebar.classList.add('show');
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        }
        
        // Swipe left to close sidebar
        if (swipeDistance < -swipeThreshold && sidebar.classList.contains('show')) {
            // Use requestAnimationFrame to prevent forced reflow
            requestAnimationFrame(() => {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    }
    
    // Close sidebar when clicking outside (document click)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1199.98 && sidebar.classList.contains('show')) {
            // Check if click is outside sidebar and not on toggle button
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                // Use requestAnimationFrame to prevent forced reflow
                requestAnimationFrame(() => {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                });
            }
        }
    });
}

function initializeSidebarToggles() {
    // Chevron rotation for sidebar toggles
    const sidebarToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            const chevron = this.querySelector('.bi-chevron-right');
            if (chevron) {
                // Toggle chevron rotation
                if (chevron.style.transform === 'rotate(90deg)') {
                    chevron.style.transform = 'rotate(0deg)';
                } else {
                    chevron.style.transform = 'rotate(90deg)';
                }
            }
        });
    });
    
    // Initialize chevron states for already expanded sections
    const expandedToggles = document.querySelectorAll('[aria-expanded="true"]');
    expandedToggles.forEach(toggle => {
        const chevron = toggle.querySelector('.bi-chevron-right');
        if (chevron) {
            chevron.style.transform = 'rotate(90deg)';
        }
    });
    
    // Listen for Bootstrap collapse events to update chevron rotation
    document.addEventListener('show.bs.collapse', function(e) {
        const toggle = e.target.previousElementSibling;
        if (toggle && toggle.hasAttribute('data-bs-toggle')) {
            const chevron = toggle.querySelector('.bi-chevron-right');
            if (chevron) {
                chevron.style.transform = 'rotate(90deg)';
            }
        }
    });
    
    document.addEventListener('hide.bs.collapse', function(e) {
        const toggle = e.target.previousElementSibling;
        if (toggle && toggle.hasAttribute('data-bs-toggle')) {
            const chevron = toggle.querySelector('.bi-chevron-right');
            if (chevron) {
                chevron.style.transform = 'rotate(0deg)';
            }
        }
    });
}

function initializeMobileEnhancements() {
    // Check if already initialized to prevent duplicates
    if (document.body.hasAttribute('data-mobile-enhanced')) {
        return;
    }
    
    // Mobile table improvements
    enhanceMobileTables();
    
    // Mobile form improvements
    enhanceMobileForms();
    
    // Mobile navigation improvements
    enhanceMobileNavigation();
    
    // Mark as initialized to prevent duplicate calls
    document.body.setAttribute('data-mobile-enhanced', 'true');
}

function enhanceMobileTables() {
    // Make tables more mobile-friendly
    const tables = document.querySelectorAll('.table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentElement.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
    
    // Add mobile-specific table classes
    if (window.innerWidth <= 767.98) {
        tables.forEach(table => {
            table.classList.add('table-sm');
        });
    }
}

function enhanceMobileForms() {
    // Improve form layout on mobile
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Add mobile-specific classes
        if (window.innerWidth <= 575.98) {
            form.classList.add('needs-validation');
        }
        
        // Enhance form inputs for mobile
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            // Add mobile-specific attributes
            if (window.innerWidth <= 575.98) {
                input.setAttribute('autocomplete', 'off');
                if (input.type === 'text' || input.type === 'email' || input.type === 'password') {
                    input.setAttribute('autocorrect', 'off');
                    input.setAttribute('autocapitalize', 'off');
                }
            }
        });
    });
}

function enhanceMobileNavigation() {
    // Improve mobile navigation experience
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        // Check if touch feedback is already added to prevent duplicates
        if (!link.hasAttribute('data-touch-enhanced')) {
            // Add touch feedback
            link.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.95)';
            }, { passive: true });
            
            link.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            }, { passive: true });
            
            // Mark as enhanced to prevent duplicate listeners
            link.setAttribute('data-touch-enhanced', 'true');
        }
    });
    
    // Improve mobile dropdowns
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    dropdowns.forEach(dropdown => {
        if (window.innerWidth <= 575.98) {
            dropdown.style.position = 'fixed';
            dropdown.style.top = '50%';
            dropdown.style.left = '50%';
            dropdown.style.transform = 'translate(-50%, -50%)';
            dropdown.style.width = '90%';
            dropdown.style.maxWidth = '300px';
        }
    });
}

function initializeResponsiveUtilities() {
    // Add responsive utility classes
    const body = document.body;
    
    // Add screen size classes
    function updateScreenSizeClasses() {
        body.classList.remove('screen-xs', 'screen-sm', 'screen-md', 'screen-lg', 'screen-xl');
        
        if (window.innerWidth < 576) {
            body.classList.add('screen-xs');
        } else if (window.innerWidth < 768) {
            body.classList.add('screen-sm');
        } else if (window.innerWidth < 992) {
            body.classList.add('screen-md');
        } else if (window.innerWidth < 1200) {
            body.classList.add('screen-lg');
        } else {
            body.classList.add('screen-xl');
        }
    }
    
    // Initial call
    updateScreenSizeClasses();
    
    // Update on resize
    window.addEventListener('resize', updateScreenSizeClasses);
    
    // Add orientation classes
    function updateOrientationClasses() {
        body.classList.remove('orientation-portrait', 'orientation-landscape');
        if (window.innerHeight > window.innerWidth) {
            body.classList.add('orientation-portrait');
        } else {
            body.classList.add('orientation-landscape');
        }
    }
    
    // Initial call
    updateOrientationClasses();
    
    // Update on orientation change
    window.addEventListener('orientationchange', updateOrientationClasses);
    window.addEventListener('resize', updateOrientationClasses);
}

// Performance optimization for mobile
// Removed ServiceWorker registration to prevent 404 errors

// Mobile-specific optimizations
if (window.innerWidth <= 1199.98) {
    // Lazy load images on mobile
    const images = document.querySelectorAll('img[data-src]');
    if (images.length > 0) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
    
    // Optimize touch events with passive listeners
    document.addEventListener('touchstart', function() {}, {passive: true});
    document.addEventListener('touchend', function() {}, {passive: true});
}
</script>

<style>
/* Ripple effect animation */
@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

/* Enhanced focus states */
.focused input,
.focused select,
.focused textarea {
    border-color: #ffc107 !important;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
}

/* Better button states */
.btn:active {
    transform: translateY(1px);
}

/* Enhanced dropdown styling */
.dropdown-menu.show {
    animation: dropdownFadeIn 0.2s ease;
}

@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile-specific enhancements */
@media (max-width: 1199.98px) {
    .ripple-effect {
        display: none; /* Disable ripple on mobile for better performance */
    }
    
    .btn:hover,
    .nav-link:hover,
    .dropdown-item:hover {
        transform: none; /* Disable hover effects on mobile */
    }
}

/* Enhanced mobile animations */
@media (max-width: 1199.98px) {
    .sidebar {
        will-change: transform;
    }
    
    .card {
        will-change: transform;
    }
    
    .btn {
        will-change: transform;
    }
}

/* Mobile performance optimizations */
@media (max-width: 1199.98px) {
    * {
        -webkit-tap-highlight-color: transparent;
    }
    
    .btn:active,
    .nav-link:active {
        -webkit-tap-highlight-color: rgba(255, 193, 7, 0.3);
    }
}

/* Screen size utility classes */
.screen-xs .d-xs-none { display: none !important; }
.screen-xs .d-xs-block { display: block !important; }
.screen-xs .d-xs-flex { display: flex !important; }

.screen-sm .d-sm-none { display: none !important; }
.screen-sm .d-sm-block { display: block !important; }
.screen-sm .d-sm-flex { display: flex !important; }

.screen-md .d-md-none { display: none !important; }
.screen-md .d-md-block { display: block !important; }
.screen-md .d-md-flex { display: flex !important; }

.screen-lg .d-lg-none { display: none !important; }
.screen-lg .d-lg-block { display: block !important; }
.screen-lg .d-lg-flex { display: flex !important; }

.screen-xl .d-xl-none { display: none !important; }
.screen-xl .d-xl-block { display: block !important; }
.screen-xl .d-xl-flex { display: flex !important; }

/* Orientation utility classes */
.orientation-portrait .d-portrait-none { display: none !important; }
.orientation-portrait .d-portrait-block { display: block !important; }

.orientation-landscape .d-landscape-none { display: none !important; }
.orientation-landscape .d-landscape-block { display: block !important; }

/* Mobile-first responsive utilities */
@media (max-width: 575.98px) {
    .d-mobile-none { display: none !important; }
    .d-mobile-block { display: block !important; }
    .d-mobile-flex { display: flex !important; }
    .d-mobile-inline { display: inline !important; }
    .d-mobile-inline-block { display: inline-block !important; }
}

@media (min-width: 576px) {
    .d-mobile-none { display: initial !important; }
    .d-mobile-block { display: initial !important; }
    .d-mobile-flex { display: initial !important; }
    .d-mobile-inline { display: initial !important; }
    .d-mobile-inline-block { display: initial !important; }
}

/* Enhanced mobile form styling */
@media (max-width: 575.98px) {
    .form-control:focus,
    .form-select:focus {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .btn {
        min-height: 44px; /* Better touch target */
    }
    
    .nav-link {
        min-height: 44px; /* Better touch target */
    }
}

/* Mobile table improvements */
@media (max-width: 767.98px) {
    .table th,
    .table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
    }
    
    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
}

/* Mobile card improvements */
@media (max-width: 767.98px) {
    .card {
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .card-header {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .card-footer {
        padding: 1rem;
        border-top: 1px solid #dee2e6;
    }
}

/* Mobile modal improvements */
@media (max-width: 575.98px) {
    .modal-content {
        border-radius: 0.5rem;
        margin: 0.5rem;
    }
    
    .modal-header {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 1rem;
        border-top: 1px solid #dee2e6;
    }
}

/* Mobile pagination improvements */
@media (max-width: 575.98px) {
    .pagination {
        gap: 0.25rem;
    }
    
    .pagination .page-link {
        min-width: 40px;
        text-align: center;
    }
}

/* Mobile alert improvements */
@media (max-width: 575.98px) {
    .alert {
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .alert-dismissible {
        padding-right: 1rem;
    }
}

/* Mobile dropdown improvements */
@media (max-width: 575.98px) {
    .dropdown-menu {
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3);
        border: none;
    }
    
    .dropdown-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f8f9fa;
    }
    
    .dropdown-item:last-child {
        border-bottom: none;
    }
}

/* Mobile sidebar improvements */
@media (max-width: 1199.98px) {
    .sidebar {
        backdrop-filter: blur(10px);
        background: rgba(33, 37, 41, 0.95);
    }
    
    .sidebar .nav-link {
        border-radius: 0.375rem;
        margin: 0.125rem 0.5rem;
    }
    
    .sidebar .collapse .nav-link {
        margin: 0.125rem 0.5rem 0.125rem 2rem;
    }
}

/* Print styles */
@media print {
    .sidebar,
    .navbar,
    .sidebar-overlay {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
    
    .btn {
        display: none !important;
    }
    
    .table {
        font-size: 12px !important;
    }
}

/* Performance optimizations to prevent forced reflow */
.sidebar {
    will-change: transform;
    contain: layout style paint;
}

.main-content {
    will-change: margin-left;
    contain: layout style paint;
}

.navbar {
    will-change: transform;
    contain: layout style paint;
}

/* Optimize animations */
@media (prefers-reduced-motion: no-preference) {
    .sidebar,
    .main-content,
    .navbar {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
}

/* Reduce motion for users who prefer it */
@media (prefers-reduced-motion: reduce) {
    .sidebar,
    .main-content,
    .navbar,
    .btn,
    .nav-link,
    .dropdown-item {
        transition: none !important;
        animation: none !important;
    }
}

/* Optimize mobile performance */
@media (max-width: 1199.98px) {
    .sidebar {
        contain: strict;
        will-change: transform;
    }
    
    .sidebar-overlay {
        contain: strict;
        will-change: opacity;
    }
    
    /* Prevent layout shifts */
    .main-content {
        margin-left: 0 !important;
        transition: none;
    }
}

/* Optimize touch interactions */
@media (max-width: 1199.98px) {
    .nav-link,
    .btn {
        touch-action: manipulation;
        -webkit-tap-highlight-color: rgba(255, 193, 7, 0.3);
    }
    
    /* Prevent text selection during touch */
    .sidebar * {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    /* Allow text selection for content areas */
    .main-content * {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
    }
}

    /* Performance optimizations to prevent forced reflows */
    .sidebar {
        will-change: transform;
        backface-visibility: hidden;
        transform: translateZ(0);
    }
    
    .sidebar-overlay {
        will-change: opacity;
        backface-visibility: hidden;
        transform: translateZ(0);
    }
    
    /* Mobile optimizations */
    @media (max-width: 1199.98px) {
        .sidebar {
            contain: layout style paint;
        }
        
        .sidebar-overlay {
            contain: layout style paint;
        }
    }
    
    /* Touch optimizations */
    @media (hover: none) and (pointer: coarse) {
        .sidebar {
            touch-action: pan-y;
        }
        
        .sidebar-overlay {
            touch-action: pan-y;
        }
    }
</style>