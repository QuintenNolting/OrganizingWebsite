<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header('Location: websitelogin.php');
    exit();
}
$userid = $_SESSION['userid'];


$servername = "";
$username = "";
$password = "";
$dbname   = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_project'])) {
    $projectid = (int)$_POST['projectid'];
    $files = [];
    $stmt = $conn->prepare("
        SELECT i.filename
        FROM images i
        JOIN projectitems pi ON i.imageid = pi.imageid
        JOIN userprojects up ON pi.projectid = up.projectid
        WHERE pi.projectid = ? AND up.userid = ? AND i.filename IS NOT NULL
    ");
    $stmt->bind_param("ii", $projectid, $userid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $files[] = $r['filename'];
    }
    $stmt->close();

    $stmt = $conn->prepare("
        DELETE c FROM cards c
        JOIN projectitems pi ON c.cardid = pi.cardid
        JOIN userprojects up ON pi.projectid = up.projectid
        WHERE pi.projectid = ? AND up.userid = ?
    ");
    $stmt->bind_param("ii", $projectid, $userid);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
       DELETE i from images i
       JOIN projectitems pi ON i.imageid = pi.imageid
       JOIN userprojects up ON pi.projectid = up.projectid
       WHERE pi.projectid = ? AND up.userid = ?
    ");
    $stmt->bind_param("ii", $projectid, $userid);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        DELETE p from projects p
        JOIN userprojects up ON p.projectid = up.projectid
        WHERE p.projectid = ? AND up.userid = ?
    ");
    $stmt->bind_param("ii", $projectid, $userid);
    if ($stmt->execute()) {
        foreach ($files as $f) {
            @unlink(__DIR__ . '/uploads/' . basename($f));
        }
    } else {
        $error = "something went wrong";
    }
    $stmt->close();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['projectid'])) {
    $projectid = (int)$_POST['projectid'];
    $newname = trim($_POST['projectname']);

    if ($newname === "") {
        $error = "Project name is required";
    } elseif (strlen($newname) > 20) {
        $error = "Project name is too long";
    } else {
        $stmt = $conn->prepare("
            UPDATE projects p
            JOIN userprojects up ON p.projectid = up.projectid
            SET p.projectname = ?
            WHERE p.projectid = ? AND up.userid = ?
        ");
        $stmt->bind_param("sii", $newname, $projectid, $userid);
        if (!$stmt->execute()) {
            $error = "Something went wrong";
        }
        $stmt->close();
    }

} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $projectname = trim(isset($_POST['projectname']) ? $_POST['projectname'] : "");

    if ($projectname === "") {
        $error = "Project name is required";
    } elseif (strlen($projectname) > 20) {
        $error = "Project name is too long";
    } else {
        $stmt = $conn->prepare("INSERT INTO projects (projectname) VALUES (?)");
        $stmt->bind_param("s", $projectname);


        if (!$stmt->execute()) {
            $error = "Something went wrong";
        } else {
            $newprojectid = $conn->insert_id;
            $stmt->close();

            $linkstmt = $conn->prepare("INSERT INTO userprojects (userid, projectid) VALUES (?, ?)");
            $linkstmt->bind_param("ii", $userid, $newprojectid);


            if (!$linkstmt->execute()) {
                $error = "Something went wrong";
            }
            $linkstmt->close();
        }
    }
}

$stmt = $conn->prepare("
    SELECT p.projectid, p.projectname
    FROM projects p
    JOIN userprojects up ON p.projectid = up.projectid
    WHERE up.userid = ?
    ORDER BY p.projectid DESC
");
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Projects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="css/projects.css" rel="stylesheet">
    <link href="css/websitedescription.css" rel="stylesheet">
</head>
<body>

    <nav class="navbar navbar-expand wdheader">
        <div class="container-fluid">
            <a class="navbar-brand wdheadertitle wdheadertbg" href="projects.php">Charlotte</a>
            <a class="navbar-brand wdheaderdivider"></a>
            <ul class="navbar-nav me-auto">
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link wdheadercontent wdheadertbg" href="logout.php">Log Out</a></li>
            </ul>
        </div>
    </nav>
<div class="projectcollection">
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="project">
            <center>
                <a href="home.php?projectid=<?= (int)$row['projectid'] ?>"><button type="button" class="btn addprojectbtn"><img src="assets/project.png" width="60px"></button></a>
                <form method="post" action="projects.php">
                    <input type="hidden" name="projectid" value="<?= (int)$row['projectid'] ?>">
                    <input type="text" class="projectname" name="projectname" maxlength="20" value="<?= htmlspecialchars($row['projectname']) ?>">
                </form>
                <form method="post" action="projects.php" class="deleteform" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="projectid" value="<?= (int)$row['projectid'] ?>">
                    <input type="hidden" name="delete_project" value="1">
                    <button type="submit" class="deletebtn"><img src="assets/trash.png" width="25px" height="21px"></button>
                </form>
            </center>
        </div>
    <?php endwhile; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-warning projecterror"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="projects.php" class="project">
            <center>
            <button type="submit" class="btn addprojectbtn"><img src="assets/plus.png" width="60px"></button>
            <input type="text" class="projectname" name="projectname" maxlength="20" placeholder="Project Name">
            </center>
        </form>
</div>
</body>
</html>
