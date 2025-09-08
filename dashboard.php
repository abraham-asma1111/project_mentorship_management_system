 <?php
// dashboard.php - User dashboard with role-based features and summaries
require_once 'config.php'; // Includes database connection ($pdo) and session_start()
require_once 'rate_limiter.php'; // Ensure this is included for the rate limiter functions

// --- Access Control: Only logged-in users can access this page ---
if (!isset($_SESSION['user_id'])) {
    redirect('auth_portal.php'); // Redirect to login if not logged in
}

$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role_name'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];

$dashboard_message = ''; // For general dashboard messages

// Check for and display messages from other pages (e.g., project_details.php redirect)
if (isset($_SESSION['project_details_message'])) {
    $dashboard_message = '<div class="message success">' . $_SESSION['project_details_message'] . '</div>';
    unset($_SESSION['project_details_message']);
}
if (isset($_SESSION['error_message'])) {
    $dashboard_message = '<div class="message error">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

// --- Dashboard Summary Data Fetching ---
$summary_data = [];
try {
    if ($role_name === 'Mentee') {
        // Fetch mentee_id first
        $stmt_mentee = $pdo->prepare("SELECT mentee_id FROM mentees WHERE user_id = ?");
        $stmt_mentee->execute([$user_id]);
        $mentee_data = $stmt_mentee->fetch(PDO::FETCH_ASSOC);

        if ($mentee_data) {
            $mentee_id = $mentee_data['mentee_id'];

            // Total projects submitted
            $stmt_total_projects = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_owner_mentee_id = ?");
            $stmt_total_projects->execute([$mentee_id]);
            $summary_data['total_projects'] = $stmt_total_projects->fetchColumn();

            // Projects by status
            $stmt_project_status = $pdo->prepare("SELECT status, COUNT(*) AS count FROM projects WHERE project_owner_mentee_id = ? GROUP BY status");
            $stmt_project_status->execute([$mentee_id]);
            $summary_data['project_status_counts'] = $stmt_project_status->fetchAll(PDO::FETCH_KEY_PAIR);

            // Personal task counts (tasks assigned to this mentee)
            $stmt_tasks = $pdo->prepare("SELECT status, COUNT(*) AS count FROM tasks WHERE assigned_to_user_id = ? GROUP BY status");
            $stmt_tasks->execute([$user_id]);
            $summary_data['personal_task_counts'] = $stmt_tasks->fetchAll(PDO::FETCH_KEY_PAIR);

            // Fetch a list of all projects for the mentee, including assigned mentors and task progress
            $stmt_mentee_projects = $pdo->prepare("
                SELECT
                    p.project_id, p.project_name, p.status, p.created_at,
                    (SELECT COUNT(*) FROM tasks WHERE project_id = p.project_id) AS total_tasks,
                    (SELECT COUNT(*) FROM tasks WHERE project_id = p.project_id AND status = 'Completed') AS completed_tasks,
                    GROUP_CONCAT(DISTINCT CONCAT(mu.first_name, ' ', mu.last_name, ' (', mu.username, ')') SEPARATOR ', ') AS mentors_assigned
                FROM projects p
                LEFT JOIN mentorship_assignments ma ON p.project_id = ma.project_id
                LEFT JOIN users mu ON ma.mentor_user_id = mu.user_id
                WHERE p.project_owner_mentee_id = ?
                GROUP BY p.project_id
                ORDER BY p.created_at DESC
            ");
            $stmt_mentee_projects->execute([$mentee_id]);
            $summary_data['mentee_projects'] = $stmt_mentee_projects->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $dashboard_message = '<div class="message error">Your mentee profile is not fully set up. Please visit your <a href="profile.php">profile page</a> and save your details.</div>';
        }

    } elseif ($role_name === 'Mentor') {
        // Total projects assigned
        $stmt_assigned_projects = $pdo->prepare("SELECT COUNT(DISTINCT project_id) FROM mentorship_assignments WHERE mentor_user_id = ?");
        $stmt_assigned_projects->execute([$user_id]);
        $summary_data['total_assigned_projects'] = $stmt_assigned_projects->fetchColumn();

        // Tasks across assigned projects
        $stmt_mentor_tasks = $pdo->prepare("
            SELECT t.status, COUNT(t.task_id) AS count
            FROM tasks t
            JOIN mentorship_assignments ma ON t.project_id = ma.project_id
            WHERE ma.mentor_user_id = ?
            GROUP BY t.status
        ");
        $stmt_mentor_tasks->execute([$user_id]);
        $summary_data['assigned_project_task_counts'] = $stmt_mentor_tasks->fetchAll(PDO::FETCH_KEY_PAIR);

        // List of active projects with mentee info
        $stmt_active_projects = $pdo->prepare("
            SELECT
                p.project_id, p.project_name, p.status,
                u.first_name AS mentee_first_name, u.last_name AS mentee_last_name,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.project_id) AS total_tasks,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.project_id AND status = 'Completed') AS completed_tasks
            FROM projects p
            JOIN mentorship_assignments ma ON p.project_id = ma.project_id
            JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
            JOIN users u ON m.user_id = u.user_id
            WHERE ma.mentor_user_id = ? AND p.status IN ('Approved', 'In Progress')
            ORDER BY p.status DESC, p.project_name ASC
        ");
        $stmt_active_projects->execute([$user_id]);
        $summary_data['active_projects'] = $stmt_active_projects->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($role_name === 'Administrator') {
        // Total users
        $stmt_total_users = $pdo->query("SELECT COUNT(*) FROM users");
        $summary_data['total_users'] = $stmt_total_users->fetchColumn();

        // Total projects
        $stmt_total_projects_admin = $pdo->query("SELECT COUNT(*) FROM projects");
        $summary_data['total_projects_admin'] = $stmt_total_projects_admin->fetchColumn();

        // Projects by status (overall)
        $stmt_project_status_admin = $pdo->query("SELECT status, COUNT(*) AS count FROM projects GROUP BY status");
        $summary_data['project_status_counts_admin'] = $stmt_project_status_admin->fetchAll(PDO::FETCH_KEY_PAIR);

        // Pending projects for approval
        $stmt_pending_projects = $pdo->query("
            SELECT
                p.project_id, p.project_name, p.created_at,
                u.first_name, u.last_name, u.username
            FROM projects p
            JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
            JOIN users u ON m.user_id = u.user_id
            WHERE p.status = 'Proposed'
            ORDER BY p.created_at ASC
            LIMIT 5
        ");
        $summary_data['pending_projects'] = $stmt_pending_projects->fetchAll(PDO::FETCH_ASSOC);

        // Recently added users (e.g., last 5)
        $stmt_recent_users = $pdo->query("
            SELECT u.first_name, u.last_name, u.username, r.role_name, u.created_at
            FROM users u JOIN roles r ON u.role_id = r.role_id
            ORDER BY u.created_at DESC LIMIT 5
        ");
        $summary_data['recent_users'] = $stmt_recent_users->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role_name === 'Coordinator') {
        // Total projects
        $stmt_total_projects_coord = $pdo->query("SELECT COUNT(*) FROM projects");
        $summary_data['total_projects_coord'] = $stmt_total_projects_coord->fetchColumn();

        // Projects by status (overall)
        $stmt_project_status_coord = $pdo->query("SELECT status, COUNT(*) AS count FROM projects GROUP BY status");
        $summary_data['project_status_counts_coord'] = $stmt_project_status_coord->fetchAll(PDO::FETCH_KEY_PAIR);

        // Recently submitted projects (e.g., last 5)
        $stmt_recent_projects = $pdo->query("
            SELECT p.project_name, p.status, p.created_at, u.first_name, u.last_name, u.username
            FROM projects p
            JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
            JOIN users u ON m.user_id = u.user_id
            ORDER BY p.created_at DESC LIMIT 5
        ");
        $summary_data['recent_projects_coord'] = $stmt_recent_projects->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("PMMS Dashboard Summary Error: " . $e->getMessage());
    $dashboard_message = '<div class="message error">Error fetching dashboard summary.</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; margin: 0; padding: 0; color: #333; }
        .header { background-color: #2c3e50; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { margin: 0; font-size: 24px; }
        .header .user-info { font-size: 16px; }
        .header .user-info span { font-weight: bold; color: #f1c40f; }
        .nav-menu { list-style: none; margin: 0; padding: 0; background-color: #34495e; overflow: hidden; }
        .nav-menu li { float: left; }
        .nav-menu li a { display: block; color: white; text-align: center; padding: 14px 16px; text-decoration: none; transition: background-color 0.3s; }
        .nav-menu li a:hover, .nav-menu li a.active { background-color: #2980b9; }
        .nav-menu .logout { float: right; }
        .container { padding: 20px; max-width: 1200px; margin: 20px auto; background-color: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .welcome-message { font-size: 24px; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .summary-card {
            background-color: #f9f9f9;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .summary-card h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #bdc3c7;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .summary-card p {
            font-size: 1.1em;
            margin: 5px 0;
            color: #555;
        }
        .summary-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .summary-card li {
            padding: 8px 0;
            border-bottom: 1px dashed #e0e0e0;
        }
        .summary-card li:last-child {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
            margin-left: 5px;
        }
        .progress-bar-container {
            background-color: #e0e0e0;
            border-radius: 5px;
            margin-top: 10px;
        }
        .progress-bar {
            height: 10px;
            border-radius: 5px;
            background-color: #2ecc71;
            transition: width 0.5s ease-in-out;
        }
        /* Project Status Colors */
        .status-proposed { background-color: #ffc107; color: #333; }
        .status-approved { background-color: #28a745; }
        .status-in-progress { background-color: #007bff; }
        .status-completed { background-color: #6c757d; }
        .status-rejected { background-color: #dc3545; }

        /* Task Status Colors (ensure these match project_details.php) */
        .task-status-Pending { background-color: #ffc107; color: #333;}
        .task-status-In-Progress { background-color: #007bff; }
        .task-status-Completed { background-color: #28a745; }
        .task-status-On-Hold { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMMS Dashboard</h1>
        <div class="user-info">
            Welcome, <span><?php echo htmlspecialchars($first_name); ?></span>! (Role: <span><?php echo htmlspecialchars($role_name); ?></span>)
        </div>
    </div>
    <ul class="nav-menu">
        <li><a href="dashboard.php" class="active">Dashboard</a></li>
        <li><a href="profile.php">My Profile</a></li>
        <?php if ($role_name === 'Administrator' || $role_name === 'Coordinator') : ?>
            <li><a href="projects_overview.php">Projects Overview</a></li>
        <?php endif; ?>
        <?php if ($role_name === 'Administrator') : ?>
            <li><a href="admin_users.php">Manage Users</a></li>
            <li><a href="admin_log_viewer.php">View Security Logs</a></li>
            <li><a href="admin_dashboard.php">Manage Locks</a></li>
        <?php endif; ?>
        <?php if ($role_name === 'Mentor') : ?>
            <li><a href="my_mentees.php">My Mentees</a></li>
            <li><a href="my_projects.php">Mentored Projects</a></li>
        <?php endif; ?>
        <?php if ($role_name === 'Mentee') : ?>
            <li><a href="my_projects.php">My Projects</a></li>
            <li><a href="submit_proposal.php">Submit New Project Proposal</a></li>
        <?php endif; ?>
        <li class="logout"><a href="logout.php">Logout</a></li>
    </ul>

    <div class="container">
        <h2 class="welcome-message">Hello, <?php echo htmlspecialchars($first_name); ?>!</h2>
        <?php echo $dashboard_message; // Display any general dashboard messages ?>

        <div class="dashboard-grid">
            <?php if ($role_name === 'Mentee'): ?>
                <div class="summary-card">
                    <h3>My Projects Summary</h3>
                    <p>Total Projects Submitted: <strong><?php echo htmlspecialchars($summary_data['total_projects'] ?? 0); ?></strong></p>
                    <ul>
                        <?php foreach(['Proposed', 'Approved', 'In Progress', 'Completed', 'Rejected'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['project_status_counts'][$status] ?? 0); ?></strong>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>My Tasks Summary</h3>
                    <p>Tasks Assigned To Me:</p>
                    <ul>
                        <?php foreach(['Pending', 'In Progress', 'Completed', 'On Hold'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['personal_task_counts'][$status] ?? 0); ?></strong>
                                <span class="status-badge task-status-<?php echo str_replace(' ', '-', $status); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>My Projects & Mentors</h3>
                    <?php if (!empty($summary_data['mentee_projects'])): ?>
                        <ul>
                            <?php foreach ($summary_data['mentee_projects'] as $project): ?>
                                <li>
                                    <strong><a href="project_details.php?project_id=<?php echo htmlspecialchars($project['project_id']); ?>"><?php echo htmlspecialchars($project['project_name']); ?></a></strong>
                                    <br><small>Status: <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>"><?php echo htmlspecialchars($project['status']); ?></span></small>
                                    <br><small>Mentors: <?php echo htmlspecialchars($project['mentors_assigned'] ?: 'None assigned'); ?></small>
                                    <?php if ($project['total_tasks'] > 0): ?>
                                        <?php $progress_percent = round(($project['completed_tasks'] / $project['total_tasks']) * 100); ?>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%;"></div>
                                        </div>
                                        <small><?php echo $progress_percent; ?>% tasks completed</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>You have not submitted any projects yet. <a href="submit_proposal.php">Submit a proposal</a> to get started!</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($role_name === 'Mentor'): ?>
                <div class="summary-card">
                    <h3>My Mentored Projects</h3>
                    <p>Total Projects Assigned: <strong><?php echo htmlspecialchars($summary_data['total_assigned_projects'] ?? 0); ?></strong></p>
                    <p>Tasks in Assigned Projects:</p>
                    <ul>
                        <?php foreach(['Pending', 'In Progress', 'Completed', 'On Hold'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['assigned_project_task_counts'][$status] ?? 0); ?></strong>
                                <span class="status-badge task-status-<?php echo str_replace(' ', '-', $status); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>Active Mentored Projects</h3>
                    <?php if (!empty($summary_data['active_projects'])): ?>
                        <ul>
                            <?php foreach ($summary_data['active_projects'] as $project): ?>
                                <li>
                                    <strong><a href="project_details.php?project_id=<?php echo htmlspecialchars($project['project_id']); ?>"><?php echo htmlspecialchars($project['project_name']); ?></a></strong>
                                    <br><small>Mentee: <?php echo htmlspecialchars($project['mentee_first_name'] . ' ' . $project['mentee_last_name']); ?></small>
                                    <?php if ($project['total_tasks'] > 0): ?>
                                        <?php $progress_percent = round(($project['completed_tasks'] / $project['total_tasks']) * 100); ?>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%;"></div>
                                        </div>
                                        <small><?php echo $progress_percent; ?>% tasks completed</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No active projects currently assigned to you.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($role_name === 'Administrator'): ?>
                <div class="summary-card">
                    <h3>System Overview</h3>
                    <p>Total Users: <strong><?php echo htmlspecialchars($summary_data['total_users'] ?? 0); ?></strong></p>
                    <p>Total Projects: <strong><?php echo htmlspecialchars($summary_data['total_projects_admin'] ?? 0); ?></strong></p>
                    <p>Projects by Status:</p>
                    <ul>
                        <?php foreach(['Proposed', 'Approved', 'In Progress', 'Completed', 'Rejected'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['project_status_counts_admin'][$status] ?? 0); ?></strong>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>Projects Pending Approval</h3>
                    <?php if (!empty($summary_data['pending_projects'])): ?>
                        <ul>
                            <?php foreach ($summary_data['pending_projects'] as $project): ?>
                                <li>
                                    <strong><a href="project_details.php?project_id=<?php echo htmlspecialchars($project['project_id']); ?>"><?php echo htmlspecialchars($project['project_name']); ?></a></strong>
                                    <br><small>Submitted by <?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></small>
                                    <br><small>On <?php echo date('Y-m-d', strtotime($project['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No projects are currently pending approval.</p>
                    <?php endif; ?>
                </div>
                <div class="summary-card">
                    <h3>Recent Users</h3>
                    <?php if (!empty($summary_data['recent_users'])): ?>
                        <ul>
                            <?php foreach ($summary_data['recent_users'] as $user): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong> (<?php echo htmlspecialchars($user['role_name']); ?>)
                                    <br><small>Joined: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No recent user registrations.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($role_name === 'Coordinator'): ?>
                <div class="summary-card">
                    <h3>Project Overview</h3>
                    <p>Total Projects: <strong><?php echo htmlspecialchars($summary_data['total_projects_coord'] ?? 0); ?></strong></p>
                    <p>Projects by Status:</p>
                    <ul>
                        <?php foreach(['Proposed', 'Approved', 'In Progress', 'Completed', 'Rejected'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['project_status_counts_coord'][$status] ?? 0); ?></strong>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>Recent Projects</h3>
                    <?php if (!empty($summary_data['recent_projects_coord'])): ?>
                        <ul>
                            <?php foreach ($summary_data['recent_projects_coord'] as $project): ?>
                                <li>
                                    <strong><a href="project_details.php?project_id=<?php echo htmlspecialchars($project['project_id']); ?>"><?php echo htmlspecialchars($project['project_name']); ?></a></strong> (by <?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?>)
                                    <br><small>Status: <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>"><?php echo htmlspecialchars($project['status']); ?></span> - <?php echo date('Y-m-d', strtotime($project['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No recent projects.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
