<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/google_auth.php';

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);
        
        // Get profile info directly via authorized HTTP request
        $httpClient = $client->authorize();
        $response = $httpClient->get('https://www.googleapis.com/oauth2/v2/userinfo');
        $google_account_info = json_decode($response->getBody(), true);
        
        $google_id = $google_account_info['id'];
        $email = $google_account_info['email'];
        $name = $google_account_info['name'];
        
        try {
            // Check if user exists by google_id or email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
            $stmt->execute([$google_id, $email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // User exists. Update google_id if it's empty (they previously registered with email only)
                if (empty($user['google_id'])) {
                    $update_stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                    $update_stmt->execute([$google_id, $user['id']]);
                }
                
                // Login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
            } else {
                // New user via Google, register them
                $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, google_id) VALUES (?, ?, ?)");
                $insert_stmt->execute([$name, $email, $google_id]);
                
                $new_user_id = $pdo->lastInsertId();
                
                // Login
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
            }
            
            // Redirect to dashboard
            header('Location: ../../index.php');
            exit();
            
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
        
    } else {
        die("Error fetching access token.");
    }
} else {
    // If we land here without a code, redirect to login
    header('Location: ../../login.php');
    exit();
}
?>
