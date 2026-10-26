<?php
$servername = "";
$username = "";
$password = "";
$dbname   = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === "" || $password === "") {
        $error = "Username and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT userid, Password FROM Users WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['Password'])) {
                session_start();
                $_SESSION['userid'] = $row['userid'];
                $_SESSION['username'] = $username;
                header("Location: projects.php");
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }

        $stmt->close();
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="css/websitesignup.css" rel="stylesheet">
</head>
<body>
<center>
    <?php if (!empty($error)): ?>
        <div class="alert alert-warning signuperror"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="websitelogin.php">
        <div class="sucontent"><br>
            <div class="sutitle">Log in</div>
            <div class="mb-3 usernameinput">
                <label for="exampleInputUsername1" class="form-label">Username</label>
                <input type="text" class="form-control" id="exampleInputUsername1" aria-describedby="UsernameHelp" name="username">
            </div>
            <div class="sudivider"></div>
            <div class="mb-3 passwordinput">
                <label for="exampleInputPassword1" class="form-label">Password</label>
                <input type="password" class="form-control" id="exampleInputPassword1" name="password">
            </div>
            <button type="submit" class="btn subutton">Submit</button>
        </div>
    </form>


    <div class="othersucontent"><br>
        <div class="sutitle">Want a account?</div>
        <a href="websitesignup.php">
            <button class="btn sulibutton">Sign up</button>
        </a>
    </div>
</center>
</body>
</html>
