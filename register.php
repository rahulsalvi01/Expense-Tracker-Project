<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FinTrack Pro Max</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Specific tweaks for login/register page */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(20, 20, 35, 0.4) !important;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            z-index: 10;
        }
        
        .form-control {
            background: rgba(0, 0, 0, 0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-primary) !important;
            border-radius: 8px;
            padding: 12px 15px;
        }

        .form-control:focus {
            background: rgba(0, 0, 0, 0.3) !important;
            border-color: var(--accent-purple) !important;
            box-shadow: 0 0 0 0.25rem rgba(139, 92, 246, 0.25) !important;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .login-btn {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border: none;
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.4);
            color: white;
        }
        
        /* Floating animated blob behind the card */
        .blob {
            position: absolute;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.6;
            animation: float 10s ease-in-out infinite;
        }
        
        .blob-1 {
            width: 300px;
            height: 300px;
            background: rgba(139, 92, 246, 0.5);
            top: 10%;
            left: 20%;
        }
        
        .blob-2 {
            width: 250px;
            height: 250px;
            background: rgba(59, 130, 246, 0.5);
            bottom: 10%;
            right: 20%;
            animation-delay: -5s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center overflow-hidden" style="min-height: 100vh;">
<script>if(localStorage.getItem('theme') === 'light') document.body.classList.add('light-mode');</script>

    <!-- Background 3D Canvas -->
    <canvas id="bg-canvas"></canvas>

    <!-- Glowing Blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="card login-card animate-fade-in p-4 p-sm-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-light mb-2" style="background: linear-gradient(90deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Create Account</h2>
            <p class="text-secondary small">Start managing your expenses professionally</p>
        </div>
        
        <div id="alertBox" class="alert alert-danger d-none" role="alert"></div>
        <div id="successBox" class="alert alert-success d-none" role="alert"></div>

        <form id="registerForm">
            <div class="mb-3 position-relative">
                <label class="form-label text-secondary small fw-semibold">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="John Doe">
            </div>
            <div class="mb-3 position-relative">
                <label class="form-label text-secondary small fw-semibold">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
            </div>
            <div class="mb-4 position-relative">
                <label class="form-label text-secondary small fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn login-btn w-100 mb-3">Register Securely</button>
        </form>

        <div class="text-center mt-2">
            <p class="small text-secondary m-0">Already have an account? <a href="login.php" class="text-decoration-none fw-semibold" style="color: var(--accent-blue);">Login here</a></p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="app3d.js"></script> <!-- Reusing the 3D background -->
    
    <script>
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            $('#alertBox').addClass('d-none').text('');
            $('#successBox').addClass('d-none').text('');
            
            $.post('api/auth/register_process.php', $(this).serialize(), function(response) {
                if(response.status === 'success') {
                    $('#successBox').removeClass('d-none').text(response.message + " Redirecting...");
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    $('#alertBox').removeClass('d-none').text(response.message);
                }
            }, 'json').fail(function() {
                $('#alertBox').removeClass('d-none').text('Server error. Ensure your local server and database are running.');
            });
        });
    </script>
</body>
</html>