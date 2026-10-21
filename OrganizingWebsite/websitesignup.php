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
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === "" || $password === "") {
        $error = "Username and password are required.";
    } else {
        $checkStmt = $conn->prepare("SELECT userid FROM Users WHERE Username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "This user already exists";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO Users (Username, Password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashedPassword);
            if (!$stmt->execute()) {
                if ($stmt->errno == 1062) {
                    $error = "This user already exists";
                } else {
                    $error = "Something went wrong, please try again.";
                }
                $stmt->close();
            } else {
                $newUserId = $stmt->insert_id;
                $stmt->close();

                session_start();
                $_SESSION['userid'] = $newUserId;
                $_SESSION['username'] = $username;

                header("Location: projects.php");
                exit();
            }
        }

        $checkStmt->close();
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="css/websitesignup.css" rel="stylesheet">
</head>
<body>
<center>
    <?php if (!empty($error)): ?>
        <div class="alert alert-warning signuperror"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="websitesignup.php">
        <div class="sucontent"><br>
            <div class="sutitle">Sign up</div>
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
        <div class="sutitle">Already account?</div>
        <a href="websitelogin.php">
            <button class="btn sulibutton">Log in</button>
        </a>
    </div>
</center>
</body>
</html>
