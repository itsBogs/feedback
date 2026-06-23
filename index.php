<?php
session_start();
require 'db.php';

$errors = [];
$success_message = '';
$show_register_form = false; // Changed to false initially

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = $_POST['gender'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');

    if ($fullname === '') $errors[] = 'Fullname is required';
    if ($age < 1) $errors[] = 'Valid age is required';
    if (!in_array($gender, ['Male', 'Female', 'Other'])) $errors[] = 'Select a valid gender';
    if ($telephone === '') $errors[] = 'Telephone is required';
    if (strlen($telephone) != 11 || substr($telephone, 0, 2) != '09') {
        $errors[] = 'Telephone number must be 11 digits and start with 09';
    }

    // Check if an account with this telephone number already exists
    $stmt_check = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE telephone = ?');
    $stmt_check->execute([$telephone]);
    if ($stmt_check->fetchColumn() > 0) {
        echo "<script>alert('An account with this telephone number already exists.');</script>";
        // Keep the form open to show errors if the number exists
        $show_register_form = true;
    } else {
        if (count($errors) === 0) {
            $stmt = $pdo->prepare('INSERT INTO customers (fullname, age, gender, telephone) VALUES (?, ?, ?, ?)');
            $stmt->execute([$fullname, $age, $gender, $telephone]);
            $_SESSION['customer_id'] = $pdo->lastInsertId();
            $success_message = 'Account created successfully!';
            // Add a JavaScript alert for success and then redirect/hide the form
            echo "<script>alert('Account successfully created!'); window.location.href = 'index.php';</script>";
            exit; // Stop further execution after redirect
        } else {
            // If there are validation errors, keep the form open
            $show_register_form = true;
        }
    }
} elseif (isset($_GET['skip'])) {
    $_SESSION['customer_id'] = null;
    header('Location: main.php');
    exit;
} elseif (isset($_GET['login'])) {
    header('Location: login.php');
    exit;
} elseif (isset($_GET['register'])) { // Set show_register_form to true when the register parameter is present
    $show_register_form = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="web.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet"
    />
    <style>

    /* Styles for the buttons at the bottom of the main section */
    .button-group {
        display: flex;
        flex-wrap: wrap; /* Allows buttons to wrap to the next line */
        justify-content: center; /* Centers buttons horizontally */
        gap: 10px; /* Space between buttons */
        margin-top: 20px;
        margin-right: 200px;
    }

    .button-group button {
        padding: 15px 25px; /* Consistent padding for all buttons */
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 16px;
        cursor: pointer;
        border-radius: 5px;
        border: none;
        color: white;

        transition: background-color 0.3s ease, transform 0.1s ease, box-shadow 0.1s ease; /* Smooth transitions */
        /* Removed flex-grow and fixed width for a more natural length */
        flex-basis: auto; /* Allow buttons to size based on content */
        min-width: 120px; /* Ensure a minimum width if content is short */
    }

    /* Adjust specific buttons for the desired layout */
    .button-group button:nth-child(1),
    .button-group button:nth-child(2) {
        /* On larger screens, these will be side-by-side */
        /* Flex-basis: 0; and flex-grow: 1; would make them equally wide.
            Keeping flex-basis: auto; will make them naturally size to content.
            We can use max-width if they get too big. */
        max-width: calc(50% - 5px); /* To allow two buttons with gap */
    }

   .button-group button:nth-child(3) {
        /* This is the key change for 50% width and centering */
        width: 350px; /* Sets the width to 50% */
        margin-top: 10px; /* Space above the third button */
        /* To center it, since it's a flex item on its own line: */
        margin-left: auto;
        margin-right: auto;
    }
    .button-group button:hover {
        opacity: 0.9; /* Subtle opacity change on hover */
        transform: translateY(-1px); /* Slight lift on hover */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Enhanced shadow on hover */
    }

    .button-group button:active { /* "Pressed" effect when clicked */
        transform: translateY(2px); /* Moves the button down */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); /* Reduced shadow to simulate press */
    }

    .button-group button:nth-child(1) {
        background-color:  #007bff; /* Green for Login */
    }
    .button-group button:nth-child(1):hover {
        background-color: #0056b3;
        background: #a93226; /* Darker red on hover */
            transform: scale(1.08); /* More noticeable scale */
            box-shadow: 0 10px 30px var(--shadow-strong);
    }

    .button-group button:nth-child(2) {
        background-color: #007bff; /* Blue for Create Account */
    }
    .button-group button:nth-child(2):hover {
        background-color: #0056b3;
        background: #a93226; /* Darker red on hover */
            transform: scale(1.08); /* More noticeable scale */
            box-shadow: 0 10px 30px var(--shadow-strong);
    }

    .button-group button:nth-child(3) {
        background-color: #007bff; /* Red for Leave Feedback */
    }
    .button-group button:nth-child(3):hover {
        background-color: #0056b3;
        background: #a93226; /* Darker red on hover */
            transform: scale(1.08); /* More noticeable scale */
            box-shadow: 0 10px 30px var(--shadow-strong);
    }
</style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const createAccountButton = document.querySelector('.button-group button:nth-child(2)');
            const registerForm = document.querySelector('.register-form');
            const registerBackdrop = document.querySelector('.register-backdrop');

            if (createAccountButton && registerForm && registerBackdrop) {
                createAccountButton.addEventListener('click', (event) => {
                    event.preventDefault(); // Prevent default link behavior
                    registerForm.classList.add('show');
                    registerBackdrop.classList.add('show');
                });

                registerBackdrop.addEventListener('click', (event) => {
                    if (event.target === registerBackdrop) {
                        registerForm.classList.remove('show');
                        registerBackdrop.classList.remove('show');
                    }
                });
            } else {
                console.error('One or more elements not found.');
            }
        });
    </script>
</head>
<body>
    <div class="header">
        <header class="header_content">
            <a href="#logo" class="logo">
                <img
                    src="mainlogo.png"
                    alt="logoimage"
                    class="logo-icon"
                />
                <span class="logo-text">Q'n Tea House </span>
            </a>

            
            <button type="button" class="menu-button">
                <img
                    src="Menu_vector_symbol_black_shape_png_image-removebg-preview.png"
                    alt="menuButton"
                    class="menu-icon"
                />
            </button>
        </header>
    </div>

    <div class="content">
        <section class="main_section">
            <div class="content_left">
                <p class="section_label">Very proud to introduce</p>
                <h3 class="section_title">Brewed for You: <br> Help Us Make Every Sip Perfect</h3>
                <p class="section_description">
                    At Q’n Tea House, your taste matters. Each drink we serve is crafted with care — from the creamy classics to our bold, fruity twists.
                    We’re inviting you to share your feedback and let us know how we can make your milk tea experience even more enjoyable. Your opinion helps us grow, one cup at a time!
                </p>

                <div class="button-group">
                    <button onclick="window.location.href='login.php'">Login</button>
                    <button onclick="window.location.href='?register=1'">Create Account</button>
                    <button onclick="window.location.href='?skip=1'">Leave Feedback Without Account</button>
                </div>
            </div>

            <div class="content_right">
                <div class="image-container">
                    <img
                        src="az.jpg"
                        alt="sectionimage"
                        class="section-Image"
                    />
                </div>
            </div>
        </section>

      <?php if ($show_register_form): ?>
        <div class="register-backdrop show">
            <section class="register-form show">
  <h1 style="color:white; text-shadow: 2px 2px 4px rgb(0, 0, 0); font-size: 2.5em;">Create Account</h1>
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php
                // The PHP success message div is still there, but we're now using a JS alert
                // This div won't be shown because the page will redirect after the alert.
                if ($success_message): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <form method="post">
                    <div>
                        <label for="fullname">Full Name:</label>
                        <input type="text" id="fullname" name="fullname" required value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="age">Age:</label>
                        <input type="number" id="age" name="age" min="1" required value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (($_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (($_POST['gender'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="telephone">Telephone:</label>
                        <input type="tel" id="telephone" name="telephone" required value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="register_submit">Sign up</button>
                </form>
                <div class="register-form-bottom-links">
                    <p>Already have an account? <a href="login.php">Sign in</a></p>
                    <button type="button" class="back-from-register-btn" onclick="window.history.back()">Back</button>
                </div>
            </section>
        </div>

    <?php endif; ?>
    <div class="company-container">
       
        
    </div>

 <div class="feature-container">
    <div class="feature-content">
        <div class="main-info">
            <h2 class="main-title"style="color: black";>Our competitive advantage</h2>     


            <p class="main-description">
                Gather around our table for hearty meals and warm smiles! At our
                family cafeteria, every dish is made with love, creating the perfect
                atmosphere for laughter, connection, and cherished moments together.
            </p>
        </div>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="icon-container">
                    <img
                        src="Innovation_in_Logo_Design__Future-Ready-removebg-preview.png"
                        alt=""
                        class="feature-svg"
                    />
                </div>

                <div class="feature-info">
                    <div class="feature-title">Personalized Food Items</div>

                    <div class="feature-description">
                        We are provide Personalized food items enhance enjoyment, cater
                        to dietary needs, and promote healthier choices. They foster
                        creativity and make dining experiences memorable by reflecting
                        individual tastes and preferences.
                    </div>
                </div>
            </div>
            <div class="feature-card">
                <div class="icon-container">
                    <img
                        src="Premium_Vector___Costs_reduction__costs_cut__costs_optimization_business_concept-removebg-preview.png"
                        alt=""
                        class="feature-svg"
                    />
                </div>

                <div class="feature-info">
                    <div class="feature-title">Affordability</div>

                    <div class="feature-description">
                        We are provide Affordable food items make nutritious meals
                        accessible, promote family dining, and encourage variety without
                        breaking the bank. They support budget-friendly eating while
                        ensuring everyone can enjoy delicious options.
                    </div>
                </div>
            </div>
            <div class="feature-card">
                <div class="icon-container">
                    <img
                        src="Premium_Vector___Chef_logo_design_vector_template_restaurant_logo_silhouette_chef_vector_cooking_logo-removebg-preview.png"
                        alt=""
                        class="feature-svg"
                    />
                </div>

                <div class="feature-info">
                    <div class="feature-title">Experienced Chef</div>

                    <div class="feature-description">
                        We are also have, Our experienced chefs craft delicious,
                        home-style meals with passion and expertise. Their culinary
                        skills ensure every dish is flavorful and made with love,
                        bringing joy to every dining experience.
                    </div>
                </div>
            </div>
            <div class="feature-card">
                <div class="icon-container">
                    <img
                        src="Premium_Quality_Label__Quality__Label__Premium_PNG_and_Vector_with_Transparent_Background_for_Free_Download-removebg-preview.png"
                        alt=""
                        class="feature-svg"
                    />
                </div>

                <div class="feature-info">
                    <div class="feature-title">Quality Products</div>

                    <div class="feature-description">
                        We are providing, In our cafeteria takes pride in serving
                        high-quality, fresh ingredients in every dish. Each meal is
                        thoughtfully prepared to ensure you enjoy a delightful and
                        nutritious dining experience.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="testimonial-container">
        <div class="testimonial-content">
           <h2 class="testimonial-title" style="color: black;">Captured Momments</h2>
            <div class="testimonial-grid">
                <div class="testiimonial-card">
                    <div class="testimonial-text">
                       
                    </div>

                    <div class="testimonial-avatar">
                        <img src="test1.jpg" alt="" />
                    </div>

                    <div class="testimonial-details">
                        <h3 class="testiimonial-name">Thanks you</h3>

                        <p class="testiimonial-description">Customers</p>
                    </div>
                </div>
                <div class="testiimonial-card">
                    <div class="testimonial-text">
                        
                    </div>

                    <div class="testimonial-avatar">
                        <img src="test2.jpg" alt="" />
                    </div>

                    <div class="testimonial-details">
                        <h3 class="testiimonial-name">Thank you</h3>

                        <p class="testiimonial-description">Customers</p>
                    </div>
                </div>
                <div class="testiimonial-card">
                    <div class="testimonial-avatar">
                        <img
                            src="test3.jpg"
                            alt=""
                        />
                    </div>

                    <div class="testimonial-details">
                        <h3 class="testiimonial-name">Thank you</h3>

                        <p class="testiimonial-description">
                            Customers

                            
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-container">
        <div class="footer">
            <div class="footer-top">
                <div class="comp-logo">
                    <img src="mainlogo.png" alt="company logo" class="logo-svg">
                </div>
                <a href="" class="logo-link">
                    Q'n Tea House
                </a>
                <p class="filler-text">
                    Savor the moment, taste the tradition. Welcome to our family table.
                </p>
                <div class="social">
                    <a href=""><img src="boomboom.jpg" alt="facebook logo" class="social-icon"></a>
                    <a href=""><img src="instagram.svg" alt="instagram logo" class="social-icon"></a>
                    <a href=""><img src="twitter.svg" alt="twitter logo" class="social-icon"></a>
                </div>
            </div>
            <div class="footer-grid">
                <div class="footer-grid-col">
                    <h3 class="footer-grid-heading">Explore</h3>
                    <ul class="footer-links-list">
                        <li><a href="" class="footer-link">Home</a></li>
                        <li><a href="" class="footer-link">Menu</a></li>
                        <li><a href="" class="footer-link">Pricing</a></li>
                        <li><a href="" class="footer-link">About Us</a></li>
                        <li><a href="" class="footer-link">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-grid-col">
                    <h3 class="footer-grid-heading">Visit</h3>
                    <ul class="footer-links-list">
                        <li><a href="" class="footer-link">Our Locations</a></li>
                        <li><a href="" class="footer-link">Catering Services</a></li>
                        <li><a href="" class="footer-link">Takeout Options</a></li>
                    </ul>
                </div>
                <div class="footer-grid-col">
                    <h3 class="footer-grid-heading">Support</h3>
                    <ul class="footer-links-list">
                        <li><a href="" class="footer-link">FAQ</a></li>
                        <li><a href="" class="footer-link">Privacy Policy</a></li>
                        <li><a href="" class="footer-link">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-grid-col">
                    <h3 class="footer-grid-heading">Connect</h3>
                    <ul class="footer-links-list">
                        <li><a href="" class="footer-link">Newsletter</a></li>
                        <li><a href="" class="footer-link">Social Media</a></li>
                    </ul>
                </div>
            </div>
            <p class="footer-copyright">
                &copy; 2025 Q'n Tea House. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
//main