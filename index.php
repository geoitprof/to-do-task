<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Todo Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #111 0%, #333 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow-x: hidden;
        }

        .landing-container {
            max-width: 500px;
            width: 90%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .landing-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #111, #333, #111);
        }

        .logo {
            font-size: 3rem;
            color: #111;
            font-weight: 700;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            justify-content: center;
            animation: slideInDown 0.8s ease-out 0.2s both;
        }

        .logo i {
            background: linear-gradient(135deg, #111, #333);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 3.5rem;
        }

        h2 {
            color: #111;
            font-weight: 600;
            font-size: 1.8rem;
            margin-bottom: 20px;
            animation: slideInDown 0.8s ease-out 0.4s both;
        }

        .lead {
            color: #666;
            font-weight: 400;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 40px;
            animation: slideInDown 0.8s ease-out 0.6s both;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 16px 32px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #111, #333);
            border: none;
            color: #fff;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            animation: slideInUp 0.8s ease-out 0.8s both;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #222, #444);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
        }

        .btn-outline-primary {
            background: transparent;
            border: 2px solid #111;
            color: #111;
            animation: slideInUp 0.8s ease-out 1s both;
        }

        .btn-outline-primary:hover {
            background: #111;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .d-grid {
            gap: 16px;
        }

        .features {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            animation: slideInUp 0.8s ease-out 1.2s both;
        }

        .features h4 {
            color: #111;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .feature-list {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9rem;
        }

        .feature-item i {
            color: #111;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .landing-container {
                padding: 40px 30px;
                margin: 20px;
            }

            .logo {
                font-size: 2.5rem;
            }

            .logo i {
                font-size: 3rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .lead {
                font-size: 1rem;
            }

            .btn {
                padding: 14px 24px;
                font-size: 1rem;
            }

            .feature-list {
                flex-direction: column;
                align-items: center;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="landing-container">
        <div class="logo">
            <i class="fas fa-check-circle"></i>
            Todo Task
        </div>
        
        <h2>Welcome to Your Productivity Hub</h2>
        
        <p class="lead">
            Streamline your workflow with our intelligent task management system. 
            Organize, track, and accomplish more with elegant simplicity.
        </p>
        
        <div class="d-grid">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i>Get Started
            </a>
            <a href="registration.php" class="btn btn-outline-primary">
                <i class="fas fa-user-plus me-2"></i>Create Account
            </a>
        </div>
        
        <div class="features">
            <h4>Why Choose Todo Task?</h4>
            <div class="feature-list">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Responsive</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-bolt"></i>
                    <span>Fast</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>