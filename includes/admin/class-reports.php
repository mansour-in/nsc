<?php
/**
 * NSC Reports Class
 * 
 * Handles comprehensive reporting functionality
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class NSC_Reports {
    
    /**
     * Initialize the reports functionality
     */
    public function __construct() {
        // Don't add admin menu here - let admin class handle it
        add_action('admin_init', array($this, 'handle_csv_export'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_nsc_generate_report', array($this, 'ajax_generate_report'));
    }
    
    /**
     * Add reports submenu page
     */
    public function add_reports_menu() {
        // Only add submenu if we're not handling Reporter role in admin class
        $current_user = wp_get_current_user();
        $is_reporter = in_array('reporter', $current_user->roles) && !current_user_can('manage_options');
        
        if (!$is_reporter) {
            add_submenu_page(
                'nsc-contest',
                'Reports',
                'Reports',
                'manage_options',
                'nsc-reports',
                array($this, 'render_reports_page')
            );
        }
    }
    
    /**
     * Enqueue scripts and styles for reports page
     */
    public function enqueue_scripts($hook) {
        // Check for both admin submenu and reporter standalone menu hooks
        if ($hook != 'nsc-contest_page_nsc-reports' && $hook != 'toplevel_page_nsc-reports') {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', false);
        
        // Add fallback for Chart.js if CDN fails
        wp_add_inline_script('chart-js', '
            if (typeof Chart === "undefined") {
                console.log("Primary Chart.js CDN failed, loading fallback...");
                var script = document.createElement("script");
                script.src = "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js";
                script.onload = function() { console.log("Fallback Chart.js loaded successfully"); };
                document.head.appendChild(script);
            }
        ');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $(".date-picker").datepicker({
                    dateFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true
                });
            });
        ');
    }
    
    /**
     * Render the reports page
     */
    public function render_reports_page() {
        // Ensure database tables exist
        $this->ensure_database_tables();
        // Get current filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-t');
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get report data with pagination
        $report_data = $this->get_report_data($date_from, $date_to, $category, $status, $country, $per_page, $offset);
        $total_records = $this->get_total_records($date_from, $date_to, $category, $status, $country);
        $total_pages = ceil($total_records / $per_page);
        
        $summary_stats = $this->get_summary_statistics($date_from, $date_to, $category, $status, $country);
        $chart_data = $this->get_chart_data($date_from, $date_to);
        
        ?>
        <div class="wrap">
            <h1>NSC Reports</h1>
            
            <!-- Filters Section -->
            <div class="nsc-reports-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="nsc-reports">
                    
                    <table class="form-table">
                        <tr>
                            <th>Date Range</th>
                            <td>
                                <input type="text" name="date_from" class="date-picker" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                                <span> to </span>
                                <input type="text" name="date_to" class="date-picker" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">
                            </td>
                        </tr>
                        <tr>
                            <th>Category</th>
                            <td>
                                <select name="category">
                                    <option value="">All Categories</option>
                                    <option value="J1" <?php selected($category, 'J1'); ?>>J1 (3-4 years)</option>
                                    <option value="J2" <?php selected($category, 'J2'); ?>>J2 (5-7 years)</option>
                                    <option value="J3" <?php selected($category, 'J3'); ?>>J3 (8-12 years)</option>
                                    <option value="S1" <?php selected($category, 'S1'); ?>>S1 (13-15 years)</option>
                                    <option value="S2" <?php selected($category, 'S2'); ?>>S2 (16-18 years)</option>
                                    <option value="S3" <?php selected($category, 'S3'); ?>>S3 (19+ years)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Payment Status</th>
                            <td>
                                <select name="status">
                                    <option value="">All Statuses</option>
                                    <option value="paid" <?php selected($status, 'paid'); ?>>Paid</option>
                                    <option value="pending" <?php selected($status, 'pending'); ?>>Not Paid</option>
                                    <option value="failed" <?php selected($status, 'failed'); ?>>Failed</option>
                                    <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Country</th>
                            <td>
                                <select name="country">
                                    <option value="">All Countries</option>
                                    <?php
                                    $countries = $this->get_countries();
                                    foreach ($countries as $country_code) {
                                        echo '<option value="' . esc_attr($country_code) . '" ' . selected($country, $country_code, false) . '>' . esc_html($country_code) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Generate Report">
                        <a href="?page=nsc-reports&export=csv&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>&country=<?php echo urlencode($country); ?>" class="button">Export as CSV</a>
                    </p>
                </form>
            </div>
            
            <!-- Summary Statistics -->
            <div class="nsc-summary-stats">
                <h2>Summary Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3>Total Registrations</h3>
                        <span class="stat-number"><?php echo number_format($summary_stats['total_registrations']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3>Paid Participants</h3>
                        <span class="stat-number"><?php echo number_format($summary_stats['paid_participants']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3>Total Revenue</h3>
                        <span class="stat-number">₹<?php echo number_format($summary_stats['total_revenue'], 2); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3>Video Submissions</h3>
                        <span class="stat-number"><?php echo number_format($summary_stats['video_submissions']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3>Completion Rate</h3>
                        <span class="stat-number"><?php echo number_format($summary_stats['completion_rate'], 1); ?>%</span>
                    </div>
                    <div class="stat-box">
                        <h3>Payment Rate</h3>
                        <span class="stat-number"><?php echo number_format($summary_stats['payment_rate'], 1); ?>%</span>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="nsc-charts">
                <div class="chart-container">
                    <h3>Registrations Over Time</h3>
                    <canvas id="registrationChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Category Distribution</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Payment Status Distribution</h3>
                    <canvas id="paymentChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Country Distribution</h3>
                    <canvas id="countryChart"></canvas>
                </div>
            </div>
            
            <!-- Detailed Report Table -->
            <div class="nsc-detailed-report">
                <h2>Detailed Report (<?php echo count($report_data); ?> records)</h2>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select id="bulk-action">
                            <option value="">Bulk Actions</option>
                            <option value="export-selected">Export Selected</option>
                            <option value="mark-paid">Mark as Paid</option>
                        </select>
                        <button type="button" id="doaction" class="button">Apply</button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="cb-select-all"></td>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Category</th>
                            <th>Country</th>
                            <th>Registration Date</th>
                            <th>Payment Status</th>
                            <th>Payment Date</th>
                            <th>Amount</th>
                            <th>Video Status</th>
                            <th>Upload Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="12">No data found for the selected criteria.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <th class="check-column"><input type="checkbox" name="selected[]" value="<?php echo esc_attr($row->participant_id); ?>"></th>
                                <td><?php echo esc_html($row->username); ?></td>
                                <td><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></td>
                                <td><?php echo esc_html($row->email); ?></td>
                                <td><?php echo esc_html($row->phone_number ?: '—'); ?></td>
                                <td><?php echo esc_html($row->category); ?></td>
                                <td><?php echo esc_html($row->country_code); ?></td>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($row->registration_date))); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($row->payment_status); ?>">
                                        <?php echo esc_html(ucfirst($row->payment_status)); ?>
                                    </span>
                                </td>
                                <td><?php echo $row->payment_date ? esc_html(date('Y-m-d', strtotime($row->payment_date))) : '—'; ?></td>
                                <td><?php echo $row->amount ? '₹' . esc_html(number_format($row->amount, 2)) : '—'; ?></td>
                                <td>
                                    <?php if ($row->upload_status): ?>
                                    <span class="status-badge status-<?php echo esc_attr($row->upload_status); ?>">
                                        <?php echo esc_html(ucfirst($row->upload_status)); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="status-badge status-none">No Upload</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row->upload_date ? esc_html(date('Y-m-d', strtotime($row->upload_date))) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="nsc-pagination">
                    <div class="pagination-info">
                        Showing <?php echo (($current_page - 1) * $per_page) + 1; ?> to <?php echo min($current_page * $per_page, $total_records); ?> of <?php echo $total_records; ?> records
                    </div>
                    <div class="pagination-links">
                        <?php
                        $base_url = add_query_arg(array(
                            'page' => 'nsc-reports',
                            'date_from' => $date_from,
                            'date_to' => $date_to,
                            'category' => $category,
                            'status' => $status,
                            'country' => $country
                        ), admin_url('admin.php'));
                        
                        // Previous page
                        if ($current_page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>" class="pagination-link">‹ Previous</a>
                        <?php endif;
                        
                        // Page numbers
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>" class="pagination-link">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif;
                        endif;
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <span class="pagination-link current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $i, $base_url)); ?>" class="pagination-link"><?php echo $i; ?></a>
                            <?php endif;
                        endfor;
                        
                        if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>" class="pagination-link"><?php echo $total_pages; ?></a>
                        <?php endif;
                        
                        // Next page
                        if ($current_page < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>" class="pagination-link">Next ›</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .nsc-reports-filters {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .nsc-summary-stats {
            margin: 30px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: normal;
        }
        
        .stat-number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .nsc-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 400px;
            position: relative;
        }
        
        .chart-container canvas {
            max-height: 350px !important;
        }
        
        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .nsc-detailed-report {
            margin: 30px 0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-submitted {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-none {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .date-picker {
            width: 120px;
        }
        
        .nsc-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .pagination-links {
            display: flex;
            gap: 5px;
        }
        
        .pagination-link {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 3px;
            transition: all 0.2s;
        }
        
        .pagination-link:hover {
            background: #f0f0f0;
            text-decoration: none;
        }
        
        .pagination-link.current {
            background: #2271b1;
            color: white;
            border-color: #2271b1;
        }
        
        .pagination-dots {
            padding: 8px 4px;
            color: #666;
        }
        </style>
        
        <script>
        // Wait for Chart.js to load and initialize charts
        function initializeCharts() {
            if (typeof Chart === 'undefined') {
                console.log('Chart.js not loaded yet, retrying...');
                setTimeout(initializeCharts, 100);
                return;
            }
            
            // Chart.js configurations
            const chartData = <?php echo json_encode($chart_data); ?>;
            
            // Check if we have data
            const hasData = chartData.registration_dates.length > 0 || chartData.categories.length > 0;
            
            if (!hasData) {
                // Show no data messages
                document.getElementById('registrationChart').parentNode.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><h4>No registration data available</h4><p>Charts will appear once participants register</p></div>';
                document.getElementById('categoryChart').parentNode.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><h4>No category data available</h4><p>Charts will appear once participants register</p></div>';
                document.getElementById('paymentChart').parentNode.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><h4>No payment data available</h4><p>Charts will appear once payments are made</p></div>';
                document.getElementById('countryChart').parentNode.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><h4>No country data available</h4><p>Charts will appear once participants register</p></div>';
            } else {
            // Registration over time chart
            const registrationCtx = document.getElementById('registrationChart').getContext('2d');
            new Chart(registrationCtx, {
                type: 'line',
                data: {
                    labels: chartData.registration_dates,
                    datasets: [{
                        label: 'Registrations',
                        data: chartData.registration_counts,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        
            // Category distribution chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.categories,
                    datasets: [{
                        data: chartData.category_counts,
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5
                }
            });
            
            // Payment status chart
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            new Chart(paymentCtx, {
                type: 'pie',
                data: {
                    labels: chartData.payment_statuses,
                    datasets: [{
                        data: chartData.payment_counts,
                        backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5
                }
            });
            
            // Country distribution chart
            const countryCtx = document.getElementById('countryChart').getContext('2d');
            new Chart(countryCtx, {
                type: 'bar',
                data: {
                    labels: chartData.countries,
                    datasets: [{
                        label: 'Participants',
                        data: chartData.country_counts,
                        backgroundColor: '#36A2EB'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            }
        }
        
        // Initialize charts when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCharts);
        } else {
            initializeCharts();
        }
        
        // Bulk actions
        jQuery(document).ready(function($) {
            $('#cb-select-all').on('change', function() {
                $('input[name="selected[]"]').prop('checked', this.checked);
            });
            
            $('#doaction').on('click', function() {
                const action = $('#bulk-action').val();
                const selected = $('input[name="selected[]"]:checked').map(function() {
                    return this.value;
                }).get();
                
                if (!action || selected.length === 0) {
                    alert('Please select an action and at least one item.');
                    return;
                }
                
                if (action === 'export-selected') {
                    const ids = selected.join(',');
                    window.location.href = '?page=nsc-reports&export=csv&selected_ids=' + ids;
                } else if (action === 'mark-paid') {
                    if (confirm('Mark selected participants as paid?')) {
                        // AJAX call to mark as paid
                        $.post(ajaxurl, {
                            action: 'nsc_bulk_mark_paid',
                            participant_ids: selected,
                            nonce: '<?php echo wp_create_nonce('nsc_bulk_actions'); ?>'
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        });
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get comprehensive report data
     */
    private function get_report_data($date_from, $date_to, $category = '', $status = '', $country = '', $limit = 0, $offset = 0) {
        global $wpdb;
        
        $where_conditions = array("p.registration_date BETWEEN %s AND %s");
        $where_values = array($date_from, $date_to . ' 23:59:59');
        
        if (!empty($category)) {
            $where_conditions[] = "p.category = %s";
            $where_values[] = $category;
        }
        
        if (!empty($status)) {
            if ($status === 'pending') {
                // For "Not Paid" filter, include pending, created, and NULL statuses
                $where_conditions[] = "(pay.status IN ('pending', 'created') OR pay.status IS NULL)";
            } else {
                $where_conditions[] = "pay.status = %s";
                $where_values[] = $status;
            }
        }
        
        if (!empty($country)) {
            $where_conditions[] = "p.country_code = %s";
            $where_values[] = $country;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT 
                p.participant_id,
                u.user_login as username,
                p.first_name,
                p.last_name,
                u.user_email as email,
                um.meta_value as phone_number,
                p.category,
                p.country_code,
                p.registration_date,
                pay.status as payment_status,
                pay.payment_date,
                pay.amount,
                up.status as upload_status,
                up.upload_date
            FROM {$wpdb->prefix}nsc_participants p
            LEFT JOIN {$wpdb->users} u ON p.wp_user_id = u.ID
            LEFT JOIN {$wpdb->prefix}usermeta um ON (u.ID = um.user_id AND um.meta_key = 'billing_phone')
            LEFT JOIN {$wpdb->prefix}nsc_payments pay ON p.wp_user_id = pay.user_id
            LEFT JOIN {$wpdb->prefix}nsc_uploads up ON p.wp_user_id = up.user_id
            {$where_clause}
            ORDER BY p.registration_date DESC
        ";
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Get total record count for pagination
     */
    private function get_total_records($date_from, $date_to, $category = '', $status = '', $country = '') {
        global $wpdb;
        
        $where_conditions = array("p.registration_date BETWEEN %s AND %s");
        $where_values = array($date_from, $date_to . ' 23:59:59');
        
        if (!empty($category)) {
            $where_conditions[] = "p.category = %s";
            $where_values[] = $category;
        }
        
        if (!empty($status)) {
            if ($status === 'pending') {
                // For "Not Paid" filter, include pending, created, and NULL statuses
                $where_conditions[] = "(pay.status IN ('pending', 'created') OR pay.status IS NULL)";
            } else {
                $where_conditions[] = "pay.status = %s";
                $where_values[] = $status;
            }
        }
        
        if (!empty($country)) {
            $where_conditions[] = "p.country_code = %s";
            $where_values[] = $country;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT COUNT(DISTINCT p.participant_id) as total
            FROM {$wpdb->prefix}nsc_participants p
            LEFT JOIN {$wpdb->prefix}nsc_payments pay ON p.wp_user_id = pay.user_id
            LEFT JOIN {$wpdb->prefix}nsc_uploads up ON p.wp_user_id = up.user_id
            {$where_clause}
        ";
        
        return $wpdb->get_var($wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Get summary statistics
     */
    private function get_summary_statistics($date_from, $date_to, $category = '', $status = '', $country = '') {
        global $wpdb;
        
        $where_conditions = array("p.registration_date BETWEEN %s AND %s");
        $where_values = array($date_from, $date_to . ' 23:59:59');
        
        if (!empty($category)) {
            $where_conditions[] = "p.category = %s";
            $where_values[] = $category;
        }
        
        if (!empty($status)) {
            $where_conditions[] = "pay.status = %s";
            $where_values[] = $status;
        }
        
        if (!empty($country)) {
            $where_conditions[] = "p.country_code = %s";
            $where_values[] = $country;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT 
                COUNT(DISTINCT p.participant_id) as total_registrations,
                COUNT(CASE WHEN pay.status = 'paid' THEN 1 END) as paid_participants,
                SUM(CASE WHEN pay.status = 'paid' THEN pay.amount ELSE 0 END) as total_revenue,
                COUNT(CASE WHEN up.status = 'submitted' THEN 1 END) as video_submissions
            FROM {$wpdb->prefix}nsc_participants p
            LEFT JOIN {$wpdb->prefix}nsc_payments pay ON p.wp_user_id = pay.user_id
            LEFT JOIN {$wpdb->prefix}nsc_uploads up ON p.wp_user_id = up.user_id
            {$where_clause}
        ";
        
        $result = $wpdb->get_row($wpdb->prepare($sql, $where_values));
        
        $completion_rate = $result->total_registrations > 0 ? 
            ($result->video_submissions / $result->total_registrations) * 100 : 0;
        
        $payment_rate = $result->total_registrations > 0 ? 
            ($result->paid_participants / $result->total_registrations) * 100 : 0;
        
        return array(
            'total_registrations' => $result->total_registrations,
            'paid_participants' => $result->paid_participants,
            'total_revenue' => $result->total_revenue ?: 0,
            'video_submissions' => $result->video_submissions,
            'completion_rate' => $completion_rate,
            'payment_rate' => $payment_rate
        );
    }
    
    /**
     * Get chart data
     */
    private function get_chart_data($date_from, $date_to) {
        global $wpdb;
        
        // Check if we have any data first
        $has_data = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_participants");
        
        if (!$has_data) {
            // Return empty data structure for charts
            return array(
                'registration_dates' => array(),
                'registration_counts' => array(),
                'categories' => array(),
                'category_counts' => array(),
                'payment_statuses' => array(),
                'payment_counts' => array(),
                'countries' => array(),
                'country_counts' => array()
            );
        }
        
        // Registration over time
        $registration_data = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(registration_date) as date, COUNT(*) as count
            FROM {$wpdb->prefix}nsc_participants
            WHERE registration_date BETWEEN %s AND %s
            GROUP BY DATE(registration_date)
            ORDER BY date
        ", $date_from, $date_to . ' 23:59:59'));
        
        $registration_dates = array();
        $registration_counts = array();
        foreach ($registration_data as $row) {
            $registration_dates[] = $row->date;
            $registration_counts[] = (int)$row->count;
        }
        
        // Category distribution
        $category_data = $wpdb->get_results("
            SELECT category, COUNT(*) as count
            FROM {$wpdb->prefix}nsc_participants
            GROUP BY category
            ORDER BY category
        ");
        
        $categories = array();
        $category_counts = array();
        foreach ($category_data as $row) {
            $categories[] = $row->category;
            $category_counts[] = (int)$row->count;
        }
        
        // Payment status distribution
        $payment_data = $wpdb->get_results("
            SELECT COALESCE(pay.status, 'not_paid') as status, COUNT(*) as count
            FROM {$wpdb->prefix}nsc_participants p
            LEFT JOIN {$wpdb->prefix}nsc_payments pay ON p.wp_user_id = pay.user_id
            GROUP BY pay.status
        ");
        
        $payment_statuses = array();
        $payment_counts = array();
        foreach ($payment_data as $row) {
            $status = $row->status ?: 'not_paid';
            // Map database statuses to user-friendly labels
            $status_label = match($status) {
                'paid' => 'Paid',
                'pending' => 'Not Paid',
                'created' => 'Not Paid',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                default => 'Not Paid'
            };
            $payment_statuses[] = $status_label;
            $payment_counts[] = (int)$row->count;
        }
        
        // Country distribution (top 10)
        $country_data = $wpdb->get_results("
            SELECT country_code, COUNT(*) as count
            FROM {$wpdb->prefix}nsc_participants
            GROUP BY country_code
            ORDER BY count DESC
            LIMIT 10
        ");
        
        $countries = array();
        $country_counts = array();
        foreach ($country_data as $row) {
            $countries[] = $row->country_code;
            $country_counts[] = (int)$row->count;
        }
        
        return array(
            'registration_dates' => $registration_dates,
            'registration_counts' => $registration_counts,
            'categories' => $categories,
            'category_counts' => $category_counts,
            'payment_statuses' => $payment_statuses,
            'payment_counts' => $payment_counts,
            'countries' => $countries,
            'country_counts' => $country_counts
        );
    }
    
    /**
     * Get list of countries from participants
     */
    private function get_countries() {
        global $wpdb;
        
        return $wpdb->get_col("
            SELECT DISTINCT country_code 
            FROM {$wpdb->prefix}nsc_participants 
            ORDER BY country_code
        ");
    }
    
    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'nsc-reports' || !isset($_GET['export']) || $_GET['export'] !== 'csv') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export data.');
        }
        
        // Get filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-t');
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
        $selected_ids = isset($_GET['selected_ids']) ? sanitize_text_field($_GET['selected_ids']) : '';
        
        // Get data
        if ($selected_ids) {
            $data = $this->get_selected_report_data($selected_ids);
        } else {
            $data = $this->get_report_data($date_from, $date_to, $category, $status, $country);
        }
        
        // Generate filename
        $filename = 'nsc-report-' . date('Y-m-d-H-i-s') . '.csv';
        
        // Set headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array(
            'Username',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Category',
            'Country Code',
            'Registration Date',
            'Payment Status',
            'Payment Date',
            'Amount',
            'Upload Status',
            'Upload Date'
        ));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, array(
                $row->username,
                $row->first_name,
                $row->last_name,
                $row->email,
                $row->phone_number ?: '',
                $row->category,
                $row->country_code,
                $row->registration_date,
                $row->payment_status,
                $row->payment_date,
                $row->amount,
                $row->upload_status,
                $row->upload_date
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get selected report data for CSV export
     */
    private function get_selected_report_data($selected_ids) {
        global $wpdb;
        
        $ids = explode(',', $selected_ids);
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        $sql = "
            SELECT 
                u.user_login as username,
                p.first_name,
                p.last_name,
                u.user_email as email,
                um.meta_value as phone_number,
                p.category,
                p.country_code,
                p.registration_date,
                pay.status as payment_status,
                pay.payment_date,
                pay.amount,
                up.status as upload_status,
                up.upload_date
            FROM {$wpdb->prefix}nsc_participants p
            LEFT JOIN {$wpdb->users} u ON p.wp_user_id = u.ID
            LEFT JOIN {$wpdb->prefix}usermeta um ON (u.ID = um.user_id AND um.meta_key = 'billing_phone')
            LEFT JOIN {$wpdb->prefix}nsc_payments pay ON p.wp_user_id = pay.user_id
            LEFT JOIN {$wpdb->prefix}nsc_uploads up ON p.wp_user_id = up.user_id
            WHERE p.participant_id IN ({$placeholders})
            ORDER BY p.registration_date DESC
        ";
        
        return $wpdb->get_results($wpdb->prepare($sql, $ids));
    }
    
    /**
     * AJAX handler for generating reports
     */
    public function ajax_generate_report() {
        check_ajax_referer('nsc_reports', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Handle AJAX report generation if needed
        wp_send_json_success();
    }
    
    /**
     * Ensure database tables exist
     */
    private function ensure_database_tables() {
        global $wpdb;
        
        // Check if tables exist
        $participants_table = $wpdb->prefix . 'nsc_participants';
        $payments_table = $wpdb->prefix . 'nsc_payments';
        $uploads_table = $wpdb->prefix . 'nsc_uploads';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create participants table if it doesn't exist
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$participants_table} (
            participant_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT(20) UNSIGNED NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            dob DATE NOT NULL,
            category VARCHAR(3) NOT NULL,
            country_code VARCHAR(3) NOT NULL,
            registration_date DATETIME NOT NULL,
            PRIMARY KEY  (participant_id),
            KEY wp_user_id (wp_user_id)
        ) $charset_collate");
        
        // Create payments table if it doesn't exist
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$payments_table} (
            payment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            razorpay_order_id VARCHAR(255),
            razorpay_payment_id VARCHAR(255),
            amount DECIMAL(10,2),
            currency VARCHAR(3),
            status VARCHAR(20) DEFAULT 'created',
            order_date DATETIME,
            payment_date DATETIME,
            PRIMARY KEY  (payment_id),
            KEY user_id (user_id)
        ) $charset_collate");
        
        // Create uploads table if it doesn't exist
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$uploads_table} (
            upload_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            upload_date DATETIME NOT NULL,
            PRIMARY KEY  (upload_id),
            KEY user_id (user_id)
        ) $charset_collate");
    }
}

// Initialize the reports class
new NSC_Reports();