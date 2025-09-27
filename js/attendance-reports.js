/**
 * Attendance Reports JavaScript
 * Modular, accessible JavaScript for attendance reporting system
 * Features: Modal management, data visualization, export functionality
 * Version: 2.0
 */

class AttendanceReports {
    constructor() {
        this.attendanceDetailsData = {};
        this.currentReport = null;
        this.charts = {};
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeModals();
        this.setupKeyboardNavigation();
        this.initializeCharts();
        console.log('Attendance Reports initialized');
    }

    setupEventListeners() {
        // Print report button
        const printBtn = document.getElementById('printReport');
        if (printBtn) {
            printBtn.addEventListener('click', () => this.printReport());
        }

        // View all attendance details button
        const viewAllBtn = document.getElementById('viewAllAttendanceBtn');
        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', () => this.showAllAttendanceDetails());
        }

        // Print all details button
        const printAllBtn = document.getElementById('printAllDetailsBtn');
        if (printAllBtn) {
            printAllBtn.addEventListener('click', () => this.printAllDetails());
        }

        // Export buttons
        const exportCsvBtn = document.getElementById('exportCsvBtn');
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => this.exportToCSV());
        }

        const exportPdfBtn = document.getElementById('exportPdfBtn');
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', () => this.exportToPDF());
        }

        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', () => this.toggleMobileMenu());
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggle = document.getElementById('mobileMenuToggle');
                if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Window resize handling
        window.addEventListener('resize', () => this.handleResize());
    }

    initializeModals() {
        // Initialize Bootstrap modals if available
        if (typeof bootstrap !== 'undefined') {
            this.detailsModal = new bootstrap.Modal(document.getElementById('attendanceDetailsModal'));
        }
    }

    setupKeyboardNavigation() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                this.printReport();
            }

            // Escape key handling
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Focus management for modals
        const modal = document.getElementById('attendanceDetailsModal');
        if (modal) {
            modal.addEventListener('shown.bs.modal', () => {
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (firstFocusable) firstFocusable.focus();
            });
        }
    }

    initializeCharts() {
        // Initialize Chart.js if available
        if (typeof Chart !== 'undefined') {
            this.createAttendanceChart();
        }
    }

    createAttendanceChart() {
        const chartCanvas = document.getElementById('attendanceChart');
        if (!chartCanvas) return;

        const ctx = chartCanvas.getContext('2d');

        // Sample data - replace with actual data
        const data = {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [65, 25, 10],
                backgroundColor: [
                    '#28a745',
                    '#dc3545',
                    '#ffc107'
                ],
                borderWidth: 0
            }]
        };

        this.charts.attendance = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    updateAttendanceData(data) {
        this.attendanceDetailsData = data || {};
        this.currentReport = data;
    }

    showAllAttendanceDetails() {
        const modalBody = document.getElementById('allAttendanceDetailsBody');
        if (!modalBody) return;

        modalBody.innerHTML = '';

        if (!this.attendanceDetailsData || Object.keys(this.attendanceDetailsData).length === 0) {
            modalBody.innerHTML = '<div class="empty-state"><i class="fas fa-chart-bar empty-icon"></i><h3 class="empty-title">No Data Available</h3><p class="empty-message">No attendance details found for the selected criteria.</p></div>';
            if (this.detailsModal) this.detailsModal.show();
            return;
        }

        const allDates = this.getAllDates(this.attendanceDetailsData);
        const table = this.createDetailsTable(allDates);

        modalBody.appendChild(table);
        if (this.detailsModal) this.detailsModal.show();
    }

    getAllDates(data) {
        const datesSet = new Set();
        for (const student in data) {
            Object.keys(data[student]).forEach(date => datesSet.add(date));
        }
        return Array.from(datesSet).sort();
    }

    createDetailsTable(allDates) {
        const table = document.createElement('table');
        table.id = "attendanceTableAll";
        table.className = 'details-table table table-bordered table-hover table-sm';

        // Create table header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.innerHTML = '<th>Student Name</th><th>Overall Status</th>' +
            allDates.map(date => `<th>${this.formatDate(date)}</th>`).join('');
        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Create table body
        const tbody = document.createElement('tbody');
        for (const student in this.attendanceDetailsData) {
            const attendance = this.attendanceDetailsData[student];
            const percent = this.calculateAttendancePercent(attendance);
            const statusClass = percent >= 85 ? 'status-allowed' : 'status-not-allowed';
            const statusText = percent >= 85 ? 'Allowed' : 'Not Allowed';

            let row = `<td class="student-name">${this.escapeHtml(student)}</td>`;
            row += `<td><span class="status-badge ${statusClass}">${statusText} (${percent}%)</span></td>`;

            allDates.forEach(date => {
                const status = attendance[date];
                row += this.createStatusCell(status);
            });

            const tr = document.createElement('tr');
            tr.innerHTML = row;
            tbody.appendChild(tr);
        }

        table.appendChild(tbody);
        return table;
    }

    calculateAttendancePercent(attendanceObj) {
        const total = Object.keys(attendanceObj).length;
        const presentCount = Object.values(attendanceObj).filter(status => status === 'Present').length;
        return total === 0 ? 0 : Math.round((presentCount / total) * 100);
    }

    createStatusCell(status) {
        if (status === 'Present') {
            return '<td><span class="badge-present">Present</span></td>';
        } else if (status === 'Absent') {
            return '<td><span class="badge-absent">Absent</span></td>';
        } else {
            return '<td><span class="text-muted">-</span></td>';
        }
    }

    formatDate(dateString) {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&")
            .replace(/</g, "<")
            .replace(/>/g, ">")
            .replace(/"/g, """)
            .replace(/'/g, "&#039;");
    }

    printReport() {
        // Add print-specific styles
        const printStyles = `
            <style>
                @media print {
                    .sidebar, .topbar, .reports-actions, .modal {
                        display: none !important;
                    }
                    .main-content {
                        margin-left: 0;
                        padding: 0;
                    }
                    .attendance-container {
                        max-width: none;
                        padding: 0;
                    }
                    .page-header, .filters-section, .reports-section {
                        box-shadow: none;
                        border: 1px solid #ccc;
                        margin-bottom: 1rem;
                    }
                }
            </style>
        `;

        // Create print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Attendance Report - Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    ${printStyles}
                </head>
                <body>
                    <div class="container-fluid">
                        <h1 class="text-center mb-4">Attendance Report</h1>
                        <p class="text-center text-muted mb-4">Generated on ${new Date().toLocaleDateString()}</p>
                        ${document.querySelector('.attendance-container').innerHTML}
                    </div>
                </body>
            </html>
        `);

        printWindow.document.close();

        // Wait for content to load then print
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    printAllDetails() {
        const printContents = document.getElementById('allAttendanceDetailsBody').innerHTML;
        const printWindow = window.open('', '_blank');

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Attendance Details - Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            body { font-size: 12px; }
                            table { font-size: 10px; }
                            .badge { font-size: 9px; padding: 2px 6px; }
                        }
                    </style>
                </head>
                <body>
                    <h2 class="text-center mb-4">All Students Attendance Details</h2>
                    <p class="text-center text-muted mb-4">Generated on ${new Date().toLocaleDateString()}</p>
                    ${printContents}
                </body>
            </html>
        `);

        printWindow.document.close();

        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }, 300);
    }

    exportToCSV() {
        if (!this.currentReport || !this.currentReport.summary) {
            this.showAlert('No data available to export', 'warning');
            return;
        }

        let csvContent = 'Student Name,Attendance %,Present Sessions,Total Sessions,Status\n';

        this.currentReport.summary.forEach(record => {
            const status = record.attendance_percent >= 85 ? 'Allowed' : 'Not Allowed';
            csvContent += `"${record.student}","${record.attendance_percent}%","${record.present_count}","${record.total_count}","${status}"\n`;
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', `attendance_report_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    exportToPDF() {
        // Check if jsPDF is available
        if (typeof jsPDF === 'undefined') {
            this.showAlert('PDF export library not loaded. Please contact administrator.', 'error');
            return;
        }

        if (!this.currentReport || !this.currentReport.summary) {
            this.showAlert('No data available to export', 'warning');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Add title
        doc.setFontSize(20);
        doc.text('Attendance Report', 20, 30);

        // Add date
        doc.setFontSize(12);
        doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 20, 45);

        // Add table
        const tableData = this.currentReport.summary.map(record => [
            record.student,
            `${record.attendance_percent}%`,
            record.present_count.toString(),
            record.total_count.toString(),
            record.attendance_percent >= 85 ? 'Allowed' : 'Not Allowed'
        ]);

        doc.autoTable({
            head: [['Student Name', 'Attendance %', 'Present', 'Total', 'Status']],
            body: tableData,
            startY: 60,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [0, 102, 204] }
        });

        // Save the PDF
        doc.save(`attendance_report_${new Date().toISOString().split('T')[0]}.pdf`);
    }

    toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
    }

    handleResize() {
        // Handle responsive behavior
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('show');
        }

        // Update charts if they exist
        Object.values(this.charts).forEach(chart => {
            if (chart.resize) chart.resize();
        });
    }

    closeAllModals() {
        // Close any open modals
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }

    showAlert(message, type = 'info', autoDismiss = true) {
        const alertClass = `alert-${type}`;
        const icon = this.getAlertIcon(type);

        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas fa-${icon} me-2"></i>
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        const alertContainer = document.getElementById('alertContainer');
        if (alertContainer) {
            alertContainer.innerHTML = alertHtml;

            // Auto-dismiss
            if (autoDismiss) {
                setTimeout(() => {
                    const alert = alertContainer.querySelector('.alert');
                    if (alert) {
                        alert.classList.remove('show');
                        setTimeout(() => alert.remove(), 150);
                    }
                }, 5000);
            }
        }
    }

    getAlertIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Utility methods for data processing
    formatPercentage(value) {
        return Math.round(value * 100) / 100;
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Performance monitoring
    measurePerformance(action, callback) {
        if (performance.mark) {
            const startMark = `${action}_start`;
            const endMark = `${action}_end`;
            const measureName = `${action}_measure`;

            performance.mark(startMark);
            callback();
            performance.mark(endMark);
            performance.measure(measureName, startMark, endMark);

            const measure = performance.getEntriesByName(measureName)[0];
            console.log(`${action} took ${measure.duration}ms`);
        } else {
            callback();
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.attendanceReports = new AttendanceReports();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AttendanceReports;
}