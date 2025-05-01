<?php
header('Content-Type: application/json');
// Include database configuration
global $link;
require 'config.php';

// Ensure the database connection exists
if (!$link) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Define a function to verify username and password, and return user info if valid
function getUserByCredentials($link, $username, $password) {
    $query = "SELECT username, password, Name, balance, budget, spent, tot_income FROM users WHERE username = ?";
    $stmt = mysqli_prepare($link, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
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
    // Step 1: Check if the username already exists
    $checkQuery = "SELECT username FROM users WHERE username = ?";
    $checkStmt = mysqli_prepare($link, $checkQuery);
    if ($checkStmt) {
        mysqli_stmt_bind_param($checkStmt, "s", $username);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            // Username already exists
            return [
                'success' => false,
                'error' => 'An account with this username already exists.'
            ];
        }
        mysqli_stmt_close($checkStmt);
    } else {
        // Query preparation error
        return [
            'success' => false,
            'error' => 'Failed to check for duplicate username.'
        ];
    }

    // Step 2: Proceed to add user if username does not exist
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $query = "INSERT INTO users (username, password, Name, balance, budget) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($link, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssdd", $username, $hashedPassword, $name, $balance, $budget);
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true]; // Insertion successful
        } else {
            return [
                'success' => false,
                'error' => 'Failed to insert the new user account.'
            ];
        }
    } else {
        return [
            'success' => false,
            'error' => 'Failed to prepare user insertion query.'
        ];
    }
}

function updateUserBalance($link, $username, $balance) {
    $query = "SELECT balance, spent, tot_income FROM users WHERE username = ?";
    $stmt = mysqli_prepare($link, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $currentBalance = $row['balance']; // Current balance in the database
            $spent = $row['spent'];           // Current spent amount
            $totIncome = $row['tot_income'];  // Current total income

            // Calculate the difference in balance
            $difference = $balance - $currentBalance;

            // If the balance increases, it means there's new income
            if ($difference > 0) {
                $totIncome += $difference; // Add the income difference to tot_income
            } else if ($difference < 0) {
                // If balance decreases, update spent
                $spent += abs($difference); // Add the spent amount
            }

            // Update the balance, spent, and total income fields in one query
            $updateQuery = "UPDATE users SET balance = ?, spent = ?, tot_income = ? WHERE username = ?";
            $updateStmt = mysqli_prepare($link, $updateQuery);
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, "ddds", $balance, $spent, $totIncome, $username);
                // Execute the prepared statement
                if (mysqli_stmt_execute($updateStmt)) {
                    return true; // Update successful
                } else {
                    return false; // Update failed
                }
            } else {
                return false; // Query preparation for update failed
            }
        } else {
            return false; // Failed to fetch user data
        }
    } else {
        return false; // Query preparation failed
    }
}

// Define a function to update the user's budget
function updateUserBudget($link, $username, $budget) {
    // Use a prepared statement to prevent SQL injection
    $query = "UPDATE users SET budget = ? WHERE username = ?";
    $stmt = mysqli_prepare($link, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ds", $budget, $username);
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
            http_response_code(401);
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
    if (isset($data['action'])) {
        if ($data['action'] === 'updateBalance') {
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
        } elseif ($data['action'] === 'updateBudget') {
            // Update budget logic
            if (isset($data['username'], $data['budget']) && !empty($data['username'])) {
                $username = $data['username'];
                $budget = (float)$data['budget']; // Convert to float
                $success = updateUserBudget($link, $username, $budget);
                if ($success) {
                    http_response_code(200);
                    echo json_encode(['message' => 'Budget updated successfully.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update budget.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input. Username and budget are required.']);
            }
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
        $result = addUser($link, $username, $password, $name, $balance, $budget);
        if ($result['success']) {
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