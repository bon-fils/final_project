        document.addEventListener('DOMContentLoaded', function() {
            initializeCourses();
            setupEventListeners();
        });

        /**
         * Setup all event listeners
         */
        function setupEventListeners() {
            // Mobile menu toggle
            const sidebarToggle = document.querySelector('button[onclick="toggleSidebar()"]');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }

            // Search functionality
            const searchInput = document.getElementById('courseSearch');
            if (searchInput) {
                searchInput.addEventListener('input', handleSearch);
            }

            // Filter buttons
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(button => {
                button.addEventListener('click', handleFilter);
            });
        }

        /**
         * Initialize courses data
         */
        function initializeCourses() {
            const courseCards = document.querySelectorAll('.course-card');
            allCourses = Array.from(courseCards);
            filteredCourses = [...allCourses];
            updateFilterCounts();
        }

        /**
         * Sidebar toggle functionality
         */
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        }

        /**
         * Enhanced search functionality
         */
        function handleSearch() {
            const query = this.value.toLowerCase().trim();
            const filterType = document.querySelector('.filter-btn.active').dataset.filter;

            filteredCourses = allCourses.filter(card => {
                const title = card.querySelector('.course-title').textContent.toLowerCase();
                const code = card.querySelector('.course-code').textContent.toLowerCase();
                const description = card.querySelector('.course-description') ?
                    card.querySelector('.course-description').textContent.toLowerCase() : '';
                const department = card.querySelector('.meta-item') ?
                    card.querySelector('.meta-item').textContent.toLowerCase() : '';

                const matchesSearch = !query ||
                    title.includes(query) ||
                    code.includes(query) ||
                    description.includes(query) ||
                    department.includes(query);

                const matchesFilter = checkFilterMatch(card, filterType);

                return matchesSearch && matchesFilter;
            });

            updateCourseDisplay();
        }

        /**
         * Filter functionality
         */
        function handleFilter() {
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            const filterType = this.dataset.filter;
            const searchQuery = document.getElementById('courseSearch').value.toLowerCase().trim();

            filteredCourses = allCourses.filter(card => {
                const title = card.querySelector('.course-title').textContent.toLowerCase();
                const code = card.querySelector('.course-code').textContent.toLowerCase();
                const description = card.querySelector('.course-description') ?
                    card.querySelector('.course-description').textContent.toLowerCase() : '';

                const matchesSearch = !searchQuery ||
                    title.includes(searchQuery) ||
                    code.includes(searchQuery) ||
                    description.includes(searchQuery);

                const matchesFilter = checkFilterMatch(card, filterType);

                return matchesSearch && matchesFilter;
            });

            updateCourseDisplay();
        }

        /**
         * Check if course matches filter criteria
         */
        function checkFilterMatch(card, filterType) {
            switch (filterType) {
                case 'all':
                    return true;
                case 'assigned':
                    return card.querySelector('.course-status').textContent.trim().toLowerCase() === 'assigned';
                case 'department':
                    return card.querySelector('.course-status').textContent.trim().toLowerCase() === 'department';
                case 'high-attendance':
                    const attendanceRate = parseFloat(card.dataset.attendanceRate) || 0;
                    return attendanceRate > 80;
                case 'today-sessions':
                    const todaySessions = parseInt(card.dataset.todaySessions) || 0;
                    return todaySessions > 0;
                case 'recent':
                    const createdAt = parseInt(card.dataset.createdAt) || 0;
                    const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
                    return createdAt > thirtyDaysAgo;
                default:
                    return true;
            }
        }

        /**
         * Update course display with animations
         */
        function updateCourseDisplay() {
            const container = document.getElementById('coursesContainer');

            // Hide all cards first
            allCourses.forEach(card => {
                card.style.display = 'none';
                card.classList.remove('fade-in');
            });

            // Show filtered cards with staggered animation
            filteredCourses.forEach((card, index) => {
                setTimeout(() => {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                }, index * 100);
            });

            // Update filter button counts
            updateFilterCounts();
        }

        /**
         * Update filter button counts
         */
        function updateFilterCounts() {
            const filters = {
                'all': allCourses.length,
                'assigned': allCourses.filter(card => card.querySelector('.course-status').textContent.trim().toLowerCase() === 'assigned').length,
                'department': allCourses.filter(card => card.querySelector('.course-status').textContent.trim().toLowerCase() === 'department').length,
                'high-attendance': allCourses.filter(card => (parseFloat(card.dataset.attendanceRate) || 0) > 80).length,
                'today-sessions': allCourses.filter(card => (parseInt(card.dataset.todaySessions) || 0) > 0).length,
                'recent': allCourses.filter(card => {
                    const createdAt = parseInt(card.dataset.createdAt) || 0;
                    const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
                    return createdAt > thirtyDaysAgo;
                }).length
            };

            document.querySelectorAll('.filter-btn').forEach(button => {
                const filterType = button.dataset.filter;
                const count = filters[filterType] || 0;
                const iconAndText = button.innerHTML.split('<span')[0]; // Get icon and text before badge
                button.innerHTML = `${iconAndText}<span class="badge bg-light text-dark ms-1">${count}</span>`;
            });
        }

        /**
         * View course details (placeholder for future implementation)
         */
        function viewCourseDetails(courseId) {
            // Show loading state
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner loading-spinner me-1"></i>Loading...';
            button.disabled = true;

            // Simulate API call
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;

                // Show course details modal
                showCourseDetailsModal(courseId);
            }, 1000);
        }

        /**
         * Show course details modal
         */
        function showCourseDetailsModal(courseId) {
            const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
            if (!courseCard) return;

            const courseName = courseCard.querySelector('.course-title').textContent;
            const courseCode = courseCard.querySelector('.course-code').textContent;
            const description = courseCard.querySelector('.course-description').textContent;
            const department = courseCard.querySelector('.meta-item').textContent;
            const students = courseCard.querySelector('.stat-value').textContent;
            const attendance = courseCard.querySelectorAll('.stat-value')[1].textContent;
            const sessions = courseCard.querySelectorAll('.stat-value')[2].textContent;

            const modalHtml = `
                <div class="modal fade" id="courseDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>Course Details
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-4">
                                    <div class="col-md-8">
                                        <h4 class="text-gradient">${courseName}</h4>
                                        <p class="text-muted mb-3">${courseCode}</p>
                                        <p class="mb-4">${description}</p>

                                        <div class="row g-3">
                                            <div class="col-6">
                                                <div class="p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-building text-primary me-2"></i>
                                                        <div>
                                                            <small class="text-muted">Department</small>
                                                            <div class="fw-semibold">${department}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-users text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted">Students</small>
                                                            <div class="fw-semibold">${students}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-chart-line text-warning me-2"></i>
                                                        <div>
                                                            <small class="text-muted">Attendance Rate</small>
                                                            <div class="fw-semibold">${attendance}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-calendar-check text-info me-2"></i>
                                                        <div>
                                                            <small class="text-muted">Total Sessions</small>
                                                            <div class="fw-semibold">${sessions}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="stats-card mb-3">
                                                <div class="stats-icon" style="background: var(--primary-gradient); color: white;">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </div>
                                                <div class="fw-semibold">Course Overview</div>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <a href="attendance-session.php?course_id=${courseId}" class="btn btn-primary">
                                                    <i class="fas fa-video me-2"></i>Start Attendance Session
                                                </a>
                                                <a href="attendance-reports.php?course_id=${courseId}" class="btn btn-outline-secondary">
                                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                                </a>
                                                <button class="btn btn-outline-info" onclick="exportCourseData(${courseId})">
                                                    <i class="fas fa-download me-2"></i>Export Data
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('courseDetailsModal'));
            modal.show();

            // Clean up modal when hidden
            document.getElementById('courseDetailsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        /**
         * Export course data
         */
        function exportCourseData(courseId) {
            showNotification('Course data export feature coming soon!', 'info');
        }

        /**
         * Enhanced notification system
         */
        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                              type === 'error' ? 'alert-danger' :
                              type === 'warning' ? 'alert-warning' : 'alert-info';

            const icon = type === 'success' ? 'fas fa-check-circle' :
                         type === 'error' ? 'fas fa-exclamation-triangle' :
                         type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            alert.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="${icon} me-2 mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${type.toUpperCase()}</div>
                        <div>${message}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            document.body.appendChild(alert);

            // Add animation class
            setTimeout(() => alert.classList.add('show'), 10);

            // Auto remove after 4 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            }, 4000);
        }

        /**
         * Keyboard shortcuts
         */
        document.addEventListener('keydown', function(e) {
            // Ctrl + F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('courseSearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Escape to clear search
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('courseSearch');
                if (searchInput && document.activeElement === searchInput) {
                    searchInput.value = '';
                    searchInput.blur();
                    handleSearch.call(searchInput);
                }
            }
        });

        /**
         * Auto-refresh functionality
         */
        let autoRefreshInterval = null;

        function startAutoRefresh() {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            autoRefreshInterval = setInterval(() => {
                if (!document.hidden && !document.querySelector(':focus')) {
                    updateFilterCounts();
                }
            }, 60000); // Refresh every minute
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        // Start auto-refresh when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });

        // Initialize auto-refresh
        startAutoRefresh();
    </script>
</body>
</html>