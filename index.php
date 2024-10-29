<?php

declare(strict_types=1);
session_start();

require_once 'db.php';

const HTTP_OK = 200;
const HTTP_NOT_FOUND = 404;
const HTTP_METHOD_NOT_ALLOWED = 405;

$routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'DELETE' => [],
];

function get(string $path, callable $handler): void
{
    global $routes;
    $routes['GET'][$path] = $handler;
}

function post(string $path, callable $handler): void
{
    global $routes;
    $routes['POST'][$path] = $handler;
}

function put(string $path, callable $handler): void
{
    global $routes;
    $routes['PUT'][$path] = $handler;
}

function delete(string $path, callable $handler): void
{
    global $routes;
    $routes['DELETE'][$path] = $handler;
}

function dispatch(string $url, string $method): void
{
    global $routes;

    if (!isset($routes[$method])) {
        http_response_code(HTTP_METHOD_NOT_ALLOWED);
        echo "Method $method Not Allowed";
        return;
    }

    foreach ($routes[$method] as $path => $handler) {
        if (preg_match("#^$path$#", $url, $matches)) {
            array_shift($matches);
            $matches = array_map('intval', $matches);
            call_user_func_array($handler, $matches);
            return;
        }
    }

    http_response_code(HTTP_NOT_FOUND);
    handleNotFound();
}

function handleNotFound(): void
{
    echo "404 Not Found";
}

get('/', 'listBookmarks'); 
get('/c', 'createBookmark'); 
post('/c', 'storeBookmark'); 
get('/e/(\d+)', 'editBookmark');
post('/u/(\d+)', 'updateBookmark');
post('/d/(\d+)', 'deleteBookmark');
post('/v', 'middleware');

function listBookmarks(): void
{
    middleware();
    global $conn;

    echo "<h1>Bookmarks</h1>";

    $sql = "SELECT * FROM bookmarks";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['title']) . " - <a href='" . htmlspecialchars($row['url']) . "'>" . htmlspecialchars($row['url']) . "</a>";
            echo " | <a href='/e/" . $row['id'] . "'>Edit</a> | " .
                 "<form action='/d/" . $row['id'] . "' method='POST' style='display:inline;'>" .
                 "<input type='hidden' name='_method' value='DELETE'>" .
                 "<button type='submit'>Delete</button></form>";
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No bookmarks found.</p>";
    }
}

function createBookmark(): void
{
    middleware();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Bookmark</title>
</head>
<body>
    <h1>Add a New Bookmark</h1>
    <form action="/c" method="POST" enctype="multipart/form-data">
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required><br><br>

        <label for="url">URL:</label>
        <input type="url" id="url" name="url" required><br><br>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4" cols="50"></textarea><br><br>

        <label for="tags">Tags:</label>
        <input type="text" id="tags" name="tags"><br><br>

        <label for="category_id">Category ID:</label>
        <input type="number" id="category_id" name="category_id"><br><br>

        <label for="favorite">Favorite:</label>
        <input type="checkbox" id="favorite" name="favorite" value="1"><br><br>

        <label for="is_active">Active:</label>
        <input type="checkbox" id="is_active" name="is_active" value="1" checked><br><br>

        <label for="screenshot">Screenshot:</label>
        <input type="file" id="screenshot" name="screenshot" accept="image/*"><br><br>

        <input type="submit" value="Add Bookmark">
    </form>
</body>
</html>

<?php
}

function storeBookmark(): void
{
    middleware();
    global $conn;

    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    $description = trim($_POST['description']);
    $tags = trim($_POST['tags']);
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $favorite = isset($_POST['favorite']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $screenshot = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'img/';
        $uploadFile = $uploadDir . basename($_FILES['screenshot']['name']);
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadFile)) {
            $screenshot = $uploadFile;
        } else {
            http_response_code(500);
            echo "Error uploading the screenshot!";
            return;
        }
    }
    if (empty($title) || !filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo "Invalid input data!";
        return;
    }
    $sql = "INSERT INTO bookmarks (title, url, description, tags, category_id, favorite, is_active, screenshot) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssisiis", $title, $url, $description, $tags, $category_id, $favorite, $is_active, $screenshot);
    if ($stmt->execute()) {
        echo "Bookmark created successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

function editBookmark(int $id): void
{
    middleware();
    global $conn;

    $sql = "SELECT * FROM bookmarks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $bookmark = $result->fetch_assoc();
    } else {
        echo "Bookmark not found!";
        exit;
    }
    ?>
    
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit Bookmark</title>
    </head>
    <body>
        <h1>Edit Bookmark</h1>
        <form action="/u/<?php echo $bookmark['id']; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $bookmark['id']; ?>">

            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($bookmark['title']); ?>" required><br><br>

            <label for="url">URL:</label>
            <input type="url" id="url" name="url" value="<?php echo htmlspecialchars($bookmark['url']); ?>" required><br><br>

            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="4" cols="50"><?php echo htmlspecialchars($bookmark['description']); ?></textarea><br><br>

            <label for="tags">Tags:</label>
            <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars($bookmark['tags']); ?>"><br><br>

            <label for="category_id">Category ID:</label>
            <input type="number" id="category_id" name="category_id" value="<?php echo $bookmark['category_id']; ?>"><br><br>

            <label for="favorite">Favorite:</label>
            <input type="checkbox" id="favorite" name="favorite" value="1" <?php echo $bookmark['favorite'] ? 'checked' : ''; ?>><br><br>

            <label for="is_active">Active:</label>
            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $bookmark['is_active'] ? 'checked' : ''; ?>><br><br>

            <label for="screenshot">Screenshot:</label>
            <?php if ($bookmark['screenshot']): ?>
                <img src="/<?php echo htmlspecialchars($bookmark['screenshot']); ?>" alt="Screenshot" style="max-width: 200px; max-height: 150px;"><br>
            <?php endif; ?>
            <input type="file" id="screenshot" name="screenshot" accept="image/*"><br><br>

            <input type="submit" value="Update Bookmark">
        </form>
    </body>
    </html>
    <?php
}


function updateBookmark(int $id): void
{
    middleware();
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $description = trim($_POST['description']);
        $tags = trim($_POST['tags']);
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $favorite = isset($_POST['favorite']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sql = "SELECT screenshot FROM bookmarks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $oldScreenshot = null; // Initialize to null
        if ($result->num_rows > 0) {
            $oldBookmark = $result->fetch_assoc();
            $oldScreenshot = $oldBookmark['screenshot'];
        } else {
            http_response_code(404);
            echo "Bookmark not found!";
            return;
        }

        $screenshot = $oldScreenshot;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'img/'; 
            $uploadFile = $uploadDir . basename($_FILES['screenshot']['name']);
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadFile)) {
                if ($oldScreenshot && file_exists($oldScreenshot)) {
                    unlink($oldScreenshot);
                }
                $screenshot = $uploadFile; // Update to the new screenshot path
            } else {
                http_response_code(500);
                echo "Error uploading the screenshot!";
                return;
            }
        }
        if (empty($title) || !filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo "Invalid input data!";
            return;
        }
        $sql = "UPDATE bookmarks SET title=?, url=?, description=?, tags=?, category_id=?, favorite=?, is_active=?, screenshot=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisiisi", $title, $url, $description, $tags, $category_id, $favorite, $is_active, $screenshot, $id);

        if ($stmt->execute()) {
            echo "Bookmark updated successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}


function deleteBookmark(int $id): void
{
    middleware();
    global $conn;
    $sql = "SELECT screenshot FROM bookmarks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $bookmark = $result->fetch_assoc();
        $screenshot = $bookmark['screenshot'];
    } else {
        http_response_code(404);
        echo "Bookmark not found!";
        return;
    }
    $sql = "DELETE FROM bookmarks WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($screenshot && file_exists($screenshot)) {
            unlink($screenshot);
        }
        echo "Bookmark deleted successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

function middleware()
{
    $hashed_password = '$2y$10$A5XBobk5O4dzipZSEIDEkeZggwzM/YaaqAuDP9mLAWjqQ6DM0kVIu';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['original_password'])) {
        if (password_verify($_POST['password'], $hashed_password)) {
            $_SESSION['original_password'] = $_POST['password'];
            header('Location: /');
            exit;
        } else {
            echo 'Invalid password. Please try again.';
        }
    }

    if (!isset($_SESSION['original_password']) || !password_verify($_SESSION['original_password'], $hashed_password)) {
        echo '<form action="/v" method="post">
            <input type="password" name="password" id="password" placeholder="Password">
            <button type="submit">Unlock</button>
          </form>';
        exit;
    }
}

dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
