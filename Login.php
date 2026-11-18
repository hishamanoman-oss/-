<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Css/login.css">
    <title>Login</title>
</head>
<body>
    <div class="login">
        <div class="box">
            <h1>Login</h1>
            <form action="login_process.php" method="post" id="login-form">
                <div>
                    <label for="email">Email</label><br>
                    <input type="email" id="email" name="email" placeholder="Enter the email" required>
                </div><br>
                <div>
                    <label for="password">Password</label><br>
                    <input type="password" id="password" name="password" placeholder="Enter the password" required>
                </div>
                <br>
                <div>
                    <button type="submit">Login</button>
                </div>
            </form>
        </div>
    </div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="js/login.js"></script>
</body>
</html>
