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

// Define a function to fetch all users
function getAllUsers($link) {
    $query = "SELECT * FROM users";
    $result = mysqli_query($link, $query);
    if ($result) {
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        return $users;
    } else {
        return false; // Query error
    }
}

// Define a function to fetch user details by username
function getUserByUsername($link, $username) {
    // Prevent SQL injection by using prepared statements
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($link, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username); // "s" specifies the type as string
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result); // Fetch the user row as an associative array
        } else {
            return null; // No user found
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

// Handle requests based on the method and input
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch user by username if provided, otherwise fetch all users
    if (isset($_GET['username'])) {
        $username = $_GET['username'];
        $user = getUserByUsername($link, $username);

        if ($user === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to execute query.']);
        } elseif ($user === null) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
        } else {
            http_response_code(200);
            echo json_encode($user);
        }
    } else {
        // If no username is provided, return all users as a fallback
        $users = getAllUsers($link);
        if ($users === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to execute query.']);
        } else {
            http_response_code(200);
            echo json_encode($users);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new user
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate the input fields
    if (
        isset($data['username'], $data['password'], $data['name'], $data['balance'], $data['budget']) &&
        !empty($data['username']) && !empty($data['password']) && !empty($data['name'])
    ) {
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