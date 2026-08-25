<?php
session_start();
include('db.php');

$login_error = '';
$login_success = false;
$feedback_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login Form
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // hashed password for more security 
        $hashed_password = sha1($password);

        // checking the user's credentials
        $query = "SELECT ID, Username, Password, is_admin FROM users WHERE Email = ?";
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                error_log('Entered Password Hash: ' . $hashed_password);
                error_log('Stored Password Hash: ' . $row['Password']);


                // Compare the hashed password with the stored password
                if ($hashed_password === $row['Password']) {
                    $_SESSION['user_id'] = $row['ID'];
                    $_SESSION['username'] = $row['Username'];
                    $_SESSION['is_admin'] = $row['is_admin'];
                    $login_success = true;
                } else {
                    $login_error = "Incorrect password.";
                }
            } else {
                $login_error = "No account found with that email.";
            }
            $stmt->close();
        }
    }

    // Submit Review Form Handling
    if (isset($_POST['submit_review']) && isset($_SESSION['user_id'])) {
        $driveThruName = $_POST['driveThruName'];
        $review = $_POST['review'];
        $userId = $_SESSION['user_id'];

        // Query to get the drive-thru ID
        $driveThruQuery = "SELECT drive_thru_ID FROM `drive-thrus` WHERE name = ?";
        if ($stmt = $conn->prepare($driveThruQuery)) {
            $stmt->bind_param('s', $driveThruName);
            $stmt->execute();
            $stmt->bind_result($driveThruId);
            $stmt->fetch();
            $stmt->close();
        }

        if ($driveThruId) {
            // Insert the review into the database
            $stmt = $conn->prepare("INSERT INTO reviews (user_ID, drive_thru_id, Drive_Thru_Name, Review) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $userId, $driveThruId, $driveThruName, $review);
            if ($stmt->execute()) {
                $feedback_message = "Review submitted successfully!";
            } else {
                $feedback_message = "Error submitting the review.";
            }
            $stmt->close();
        } else {
            $feedback_message = "Drive-thru name not found.";
        }
    }

    // Logout Handling
    if (isset($_POST['logout'])) {
        session_unset();
        session_destroy();
        header("Location: home.html");
        exit();
    }

    // Delete Review Handling (Only Admins or the Review Owner)
    if (isset($_POST['delete_review']) && isset($_SESSION['user_id'])) {
        $review_id = $_POST['review_id'];
        if ($_SESSION['is_admin'] == 1) {
            // Admins can delete any review
            $delete_query = "DELETE FROM reviews WHERE Review_ID = ?";
        } else {
            // Regular users can only delete their own reviews
            $delete_query = "DELETE FROM reviews WHERE Review_ID = ? AND user_ID = ?";
        }

        if ($stmt = $conn->prepare($delete_query)) {
            if ($_SESSION['is_admin'] == 1) {
                $stmt->bind_param("i", $review_id);
            } else {
                $stmt->bind_param("ii", $review_id, $_SESSION['user_id']);
            }
            if ($stmt->execute()) {
                $feedback_message = "Review deleted successfully!";
            } else {
                $feedback_message = "Error deleting the review.";
            }
            $stmt->close();
        }
    }
    // Handle the form submission for updating a review
    if (isset($_POST['submit_update']) && isset($_POST['review_id'])) {
        $review_id = $_POST['review_id'];
        $updated_review = $_POST['updated_review'];

        // Update the review in the database only if the review belongs to the logged-in user
        if ($_SESSION['user_id'] == $_POST['user_id'] || $_SESSION['is_admin'] == 1) {
            $update_query = "UPDATE reviews SET Review = ? WHERE Review_ID = ? AND user_ID = ?";
            if ($stmt = $conn->prepare($update_query)) {
                $stmt->bind_param("sii", $updated_review, $review_id, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $feedback_message = "Review updated successfully!";
                } else {
                    $feedback_message = "Error updating the review.";
                }
                $stmt->close();
            }
        } else {
            $feedback_message = "You can only update your own reviews.";
        }
    }
}

// Query to fetch reviews along with drive-thru names
$query = "SELECT r.Review_ID, r.Drive_Thru_Name, r.Review, u.Username, r.user_ID
          FROM reviews r
          JOIN users u ON r.user_ID = u.ID 
          ORDER BY r.Review_ID DESC";
$result = $conn->query($query);

?>

<!-- HTML Part -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Reviews - On the Go</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="MainStyles.css">

    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f9f9f9;
        }

        .header-title {
            font-size: 3rem;
            margin-top: 20px;
            color: #003366;
            text-align: center;
        }

        form {
            margin: 20px auto;
            padding: 20px;
            max-width: 600px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        form h3 {
            margin-bottom: 20px;
            font-size: 1.8rem;
            color: #003366;
            font-weight: 600;
        }

        form label {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        form input,
        form textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            background: #f8f8f8;
            transition: all 0.3s ease;
        }

        form input:focus,
        form textarea:focus {
            border-color: #003366;
            box-shadow: 0 0 8px rgba(0, 51, 102, 0.3);
            outline: none;
        }

        form button {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background-color: #003366;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }


        form button:hover {
            background-color: #00509e;
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.3);
        }

        .error {
            color: red;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .review {
            margin: 20px auto;
            padding: 15px;
            max-width: 600px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .review p {
            margin: 5px 0;
            font-size: 1rem;
            color: #333;
        }

        .review p strong {
            color: #003366;
        }

        .review button {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            background-color: #cc0000;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .review button:hover {
            background-color: #ff0000;
        }

        h2 {
            font-size: 2rem;
            color: #003366;
            margin-top: 40px;
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-light py-3 shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#">On The Go</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="home.html">Home</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="driveThruDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                Drive-Thrus
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="driveThruDropdown">
                                <li><a class="dropdown-item" href="drinks-desserts.html">Drinks and Desserts</a></li>
                                <li><a class="dropdown-item" href="food.html">Food</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reviews.html">Reviews</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about.html">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.html">Contact Us</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <br>

    <div class="container">
        <h1 class="header-title">Drive-Thrus Reviews</h1>
        <br>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login Form if the user is not logged in -->
            <form method="POST" action="reviews.php">
                <h3>Login to Post a Review</h3>
                <?php if (!empty($login_error)): ?>
                    <p class="error"><?= htmlspecialchars($login_error); ?></p>
                <?php endif; ?>
                <label for="email">Email:</label>
                <input type="text" name="email" required>
                <label for="password">Password:</label>
                <input type="password" name="password" required>
                <button type="submit" name="login">Login</button>
            </form>
        <?php else: ?>
            <!-- Review Submission Form -->
            <form method="POST" action="reviews.php">
                <h3>Submit Your Review</h3>
                <label for="driveThruName">Drive Thru Name:</label>
                <input type="text" name="driveThruName" required>
                <label for="review">Review:</label>
                <textarea name="review" placeholder="Write your review here..." required></textarea>
                <button type="submit" name="submit_review">Submit Review</button>
            </form>

            <!-- Logout Form -->
            <form method="POST" action="reviews.php">
                <button type="submit" name="logout">Logout</button>
            </form>
        <?php endif; ?>


        <!-- Feedback message -->
        <?php if ($feedback_message): ?>
            <p class="feedback"><?= htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>

        <h2>Reviews</h2>
        <!-- Display Reviews -->
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="review">
                    <p><strong><?= htmlspecialchars($row['Username']); ?></strong></p>
                    <p><em>Drive Thru: <?= htmlspecialchars($row['Drive_Thru_Name']); ?></em></p>
                    <p><?= nl2br(htmlspecialchars($row['Review'])); ?></p>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_id'] == $row['user_ID'] || $_SESSION['is_admin'] == 1): ?>
                            <!-- Admin or Review Owner can delete -->
                            <form method="POST" action="reviews.php" onsubmit="return confirmDelete();">
                                <input type="hidden" name="review_id" value="<?= $row['Review_ID']; ?>">
                                <button type="submit" name="delete_review">Delete Review</button>
                            </form>

                            <!-- Admin or Review Owner can update -->
                            <form method="POST" action="reviews.php">
                                <input type="hidden" name="review_id" value="<?= $row['Review_ID']; ?>">
                                <input type="hidden" name="user_id" value="<?= $row['user_ID']; ?>"> 
                                <textarea name="updated_review" required placeholder="Update your review"><?= htmlspecialchars($row['Review']); ?></textarea>
                                <button type="submit" name="submit_update" > Update Review</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>

        <script>
            // Confirmation alert for Delete action
            function confirmDelete() {
                if (confirm("Are you sure you want to delete this review?")) {
                    alert("Review deleted successfully.");
                    return true; 
                } else {
                    return false; 
                }
            }

        </script>
    </div>
    <footer>
        <div class="container text-center">
            <br>
            <h5>Connect with</h5>
            <h5 class="display-6 fw-bold">On The Go</h5>
            <br>
            <div class="d-flex justify-content-center align-items-center my-2">
                <span class="material-symbols-outlined me-2">call</span>
                <p class="mb-0">+966 53 720 2289</p>
            </div>
            <div class="d-flex justify-content-center align-items-center my-2">
                <span class="material-symbols-outlined me-2">mail</span>
                <p class="mb-0">OntheGo@gmail.com</p>
            </div>
            <p class="mt-3">© 2024 On the Go. All Rights Reserved.</p>
        </div>
    </footer>

</body>

</html>