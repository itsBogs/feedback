<?php
session_start();
require 'db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    if ($fullname === '') $errors[] = 'Fullname is required.';
    if ($telephone === '') $errors[] = 'Telephone (password) is required.';

    if (empty($errors)) {
       $stmt = $pdo->prepare('SELECT customer_id FROM customers WHERE fullname = ? AND telephone = ? LIMIT 1');
        $stmt->execute([$fullname, $telephone]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['customer_id'] = $user['customer_id'];

            header('Location: main.php');
            exit;
        } else {
            $errors[] = 'Invalid fullname or telephone number.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - Cafeteria Feedback</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <style>
/* Modernized Color Palette & Variables (Copied from your provided design) */

:root {
    /* REVISED COLOR PALETTE FOR BUBBLE TEA THEME */
    --primary-dark: #3F474A; /* Dark charcoal/brown-grey, deep background base, hinting at the black board */
    --primary-medium: #556267; /* Slightly lighter blue-grey, like a deeper shadow/contrast to the mint */
    --primary-light: #89B6A5; /* A muted, desaturated mint green - used for subtle accents and backgrounds */

    --accent-mint: #f39c12; /* The bright, light mint from the bubble tea, used for highlights/buttons */
    --accent-mint-dark: #82D1D1; /* A darker shade of the mint for hover states */

    --accent-caramel: #D4A87C; /* The warm, light caramel/brown from the bubble tea for secondary accents */
    --accent-caramel-dark: #B9875A; /* Darker caramel for hover states or stronger accents */

    --text-light: #ECF0F1; /* Light grey for primary text */
    --text-dark: #3F474A; /* Dark text on light backgrounds (now matching primary-dark for consistency) */

    --background-overlay: rgba(0, 0, 0, 0.5); /* Darker, slightly opaque overlay */
    --border-subtle: rgba(236, 240, 241, 0.2); /* Subtle light border */
    --error-red: #E74C3C; /* Brighter error red (standard) */
    --success-green: #27AE60; /* Brighter success green (standard) */
    --shadow-subtle: rgba(0, 0, 0, 0.2);
    --shadow-medium: rgba(0, 0, 0, 0.4);
    --shadow-strong: rgba(0, 0, 0, 0.6);
    /* Removed background-color: aliceblue; from :root as it conflicts with body background */
}

body {
    margin: 0;
    padding: 0;
    background-image: url('main1.jpg'); /* Make sure this path is correct! */
    background-color: var(--primary-dark); /* Fallback color for the background */
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    font-family: 'Roboto', sans-serif;
    color: var(--text-light);
    display: flex;
    justify-content: center; /* Center the login form horizontally */
    align-items: center; /* Center the login form vertically */
    min-height: 100vh; /* Full viewport height */
    padding: 30px 15px;
    box-sizing: border-box;
    line-height: 1.6;
    position: relative; /* For positioning of the Admin button */
}

/* Changed .login-container to .container to match your HTML */
.container {
    width: 100%;
    max-width: 450px; /* Smaller max-width for login form */
    background: var(--background-overlay); /* Dark, translucent layer over the image */
    border-radius: 20px;
    padding: 40px; /* Larger padding for more space */
    box-shadow: 0 10px 30px var(--shadow-strong);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-subtle);
    animation: fadeIn 0.8s ease-out;
    display: flex;
    flex-direction: column;
    gap: 25px; /* Added gap between elements */
    box-sizing: border-box;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

h1 {
    font-family: 'Playfair Display', serif;
    text-align: center;
    font-weight: 700;
    font-size: 3em; /* Larger heading for login */
    color: var(--accent-mint); /* Changed to mint */
    text-shadow: 1px 1px 4px rgba(0,0,0,0.7);
    letter-spacing: 0.5px;
    margin-bottom: 30px; /* Larger margin at the bottom */
    border-bottom: 2px solid var(--accent-mint); /* Changed to mint */
    padding-bottom: 10px;
    display: inline-block;
    margin-left: auto;
    margin-right: auto;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 1.1em;
    color: var(--text-light);
}

input[type="text"],
input[type="password"] { /* Added password input */
    width: 100%;
    padding: 14px;
    /* margin-bottom: 25px; removed to control spacing with gap on container */
    border-radius: 12px;
    border: 1px solid var(--primary-light); /* Light mint border */
    background: var(--primary-medium); /* Darker background */
    color: var(--text-light);
    font-size: 1.05em;
    transition: all 0.3s ease;
    box-sizing: border-box;
    box-shadow: inset 0 2px 5px var(--shadow-subtle);
}

input[type="text"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: var(--accent-mint); /* Mint border on focus */
    box-shadow: 0 0 0 3px rgba(181, 255, 252, 0.4), inset 0 2px 5px var(--shadow-subtle); /* Mint glow */
    background-color: var(--primary-dark); /* Slightly darker on focus */
}

button[type="submit"] { /* Made specific to submit button */
    width: fit-content;
    padding: 18px 35px;
    margin: 30px auto 10px; /* Adjusted margin to fit the gap */
    display: block;
    background-color: var(--accent-caramel); /* Changed to caramel for login button */
    color: var(--text-dark); /* Dark text on caramel background */
    font-weight: 700;
    font-size: 1.2em;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px var(--shadow-medium);
    text-transform: uppercase;
    letter-spacing: 1px;
    outline: none;
}

button[type="submit"]:hover {
    background-color: var(--accent-caramel-dark); /* Darker caramel on hover */
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 12px 25px var(--shadow-strong);
}

.errors, .success {
    margin-bottom: 0; /* Remove margin-bottom as container gap handles spacing */
    padding: 18px;
    border-radius: 12px;
    font-weight: 700;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    animation: slideIn 0.6s ease-out;
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

/* Admin button outside the container */
.admin-btn {
    position: fixed; /* Fixed position relative to viewport */
    top: 20px;
    right: 20px;
    background: var(--accent-mint); /* Changed to mint for admin button */
    color: var(--text-dark); /* Dark text on mint */
    padding: 12px 22px;
    border-radius: 12px;
    font-weight: bold;
    text-decoration: none;
    box-shadow: 0 6px 18px var(--shadow-strong);
    transition: background-color 0.3s, transform 0.3s, box-shadow 0.3s;
    z-index: 1000;
    font-size: 0.95em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer; /* Added cursor pointer */
}

.admin-btn:hover {
    background: var(--accent-mint-dark); /* Darker mint on hover */
    transform: scale(1.08);
    box-shadow: 0 10px 30px var(--shadow-strong);
}

/* Back button styles */
.back-button {
    text-align: center;
    /* margin-top: 25px; removed as container gap handles this */
    width: 100%;
    /* max-width: 400px; */ /* Not needed as button width is fit-content */
    margin-left: auto;
    margin-right: auto;
}

.back-button button {
    background: var(--primary-light); /* Muted mint color */
    color: var(--text-dark); /* Dark text for contrast */
    box-shadow: none;
    padding: 12px 25px;
    font-size: 1em;
    width: fit-content; /* Ensure it's not full width */
    margin: 0 auto; /* Center the back button */
    border: none; /* Make sure it doesn't have default button border */
    border-radius: 15px; /* Consistent border-radius */
    cursor: pointer;
    transition: all 0.3s ease;
}

.back-button button:hover {
    background: var(--primary-medium); /* Slightly darker on hover */
    transform: translateY(-3px);
    box-shadow: 0 4px 10px var(--shadow-medium); /* Adjusted shadow */
}
button[type="submit"] {
    width: fit-content;
    padding: 18px 35px;
    margin: 30px auto 10px; /* Adjusted margin to fit the gap */
    display: block;
    background-color: #0056b3; /* Primary button color */
    color: var(--text-dark);
    font-weight: 700;
    font-size: 1.2em;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px var(--shadow-medium);
    text-transform: uppercase;
    letter-spacing: 1px;
    outline: none;
                font-family: 'Playfair Display', serif;
                color: white;

}

button[type="submit"]:hover {
    background: #a93226; /* Darker red on hover */
    transform: scale(1.08); /* More noticeable scale */
    box-shadow: 0 10px 30px var(--shadow-strong);
}
  </style>
</head>
<body>
  <div class="container">
  <h1 style="color:white; text-shadow: 2px 2px 4px rgb(0, 0, 0); font-size: 2.5em;">Login</h1>
    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?=htmlspecialchars($err)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <label for="fullname">Fullname</label>
      <input type="text" id="fullname" name="fullname" required placeholder="Your fullname" value="<?=htmlspecialchars($_POST['fullname'] ?? '')?>" />

      <label for="telephone">Telephone (Password)</label>
      <input type="password" id="telephone" name="telephone" required placeholder="Your telephone number" />

      <button type="submit">Login</button>
    </form>

    <div class="back-button">
      <button onclick="window.location.href='index.php'">Back</button>
    </div>
  </div>
</body>
</html>