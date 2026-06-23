<?php
session_start();

// Database connection parameters
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Replace with your database username
define('DB_PASSWORD', '');    // Replace with your database password
define('DB_NAME', 'customer_feedback_system');

// Admin login password (for demonstration purposes, use a strong hash in production)
define('ADMIN_PASSWORD', 'admin123'); // Change this to a strong password!

// Initialize variables
$error = '';
$current_section = $_GET['section'] ?? 'dashboard';
$selected_category = $_GET['category'] ?? 'All';
$search_term = $_GET['search'] ?? '';
$product_error_message = '';
$product_add_success_message = '';
$product_add_error_message = '';
$user_error_message = '';
$report_month = $_GET['report_month'] ?? date('Y-m'); // Default to current month for report

// Establish database connection
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // REMOVED THE LINE THAT CAUSED THE ERROR: $pdo->setAttribute(PDO_ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Handle AJAX request for customer feedback data FIRST
if (isset($_GET['view_customer_feedback']) && $current_section === 'feedback') {
    $customer_id_to_view = $_GET['view_customer_feedback'];
    $customer_feedback_data = get_customer_all_feedback($pdo, $customer_id_to_view);
    header('Content-Type: application/json');
    echo json_encode($customer_feedback_data);
    exit; // Stop execution to only output JSON
}


// Admin login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) { // In a real application, hash and verify passwords securely
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Incorrect password.';
        }
    }
}

// If not logged in, display login form and exit
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <form method="post" class="container login-form">
       <h1 style="color:white; text-shadow: 2px 2px 4px rgb(0, 0, 0); font-size: 2.5em;">Submit Product</h1>

            <?php if (!empty($error)): ?>
                <div class="errors"><?=htmlspecialchars($error)?></div>
            <?php endif; ?>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autofocus>
            <button type="submit">Login</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Logout logic
if ($current_section === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// --- Functions to interact with the database ---

function get_total_count($pdo, $table_name) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM " . $table_name);
    return $stmt->fetchColumn();
}

function get_average_rating($pdo) {
    $stmt = $pdo->query("SELECT AVG(rating) FROM feedback");
    $avg = $stmt->fetchColumn();
    return round($avg, 1);
}

function get_most_rated_flavor($pdo) {
    $stmt = $pdo->query("SELECT f.flavor_name, COUNT(fb.flavor_id) AS rating_count
                            FROM feedback fb
                            JOIN flavors f ON fb.flavor_id = f.flavor_id
                            GROUP BY f.flavor_name
                            ORDER BY rating_count DESC
                            LIMIT 1");
    $result = $stmt->fetch();
    return $result ? $result['flavor_name'] : 'N/A';
}

function get_rating_distribution($pdo) {
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $stmt = $pdo->query("SELECT rating, COUNT(*) as count FROM feedback GROUP BY rating");
    while ($row = $stmt->fetch()) {
        $distribution[$row['rating']] = $row['count'];
    }
    return $distribution;
}

function get_dynamic_categories($pdo, $type) {
    if ($type === 'feedback') {
        $stmt = $pdo->query("SELECT DISTINCT category FROM flavors ORDER BY category");
    } elseif ($type === 'products') {
        $stmt = $pdo->query("SELECT DISTINCT category FROM flavors ORDER BY category");
    } else {
        return [];
    }
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


function get_feedbacks($pdo, $category, $search_term) {
    // Group feedback by customer to show all feedback for a customer in one view
    $sql = "SELECT c.customer_id, c.fullname, c.age, c.gender, c.telephone
            FROM customers c
            JOIN feedback fb ON c.customer_id = fb.customer_id"; // Only join to filter customers who have feedback
    $conditions = [];
    $params = [];

    if ($category !== 'All') {
        $conditions[] = "f.category = ?";
        $params[] = $category;
        // Need to add JOIN for flavors if category is filtered
        $sql .= " JOIN flavors f ON fb.flavor_id = f.flavor_id";
    }
    if (!empty($search_term)) {
        $conditions[] = "(c.fullname LIKE ? OR c.telephone LIKE ?)";
        $params[] = '%' . $search_term . '%';
        $params[] = '%' . $search_term . '%';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " GROUP BY c.customer_id, c.fullname, c.age, c.gender, c.telephone ORDER BY MAX(fb.created_at) DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// New function to get all feedback for a specific customer
function get_customer_all_feedback($pdo, $customer_id) {
    $sql = "SELECT fb.feedback_id, f.flavor_name, fb.rating, fb.comment, f.image_path, fb.created_at
            FROM feedback fb
            JOIN flavors f ON fb.flavor_id = f.flavor_id
            WHERE fb.customer_id = ?
            ORDER BY fb.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function get_users($pdo) {
    // Removed customer_image_path from SELECT
    $stmt = $pdo->query("SELECT customer_id, fullname, age, gender, telephone FROM customers ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function get_all_flavors($pdo, $category, $search_term) {
    $sql = "SELECT flavor_id AS id, category, flavor_name, image_path FROM flavors";
    $conditions = [];
    $params = [];

    if ($category !== 'All') {
        $conditions[] = "category = ?";
        $params[] = $category;
    }
    if (!empty($search_term)) {
        $conditions[] = "(flavor_name LIKE ? OR category LIKE ?)";
        $params[] = '%' . $search_term . '%';
        $params[] = '%' . $search_term . '%';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY category, flavor_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_suggestions($pdo) {
    $stmt = $pdo->query("SELECT s.suggestion_id AS id, c.fullname, s.suggestion_text, s.created_at
                            FROM suggestions s
                            LEFT JOIN customers c ON s.customer_id = c.customer_id
                            ORDER BY s.created_at DESC");
    return $stmt->fetchAll();
}

// Function to get monthly feedback data for the report
function get_monthly_feedback_report($pdo, $year_month) {
    $start_date = $year_month . '-01 00:00:00';
    $end_date = date('Y-m-t 23:59:59', strtotime($year_month));

    // Total feedback for the month
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE created_at BETWEEN ? AND ?");
    $stmt_total->execute([$start_date, $end_date]);
    $total_feedback_month = $stmt_total->fetchColumn();

    // Number of ratings per star level for the month
    $rating_distribution_month = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $stmt_distribution = $pdo->prepare("SELECT rating, COUNT(*) as count FROM feedback WHERE created_at BETWEEN ? AND ? GROUP BY rating");
    $stmt_distribution->execute([$start_date, $end_date]);
    while ($row = $stmt_distribution->fetch()) {
        $rating_distribution_month[$row['rating']] = $row['count'];
    }

    // All feedback comments listed with dates, customer names, and flavor names for the month
    $stmt_comments = $pdo->prepare("SELECT fb.comment, fb.created_at, c.fullname, f.flavor_name
                                    FROM feedback fb
                                    LEFT JOIN customers c ON fb.customer_id = c.customer_id
                                    JOIN flavors f ON fb.flavor_id = f.flavor_id
                                    WHERE fb.comment IS NOT NULL AND fb.comment != '' AND fb.created_at BETWEEN ? AND ?
                                    ORDER BY fb.created_at DESC");
    $stmt_comments->execute([$start_date, $end_date]);
    $comments_month = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);

    // Most rated flavor for the month
    $stmt_most_rated_flavor_month = $pdo->prepare("SELECT f.flavor_name, COUNT(fb.flavor_id) AS rating_count
                                                    FROM feedback fb
                                                    JOIN flavors f ON fb.flavor_id = f.flavor_id
                                                    WHERE fb.created_at BETWEEN ? AND ?
                                                    GROUP BY f.flavor_name
                                                    ORDER BY rating_count DESC
                                                    LIMIT 1");
    $stmt_most_rated_flavor_month->execute([$start_date, $end_date]);
    $most_rated_flavor_month_result = $stmt_most_rated_flavor_month->fetch();
    $most_rated_flavor_month = $most_rated_flavor_month_result ? $most_rated_flavor_month_result['flavor_name'] : 'N/A';


    return [
        'total_feedback' => $total_feedback_month,
        'rating_distribution' => $rating_distribution_month,
        'comments' => $comments_month,
        'most_rated_flavor_month' => $most_rated_flavor_month // Add this to the return array
    ];
}


// Handle POST requests for deletion and adding products
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_feedback_id'])) { // Deletes a specific feedback entry
        $id = $_POST['delete_feedback_id'];
        $stmt = $pdo->prepare("DELETE FROM feedback WHERE feedback_id = ?");
        $stmt->execute([$id]);
        // No redirect here, handled by JS if successful in modal
        // header('Location: admin.php?section=feedback&category=' . urlencode($selected_category) . '&search=' . urlencode($search_term));
        // exit;
    } elseif (isset($_POST['delete_customer_feedback_id'])) { // Deletes ALL feedback for a customer
        $customer_id = $_POST['delete_customer_feedback_id'];
        $stmt = $pdo->prepare("DELETE FROM feedback WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        header('Location: admin.php?section=feedback&category=' . urlencode($selected_category) . '&search=' . urlencode($search_term));
        exit;
    } elseif (isset($_POST['delete_product_id'])) {
        $id = $_POST['delete_product_id'];
        try {
            // First, get the image path to delete the file
            $stmt_get_image = $pdo->prepare("SELECT image_path FROM flavors WHERE flavor_id = ?");
            $stmt_get_image->execute([$id]);
            $image_path_to_delete = $stmt_get_image->fetchColumn();

            // Then, delete the product from the database
            $stmt = $pdo->prepare("DELETE FROM flavors WHERE flavor_id = ?");
            $stmt->execute([$id]);

            // If deletion successful, and an image path exists, delete the image file
            if ($stmt->rowCount() > 0 && !empty($image_path_to_delete) && file_exists($image_path_to_delete)) {
                unlink($image_path_to_delete);
            }
            header('Location: admin.php?section=products&message=Product deleted');
            exit;
        } catch (PDOException $e) {
            $product_error_message = "Error deleting product: " . $e->getMessage();
            $current_section = 'products';
        }
    } elseif (isset($_POST['delete_suggestion_id'])) {
        $id = $_POST['delete_suggestion_id'];
        $stmt = $pdo->prepare("DELETE FROM suggestions WHERE suggestion_id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?section=suggestions');
        exit;
    } elseif (isset($_POST['delete_user_id'])) { // User deletion logic
        $id = $_POST['delete_user_id'];
        try {
            // Removed code to get and delete customer image path
            // $stmt_get_customer_image = $pdo->prepare("SELECT customer_image_path FROM customers WHERE customer_id = ?");
            // $stmt_get_customer_image->execute([$id]);
            // $customer_image_path_to_delete = $stmt_get_customer_image->fetchColumn();

            // First, set customer_id to NULL in related feedback entries
            $stmt_feedback = $pdo->prepare("UPDATE feedback SET customer_id = NULL WHERE customer_id = ?");
            $stmt_feedback->execute([$id]);

            // Then, set customer_id to NULL in related suggestions
            $stmt_suggestion = $pdo->prepare("UPDATE suggestions SET customer_id = NULL WHERE customer_id = ?");
            $stmt_suggestion->execute([$id]);

            // Finally, delete the user
            $stmt_user = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt_user->execute([$id]);

            // Removed code to delete customer image file
            // if ($stmt_user->rowCount() > 0 && !empty($customer_image_path_to_delete) && file_exists($customer_image_path_to_delete)) {
            //     unlink($customer_image_path_to_delete);
            // }

            header('Location: admin.php?section=users&message=User deleted');
            exit;
        } catch (PDOException $e) {
            $user_error_message = "Error deleting user: " . $e->getMessage();
            $current_section = 'users'; // Stay on users section to show error
        }
    } elseif (isset($_POST['add_category']) && isset($_POST['add_flavor_name'])) {
        $category = trim($_POST['add_category']);
        $flavor_name = trim($_POST['add_flavor_name']);
        $image_path = null; // Initialize image path to null

        if (!empty($category) && !empty($flavor_name)) {
            // Handle image upload
            if (isset($_FILES['add_image']) && $_FILES['add_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['add_image']['tmp_name'];
                $file_name = $_FILES['add_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($file_ext, $allowed_extensions)) {
                    $new_file_name = uniqid('flavor_') . '.' . $file_ext;
                    $upload_dir = 'uploads/';
                    $destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp_name, $destination)) {
                        $image_path = $destination;
                    } else {
                        $product_add_error_message = "Error uploading image.";
                    }
                } else {
                    $product_add_error_message = "Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.";
                }
            }

            if (empty($product_add_error_message)) { // Proceed only if no image upload errors
                // Check if flavor already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM flavors WHERE category = ? AND flavor_name = ?");
                $stmt->execute([$category, $flavor_name]);
                if ($stmt->fetchColumn() > 0) {
                    $product_add_error_message = "Error: This flavor already exists in this category.";
                    // If flavor already exists and an image was uploaded, delete the uploaded image
                    if ($image_path && file_exists($image_path)) {
                        unlink($image_path);
                    }
                } else {
                    // Prepare the insert statement for flavor including image_path
                    $stmt = $pdo->prepare("INSERT INTO flavors (category, flavor_name, image_path) VALUES (?, ?, ?)");
                    try {
                        $stmt->execute([$category, $flavor_name, $image_path]);
                        $product_add_success_message = "Product added successfully!";
                        // Clear post data to prevent re-submission on refresh
                        $_POST = [];
                    } catch (PDOException $e) {
                        $product_add_error_message = "Error adding product to database: " . $e->getMessage();
                        // If there's a DB error and an image was uploaded, delete the uploaded image
                        if ($image_path && file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                }
            }
        } else {
            $product_add_error_message = "Category and Flavor Name cannot be empty.";
        }
        $current_section = 'products'; // Always stay on products section to show messages
    }
}


// Fetch data based on current section
$feedbacks = [];
$users = [];
$all_flavors = [];
$suggestions = [];
$total_feedbacks = 0;
$avg_rating = 0;
$most_rated_flavor = 'N/A';
$total_users = 0;
$total_suggestions = 0;
$rating_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$max_rating_count = 0;
$dynamic_categories = [];
$monthly_report_data = [];


switch ($current_section) {
    case 'dashboard':
        $total_feedbacks = get_total_count($pdo, 'feedback');
        $avg_rating = get_average_rating($pdo);
        $most_rated_flavor = get_most_rated_flavor($pdo);
        $total_users = get_total_count($pdo, 'customers');
        $total_suggestions = get_total_count($pdo, 'suggestions');
        $rating_distribution = get_rating_distribution($pdo);
        $max_rating_count = max($rating_distribution);
        $monthly_report_data = get_monthly_feedback_report($pdo, $report_month); // Fetch report data
        break;
    case 'feedback':
        $feedbacks = get_feedbacks($pdo, $selected_category, $search_term);
        $dynamic_categories = get_dynamic_categories($pdo, 'feedback');
        break;
    case 'users':
        $users = get_users($pdo);
        break;
    case 'products':
        $all_flavors = get_all_flavors($pdo, $selected_category, $search_term);
        $dynamic_categories = get_dynamic_categories($pdo, 'products');
        break;
    case 'suggestions':
        $suggestions = get_suggestions($pdo);
        break;
    default:
        // Default to dashboard if an invalid section is requested
        $current_section = 'dashboard';
        $total_feedbacks = get_total_count($pdo, 'feedback');
        $avg_rating = get_average_rating($pdo);
        $most_rated_flavor = get_most_rated_flavor($pdo);
        $total_users = get_total_count($pdo, 'customers');
        $total_suggestions = get_total_count($pdo, 'suggestions');
        $rating_distribution = get_rating_distribution($pdo);
        $max_rating_count = max($rating_distribution);
        $monthly_report_data = get_monthly_feedback_report($pdo, $report_month);
        break;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* General styling for the admin panel */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f4f7f6;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        /* Sidebar styles */
        .sidebar {
            width: 250px;
            background-color: #2c3e50; /* Dark blue-grey */
            color: #ecf0f1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .sidebar .logo {
            font-size: 1.8em;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            color: #ecf0f1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .sidebar .navigation {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1; /* Allows navigation to take available space */
        }

        .sidebar .navigation li {
            margin-bottom: 10px;
        }

        .sidebar .navigation a {
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
            display: flex;
            align-items: center;
        }

        .sidebar .navigation a:hover,
        .sidebar .navigation a.active {
            background-color: #34495e; /* Slightly lighter dark blue-grey */
            color: #ffffff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .sidebar .navigation a.active {
            background-color: #3498db; /* A vibrant blue for active state */
            font-weight: bold;
        }

        .sidebar .logout-section button {
            width: 100%;
            padding: 12px 15px;
            background-color: #e74c3c; /* Red for logout */
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .sidebar .logout-section button:hover {
            background-color: #c0392b; /* Darker red on hover */
        }

        /* Main content styles */
        .content {
            flex-grow: 1;
            padding: 30px;
            background-color: #f4f7f6;
        }

        .content header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .content header h1 {
            font-size: 2em;
            color: #2c3e50;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .search-container {
            display: flex;
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .search-container input[type="text"] {
            border: none;
            padding: 10px 15px;
            outline: none;
            flex-grow: 1;
            font-size: 0.95em;
        }

        .search-container button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.95em;
        }

        .search-container button:hover {
            background-color: #2980b9;
        }

        .filter-tabs {
            margin-bottom: 25px;
            background-color: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-tabs a {
            text-decoration: none;
            color: #555;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
            font-weight: 500;
            white-space: nowrap; /* Prevent wrapping for long category names */
        }

        .filter-tabs a:hover {
            background-color: #e9ecef;
            color: #333;
        }

        .filter-tabs a.active {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Dashboard styles */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .dashboard .card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-bottom: 5px solid #3498db; /* Accent border */
        }

        .dashboard .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .dashboard .card h3 {
            margin-top: 0;
            font-size: 1.2em;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .dashboard .card p {
            font-size: 2.2em;
            font-weight: bold;
            color: #3498db;
            margin: 0;
        }

        .dashboard .card .star {
            color: #f39c12; /* Gold star color */
            font-size: 1.8em;
            vertical-align: middle;
            margin-left: 5px;
        }

        .rating-graph-container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }

        .rating-graph-container h3 {
            margin-top: 0;
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 25px;
            text-align: center;
        }

        .rating-bar-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .rating-label {
            font-weight: bold;
            margin-right: 15px;
            width: 60px; /* Fixed width for labels */
            text-align: right;
            color: #555;
        }

        .rating-bar-background {
            flex-grow: 1;
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .rating-bar {
            height: 100%;
            background-color: #2ecc71; /* Green for good ratings */
            border-radius: 10px;
            transition: width 0.5s ease-out;
        }

        /* Color variations for rating bars */
        .rating-bar.rating-5 { background-color: #2ecc71; } /* Green */
        .rating-bar.rating-4 { background-color: #27ae60; } /* Darker Green */
        .rating-bar.rating-3 { background-color: #f1c40f; } /* Yellow */
        .rating-bar.rating-2 { background-color: #e67e22; } /* Orange */
        .rating-bar.rating-1 { background-color: #e74c3c; } /* Red */

        .rating-count {
            margin-left: 15px;
            font-weight: bold;
            color: #333;
            width: 40px; /* Fixed width for counts */
            text-align: left;
        }

        /* Table styles */
        .data-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .data-table th, .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.95em;
        }

        .data-table th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .data-table tbody tr:hover {
            background-color: #f1f7fe;
        }

        .feedback-details-cell button,
        .suggestion-details-cell button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.9em;
        }

        .feedback-details-cell button:hover,
        .suggestion-details-cell button:hover {
            background-color: #2980b9;
        }

        .data-table .delete-form button,
        .data-table .delete-product-form button {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.9em;
        }

        .data-table .delete-form button:hover,
        .data-table .delete-product-form button:hover {
            background-color: #c0392b;
        }

        /* Messages */
        .error-message, .success-message {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }

        .error-message {
            background-color: #fdd;
            color: #d32f2f;
            border: 1px solid #d32f2f;
        }

        .success-message {
            background-color: #d4edda;
            color: #28a745;
            border: 1px solid #28a745;
        }

        /* Add Product Section */
        .add-product-section {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }

        .add-product-section h3 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 20px;
            text-align: center;
        }

        .add-product-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            max-width: 500px;
            margin: 0 auto;
        }

        .add-product-form label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }

        .add-product-form input[type="text"],
        .add-product-form input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .add-product-form input[type="text"]:focus,
        .add-product-form input[type="file"]:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .add-product-form button {
            width: 100%;
            padding: 12px;
            background-color: #28a745; /* Green for add */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 15px;
        }

        .add-product-form button:hover {
            background-color: #218838;
        }

        .product-list-table img {
            display: block;
            margin: 0 auto;
            border: 1px solid #eee;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 90%;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 15px;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: #333;
            text-decoration: none;
        }

        .modal h2 {
            margin-top: 0;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .modal p {
            margin-bottom: 10px;
            font-size: 1.1em;
            line-height: 1.6;
        }

        .modal p strong {
            color: #333;
        }

        .modal-text-content {
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            min-height: 80px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap; /* Preserves whitespace and wraps text */
            word-wrap: break-word; /* Breaks long words */
            margin-top: 10px;
            line-height: 1.5;
            color: #444;
        }

        .modal-image-container {
            text-align: center;
            margin-bottom: 15px;
        }

        /* Customer Feedback Modal Specific Styles */
        .customer-feedback-item {
            background-color: #f0f4f7;
            border: 1px solid #e0e7ed;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .customer-feedback-item .product-info {
            flex-grow: 1;
        }

        .customer-feedback-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #ccc;
            flex-shrink: 0;
        }

        .customer-feedback-item .rating-stars {
            color: #f39c12;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        .customer-feedback-item .feedback-date {
            font-size: 0.85em;
            color: #777;
            margin-top: 5px;
        }
        /* Feedback Report Section */
        .report-section {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-top: 40px;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .report-header h3 {
            margin: 0;
            font-size: 1.5em;
            color: #2c3e50;
        }

        .report-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .report-controls label {
            font-weight: bold;
            color: #555;
        }

        .report-controls input[type="month"] {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
        }

        .report-controls button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .report-controls button:hover {
            background-color: #2980b9;
        }

        .report-summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .report-summary-cards .card {
            background-color: #ecf0f1;
            border-left: 5px solid #3498db;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .report-summary-cards .card h4 {
            margin-top: 0;
            font-size: 1em;
            color: #555;
        }

        .report-summary-cards .card p {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }

        .report-comments-section {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 25px;
        }

        .report-comments-section h4 {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .report-comment-item {
            background-color: #fcfcfc;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .report-comment-item p {
            margin: 0 0 8px 0;
            line-height: 1.5;
            color: #444;
        }

        .report-comment-item .comment-meta {
            font-size: 0.9em;
            color: #777;
            text-align: right;
            margin-top: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                padding: 15px;
                position: static;
            }
            .sidebar .navigation {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
                margin-bottom: 20px;
            }
            .sidebar .navigation li {
                margin-bottom: 0;
            }
            .sidebar .navigation a {
                padding: 10px 15px;
                font-size: 0.9em;
            }
            .sidebar .logout-section {
                padding-top: 15px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
            .content {
                padding: 20px;
            }
            .content header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .header-actions {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }
            .search-container {
                width: 100%;
            }
            .dashboard {
                grid-template-columns: 1fr;
            }
            .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .report-controls {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div>
                <div class="logo">Admin Panel</div>
                <ul class="navigation">
                    <li><a href="admin.php?section=dashboard" class="<?= ($current_section === 'dashboard') ? 'active' : '' ?>">Dashboard</a></li>
                    <li><a href="admin.php?section=feedback&category=All" class="<?= ($current_section === 'feedback') ? 'active' : '' ?>">Feedback</a></li>
                    <li><a href="admin.php?section=users" class="<?= ($current_section === 'users') ? 'active' : '' ?>">Users</a></li>
                    <li><a href="admin.php?section=products" class="<?= ($current_section === 'products') ? 'active' : '' ?>">Manage Products</a></li>
                    <li><a href="admin.php?section=suggestions" class="<?= ($current_section === 'suggestions') ? 'active' : '' ?>">Suggestions</a></li>
                </ul>
            </div>
            <div class="logout-section">
                <form method="post" action="admin.php?section=logout">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </aside>
        <main class="content">
            <header>
                <h1>
                    <?php
                    if ($current_section === 'users') {
                        echo 'Customer Accounts';
                    } elseif ($current_section === 'products') {
                        echo 'Manage Products';
                    } elseif ($current_section === 'suggestions') {
                        echo 'Customer Suggestions';
                    } elseif ($current_section === 'dashboard') {
                        echo 'Admin Dashboard';
                    } else {
                        echo 'Customer Feedback';
                    }
                    ?>
                </h1>
                <div class="header-actions">
                    <?php if ($current_section === 'feedback' || $current_section === 'products'): ?>
                        <div class="search-container">
                            <input type="text" id="searchInput" name="search" placeholder="Search Name or Cellphone" value="<?= htmlspecialchars($search_term) ?>">
                            <button onclick="submitSearch()">Search</button>
                        </div>
                    <?php endif; ?>
                </div>
            </header>
            <?php if ($current_section === 'feedback' || $current_section === 'products'): ?>
                <div class="filter-tabs">
                    <a href="admin.php?section=<?= $current_section ?>&category=All" class="<?= $selected_category === 'All' ? 'active' : '' ?>">All</a>
                    <?php foreach ($dynamic_categories as $cat): ?>
                        <a href="admin.php?section=<?= $current_section ?>&category=<?= urlencode($cat) ?>" class="<?= $selected_category === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="data-table">
                <?php if ($current_section === 'dashboard'): ?>
                    <h2>Dashboard Overview</h2>
                    <div class="dashboard">
                        <div class="card">
                            <h3>Total Feedbacks</h3>
                            <p><?= $total_feedbacks ?></p>
                        </div>
                        <div class="card">
                            <h3>Average Rating</h3>
                            <p><?= $avg_rating ?> <span class="star">&#9733;</span></p>
                        </div>
                        <div class="card">
                            <h3>Most Rated Flavor</h3>
                            <p><?= htmlspecialchars($most_rated_flavor) ?></p>
                        </div>
                        <div class="card">
                            <h3>Total Users</h3>
                            <p><?= $total_users ?></p>
                        </div>
                        <div class="card">
                            <h3>Total Suggestions</h3>
                            <p><?= $total_suggestions ?></p>
                        </div>
                    </div>

                    <div class="rating-graph-container">
                        <h3>Feedback Rating Distribution</h3>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="rating-bar-wrapper">
                                <span class="rating-label"><?= $i ?> &#9733;</span>
                                <div class="rating-bar-background">
                                    <div class="rating-bar rating-<?= $i ?>" style="width: <?= $max_rating_count > 0 ? ($rating_distribution[$i] / $max_rating_count) * 100 : 0 ?>%;"></div>
                                </div>
                                <span class="rating-count"><?= $rating_distribution[$i] ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="report-section">
                        <div class="report-header">
                            <h3>Monthly Feedback Report for <?= date('F Y', strtotime($report_month)) ?></h3>
                            <div class="report-controls">
                                <label for="reportMonthPicker">Select Month:</label>
                                <input type="month" id="reportMonthPicker" value="<?= htmlspecialchars($report_month) ?>" onchange="updateReportMonth(this.value)">
                                <button onclick="printReport()">Print Report</button>
                            </div>
                        </div>

                        <div class="report-summary-cards">
                            <div class="card">
                                <h4>Total Feedback This Month</h4>
                                <p><?= $monthly_report_data['total_feedback'] ?></p>
                            </div>
                            <div class="card">
                                <h4>Average Rating This Month</h4>
                                <p><?= $monthly_report_data['total_feedback'] > 0 ? round(array_sum(array_map(function($rating, $count) { return $rating * $count; }, array_keys($monthly_report_data['rating_distribution']), $monthly_report_data['rating_distribution'])) / $monthly_report_data['total_feedback'], 1) : 'N/A' ?> <span class="star">&#9733;</span></p>
                            </div>
                            <div class="card">
                                <h4>Most Rated Flavor This Month</h4>
                                <p><?= htmlspecialchars($monthly_report_data['most_rated_flavor_month']) ?></p>
                            </div>
                        </div>

                        <div class="rating-graph-container">
                            <h3>Rating Distribution This Month</h3>
                            <?php
                            $max_monthly_rating_count = max($monthly_report_data['rating_distribution']);
                            for ($i = 5; $i >= 1; $i--): ?>
                                <div class="rating-bar-wrapper">
                                    <span class="rating-label"><?= $i ?> &#9733;</span>
                                    <div class="rating-bar-background">
                                        <div class="rating-bar rating-<?= $i ?>" style="width: <?= $max_monthly_rating_count > 0 ? ($monthly_report_data['rating_distribution'][$i] / $max_monthly_rating_count) * 100 : 0 ?>%;"></div>
                                    </div>
                                    <span class="rating-count"><?= $monthly_report_data['rating_distribution'][$i] ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="report-comments-section">
                            <h4>All Product Feedback Comments This Month</h4>
                            <?php if (count($monthly_report_data['comments']) > 0): ?>
                                <?php foreach ($monthly_report_data['comments'] as $comment): ?>
                                    <div class="report-comment-item">
                                        <p><strong>Product:</strong> <?= htmlspecialchars($comment['flavor_name'] ?? 'N/A') ?></p>
                                        <p><?= htmlspecialchars($comment['comment']) ?></p>
                                        <div class="comment-meta">
                                            - <?= htmlspecialchars($comment['fullname'] ?? 'Anonymous') ?> on <?= htmlspecialchars(date('Y-m-d H:i', strtotime($comment['created_at']))) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #777;">No product feedback comments found for this month.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($current_section === 'feedback'): ?>
                    <table class="feedback-table">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Customer Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Telephone</th>
                                <th>View All Feedback</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($feedbacks) > 0): ?>
                                <?php foreach ($feedbacks as $feedback_group): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($feedback_group['customer_id']) ?></td>
                                        <td><?= htmlspecialchars($feedback_group['fullname'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($feedback_group['age'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($feedback_group['gender'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($feedback_group['telephone'] ?? 'N/A') ?></td>
                                        <td class="feedback-details-cell">
                                            <button class="view-customer-feedback-btn"
                                                data-customer-id="<?= htmlspecialchars($feedback_group['customer_id']) ?>"
                                                data-customer-name="<?= htmlspecialchars($feedback_group['fullname'] ?? 'N/A') ?>">
                                                View All Feedback
                                            </button>
                                        </td>
                                        <td>
                                            <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete all feedback for this customer?');">
                                                <input type="hidden" name="delete_customer_feedback_id" value="<?= $feedback_group['customer_id'] ?>">
                                                <button type="submit">Delete All</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No feedback found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php elseif ($current_section === 'users'): ?>
                    <?php if (!empty($user_error_message)): ?>
                        <div class="error-message"><?= htmlspecialchars($user_error_message) ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['message']) && $_GET['message'] === 'User deleted'): ?>
                        <div class="success-message">User deleted successfully!</div>
                    <?php endif; ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Telephone</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['customer_id']) ?></td>
                                        <td><?= htmlspecialchars($user['fullname']) ?></td>
                                        <td><?= htmlspecialchars($user['age']) ?></td>
                                        <td><?= htmlspecialchars($user['gender']) ?></td>
                                        <td><?= htmlspecialchars($user['telephone']) ?></td>
                                        <td>
                                            <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this user and all associated feedback and suggestions? This cannot be undone.');">
                                                <input type="hidden" name="delete_user_id" value="<?= $user['customer_id'] ?>">
                                                <button type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No users found.</td> </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php elseif ($current_section === 'products'): ?>
                    <div class="add-product-section">
                        <h3>Add New Product</h3>
                        <?php if (!empty($product_add_error_message)): ?>
                            <div class="error-message"><?= htmlspecialchars($product_add_error_message) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($product_add_success_message)): ?>
                            <div class="success-message"><?= htmlspecialchars($product_add_success_message) ?></div>
                        <?php endif; ?>
                        <form method="post" class="add-product-form" enctype="multipart/form-data">
                            <label for="add_category">Category:</label>
                            <input type="text" id="add_category" name="add_category" required value="<?= htmlspecialchars($_POST['add_category'] ?? '') ?>">
                            <label for="add_flavor_name">Flavor Name:</label>
                            <input type="text" id="add_flavor_name" name="add_flavor_name" required value="<?= htmlspecialchars($_POST['add_flavor_name'] ?? '') ?>">
                            <label for="add_image">Product Image:</label>
                            <input type="file" id="add_image" name="add_image" accept="image/*">
                            <button type="submit">Add Product</button>
                        </form>
                    </div>

                    <h3>Existing Products</h3>
                    <?php if (isset($_GET['message']) && $_GET['message'] === 'Product deleted'): ?>
                        <div class="success-message">Product deleted successfully!</div>
                    <?php endif; ?>
                    <?php if (!empty($product_error_message)): ?>
                        <div class="error-message"><?= htmlspecialchars($product_error_message) ?></div>
                    <?php endif; ?>
                    <table class="product-list-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Flavor Name</th>
                                <th>Image</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($all_flavors) > 0): ?>
                                <?php foreach ($all_flavors as $flavor): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($flavor['id']) ?></td>
                                        <td><?= htmlspecialchars($flavor['category']) ?></td>
                                        <td><?= htmlspecialchars($flavor['flavor_name']) ?></td>
                                        <td>
                                            <?php if (!empty($flavor['image_path']) && file_exists($flavor['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($flavor['image_path']) ?>" alt="<?= htmlspecialchars($flavor['flavor_name']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                            <?php else: ?>
                                                No Image
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" class="delete-product-form" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="delete_product_id" value="<?= $flavor['id'] ?>">
                                                <button type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No products found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php elseif ($current_section === 'suggestions'): ?>
                    <table class="suggestions-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Suggestion Details</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suggestions) > 0): ?>
                                <?php foreach ($suggestions as $suggestion): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($suggestion['id']) ?></td>
                                        <td><?= htmlspecialchars($suggestion['fullname'] ?? 'N/A') ?></td>
                                        <td class="suggestion-details-cell">
                                            <button class="view-suggestion-btn"
                                                data-suggestion="<?= htmlspecialchars($suggestion['suggestion_text']) ?>">
                                                View Details
                                            </button>
                                        </td>
                                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($suggestion['created_at']))) ?></td>
                                        <td>
                                            <form method="post" class="delete-suggestion-form" onsubmit="return confirm('Are you sure you want to delete this suggestion?');">
                                                <input type="hidden" name="delete_suggestion_id" value="<?= $suggestion['id'] ?>">
                                                <button type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No suggestions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="customerFeedbackModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="customerFeedbackModalTitle">Feedback from Customer</h2>
            <div id="customerFeedbackList">
                </div>
        </div>
    </div>


    <div id="suggestionModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Suggestion Details</h2>
            <p><strong>Suggestion:</strong></p>
            <div id="modalSuggestion" class="modal-text-content"></div>
        </div>
    </div>

   <script>
    function submitSearch() {
        const searchTerm = document.getElementById('searchInput').value;
        const currentSection = '<?= $current_section ?>';
        const selectedCategory = '<?= urlencode($selected_category) ?>';
        window.location.href = `admin.php?section=${currentSection}&category=${selectedCategory}&search=${encodeURIComponent(searchTerm)}`;
    }

    function updateReportMonth(selectedMonth) {
        window.location.href = `admin.php?section=dashboard&report_month=${selectedMonth}`;
    }

    function printReport() {
        const reportContent = document.querySelector('.report-section').outerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Monthly Feedback Report</title>');
        printWindow.document.write('<link rel="stylesheet" href="style.css">'); // Link your existing CSS
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; color: #333; }
            .report-section { background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: none; border: 1px solid #eee; }
            .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
            .report-header h3 { font-size: 1.5em; color: #2c3e50; margin: 0; }
            .report-controls { display: none; /* Hide controls in print */ }
            .report-summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .report-summary-cards .card { background-color: #ecf0f1; border-left: 5px solid #3498db; padding: 20px; border-radius: 8px; box-shadow: none; border: 1px solid #ddd; }
            .report-summary-cards .card h4 { margin-top: 0; font-size: 1em; color: #555; }
            .report-summary-cards .card p { font-size: 1.8em; font-weight: bold; color: #2c3e50; margin: 0; }
            .rating-graph-container { background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: none; border: 1px solid #eee; margin-bottom: 30px;}
            .rating-graph-container h3 { margin-top: 0; font-size: 1.3em; color: #2c3e50; margin-bottom: 25px; text-align: center; }
            .rating-bar-wrapper { display: flex; align-items: center; margin-bottom: 10px; }
            .rating-label { font-weight: bold; margin-right: 15px; width: 60px; text-align: right; color: #555; }
            .rating-bar-background { flex-grow: 1; height: 18px; background-color: #e0e0e0; border-radius: 9px; overflow: hidden; }
            .rating-bar { height: 100%; background-color: #2ecc71; border-radius: 9px; }
            .rating-bar.rating-5 { background-color: #2ecc71; }
            .rating-bar.rating-4 { background-color: #27ae60; }
            .rating-bar.rating-3 { background-color: #f1c40f; }
            .rating-bar.rating-2 { background-color: #e67e22; }
            .rating-bar.rating-1 { background-color: #e74c3c; }
            .rating-count { margin-left: 15px; font-weight: bold; color: #333; width: 40px; text-align: left; }
            .report-comments-section { margin-top: 30px; border-top: 1px solid #eee; padding-top: 25px; }
            .report-comments-section h4 { font-size: 1.2em; color: #2c3e50; margin-bottom: 20px; }
            .report-comment-item { background-color: #fcfcfc; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; page-break-inside: avoid; }
            .report-comment-item p { margin: 0 0 8px 0; line-height: 1.5; color: #444; }
            .report-comment-item .comment-meta { font-size: 0.85em; color: #777; text-align: right; margin-top: 10px; }
            @media print {
                body {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .report-section, .rating-graph-container, .report-summary-cards .card, .report-comment-item {
                    border: 1px solid #eee !important;
                    box-shadow: none !important;
                }
                .rating-bar, .report-summary-cards .card {
                    background-color: #e0e0e0 !important; /* Fallback for print background */
                }
                .rating-bar.rating-5 { background-color: #2ecc71 !important; }
                .rating-bar.rating-4 { background-color: #27ae60 !important; }
                .rating-bar.rating-3 { background-color: #f1c40f !important; }
                .rating-bar.rating-2 { background-color: #e67e22 !important; }
                .rating-bar.rating-1 { background-color: #e74c3c !important; }
            }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(reportContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Get the modals
        const customerFeedbackModal = document.getElementById('customerFeedbackModal');
        const suggestionModal = document.getElementById('suggestionModal');

        // Get the <span> element that closes the modal
        const closeButtons = document.querySelectorAll('.close-button');

        // Get elements to populate in the modals
        const customerFeedbackModalTitle = document.getElementById('customerFeedbackModalTitle');
        const customerFeedbackList = document.getElementById('customerFeedbackList');
        const modalSuggestion = document.getElementById('modalSuggestion');

        // When the user clicks on a "View All Feedback" button for a customer
        document.querySelectorAll('.view-customer-feedback-btn').forEach(button => {
            button.addEventListener('click', function() {
                const customerId = this.dataset.customerId;
                const customerName = this.dataset.customerName;

                customerFeedbackModalTitle.textContent = 'Feedback from ' + customerName;
                customerFeedbackList.innerHTML = '<p>Loading feedback...</p>'; // Show loading message

                // Use fetch API to get all feedback for this customer
                fetch(`admin.php?section=feedback&view_customer_feedback=${customerId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok ' + response.statusText);
                        }
                        return response.json(); // Expecting JSON directly
                    })
                    .then(data => {
                        customerFeedbackList.innerHTML = ''; // Clear loading message

                        if (data.length > 0) {
                            data.forEach(feedback => {
                                const feedbackItem = document.createElement('div');
                                feedbackItem.classList.add('customer-feedback-item');

                                let stars = '';
                                for (let i = 0; i < parseInt(feedback.rating); i++) {
                                    stars += '<span class="star">&#9733;</span>';
                                }

                                let imageHtml = '';
                                // Check if image_path exists and is not empty or 'null' (as string)
                                if (feedback.image_path && feedback.image_path !== '' && feedback.image_path !== 'null') {
                                    imageHtml = `<img src="${htmlspecialchars(feedback.image_path)}" alt="${htmlspecialchars(feedback.flavor_name)}">`;
                                }

                                feedbackItem.innerHTML = `
                                    ${imageHtml}
                                    <div class="product-info">
                                        <h4>${htmlspecialchars(feedback.flavor_name)}</h4>
                                        <div class="rating-stars">${stars}</div>
                                        <p>${htmlspecialchars(feedback.comment)}</p>
                                        <div class="feedback-date">${htmlspecialchars(new Date(feedback.created_at).toLocaleString())}</div>
                                        <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this specific feedback?');" style="margin-top: 10px;">
                                            <input type="hidden" name="delete_feedback_id" value="${feedback.feedback_id}">
                                            <button type="submit">Delete This Feedback</button>
                                        </form>
                                    </div>
                                `;
                                customerFeedbackList.appendChild(feedbackItem);
                            });
                        } else {
                            customerFeedbackList.innerHTML = '<p style="text-align: center; color: #777;">No specific feedback found for this customer.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching customer feedback:', error);
                        customerFeedbackList.innerHTML = '<p style="text-align: center; color: #d32f2f;">Failed to load feedback. Please try again. Check console for details.</p>';
                    });

                customerFeedbackModal.style.display = 'flex';
            });
        });

        // When the user clicks on a "View Details" button for suggestion
        document.querySelectorAll('.view-suggestion-btn').forEach(button => {
            button.addEventListener('click', function() {
                const suggestion = this.dataset.suggestion;
                modalSuggestion.textContent = suggestion;
                suggestionModal.style.display = 'flex';
            });
        });

        // When the user clicks on <span> (x) or outside the modal, close it
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.modal').style.display = 'none';
            });
        });

        window.addEventListener('click', function(event) {
            if (event.target === customerFeedbackModal) {
                customerFeedbackModal.style.display = 'none';
            }
            if (event.target === suggestionModal) {
                suggestionModal.style.display = 'none';
            }
        });

        // Helper function for HTML escaping
        function htmlspecialchars(str) {
            if (typeof str !== 'string') { // Handle non-string inputs gracefully
                return str;
            }
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    });
</script>
</body>
</html>