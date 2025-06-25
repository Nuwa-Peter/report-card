<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Optional: Set a flash message to explain why they are on the login page
    // $_SESSION['login_error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}

// No specific data processing needed on the dashboard itself for this initial version,
// but session_start() is good practice if we add user-specific elements later.
date_default_timezone_set('Africa/Kampala'); // Or user's preferred timezone

require_once 'db_connection.php'; // Ensure $pdo is available

// --- Dashboard Progress Data Fetching ---
$current_year_name = date('Y');
$current_academic_year_id = null; // Initialize
$dashboard_progress_data = [];   // Initialize
$classes_list = [];              // Initialize
$terms_list = [];                // Initialize

try {
    $stmt_ay = $pdo->prepare("SELECT id FROM academic_years WHERE year_name = :year_name LIMIT 1");
    $stmt_ay->execute([':year_name' => $current_year_name]);
    $current_academic_year_id = $stmt_ay->fetchColumn();

    if ($current_academic_year_id) {
        $stmt_classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC"); // Consider a sort order column if 'P1, P2...' is not alphabetical
        $classes_list = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

        // Fetch terms, ensuring a specific order (e.g., Term I, II, III)
        // This might require an 'order_index' column in 'terms' table or specific IDs.
        // For now, assume ordering by ID or term_name gives a logical sequence.
        $stmt_terms = $pdo->query("SELECT id, term_name FROM terms ORDER BY id ASC");
        $terms_list = $stmt_terms->fetchAll(PDO::FETCH_ASSOC);

        $stmt_batch_check = $pdo->prepare(
            "SELECT id FROM report_batch_settings
             WHERE academic_year_id = :academic_year_id
               AND class_id = :class_id
               AND term_id = :term_id
             LIMIT 1"
        );
        $stmt_summary_check = $pdo->prepare(
            "SELECT COUNT(id) FROM student_report_summary WHERE report_batch_id = :report_batch_id LIMIT 1"
        );

        foreach ($classes_list as $class_item) {
            $class_id = $class_item['id'];
            $class_name = $class_item['class_name'];
            $dashboard_progress_data[$class_name] = [];

            foreach ($terms_list as $term_item) {
                $term_id = $term_item['id'];
                $term_name_key = $term_item['term_name']; // Use term_name as key for consistency

                $stmt_batch_check->execute([
                    ':academic_year_id' => $current_academic_year_id,
                    ':class_id' => $class_id,
                    ':term_id' => $term_id
                ]);
                $report_batch_id = $stmt_batch_check->fetchColumn();
                $status = "Pending"; // Default

                if ($report_batch_id) {
                    $stmt_summary_check->execute([':report_batch_id' => $report_batch_id]);
                    $summary_count = $stmt_summary_check->fetchColumn();
                    if ($summary_count !== false && $summary_count > 0) { // Check count is not false from fetchColumn
                        $status = "Completed";
                    } else {
                        $status = "Data Imported";
                    }
                }
                $dashboard_progress_data[$class_name][$term_name_key] = [
                    'status' => $status,
                    'report_batch_id' => $report_batch_id
                ];
            }
        }
    } else {
        if (isset($_SESSION['user_id'])) { // Only set session message if a user is logged in
          $_SESSION['info_message'] = "Dashboard Progress: The current Academic Year (" . htmlspecialchars($current_year_name) . ") is not found in the system. Progress display may be incomplete.";
        }
    }
} catch (PDOException $e) {
    error_log("Dashboard Progress Data Fetching Error: " . $e->getMessage());
    if (isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "Could not load dashboard progress data due to a database error.";
    }
    // $dashboard_progress_data will remain empty or partially filled.
}
// --- End Dashboard Progress Data Fetching ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report System Dashboard - Maria Ow'embabazi P/S</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet"> <!-- Font Awesome for icons -->
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        body {
            background-color: #e0f7fa; /* Sky blue theme - light cyan */
            display: flex;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #007bff; /* Bootstrap primary blue */
            color: #fff;
            transition: all 0.3s;
            position: fixed; /* Fixed Sidebar */
            height: 100%;
            overflow-y: auto;
        }
        #sidebar.active {
            margin-left: -250px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #0069d9; /* Darker blue */
            text-align: center;
        }
        #sidebar .sidebar-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid #fff;
        }
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #47748b;
        }
        #sidebar ul p {
            color: #fff;
            padding: 10px;
            text-align: center;
            font-size: 0.9em;
        }
        #sidebar ul li a {
            padding: 12px 20px;
            font-size: 1.1em;
            display: block;
            color: #f8f9fa; /* Lighter text for better contrast on blue */
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        #sidebar ul li a:hover {
            color: #007bff; /* Blue text */
            background: #fff; /* White background */
        }
        #sidebar ul li.active > a, a[aria-expanded="true"] {
            color: #fff;
            background: #0062cc; /* Slightly darker blue for active */
        }
        #content {
            width: calc(100% - 250px); /* Adjust based on sidebar width */
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
            margin-left: 250px; /* Match sidebar width */
            background-color: #e0f7fa; /* Ensure content background matches body */
        }
        #content.active {
            width: 100%;
            margin-left: 0;
        }
        .navbar-custom {
            background-color: #ffffff; /* White navbar */
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .datetime-display {
            font-size: 0.9em;
            color: #555;
        }
        .main-content-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px; /* Hide sidebar by default on smaller screens */
            }
            #sidebar.active {
                margin-left: 0; /* Show sidebar when active */
            }
            #content {
                width: 100%;
                margin-left: 0;
            }
            #content.active { /* When sidebar is open on small screen, content might need to shift or be overlaid */
                 margin-left: 250px; /* Example: shift content if sidebar is not an overlay */
            }
             #sidebarCollapse span { display: block; } /* Always show toggler */
        }

    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.png" alt="School Logo" onerror="this.style.display='none';">
            <h5>Maria Ow'embabazi P/S</h5>
            <p class="datetime-display"><?php echo date("D, d M Y H:i"); ?></p>
        </div>

        <ul class="list-unstyled components">
            <p>Main Navigation</p>
            <li class="active"> <!-- Example: make dashboard link active by default -->
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard Home</a>
            </li>
            <li>
                <a href="download_template.php"><i class="fas fa-file-excel"></i> Download Marks Entry Template</a>
            </li>
            <li>
                <a href="data_entry.php"><i class="fas fa-edit"></i> Marks Entry</a> <!-- Was "Generate New Reports" -->
            </li>
            <li>
                <a href="view_report_archives.php"><i class="fas fa-archive"></i> View Report Archives</a> <!-- New page, replaces old "View Processed Data" & "Report Archives" submenu -->
            </li>
            <li>
                <a href="summary_sheet.php"><i class="fas fa-chart-pie"></i> Summary Sheets</a> <!-- Direct link, summary_sheet.php handles batch selection -->
            </li>
            <li>
                <a href="about.php"><i class="fas fa-info-circle"></i> About & Help</a>
            </li>
            <!--
            <li>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
            -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
            <li class="mt-3 pt-2 border-top border-secondary-subtle"> <!-- Visually separate admin links -->
                <p class="text-white-50 small ps-3 text-uppercase">Administration</p>
            </li>
            <li>
                <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Page Content -->
    <div id="content">
        <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                    <span>Toggle Sidebar</span>
                </button>

                <!-- Right aligned navbar items -->
                <div class="ms-auto d-flex align-items-center">
                    <?php if (isset($_SESSION['username'])): ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-dark" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>About User</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Others (Placeholder)</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Optional: Show a Login button if user is not logged in, though page protection should handle this -->
                        <!-- <a href="login.php" class="btn btn-primary">Login</a> -->
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="container-fluid pt-3">
            <h2>Dashboard</h2>
            <div class="main-content-card">
                <p>Welcome to the Maria Ow'embabazi Primary School Report Card System Dashboard.</p>
                <p>Use the sidebar to navigate through the available options. You can generate new reports, view summaries, or download templates.</p>
                <!-- More dashboard widgets/summaries can go here later -->
            </div>

            <?php if (!empty($dashboard_progress_data) && !empty($classes_list) && !empty($terms_list) && $current_academic_year_id): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Data Progress for Academic Year <?php echo htmlspecialchars($current_year_name); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Class</th> <!-- Changed back -->
                                    <?php foreach ($terms_list as $term): ?>
                                        <th>Term <?php echo htmlspecialchars($term['term_name']); ?></th> <!-- Prepended "Term " -->
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes_list as $class_item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class_item['class_name']); ?></strong></td>
                                        <?php foreach ($terms_list as $term_item): ?>
                                            <?php
                                                $status_info = $dashboard_progress_data[$class_item['class_name']][$term_item['term_name']] ?? ['status' => 'Pending', 'report_batch_id' => null];
                                                $status_text = $status_info['status'];
                                                $batch_id_for_link = $status_info['report_batch_id'];
                                                $badge_class = 'bg-secondary'; // Default for Pending
                                                $icon = 'fas fa-hourglass-half'; // Default for Pending
                                                $link = '#'; // Default link

                                                if ($status_text === 'Completed') {
                                                    $badge_class = 'bg-success';
                                                    $icon = 'fas fa-check-circle';
                                                    if ($batch_id_for_link) {
                                                        $link = 'view_processed_data.php?batch_id=' . htmlspecialchars($batch_id_for_link);
                                                    }
                                                } elseif ($status_text === 'Data Imported') {
                                                    $badge_class = 'bg-warning text-dark'; // Ensure text is dark on yellow
                                                    $icon = 'fas fa-file-import';
                                                     if ($batch_id_for_link) {
                                                        $link = 'view_processed_data.php?batch_id=' . htmlspecialchars($batch_id_for_link);
                                                    }
                                                } else { // Pending
                                                     // Link to data_entry.php, potentially pre-filling class, term, year would be an enhancement
                                                     // For now, a general link or no link.
                                                     // Let's make it link to data_entry.php for now.
                                                     $link = 'data_entry.php';
                                                }
                                            ?>
                                            <td>
                                                <?php if ($link !== '#'): ?>
                                                    <a href="<?php echo $link; ?>" class="text-decoration-none">
                                                        <span class="badge <?php echo $badge_class; ?> p-2">
                                                            <i class="<?php echo $icon; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($status_text); ?>
                                                        </span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge <?php echo $badge_class; ?> p-2">
                                                        <i class="<?php echo $icon; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($status_text); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif (!$current_academic_year_id && isset($_SESSION['user_id'])): // Show message if year not found but user logged in ?>
                <!-- Message about academic year not found is already handled by session message display at top of page -->
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sidebarCollapse = document.getElementById('sidebarCollapse');
            var sidebar = document.getElementById('sidebar');
            var content = document.getElementById('content');

            if(sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
