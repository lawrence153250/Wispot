<?php
// Start the session
session_start();

// Set session timeout to 15 minutes (900 seconds)
$inactive = 900; 

// Check if timeout variable is set
if (isset($_SESSION['timeout'])) {
    // Calculate the session's lifetime
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        // Logout and redirect to login page
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}

// Update timeout with current time
$_SESSION['timeout'] = time();

// Check if user is logged in and is admin
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// 1. Get total visitors per month
$visitors_query = "SELECT 
                    DATE_FORMAT(visit_date, '%Y-%m') AS month, 
                    SUM(visit_count) AS total_visits
                   FROM visitor_counts
                   GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
                   ORDER BY month DESC
                   LIMIT 12";
$visitors_result = mysqli_query($conn, $visitors_query);

// 2. Get sentiment analysis scores from feedback
$feedback_query = "SELECT 
                    AVG(overall_rating) AS avg_rating,
                    sentiment,
                    COUNT(*) AS total_feedbacks
                   FROM feedback
                   GROUP BY sentiment";
$feedback_result = mysqli_query($conn, $feedback_query);

// 3. Get most common packages booked (updated with correct column name)
$packages_query = "SELECT 
                    p.packageName, 
                    COUNT(b.bookingId) AS booking_count
                   FROM booking b
                   JOIN package p ON b.packageId = p.packageId
                   WHERE b.bookingStatus = 'Completed'
                   GROUP BY b.packageId
                   ORDER BY booking_count DESC
                   LIMIT 5";
$packages_result = mysqli_query($conn, $packages_query);

// 4. Get weekly bookings and revenue (updated query)
$weekly_data_query = "SELECT 
                        YEARWEEK(dateOfBooking) AS week,
                        COUNT(*) AS booking_count,
                        SUM(price) AS total_revenue
                      FROM booking
                      WHERE bookingStatus = 'Completed'
                      GROUP BY YEARWEEK(dateOfBooking)
                      ORDER BY week DESC
                      LIMIT 8";
$weekly_data_result = mysqli_query($conn, $weekly_data_query);

// Prepare data for chart
$weeks = [];
$booking_counts = [];
$revenues = [];

while ($row = mysqli_fetch_assoc($weekly_data_result)) {
    $weeks[] = date('M d', strtotime(substr($row['week'], 0, 4) . 'W' . substr($row['week'], 4, 2) . '1'));
    $booking_counts[] = $row['booking_count'];
    $revenues[] = $row['total_revenue'];
}

// Prepare data for chart
$weeks = array_reverse($weeks);
$booking_counts = array_reverse($booking_counts);
$revenues = array_reverse($revenues);
$sentiment_counts = [];
$total_feedbacks = 0;
$sum_ratings = 0;



while ($row = mysqli_fetch_assoc($feedback_result)) {
    $sentiment = $row['sentiment'] ?: 'Not Analyzed';
    $sentiment_counts[$sentiment] = $row['total_feedbacks'];
    $total_feedbacks += $row['total_feedbacks'];
    $sum_ratings += $row['avg_rating'] * $row['total_feedbacks'];
}

$average_rating = $total_feedbacks > 0 ? $sum_ratings / $total_feedbacks : 0;

// Reverse to show oldest first
$weeks = array_reverse($weeks);
$booking_counts = array_reverse($booking_counts);
$revenues = array_reverse($revenues);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Sidebar Styles */

        .sidebar .sidebar-menu li a.nav-link {
    color: #FFFFFF !important;
}
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            height: 100vh;
            padding: 20px 0;
            position: fixed;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            font-size: 2vh;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu li:hover {
            background-color: #34495e;
        }
        
        .sidebar-menu li a.nav-link {
            color: #FFFFFF;
        }

        .sidebar-menu li.active {
            background-color: #34485f;
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        /* Card Styles */
        .report-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .report-card h3 {
            color: #3498db;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        /* Chart Container */
        .chart-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, 
        .data-table td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .sentiment-score {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h1, 
            .sidebar-menu li span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
        }
    </style>
</head>
<body>
<!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="staff_dashboard.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a class="nav-link" href="staff_dashboard.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
            <li><a class="nav-link" href="staff_accounts.php">ACCOUNTS</a></li>
            <li><a class="nav-link" href="staff_packages.php">PACKAGES</a></li>
            <li><a class="nav-link" href="staff_vouchers.php">VOUCHERS</a></li>
            <li><a class="nav-link" href="staff_inventory.php">INVENTORY</a></li>
            <li class="active"><a class="nav-link" href="staff_reports.php">REPORTS</a></li>
            <li><a class="nav-link" href="staff_feedbacks.php">FEEDBACKS</a></li>
            <li><a class="nav-link" href="staff_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="staff_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>REPORTS</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- 1. Website Visitors Report -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Total Visits</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($visitors_result)): ?>
                <tr>
                    <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                    <td><?php echo $row['total_visits'] ?? 0; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- 2. Sentiment Analysis Report -->
        <div class="report-card">
            <h3>Customer Feedback Analysis</h3>
            
            <!-- Overall Rating Section -->
            <div class="mb-4">
                <h4>Overall Rating</h4>
                <div class="d-flex align-items-center">
                    <div class="display-4 me-3" style="color: #3498db;">
                        <?php echo number_format($average_rating, 1); ?>/5.0
                    </div>
                    <div>
                        <div class="star-rating" style="color: #f1c40f; font-size: 24px;">
                            <?php
                            $full_stars = floor($average_rating);
                            $half_star = ($average_rating - $full_stars) >= 0.5;
                            
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $full_stars) {
                                    echo '<i class="bi bi-star-fill"></i>';
                                } elseif ($i == $full_stars + 1 && $half_star) {
                                    echo '<i class="bi bi-star-half"></i>';
                                } else {
                                    echo '<i class="bi bi-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <small class="text-muted">Based on <?php echo $total_feedbacks; ?> feedback submissions</small>
                    </div>
                </div>
        </div>

        <!-- Sentiment Distribution Section -->
        <div>
            <h4>Sentiment Analysis</h4>
            <div class="row">
                <?php
                $sentiment_colors = [
                    'Positive' => 'success',
                    'Neutral' => 'warning',
                    'Negative' => 'danger',
                    'Not Analyzed' => 'secondary'
                ];
                
                foreach ($sentiment_counts as $sentiment => $count) {
                    $percentage = $total_feedbacks > 0 ? ($count / $total_feedbacks) * 100 : 0;
                    $color = $sentiment_colors[$sentiment] ?? 'info';
                    ?>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?php echo $sentiment; ?></h5>
                                <div class="display-5 text-<?php echo $color; ?>">
                                    <?php echo $count; ?>
                                </div>
                                <div class="progress mt-2" style="height: 10px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" 
                                        style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        
        <!-- 3. Popular Packages Report -->
        <div class="report-card">
            <h3>Most Popular Packages (Completed Bookings)</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Number of Bookings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($packages_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['packageName']); ?></td>
                            <td><?php echo $row['booking_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 4. Weekly Bookings and Revenue Chart -->
        <div class="chart-container">
            <h3>Weekly Bookings and Revenue</h3>
            <canvas id="weeklyChart"></canvas>
        </div>
    </div>

    <script>
        // Weekly Bookings and Revenue Chart
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weeks); ?>,
                datasets: [
                    {
                        label: 'Number of Completed Bookings',
                        data: <?php echo json_encode($booking_counts); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1,
                        type: 'bar'
                    },
                    {
                        label: 'Total Revenue from Completed Bookings',
                        data: <?php echo json_encode($revenues); ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Bookings'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Revenue'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>