<?php

include("config.php");
include("firebaseRDB.php");
$db = new firebaseRDB($databaseURL);
// Simple user storage (in production, use a database)
// Initialize users file storage

$users_data = $db->retrieve("users");
$users_raw = json_decode($users_data, true) ?? [];

// Initialize session variables

// Handle registration
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    
    $register_errors = [];
    
    // Validation
    if (empty($username)) {
        $register_errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $register_errors[] = "Username must be at least 3 characters long";
    } elseif (isset($users[$username])) {
        $register_errors[] = "Username already exists";
    }
    
    if (empty($email)) {
        $register_errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $register_errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $register_errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $register_errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    foreach ($users_raw as $user_data) {
        if ($user_data['email'] === $email) {
            $register_errors[] = "Email already registered";
            break;
        }
    }
    
    if (empty($register_errors)) {
    // Register to Firebase
   $nextUserID = $db->getNextID("counters/users");
    $customUserKey = "user" . $nextUserID;

    $db->insertWithCustomKey("users", $customUserKey, [
        'username' => $username,
        'password' => $password,
        'email' => $email,
        'created_at' => date('Y-m-d H:i:s'),
        'user_id' => $customUserKey
    ]);
}

}

// Handle login
if (isset($_POST['login'])) {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    $found = false;

    foreach ($users_raw as $firebase_id => $user_data) {
        if (
            isset($user_data['username'], $user_data['password']) &&
            $user_data['username'] === $input_username &&
            $user_data['password'] === $input_password
        ) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['user_email'] = $user_data['email'];
            $_SESSION['created_at'] = $user_data['created_at'];
            $_SESSION['firebase_id'] = $firebase_id;

            if (!isset($_SESSION['monthly_income'])) {
                $_SESSION['monthly_income'] = 0;
            }

            $found = true;
            break;
        }
    }

    if (!$found) {
        $login_error = "Invalid username or password!";
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle adding bills
if (isset($_POST['add_bill']) && $_SESSION['logged_in']) {
    $bill_name = trim($_POST['bill_name']);
    $bill_amount = floatval($_POST['bill_amount']);

    if (!empty($bill_name) && $bill_amount > 0) {
        $username = $_SESSION['username'];
        $db = new firebaseRDB($databaseURL);

        // Get existing bills
        $user_firebase_id = $_SESSION['firebase_id'] ?? null;
        $bills_data = $db->retrieve("users/$user_firebase_id/bills");
        $bills = json_decode($bills_data, true);

        // Determine next ID
        $next_index = 1;
        if (is_array($bills)) {
            while (isset($bills["bill" . $next_index])) {
                $next_index++;
            }
        }
        $bill_id = "bill" . $next_index;

        // Insert the new bill
        $new_bill = [
            'name' => $bill_name,
            'amount' => $bill_amount,
            'date_added' => date('Y-m-d H:i:s')
        ];

        $user_firebase_id = $_SESSION['firebase_id'] ?? null;
        $insert_result = $db->insertWithCustomKey("users/$user_firebase_id/bills", $bill_id, $new_bill);

        // Feedback
        if (strpos($insert_result, 'error') === false) {
            $success_message = "Bill added successfully!";
        } else {
            $error_message = "Failed to add bill.";
        }
    } else {
        $error_message = "Please enter valid bill name and amount!";
    }
}

// Handle setting monthly income
if (isset($_POST['set_income']) && $_SESSION['logged_in']) {
    $income = floatval($_POST['monthly_income']);
    if ($income >= 0) {
        $_SESSION['monthly_income'] = $income;
        
        $income_success = "Monthly income updated successfully!";
    }
}

// Handle deleting bills
if (isset($_POST['delete_bill']) && $_SESSION['logged_in']) {
    $bill_id = $_POST['bill_id'];

    $username = $_SESSION['username'];
    $users_data = $db->retrieve("users");
    $users_raw = json_decode($users_data, true) ?? [];

    $user_firebase_id = null;
    foreach ($users_raw as $key => $user_info) {
        if ($user_info['username'] === $username) {
            $user_firebase_id = $key;
            break;
        }
    }

    if ($user_firebase_id) {
        $db->delete("users/$user_firebase_id/bills", $bill_id);
        $delete_success = "Bill deleted successfully!";
    } else {
        $delete_error = "Unable to locate user ID. Deletion failed.";
    }
}

// Calculate totals
// Retrieve bills from Firebase
$username = $_SESSION['username'] ?? null;
$user_firebase_id = $users[$username]['firebase_id'] ?? null;
$bills = [];
$total_bills = 0;

if ($username && $user_firebase_id) {
    if (isset($_POST['set_income']) && $_SESSION['logged_in']) {
    $income = floatval($_POST['monthly_income']);
    if ($income >= 0) {
        $_SESSION['monthly_income'] = $income;
        $firebase_id = $_SESSION['firebase_id']; // this must be set during login
        $db->update("users", $firebase_id, ["monthly_income" => $income]);
        $income_success = "Monthly income updated successfully!";
    }
}

}
    $bills_data = $db->retrieve("users/$user_firebase_id/bills");
    $bills = json_decode($bills_data, true) ?? [];

    foreach ($bills as $bill) {
        $total_bills += floatval($bill['amount']);
    }




// Determine which form to show
$show_register = isset($_GET['register']) || (isset($_POST['register']) && !empty($register_errors));
$show_login = !$show_register || isset($register_success);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Bill Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .auth-form {
            max-width: 450px;
            margin: 0 auto;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4facfe;
        }
        
        .btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            margin-left: 10px;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 5px solid #4facfe;
        }
        
        .card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .summary {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        
        .summary-item {
            display: inline-block;
            margin: 0 20px;
        }
        
        .summary-item h4 {
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        
        .summary-item .amount {
            font-size: 2em;
            font-weight: bold;
        }
        
        .positive {
            color: #4caf50;
        }
        
        .negative {
            color: #f44336;
        }
        
        .bills-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .bill-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .bill-item:last-child {
            border-bottom: none;
        }
        
        .bill-info {
            flex: 1;
        }
        
        .bill-name {
            font-weight: bold;
            color: #333;
        }
        
        .bill-amount {
            color: #666;
            font-size: 1.1em;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 8px;
        }
        
        .form-toggle {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .form-toggle a {
            color: #4facfe;
            text-decoration: none;
            font-weight: bold;
        }
        
        .form-toggle a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }
        
        .user-details {
            font-size: 0.9em;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .summary-item {
                display: block;
                margin: 10px 0;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Monthly Bill Manager</h1>
            <p>Track your bills and manage your monthly budget</p>
        </div>
        
        <div class="content">
            <?php if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']): ?>
                <?php if ($show_register && !isset($register_success)): ?>
                    <!-- Registration Form -->
                    <div class="auth-form">
                        <h2>Create New Account</h2>
                        <p style="margin: 20px 0; color: #666;">Join us to start managing your bills</p>
                        
                        <?php if (!empty($register_errors)): ?>
                            <div class="alert alert-error">
                                <?php foreach ($register_errors as $error): ?>
                                    <div><?php echo htmlspecialchars($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="reg_username">Username:</label>
                                <input type="text" id="reg_username" name="reg_username" 
                                       value="<?php echo isset($_POST['reg_username']) ? htmlspecialchars($_POST['reg_username']) : ''; ?>" required>
                                <div class="password-requirements">At least 3 characters, must be unique</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="reg_email">Email:</label>
                                <input type="email" id="reg_email" name="reg_email" 
                                       value="<?php echo isset($_POST['reg_email']) ? htmlspecialchars($_POST['reg_email']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="reg_password">Password:</label>
                                <input type="password" id="reg_password" name="reg_password" required>
                                <div class="password-requirements">At least 6 characters</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="reg_confirm_password">Confirm Password:</label>
                                <input type="password" id="reg_confirm_password" name="reg_confirm_password" required>
                            </div>
                            
                            <button type="submit" name="register" class="btn">Create Account</button>
                            <a href="?login" class="btn btn-secondary">Back to Login</a>
                        </form>
                        
                        <div class="form-toggle">
                            <p>Already have an account? <a href="?login">Login here</a></p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login Form -->
                    <div class="auth-form">
                        <h2>Login to Your Account</h2>
                        
                        
                        <?php if (isset($register_success)): ?>
                            <div class="alert alert-success"><?php echo $register_success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-error"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            
                            <button type="submit" name="login" class="btn">Login</button>
                            <a href="?register" class="btn btn-secondary">Register</a>
                        </form>
                        
                        <div class="form-toggle">
                            <p>Don't have an account? <a href="?register">Register here</a></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Dashboard -->
                <div class="user-info">
                    <div>
                        <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                        <div class="user-details">
                            <?php
                                $username = $_SESSION['username'];
                                $created_at = isset($users[$username]['created_at']) ? $users[$username]['created_at'] : null;?>
                                
                            <?php
                            $username = $_SESSION['username'];
                            $users_data = $db->retrieve("users");
                            $users_raw = json_decode($users_data, true) ?? [];
                            $firebase_id = null;
                            $created_at = null;
                          foreach ($users_raw as $key => $info) {
                                if (isset($info['username']) && $info['username'] === $username) {
                                $firebase_id = $key;
                                $created_at = isset($info['created_at']) ? $info['created_at'] : null;

        
                                if (!isset($_SESSION['firebase_id'])) {
                                 $_SESSION['firebase_id'] = $firebase_id;
                                }

                                break;
                                }
                            }
                            ?>
                            <?php echo htmlspecialchars($_SESSION['user_email']); ?> • 
                            Member since <?php echo $created_at ? date('M Y', strtotime($created_at)) : 'N/A'; ?>
                        </div>
                    </div>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="logout" class="btn btn-danger btn-small">Logout</button>
                    </form>
                </div>
                
                <!-- Alerts -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if (isset($income_success)): ?>
                    <div class="alert alert-success"><?php echo $income_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($delete_success)): ?>
                    <div class="alert alert-success"><?php echo $delete_success; ?></div>
                <?php endif; ?>
                
                <!-- Summary Card -->
<?php
$total_bills = 0;


$bills_data = $db->retrieve("users/$firebase_id/bills");
$bills = json_decode($bills_data, true) ?? [];


foreach ($bills as $bill) {
    $total_bills += floatval($bill['amount']);
}


$remaining_income = $_SESSION['monthly_income'] - $total_bills;
$remaining_class = $remaining_income >= 0 ? 'positive' : 'negative';

?>
                <div class="dashboard">
                    <div class="card summary">
    <h3>💼 Monthly Financial Summary</h3>
    <div class="summary-item">
        <h4>Monthly Income</h4>
        <div class="amount">$<?php echo number_format($_SESSION['monthly_income'], 2); ?></div>
    </div>
    <div class="summary-item">
        <h4>Total Bills</h4>
        <div class="amount">$<?php echo number_format($total_bills, 2); ?></div>
    </div>
    <div class="summary-item">
        <h4>Remaining</h4>
        <div class="amount <?php echo $remaining_class; ?>">
            $<?php echo number_format($remaining_income, 2); ?>
        </div>
    </div>
</div>
                    
                    <!-- Set Income -->
                    <div class="card">
                        <h3>💵 Set Monthly Income</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label for="monthly_income">Monthly Income ($):</label>
                                <input type="number" step="0.01" id="monthly_income" name="monthly_income" 
                                       value="<?php echo $_SESSION['monthly_income']; ?>" required>
                            </div>
                            <button type="submit" name="set_income" class="btn">Update Income</button>
                        </form>
                    </div>
                    
                    <!-- Add Bill -->
                    <div class="card">
                        <h3>📄 Add New Bill</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label for="bill_name">Bill Name:</label>
                                <input type="text" id="bill_name" name="bill_name" placeholder="e.g., Electricity, Water, Internet" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bill_amount">Monthly Amount ($):</label>
                                <input type="number" step="0.01" id="bill_amount" name="bill_amount" placeholder="0.00" required>
                            </div>
                            
                            <button type="submit" name="add_bill" class="btn">Add Bill</button>
                        </form>
                    </div>
                    
                    <!-- Bills List -->
                    <div class="card">
    <h3>📋 Your Monthly Bills</h3>
    <?php if (empty($bills)): ?>
        <p style="color: #666; text-align: center; padding: 20px;">No bills added yet. Add your first bill above!</p>
    <?php else: ?>
        <div class="bills-list">
            <?php foreach ($bills as $bill_id => $bill) : ?>
                <div class="bill-item">
                    <div class="bill-info">
                        <div class="bill-name"><?php echo htmlspecialchars($bill['name']); ?></div>
                        <div class="bill-amount">$<?php echo number_format($bill['amount'], 2); ?></div>
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="bill_id" value="<?php echo htmlspecialchars($bill_id); ?>">
                        <button type="submit" name="delete_bill" class="btn btn-danger btn-small" 
                                onclick="return confirm('Are you sure you want to delete this bill?')">
                            Delete
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>


 
</script>
</html>