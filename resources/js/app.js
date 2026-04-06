import './bootstrap';
import '../css/app.css';
import SweetAlert2 from 'sweetalert2';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
// Set global SweetAlert defaults to enforce English language and modern premium styling
window.Swal = SweetAlert2.mixin({
    confirmButtonText: 'Confirm',
    cancelButtonText: 'Cancel',
    denyButtonText: 'No',
    reverseButtons: true, // Modern approach: Cancel on left, Confirm on right
    customClass: {
        popup: 'swal2-premium-main',
        title: 'swal2-premium-main-title',
        htmlContainer: 'swal2-premium-main-text',
        confirmButton: 'btn btn-primary rounded-pill px-4 mx-2 fw-bold shadow',
        cancelButton: 'btn btn-light border rounded-pill px-4 mx-2 fw-bold shadow-sm',
        denyButton: 'btn btn-danger rounded-pill px-4 mx-2 fw-bold shadow',
        actions: 'swal2-premium-actions mt-4'
    },
    buttonsStyling: false, // Use Bootstrap classes
});

// Configure a premium Toast helper based on the main mixin
window.Toast = window.Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    timer: 4000,
    timerProgressBar: true,
    customClass: {
        popup: 'swal2-premium-toast swal2-premium-popup',
        title: 'swal2-premium-title',
        timerProgressBar: 'swal2-timer-progress-bar',
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', window.Swal.stopTimer)
        toast.addEventListener('mouseleave', window.Swal.resumeTimer)
    }
});

// Ziggy is loaded via @routes in Blade, so window.route is already available globally.

import ajaxTable from './ajax-table';

// Theme Management
window.toggleTheme = () => {
    let theme = localStorage.getItem('app-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('app-theme', theme);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
};

// Apply theme on load
(function() {
    let theme = localStorage.getItem('app-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
})();

Alpine.data('ajaxTable', ajaxTable);
Alpine.start();
