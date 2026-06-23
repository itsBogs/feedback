<?php
session_start();
require 'db.php'; // Assuming db.php handles PDO connection and error handling

// It's good practice to ensure error reporting is set, especially during development.
// db.php should ideally set PDO::ATTR_ERRMODE to PDO::ERRMODE_EXCEPTION.
// If not, consider adding: $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!array_key_exists('customer_id', $_SESSION)) {
    // If session variable is not even set, redirect
    header('Location: index.php');
    exit;
}

// Get customer_id from session. This can be null if anonymous (based on your schema default).
$customer_id = $_SESSION['customer_id'];

// Define the valid feedback codes
$valid_feedback_codes = ['qnteahouse', 'qnteafeedback']; // Add your desired codes here

// Fetch all flavors from the database, including flavor_id, category, flavor_name, and image_path
$stmt = $pdo->query("SELECT category, flavor_name, flavor_id, image_path FROM flavors ORDER BY category, flavor_name");
$flavors_by_category = []; // For displaying products grouped by category in the initial selection
$all_flavors_by_id = []; // For quick lookup of flavor details by ID

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $flavors_by_category[$row['category']][] = [
        'flavor_name' => $row['flavor_name'],
        'id' => $row['flavor_id'],
        'image_path' => $row['image_path']
    ];
    $all_flavors_by_id[$row['flavor_id']] = [
        'flavor_name' => $row['flavor_name'],
        'category' => $row['category'],
        'image_path' => $row['image_path']
    ];
}

$errors = [];
$success_message = '';
$selected_flavors_for_feedback = $_SESSION['selected_flavors_for_feedback'] ?? []; // Store flavor IDs
$feedback_code_entered = $_SESSION['feedback_code_entered'] ?? false; // New session variable for code validation

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Process the checklist for selected flavors (products) ---
    // This part runs when the 'Continue' button for product selection is pressed
    if (isset($_POST['selected_product_ids']) && !isset($_POST['feedback_submission']) && !isset($_POST['validate_code'])) {
        $selected_flavors_for_feedback = array_map('intval', $_POST['selected_product_ids']);
        $_SESSION['selected_flavors_for_feedback'] = $selected_flavors_for_feedback; // Save selected flavor IDs in session

        // If no flavors were selected, show an error and stay on the selection page
        if (empty($selected_flavors_for_feedback)) {
            $errors[] = "Please select at least one product to provide feedback for.";
            unset($_SESSION['selected_flavors_for_feedback']); // Clear if empty
        }
    }

    // --- Process feedback code validation ---
    if (isset($_POST['validate_code'])) {
        $entered_code = trim($_POST['feedback_code'] ?? '');
        if (in_array(strtolower($entered_code), $valid_feedback_codes)) {
            $_SESSION['feedback_code_entered'] = true;
            $feedback_code_entered = true;
        } else {
            $errors[] = "Invalid feedback code. Please try again.";
            $_SESSION['feedback_code_entered'] = false; // Reset if invalid
            $feedback_code_entered = false;
        }
    }

    // --- Process feedback submission ---
    // This part runs when the 'Submit Feedback' button is pressed
    if (isset($_POST['feedback_submission'])) {
        // Only allow feedback submission if the code has been successfully entered
        if (!$feedback_code_entered) {
            $errors[] = "Please enter and validate the feedback code first.";
        } elseif (empty($_SESSION['selected_flavors_for_feedback'])) {
            $errors[] = "No products were selected for feedback. Please go back and select some.";
        } else {
            $feedbacks_to_insert = [];
            $has_feedback = false; // Flag to check if any actual feedback was provided

            foreach ($_SESSION['selected_flavors_for_feedback'] as $flavor_id) {
                // Get the feedback type, rating, and comment for this specific flavor_id
                $flavor_type = $_POST['flavor_type_' . $flavor_id] ?? ''; // This is the 'Flavor' or 'Quality' or 'Pricing' value from the dropdown
                $rating = intval($_POST['rating_' . $flavor_id] ?? 0);
                $comment = trim($_POST['comment_' . $flavor_id] ?? '');

                // Only process if at least one feedback field for this product is filled
                if ($flavor_type !== '' || $rating !== 0 || $comment !== '') {
                    $has_feedback = true;

                    // Basic validation for rating
                    if ($rating < 1 || $rating > 5) {
                        $errors[] = "Please provide a star rating between 1 and 5 for " . htmlspecialchars($all_flavors_by_id[$flavor_id]['flavor_name']) . ".";
                    }

                    // If no errors for this specific feedback item, add to batch
                    if (empty($errors)) {
                        $feedbacks_to_insert[] = [
                            'flavor_id' => $flavor_id,
                            'feedback_type' => $flavor_type, // This will no longer be inserted into the DB
                            'rating' => $rating,
                            'comment' => $comment,
                        ];
                    }
                }
            } // End foreach selected flavors

            if (empty($errors)) {
                if (!$has_feedback) {
                    $errors[] = 'Please provide feedback for at least one selected product.';
                } else {
                    try {
                        $pdo->beginTransaction(); // Start transaction for multiple inserts

                        // MODIFIED LINE: Removed feedback_type from the INSERT statement
                        $stmt = $pdo->prepare("INSERT INTO feedback (customer_id, flavor_id, rating, comment) VALUES (?, ?, ?, ?)");

                        foreach ($feedbacks_to_insert as $fb) {
                            // MODIFIED LINE: Removed $fb['feedback_type'] from the execute parameters
                            $stmt->execute([$customer_id, $fb['flavor_id'], $fb['rating'], $fb['comment']]);
                        }
                        $pdo->commit(); // Commit transaction

                        $success_message = "Feedback submitted successfully! Thank you.";
                        unset($_SESSION['selected_flavors_for_feedback']); // Clear selected flavors after submission
                        unset($_SESSION['feedback_code_entered']); // Clear the feedback code session after submission
                    } catch (PDOException $e) {
                        $pdo->rollBack(); // Rollback on error
                        error_log("Feedback submission error: " . $e->getMessage()); // Log the error
                        $errors[] = "An error occurred while submitting your feedback. Please try again.";
                    }
                }
            }
        }
    }

    // --- Process suggestions (this part remains largely the same) ---
    // Suggestions can always be submitted, even without a feedback code, as they are general
    if (isset($_POST['suggestion_text'])) {
        $suggestion_text = trim($_POST['suggestion_text']);
        if (!empty($suggestion_text)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO suggestions (customer_id, suggestion_text) VALUES (?, ?)");
                $stmt->execute([$customer_id, $suggestion_text]);
                if (empty($success_message)) {
                    $success_message = "Your suggestion has been submitted.";
                } else {
                    $success_message .= " Your suggestion has also been submitted."; // Append to existing message
                }
            } catch (PDOException $e) {
                error_log("Suggestion submission error: " . $e->getMessage()); // Log the error
                $errors[] = "An error occurred while submitting your suggestion. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Submit Feedback</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Modernized Color Palette & Variables */
:root {
    --primary-dark: #2c3e50; /* Darker blue-grey for main background */
    --primary-medium: #34495e; /* Medium blue-grey for sections */
    --primary-light: #4a6572; /* Lighter blue-grey for subtle accents */

    /* Retaining the original gold accents for stars, headings, input focus, and category checkboxes */
    --accent-gold: #f39c12; /* Golden orange for highlights/stars/headings/checkboxes */
    --accent-gold-dark: #e67e22; /* Darker gold for hover states of gold elements */

    /* New specific variables for the button blue */
    --button-bg-blue: #007bff; /* Your desired blue for button background */
    --button-hover-blue: #a93226; /* Darker blue for button hover */
    --text-light: #ecf0f1; /* Light grey for primary text */
    --text-dark: #ffffff; /* White text for contrast on darker backgrounds (like blue buttons) */
    --background-overlay: rgba(0, 0, 0, 0.4); /* Darker, slightly opaque overlay */
    --border-subtle: rgba(236, 240, 241, 0.2); /* Subtle light border */
    --error-red: #e74c3c; /* Brighter error red */
    --success-green: #27ae60; /* Brighter success green */
    --shadow-subtle: rgba(0, 0, 0, 0.2);
    --shadow-medium: rgba(0, 0, 0, 0.4);
    --shadow-strong: rgba(0, 0, 0, 0.6);
}

*{
    font-family: 'Playfair Display', serif;
}
body {
    margin: 0;
    padding: 0;
    background-image: url('main1.jpg'); /* Ensure this path is correct */
    background-color: var(--primary-dark);
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    font-family: 'Roboto', sans-serif;
    color: var(--text-light);
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    min-height: 100vh;
    padding: 30px 15px; /* Adjusted padding for portrait view */
    box-sizing: border-box;
    line-height: 1.6;
}

.container {
    width: 100%; /* Take full width on small screens */
    max-width: 1200px; /* Increased max-width for 5 columns */
    background: var(--background-overlay);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px var(--shadow-strong);
    backdrop-filter: blur(10px); /* Slightly more blur */
    border: 1px solid var(--border-subtle);
    animation: fadeIn 0.8s ease-out, backgroundPan 20s linear infinite alternate; /* Added backgroundPan animation */
    display: flex;
    flex-direction: column;
    gap: 20px; /* Add gap between sections */
    box-sizing: border-box;
    background-size: 200% 200%; /* Make background larger than container for movement */
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Keyframes for background movement */
@keyframes backgroundPan {
    0% {
        background-position: 0% 0%;
    }
    100% {
        background-position: 100% 100%;
    }
}


h1, h2.category, .form-section h2 {
    font-family: 'Playfair Display', serif;
    text-align: center;
    font-weight: 700;
    color: var(--accent-gold); /* Gold for headings */
    text-shadow: 1px 1px 4px rgba(0,0,0,0.7);
    letter-spacing: 0.5px;
}

h1 {
    font-size: 2.8em; /* Slightly larger heading */
    margin-bottom: 25px;
    border-bottom: 2px solid var(--accent-gold); /* Gold underline */
    padding-bottom: 10px;
    display: inline-block; /* To make underline fit content */
    margin-left: auto;
    margin-right: auto;
}

h2.category, .form-section h2 {
    font-size: 1.8em; /* Slightly larger category headings */
    margin-top: 30px;
    margin-bottom: 20px;
    position: relative;
    color: var(--text-light); /* Changed for better contrast */
}

h2.category::after, .form-section h2::after {
    content: '';
    display: block;
    width: 80px; /* Wider underline */
    height: 3px;
    background: var(--accent-gold); /* Gold underline */
    margin: 10px auto 0;
    border-radius: 2px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700; /* Bolder labels */
    font-size: 1.1em; /* Slightly larger labels */
    color: var(--text-light);
}

select, textarea, input[type="text"] {
    width: 100%;
    padding: 14px; /* Increased padding */
    margin-bottom: 25px; /* More space below inputs */
    border-radius: 12px; /* More rounded corners */
    border: 1px solid var(--primary-light); /* Lighter border */
    background: var(--primary-medium);
    color: var(--text-light);
    font-size: 1.05em; /* Slightly larger font */
    transition: all 0.3s ease; /* Smooth transition for all properties */
    box-sizing: border-box;
    box-shadow: inset 0 2px 5px var(--shadow-subtle); /* Subtle inner shadow */
}

select:focus, textarea:focus, input[type="text"]:focus {
    outline: none;
    border-color: var(--accent-gold); /* Gold focus border */
    box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.4), inset 0 2px 5px var(--shadow-subtle); /* Gold ring glow */
    background-color: var(--primary-dark); /* Slightly darker on focus */
}

textarea {
    resize: vertical;
    min-height: 120px; /* Significantly increased min-height for comment boxes */
    line-height: 1.5; /* Better readability */
}

/* --- Product Selection Styles (Updated) --- */
.product-selection-section {
    padding: 25px; /* Increased padding */
    background: var(--primary-medium); /* Use primary-medium for section background */
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px var(--shadow-medium); /* More pronounced shadow */
    border: 1px solid var(--border-subtle);
}

.product-checkboxes-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr); /* Set to fixed 5 columns */
    gap: 20px; /* Increased gap */
    margin-top: 25px;
}

.product-checkbox-card {
    display: flex;
    flex-direction: column; /* Stack image and text */
    align-items: center; /* Center content horizontally */
    cursor: pointer;
    padding: 15px;
    background-color: var(--primary-light);
    border-radius: 12px; /* More rounded */
    transition: all 0.2s ease;
    box-shadow: 0 4px 10px var(--shadow-subtle);
    border: 1px solid rgba(255, 255, 255, 0.1); /* Lighter, more subtle border */
    text-align: center;
    position: relative; /* For the checkbox positioning */
}

.product-checkbox-card:hover {
    background-color: var(--primary-dark);
    transform: translateY(-4px); /* More pronounced lift */
    box-shadow: 0 8px 20px var(--shadow-medium);
}

.product-checkbox-card img {
    max-width: 100px; /* Fixed size for product images */
    height: 100px;
    object-fit: cover; /* Ensure images cover the area without distortion */
    border-radius: 8px;
    margin-bottom: 10px;
    border: 2px solid var(--accent-gold);
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
}

.product-checkbox-card input[type="checkbox"] {
    position: absolute; /* Position checkbox at the top-right */
    top: 10px;
    right: 10px;
    width: 28px; /* Larger checkbox */
    height: 28px;
    appearance: none;
    border: 2px solid var(--button-bg-blue); /* Use button blue for checkbox border */
    border-radius: 7px; /* More rounded square */
    background-color: var(--primary-dark);
    cursor: pointer;
    transition: background-color 0.2s, border-color 0.2s, transform 0.2s;
    z-index: 1; /* Ensure it's above other content */
}

.product-checkbox-card input[type="checkbox"]:checked {
    background-color: var(--button-bg-blue); /* Use button blue when checked */
    border-color: var(--button-bg-blue);
    transform: scale(1.1);
}

.product-checkbox-card input[type="checkbox"]:checked::after {
    content: '\2713'; /* Checkmark symbol */
    font-size: 24px; /* Larger checkmark */
    color: var(--text-dark); /* White checkmark */
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
}

.product-checkbox-card .product-name {
    font-size: 1.2em; /* Larger text */
    font-weight: 600; /* Medium weight */
    color: var(--text-dark); /* White text */
    margin-bottom: 5px;
}

.product-checkbox-card .product-category {
    font-size: 0.9em;
    color: var(--text-light); /* Lighter grey for category */
    font-style: italic;
}

/* --- End Product Selection Styles --- */

.star-rating {
    font-size: 38px; /* Larger stars */
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    margin-bottom: 30px; /* More space below */
    gap: 8px; /* More space between stars */
}

.star-rating input[type="radio"] {
    display: none;
}

.star-rating label {
    color: var(--primary-light); /* Lighter grey for unselected */
    padding: 0 5px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.4); /* Subtle shadow on stars */
}

.star-rating label:hover {
    transform: scale(1.2); /* More pronounced hover effect */
    color: var(--accent-gold); /* Always use accent-gold for star hover */
}

.star-rating input[type="radio"]:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color: var(--accent-gold); /* Always use accent-gold for selected stars */
}
/* Make sure the selected star and all stars to its left are colored */
.star-rating input[type="radio"]:checked + label,
.star-rating input[type="radio"]:checked + label ~ label {
    color: var(--accent-gold); /* Always use accent-gold for selected stars */
}


/* General Button Styling (applies to most buttons including "Submit Feedback") */
button {
    width: fit-content; /* Adjusts to content, more robust than auto */
    padding: 18px 35px; /* Larger padding */
    margin: 30px auto 10px; /* Center button and adjust margins */
    display: block;
    background-color: var(--button-bg-blue); /* Use the new blue variable */
    color: var(--text-dark); /* White text for contrast on blue */
    font-weight: 700;
    font-size: 1.2em; /* Larger font */
    border: none;
    border-radius: 15px; /* More rounded */
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px var(--shadow-medium); /* More prominent shadow */
    text-transform: uppercase;
    letter-spacing: 1px; /* Increased letter spacing */
    outline: none; /* Remove outline on focus */
}

button:hover {
    background-color: var(--button-hover-blue); /* Use the new darker blue for hover */
    transform: translateY(-5px) scale(1.02); /* More pronounced lift and slight scale */
    box-shadow: 0 12px 25px var(--shadow-strong);
}

.errors, .success {
    margin-bottom: 25px;
    padding: 18px; /* Increased padding */
    border-radius: 12px; /* More rounded */
    font-weight: 700;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    animation: slideIn 0.6s ease-out; /* Slightly longer animation */
    width: 100%;
    box-sizing: border-box;
    line-height: 1.5;
    font-size: 1.1em;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.errors {
    background-color: var(--error-red);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.success {
    background-color: var(--success-green);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.success-dialogue button {
    width: auto;
    padding: 12px 25px;
    margin-top: 20px;
    font-size: 1em;
    display: inline-block;
    background-color: var(--button-bg-blue); /* Use the new blue variable for success button */
    color: var(--text-dark);
    box-shadow: 0 4px 10px var(--shadow-subtle);
}

.success-dialogue button:hover {
    background-color: var(--button-hover-blue); /* Use the new darker blue for success button hover */
    transform: translateY(-2px);
    box-shadow: 0 6px 15px var(--shadow-medium);
}

.back-button {
    text-align: center;
    margin-top: 25px;
    width: 100%;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.back-button button {
    background: var(--primary-light); /* Muted button color, as it was */
    color: var(--text-light);
    box-shadow: none;
    padding: 12px 25px; /* Standardize padding */
    font-size: 1em;
}

.back-button button:hover {
    background: #5f7a8c; /* Slightly darker hover */
    transform: translateY(-3px);
    box-shadow: 0 4px 10px var(--shadow-light);
}

.selected-items-display { /* Renamed from selected-categories-display */
    background-color: var(--primary-medium);
    padding: 18px; /* Increased padding */
    border-radius: 12px;
    margin-bottom: 25px;
    text-align: center;
    font-style: italic;
    border: 1px solid var(--border-subtle);
    font-size: 1.05em;
    box-shadow: inset 0 0 8px rgba(0,0,0,0.2);
}

.suggestion-box {
    background-color: var(--primary-medium);
    padding: 25px;
    border-radius: 15px;
    margin-top: 30px;
    box-shadow: 0 5px 15px var(--shadow-medium);
    border: 1px solid var(--border-subtle);
}

/* Admin Section Styles - Enhanced for better contrast/readability */
.admin-section {
    margin-top: 50px;
    padding: 30px;
    background: var(--primary-dark); /* Darker background for admin section */
    border-radius: 18px; /* More rounded */
    box-shadow: 0 10px 25px var(--shadow-strong);
    border: 1px solid rgba(255, 248, 220, 0.1); /* Very subtle border */
    color: var(--text-light); /* Ensure text is light */
}

.admin-section h2 {
    color: var(--accent-gold); /* Gold for admin heading */
    margin-bottom: 25px;
    border-bottom: 2px solid var(--accent-gold-dark); /* Stronger underline */
    padding-bottom: 12px;
    font-size: 2em; /* Larger heading */
    text-shadow: 1px 1px 3px rgba(0,0,0,0.6);
}

.add-product-form {
    display: flex;
    flex-direction: column;
    gap: 20px; /* Increased gap */
    margin-bottom: 40px;
}

.add-product-form label {
    color: var(--text-light); /* Ensure labels are visible */
    font-weight: 500;
}

.add-product-form input[type="text"] {
    margin-bottom: 0;
    background-color: var(--primary-light); /* Lighter input background */
    border-color: var(--border-subtle);
}

.add-product-form input[type="text"]:focus {
    background-color: var(--primary-medium);
}

.add-product-form button {
    background-color: var(--button-bg-blue); /* **Changed to use new blue variable** */
    max-width: 250px; /* Slightly wider button */
    margin: 20px auto 0;
    font-size: 1.1em;
    color: var(--text-dark); /* Ensure text is dark (white) */
}

.add-product-form button:hover {
    background-color: var(--button-hover-blue); /* **Changed to use new blue hover variable** */
    transform: translateY(-4px);
    box-shadow: 0 8px 18px var(--shadow-medium);
}

.product-list {
    width: 100%;
    border-collapse: separate; /* Use separate to apply border-radius to tbody */
    border-spacing: 0; /* Remove spacing */
    margin-top: 25px;
    background-color: var(--primary-medium);
    border-radius: 15px; /* More rounded */
    overflow: hidden; /* For rounded corners */
    box-shadow: 0 5px 15px var(--shadow-strong);
}

.product-list th, .product-list td {
    padding: 15px 20px; /* Increased padding */
    text-align: left;
    border-bottom: 1px solid var(--primary-light); /* Lighter border */
    color: var(--text-light);
    font-size: 0.95em;
}

.product-list th {
    background-color: var(--primary-dark);
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--accent-gold); /* Gold header text */
}

.product-list tr:nth-child(even) {
    background-color: rgba(0,0,0,0.1); /* Subtle stripe */
}

.product-list tr:hover {
    background-color: var(--primary-light); /* Lighter hover color */
    color: var(--text-light); /* Keep text light */
    cursor: pointer;
}

.product-list .delete-btn {
    background-color: #e74c3c; /* Flat red for delete */
    color: white;
    padding: 10px 15px; /* Standardized padding */
    border-radius: 10px; /* More rounded */
    text-decoration: none;
    font-size: 0.9em;
    transition: background-color 0.2s, transform 0.2s;
    width: auto;
    margin: 0;
    display: inline-block; /* Ensure it behaves like a button */
    box-shadow: 0 2px 5px var(--shadow-subtle);
    text-transform: capitalize; /* Standard capitalization */
}

.product-list .delete-btn:hover {
    background-color: #c0392b; /* Darker red on hover */
    transform: translateY(-2px);
    box-shadow: 0 4px 10px var(--shadow-medium);
}

/* Responsive adjustments */
/* Desktop / Larger Screens: 5 per row */
@media (min-width: 1201px) { /* Adjust breakpoint as needed */
    .product-checkboxes-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

/* Larger Tablets / Small Desktops: 4 per row */
@media (max-width: 1200px) {
    .product-checkboxes-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Tablets (Portrait) / Smaller Laptops: 3 per row */
@media (max-width: 992px) {
    .product-checkboxes-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Large Phones / Small Tablets: 2 per row */
@media (max-width: 768px) {
    h1 {
        font-size: 2.2em;
    }
    h2.category, .form-section h2 {
        font-size: 1.5em;
    }
    .container {
        padding: 20px;
        margin: 20px 0;
    }
    .product-checkboxes-grid { /* Corrected to target .product-checkboxes-grid */
        grid-template-columns: repeat(2, 1fr);
    }
    .product-checkbox-card {
        padding: 12px 15px;
        font-size: 1em;
    }
    button {
        padding: 15px 25px;
        font-size: 1em;
    }
    .star-rating {
        font-size: 30px;
        gap: 3px;
    }
    .product-list th, .product-list td {
        padding: 10px 12px;
        font-size: 0.85em;
    }
    .admin-btn { /* Note: This class isn't in your provided HTML structure, but keeping the rule if it exists elsewhere */
        top: 15px;
        right: 15px;
        padding: 8px 15px;
        font-size: 0.8em;
    }
}

/* Small Phones: 1 per row */
@media (max-width: 480px) {
    .product-checkboxes-grid {
        grid-template-columns: 1fr;
    }
}

    /* NEW CSS for the individual feedback item containers (distinct from product selection) */
.feedback-form-section {
    padding: 25px; /* Increased padding */
    background: var(--primary-medium); /* Use primary-medium for section background */
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px var(--shadow-medium); /* More pronounced shadow */
    border: 1px solid var(--border-subtle);
}

.feedback-item-container { /* Renamed from flavor-item-container for clarity */
    display: flex;
    flex-direction: column;
    align-items: center; /* Center content horizontally */
    margin-bottom: 25px; /* Space between flavor groups */
    padding: 20px; /* Increased padding within each feedback item */
    background-color: var(--primary-light); /* Slightly lighter background for each item */
    border-radius: 15px;
    box-shadow: 0 5px 15px var(--shadow-subtle);
    border: 1px solid rgba(255, 255, 255, 0.15); /* Slightly stronger border */
    text-align: center; /* Ensure text inside is centered */
}

.feedback-item-container h3.category { /* Targeting the specific H3 inside for styling */
    font-size: 1.6em; /* Make flavor label slightly larger */
    font-weight: 700;
    color: var(--accent-gold); /* Gold for product name in feedback */
    margin-top: 5px; /* Adjust top margin */
    margin-bottom: 15px; /* Space below the product name */
    text-shadow: 1px 1px 3px rgba(0,0,0,0.6);
}

.feedback-item-container img.flavor-image {
    max-width: 180px; /* Increased size for images in feedback form */
    height: auto;
    border-radius: 12px; /* Slightly more rounded corners for images */
    margin-bottom: 20px; /* More space below image */
    box-shadow: 0 6px 15px rgba(0,0,0,0.6); /* More prominent shadow for images */
    border: 3px solid var(--accent-gold); /* Thicker gold border around image */
}

.feedback-item-container label { /* Apply to all labels inside feedback item */
    font-size: 1.1em; /* Make labels slightly larger */
    font-weight: 600; /* Medium bold */
    color: var(--text-dark); /* Ensure it's clearly visible */
    margin-bottom: 10px;
}

.feedback-item-container .star-rating {
    margin-top: 10px; /* Add space above stars */
    margin-bottom: 25px; /* More space below stars */
}

.feedback-item-container textarea {
    min-height: 150px; /* Even larger comment box */
    padding: 15px;
    border-radius: 15px;
    background: var(--primary-dark); /* Darker background for textarea */
    color: var(--text-light);
    border: 1px solid var(--accent-gold); /* Gold border for textarea */
}

.feedback-item-container textarea:focus {
    box-shadow: 0 0 0 4px rgba(243, 156, 18, 0.6), inset 0 2px 5px var(--shadow-subtle); /* More prominent gold glow */
}

/* Adjustments for the dropdown (if you re-introduce it) */
.feedback-item-container select.feedback-select { /* Renamed for clarity */
    width: 90%; /* Make select wider within its container */
    max-width: 400px; /* Max width for consistency */
    margin-bottom: 20px; /* More space below select */
}

/* Style for the new feedback code section */
.feedback-code-section {
    background-color: var(--primary-medium);
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px var(--shadow-medium);
    border: 1px solid var(--border-subtle);
    text-align: center;
}

.feedback-code-section h2 {
    color: var(--accent-gold);
    margin-bottom: 20px;
}

.feedback-code-section input[type="text"] {
    max-width: 300px;
    margin-left: auto;
    margin-right: auto;
    display: block; /* To center the input */
}
.feedback-code-section button {
    margin-top: 15px;
}
    </style>

</head>
<body>
    <?php if (isset($_SESSION['admin'])): ?>
        <a href="admin.php" class="admin-btn">Admin Dashboard</a>
    <?php endif; ?>

    <div class="container">
  <h1 style="color:white; text-shadow: 2px 2px 4px rgb(0, 0, 0); font-size: 2.5em;">Submit Product</h1>
        <?php if ($errors): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?=htmlspecialchars($e)?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($success_message): ?>
            <div class="success success-dialogue">
                <?=htmlspecialchars($success_message)?>
                <button onclick="window.location.href='index.php'">Okay</button>
            </div>
        <?php endif; ?>

        <?php
        // Logic to decide which form to show:
        // 1. If success_message is shown, don't show any form.
        // 2. If no products are selected for feedback, show the product selection form.
        // 3. If products are selected but the code is not entered, show the code validation form.
        // 4. Otherwise, show the feedback submission form for selected products.
        if (!$success_message):
            if (empty($selected_flavors_for_feedback)): // Show initial product selection
        ?>
            <form method="post" novalidate>
                <div class="product-selection-section">
                    <h2>Select Products to Provide Feedback For</h2>
                    <?php if (empty($flavors_by_category)): ?>
                        <p style="text-align: center; font-style: italic;">No products available for feedback yet.</p>
                    <?php else: ?>
                        <div class="product-checkboxes-grid">
                            <?php foreach ($flavors_by_category as $category => $categoryFlavors): ?>
                                <?php foreach ($categoryFlavors as $flavor): ?>
                                    <label class="product-checkbox-card">
                                        <input type="checkbox" name="selected_product_ids[]" value="<?=htmlspecialchars($flavor['id'])?>"
                                               <?= in_array($flavor['id'], $selected_flavors_for_feedback) ? 'checked' : '' ?>>
                                        <?php if (!empty($flavor['image_path'])): ?>
                                            <img src="<?=htmlspecialchars($flavor['image_path'])?>" alt="<?=htmlspecialchars($flavor['flavor_name'])?>">
                                        <?php else: ?>
                                            <div style="width: 100px; height: 100px; background-color: #ccc; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 0.8em; color: #555; margin-bottom: 10px;">No Image</div>
                                        <?php endif; ?>
                                        <span class="product-name"><?=htmlspecialchars($flavor['flavor_name'])?></span>
                                        <span class="product-category">(<?=htmlspecialchars($category)?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit">Continue to Feedback</button>
                    <button type="button" class="back-from-register-btn" onclick="window.history.back()">Back</button>
                    <style>
                      .back-from-register-btn {
                        background-color:#82D1D1; /* Blue background */
                        color: white; /* White text */
                        padding: 10px 20px; /* Adjust padding for size */
                        font-size: 18px; /* Set font size */
                        border: none;
                        border-radius: 5px;
                        cursor: pointer;
                      }

                      .back-from-register-btn:hover {
                        background-color:rgb(38, 73, 73); /* Darker blue on hover */
                      }
                    </style>

                </div>
            </form>
        <?php elseif (!$feedback_code_entered): // Show code validation form if products selected but code not entered ?>
            <form method="post" novalidate>
                <div class="feedback-code-section">
                    <h2>Enter Feedback Code</h2>

                    <p>To ensure genuine feedback, please enter the feedback code provided by the establishment.</p>
                    <label for="feedback_code">Feedback Code:</label>
                    <input type="text" id="feedback_code" name="feedback_code" required placeholder="e.g., qnteahouse">
                    <button type="submit" name="validate_code">Validate Code</button>
                    <small><a href="?clear_selection=1" style="color: var(--accent-gold); text-decoration: none; display: block; margin-top: 15px;">(Change product selection)</a></small>
                </div>
            </form>
        <?php else: // Show feedback form for selected products if code is validated ?>
            <form method="post">
                <h2>Provide Feedback for Selected Products</h2>
                <div class="selected-items-display">
                    You are providing feedback for: **
                    <?php
                    $displayedProducts = [];
                    foreach ($selected_flavors_for_feedback as $f_id) {
                        if (isset($all_flavors_by_id[$f_id])) {
                            $displayedProducts[] = htmlspecialchars($all_flavors_by_id[$f_id]['flavor_name']);
                        }
                    }
                    echo implode(', ', $displayedProducts);
                    ?>**
                    <br><small><a href="?clear_selection=1" style="color: var(--accent-gold); text-decoration: none;">(Change selection)</a></small>
                </div>

                <?php
                // Loop through selected flavor IDs to generate feedback fields
                foreach ($selected_flavors_for_feedback as $flavor_id):
                    if (isset($all_flavors_by_id[$flavor_id])):
                        $flavor_data = $all_flavors_by_id[$flavor_id];
                        $unique_id = htmlspecialchars($flavor_id); // Use flavor_id for unique IDs
                ?>
                    <div class="feedback-item-container">
                        <h3 class="category"><?=htmlspecialchars($flavor_data['flavor_name'])?> (<?=htmlspecialchars($flavor_data['category'])?>)</h3>
                        <?php if (!empty($flavor_data['image_path'])): ?>
                            <img src="<?=htmlspecialchars($flavor_data['image_path'])?>" alt="<?=htmlspecialchars($flavor_data['flavor_name'])?>" class="flavor-image">
                        <?php else: ?>
                            <p>No image available for <?=htmlspecialchars($flavor_data['flavor_name'])?></p>
                        <?php endif; ?>

                        <label>Star Rating *</label>
                        <div class="star-rating" aria-label="Star Rating for <?=htmlspecialchars($flavor_data['flavor_name'])?>">
                            <?php for ($i = 5; $i >= 1; $i--):
                                $checked = (isset($_POST['rating_'.$unique_id]) && intval($_POST['rating_'.$unique_id]) === $i) ? 'checked' : '';
                            ?>
                                <input type="radio" id="star_<?=$unique_id?>_<?=$i?>" name="rating_<?=$unique_id?>" value="<?=$i?>" <?=$checked?> required>
                                <label for="star_<?=$unique_id?>_<?=$i?>" title="<?=$i?> stars">&#9733;</label>
                            <?php endfor; ?>
                        </div>

                        <label for="comment_<?=$unique_id?>">Comment (optional)</label>
                        <textarea id="comment_<?=$unique_id?>" name="comment_<?=$unique_id?>" placeholder="Your detailed comments about this product..."><?=htmlspecialchars($_POST['comment_'.$unique_id] ?? '')?></textarea>
                    </div>
                <?php
                    endif; // End if flavor_data exists
                endforeach; // End foreach selected_flavors_for_feedback
                ?>

                <div class="suggestion-box">
                    <label for="suggestion_text">Suggestions or Additional Comments:</label>
                    <textarea id="suggestion_text" name="suggestion_text" placeholder="Enter any general suggestions or additional comments here..."></textarea>
                    <button type="submit" name="feedback_submission">Submit Feedback</button>
                </div>
            </form>
        <?php
            endif; // End if/else for showing product selection, code validation, or feedback form
        endif; // End if not success_message
        ?>

        <?php
        // This part handles clearing the selection if the "Change selection" link is clicked
        if (isset($_GET['clear_selection']) && $_GET['clear_selection'] == 1) {
            unset($_SESSION['selected_flavors_for_feedback']);
            unset($_SESSION['feedback_code_entered']); // Also clear the code validation flag
            echo '<script>window.location.href = "main.php";</script>'; // Redirect to main.php
            exit;
        }
        ?>

        <?php if (isset($_SESSION['admin'])): ?>
            <div class="admin-section">
                <h2>Manage Products</h2>

                <form method="POST" action="add_flavor.php" class="add-product-form">
                    <label for="category">Category:</label>
                    <input type="text" id="category" name="category" required placeholder="e.g., Coffee, Ice Cream">
                    <label for="flavor_name">Flavor Name:</label>
                    <input type="text" id="flavor_name" name="flavor_name" required placeholder="e.g., Espresso, Vanilla Bean">
                     <label for="image_path">Image Path (e.g., images/chickennuggets.jpg):</label>
                    <input type="text" id="image_path" name="image_path" placeholder="e.g., images/product_name.jpg">
                    <button type="submit">Add Product</button>
                </form>

                <?php if (!empty($all_flavors_by_id)): ?>
                    <table class="product-list">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Flavor Name</th>
                                <th>Image Path</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_flavors_by_id as $flavor_id => $flavor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($flavor_id) ?></td>
                                    <td><?= htmlspecialchars($flavor['category']) ?></td>
                                    <td><?= htmlspecialchars($flavor['flavor_name']) ?></td>
                                    <td><?= htmlspecialchars($flavor['image_path']) ?></td>
                                    <td>
                                        <a href="delete_flavor.php?id=<?= htmlspecialchars($flavor_id) ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this flavor?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No flavors found in the database.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>