<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="card shadow-sm border-0 p-4" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark">Create Account</h3>
            <p class="text-muted small">Start managing your expenses professionally</p>
        </div>
        
        <form id="registerForm">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="John Doe">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Register</button>
        </form>

        <div class="text-center mt-3">
            <p class="small text-muted">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            $.post('api/auth/register_process.php', $(this).serialize(), function(response) {
                if(response.status === 'success') {
                    alert(response.message);
                    window.location.href = 'login.php';
                } else {
                    alert(response.message);
                }
            }, 'json');
        });
    </script>
</body>
</html>