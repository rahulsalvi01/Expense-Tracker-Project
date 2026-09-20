<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FinTrack Pro Max</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Specific tweaks for login page */
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
            <h2 class="fw-bold text-light mb-2" style="background: linear-gradient(90deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">FinTrack</h2>
            <p class="text-secondary small">Welcome Back. Log in to your dashboard.</p>
        </div>
        
        <div id="alertBox" class="alert alert-danger d-none" role="alert"></div>

        <form id="loginForm">
            <div class="mb-3 position-relative">
                <label class="form-label text-secondary small fw-semibold">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
            </div>
            <div class="mb-4 position-relative">
                <label class="form-label text-secondary small fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn login-btn w-100">Login Securely</button>
        </form>

        <div class="d-flex align-items-center my-3">
            <hr class="flex-grow-1 border-secondary opacity-25">
            <span class="mx-3 text-secondary small">or</span>
            <hr class="flex-grow-1 border-secondary opacity-25">
        </div>

        <button type="button" onclick="window.location.href='api/auth/google_login.php';" class="btn w-100 mb-4 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary); border-radius: 8px; padding: 12px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.1)';" onmouseout="this.style.background='rgba(255,255,255,0.05)';">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20px" height="20px" class="me-2">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.7 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                <path fill="none" d="M0 0h48v48H0z"/>
            </svg>
            Continue with Google
        </button>

        <div class="text-center mt-2">
            <p class="small text-secondary m-0">Don't have an account? <a href="register.php" class="text-decoration-none fw-semibold" style="color: var(--accent-blue);">Register here</a></p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="app3d.js"></script> <!-- Reusing the 3D background -->
    
    <script>
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            $('#alertBox').addClass('d-none').text(''); // Reset alert
            
            $.post('api/auth/login_process.php', $(this).serialize(), function(response) {
                if(response.status === 'success') {
                    window.location.href = 'index.php';
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