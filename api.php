<?php
header('Content-Type: application/json');
// Include database configuration
global $link;
require 'config.php'; // Assuming this file contains your database connection setup

// Ensure the database connection exists
if (!$link) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Define a function to verify username and password, and return user info if valid
function getUserByCredentials($link, $username, $password) {
    // Use a prepared statement to select the user by username
    $query = "SELECT username, password, Name, balance, budget FROM users WHERE username = ?";
    $stmt = mysqli_prepare($link, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username); // "s" specifies the type as string
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            // Verify the provided password with the hashed password in the database
            if (password_verify($password, $user['password'])) {
                // Remove the hashed password before returning the data
                unset($user['password']);
                return $user; // Return the user details
            } else {
                return null; // Password does not match
            }
        } else {
            return null; // Username not found
        }
    } else {
        return false; // Query preparation error
    }
}

// Define a function to add a new user
function addUser($link, $username, $password, $name, $balance, $budget) {
    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Use a prepared statement to prevent SQL injection
    $query = "INSERT INTO users (username, password, Name, balance, budget) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($link, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssdd", $username, $hashedPassword, $name, $balance, $budget);

        // Execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            return true; // Insertion successful
        } else {
            return false; // Insertion failed
        }
    } else {
        return false; // Query preparation error
    }
}

// Define a function to update the user's current balance
function updateUserBalance($link, $username, $balance) {
    // Use a prepared statement to prevent SQL injection
    $query = "UPDATE users SET balance = ? WHERE username = ?";
    $stmt = mysqli_prepare($link, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ds", $balance, $username);

        // Execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            return true; // Update successful
        } else {
            return false; // Update failed
        }
    } else {
        return false; // Query preparation error
    }
}

// Handle requests based on the method and input
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch user by username and password
    if (isset($_GET['username'], $_GET['password'])) {
        $username = $_GET['username'];
        $password = $_GET['password'];

        // Call the function to verify credentials and fetch user info
        $user = getUserByCredentials($link, $username, $password);

        if ($user === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to execute query.']);
        } elseif ($user === null) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Invalid username or password.']);
        } else {
            http_response_code(200);
            echo json_encode($user);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['action']) == 'updateBalance') {
        // Update balance logic
        if (isset($data['username'], $data['balance']) && !empty($data['username'])) {
            $username = $data['username'];
            $balance = (float)$data['balance']; // Convert to float

            $success = updateUserBalance($link, $username, $balance);

            if ($success) {
                http_response_code(200);
                echo json_encode(['message' => 'Balance updated successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update balance.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input. Username and balance are required.']);
        }
    } elseif (
        isset($data['username'], $data['password'], $data['name'], $data['balance'], $data['budget']) &&
        !empty($data['username']) && !empty($data['password']) && !empty($data['name'])
    ) {
        // Add new user logic
        $username = $data['username'];
        $password = $data['password'];
        $name = $data['name'];
        $balance = (float)$data['balance']; // Convert to float
        $budget = (float)$data['budget']; // Convert to float

        // Call the function to add a user
        $success = addUser($link, $username, $password, $name, $balance, $budget);

        if ($success) {
            http_response_code(201);
            echo json_encode(['message' => 'User added successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add user.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input. Please provide all required fields.']);
    }
} else {
    // For unsupported HTTP methods
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
}
?>