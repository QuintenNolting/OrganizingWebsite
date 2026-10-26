<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header('Location: websitelogin.php');
    exit();
}
$userid = $_SESSION['userid'];
$projectid = isset($_REQUEST['projectid']) ? (int)$_REQUEST['projectid'] : 0;

$servername = "";
$username = "";
$password = "";
$dbname   = "";
$cardkey = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT 1 FROM userprojects WHERE userid = ? AND projectid = ?");
$stmt->bind_param("ii", $userid, $projectid);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    header('Location: projects.php');
    exit();
}
$stmt->close();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_card'])) {
    $cardid = (int)$_POST['cardid'];

    $stmt = $conn->prepare("
    DELETE c FROM cards c
    JOIN projectitems pi ON c.cardid = pi.cardid
    WHERE c.cardid = ? AND pi.projectid = ?
    ");
    $stmt->bind_param("ii", $cardid, $projectid);
    $stmt->execute();
    $stmt->close();

    header('Location: home.php?projectid=' . $projectid);
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_position'])) {
    $x = (int)$_POST['x'];
    $y = (int)$_POST['y'];

    if (isset($_POST['cardid'])) {
        $cardid = (int)$_POST['cardid'];
        $stmt = $conn->prepare("
            UPDATE cards c
            JOIN projectitems pi ON c.cardid = pi.cardid
            SET c.location_x = ?, c.location_y = ?
            WHERE c.cardid = ? AND pi.projectid = ?
        ");
        $stmt->bind_param("iiii", $x, $y, $cardid, $projectid);
    } else {
        $imageid = (int)$_POST['imageid'];
        $stmt = $conn->prepare("
            UPDATE images i
            JOIN projectitems pi ON i.imageid = pi.imageid
            SET i.location_x = ?, i.location_y = ?
            WHERE i.imageid = ? AND pi.projectid = ?
        ");
        $stmt->bind_param("iiii", $x, $y, $imageid, $projectid);
    }
    $stmt->execute();
    $stmt->close();
    exit();

    } elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_size'])) {
    $w = max(100, min(2000, (int)$_POST['w']));
    $h = max(60, min(2000, (int)$_POST['h']));

    if (isset($_POST['cardid'])) {
        $cardid = (int)$_POST['cardid'];
        $stmt = $conn->prepare("
        UPDATE cards c
        JOIN projectitems pi ON c.cardid = pi.cardid
        SET c.width = ?, c.height = ?
        WHERE c.cardid = ? AND pi.projectid = ?
        ");
        $stmt->bind_param("iiii", $w, $h, $cardid, $projectid);
    } else {
        $imageid = (int)$_POST['imageid'];
        $stmt = $conn->prepare("
        UPDATE images i
        JOIN projectitems pi ON i.imageid = pi.imageid
        SET i.width = ?, i.height = ?
        WHERE i.imageid = ? AND pi.projectid = ?
        ");
        $stmt->bind_param("iiii", $w, $h, $imageid, $projectid);
    }
    $stmt->execute();
    $stmt->close();
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_card'])) {
    $stmt = $conn->prepare("INSERT INTO cards (cardtext, location_x, location_y) VALUES ('', 20, 100)");
    $stmt->execute();
    $stmt->close();
    $linkstmt = $conn->prepare("INSERT INTO projectitems (projectid, cardid) VALUES (?, LAST_INSERT_ID())");
    $linkstmt->bind_param("i", $projectid);
    $linkstmt->execute();
    $linkstmt->close();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_image'])) {
    $stmt = $conn->prepare("INSERT INTO images (location_x, location_y) VALUES (20, 100)");
    $stmt->execute();
    $stmt->close();
    $linkstmt = $conn->prepare("INSERT INTO projectitems (projectid, imageid) VALUES (?, LAST_INSERT_ID())");
    $linkstmt->bind_param("i", $projectid);
    $linkstmt->execute();
    $linkstmt->close();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_image'])) {
    $imageid = (int)$_POST['imageid'];

    $filename = null;
    $stmt = $conn->prepare("
        SELECT i.filename FROM images i
        JOIN projectitems pi ON i.imageid = pi.imageid
        WHERE i.imageid = ? AND pi.projectid = ?
    ");
    $stmt->bind_param("ii", $imageid, $projectid);
    $stmt->execute();
    $stmt->bind_result($filename);
    $found = $stmt->fetch();
    $stmt->close();

    if ($found) {
        $stmt = $conn->prepare("DELETE FROM images WHERE imageid = ?");
        $stmt->bind_param("i", $imageid);
        $stmt->execute();
        $stmt->close();
        if ($filename) {
            @unlink(__DIR__ . '/uploads/' . basename($filename));
        }
    }

    header('Location: home.php?projectid=' . $projectid);
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_image'])) {
    $imageid = (int)$_POST['imageid'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
        $info = @getimagesize($_FILES['image']['tmp_name']);

        if ($info !== false && isset($allowed[$info[2]]) && $_FILES["image"]["size"] <= 5 * 1024 * 1024) {
            $stmt = $conn->prepare("
            SELECT i.filename FROM images i
            JOIN projectitems pi ON i.imageid = pi.imageid
            WHERE i.imageid = ? AND pi.projectid = ?
            ");
            $stmt->bind_param("ii", $imageid, $projectid);
            $stmt->execute();
            $oldfile = null;
            $stmt->bind_result($oldfile);
            $found = $stmt->fetch();
            $stmt->close();

            if ($found) {
                $filename = bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $allowed[$info[2]];
                if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                    $stmt = $conn->prepare("UPDATE images SET filename = ? WHERE imageid = ?");
                    $stmt->bind_param("si", $filename, $imageid);
                    $stmt->execute();
                    $stmt->close();
                    if ($oldfile) {
                        unlink(__DIR__ . '/uploads/' . basename($oldfile));
                    }
                }
            }
        }
        header('Location: home.php?projectid=' . $projectid);
        exit();
}

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cardid'])) {
    $cardid = (int)$_POST['cardid'];
    $newtext = trim($_POST['cardtext']);

    $stmt = $conn->prepare("
        UPDATE cards c
        JOIN projectitems pi ON c.cardid = pi.cardid
        SET c.cardtext = TO_BASE64(AES_ENCRYPT(?, ?))
        WHERE c.cardid = ? AND pi.projectid = ?
    ");
    $stmt->bind_param("ssii", $newtext, $cardkey, $cardid, $projectid);
    if (!$stmt->execute()) {
        $error = "Something went wrong";
    }
    $stmt->close();
    exit();
}

$stmt = $conn->prepare("
    SELECT c.cardid,
           COALESCE(CONVERT(AES_DECRYPT(FROM_BASE64(c.cardtext), ?) USING utf8mb4), '') AS cardtext,
           c.width, c.height, c.location_x, c.location_y
    FROM cards c
    JOIN projectitems pi ON c.cardid = pi.cardid
    WHERE pi.projectid = ?
");
$stmt->bind_param("si", $cardkey, $projectid);
$stmt->execute();
$result = $stmt->get_result();

$stmt = $conn->prepare("
    SELECT i.imageid, i.filename, i.width, i.height, i.location_x, i.location_y
    FROM images i
    JOIN projectitems pi ON i.imageid = pi.imageid
    WHERE pi.projectid = ?
");
$stmt->bind_param("i", $projectid);
$stmt->execute();
$imageresult = $stmt->get_result();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Projects</title>
    <script src="js/bootstrap.bundle.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="css/websitedescription.css" rel="stylesheet">
    <link href="css/home.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand wdheader">
    <div class="container-fluid">
        <a class="navbar-brand wdheadertitle wdheadertbg" href="projects.php">Charlotte</a>
        <a class="navbar-brand wdheaderdivider"></a>
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link wdheadercontent wdheadertbg" data-bs-toggle="offcanvas" data-bs-target="#offcanvasBottom" aria-controls="offcanvasBottom">Items</a></li>
        </ul>
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link wdheadercontent wdheadertbg" href="logout.php">Log Out</a></li>
        </ul>
    </div>
</nav>


<div class="offcanvas offcanvas-bottom" style="--bs-offcanvas-height: auto" tabindex="-1" id="offcanvasBottom" aria-labelledby="offcanvasBottomLabel" data-bs-backdrop="false">
    <div class="cardsmenu">
        <div class="offcanvas-header">
            <div class="cardsmenutitle" id="offcanvasBottomLabel">Cards & Stuff</div>
            <button type="button" class="cardsmenuexitbtn" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bi bi-x"></i></button>
        </div>
        <div class="offcanvas-body d-flex gap-3">
            <form method="post" action="home.php">
                <input type="hidden" name="projectid" value="<?= (int)$projectid ?>">
                <div class="addcardbackground"><button type="submit" name="add_card" value="1" class="addcardbtn">Card</button></div>
            </form>
            <form method="post" action="home.php">
                <input type="hidden" name="projectid" value="<?= (int)$projectid ?>">
                <div class="addcardbackground"><button type="submit" name="add_image" value="1" class="addcardbtn">Image</button></div>
            </form>
        </div>
    </div>
</div>
<div class="screen">
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card draggable" data-type="card" data-id="<?= (int)$row['cardid'] ?>" style="position: absolute; cursor: grab; user-select: none; left: <?= (int)$row['location_x'] ?>px; top: <?= (int)$row['location_y'] ?>px; width: <?= (int)$row['width'] ?>px;<?= $row['height'] ? ' min-height: ' . (int)$row['height'] . 'px;' : '' ?>">
            <div class="card-body carditem">
                <form method="post" action="home.php" class="contentform">
                    <input type="hidden" name="projectid" value="<?= (int)$projectid ?>">
                    <input type="hidden" name="cardid" value="<?= (int)$row['cardid'] ?>">
                    <textarea class="cardtext" name="cardtext"><?= htmlspecialchars($row['cardtext']) ?></textarea>
                </form>
                <form method="post" action="home.php" class="deleteform" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="projectid" value="<?= (int)$projectid ?>">
                    <input type="hidden" name="cardid" value="<?= (int)$row['cardid']?>">
                    <input type="hidden" name="delete_card" value="1">
                    <button type="submit" class="deletecardbtn"><img src="assets/trash.png" width="25px" height="21px"></button>
                </form>
                <div class="resizer"></div>
            </div>
        </div>
    <?php endwhile; ?>

    <?php while ($row = $imageresult->fetch_assoc()): ?>
        <div class="card draggable" data-type="image" data-id="<?= (int)$row['imageid'] ?>" style="position: absolute; cursor: grab; user-select: none; left: <?= (int)$row['location_x'] ?>px; top: <?= (int)$row['location_y'] ?>px; width: <?= (int)$row['width'] ?>px;<?= $row['height'] ? ' height: ' . (int)$row['height'] . 'px;' : '' ?>">
            <div class="card-body carditem">
                <form method="post" action="home.php" enctype="multipart/form-data" class="contentform">
                    <input type="hidden" name="projectid" value="<?= (int)$projectid ?>">
                    <input type="hidden" name="imageid" value="<?= (int)$row['imageid'] ?>">
                    <input type="hidden" name="upload_image" value="1">
                    <input type="file" name="image" accept="image/*" hidden>
                    <?php if ($row['filename']): ?>
                        <img class="imagepreview" src="uploads/<?= htmlspecialchars($row['filename']) ?>" draggable="false">
                    <?php else: ?>
                        <button type="button" class="imagecontent">Import image from files</button>
                    <?php endif; ?>
                </form>
                <form method="post" action="home.php" class="deleteform" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="projectid" value="<?= (int)$projectid ?>">
                    <input type="hidden" name="imageid" value="<?= (int)$row['imageid'] ?>">
                    <input type="hidden" name="delete_image" value="1">
                    <button type="submit" class="deletecardbtn"><img src="assets/trash.png" width="25px" height="21px"></button>
                </form>
                <div class="resizer"></div>
            </div>
        </div>
    <?php endwhile; ?>
</div>


<script>const PROJECT_ID = <?= (int)$projectid ?>;</script>
<script src="js/home.js"></script>
</body>
</html>
