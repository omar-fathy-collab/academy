<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            color: #fff;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }
        .error-box {
            animation: fadeIn 0.8s ease-in-out;
        }
        h1 {
            font-size: 7rem;
            font-weight: 900;
        }
        h3 {
            font-size: 1.8rem;
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: #fff;
            color: #2575fc;
            border-radius: 50px;
            font-weight: 600;
            padding: 10px 25px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-custom:hover {
            background-color: #2575fc;
            color: #fff;
        }
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(20px);}
            to {opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>404</h1>
        <h3>Oops! Page not found.</h3>
        <p>The page you're looking for doesn't exist or has been moved.</p>
        <a href="{{ url()->previous() }}" class="btn btn-custom mt-3">Go Back</a>
        <p class="mt-4 text-light">
            📞 01019522345 <br>
            📧 <a href="mailto:dev.omartolba@gmail.com" class="text-white text-decoration-underline">dev.omartolba@gmail.com</a>
        </p>
    </div>
</body>
</html>
