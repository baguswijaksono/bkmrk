<?php

declare(strict_types=1);

session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bookmark_manager";
$authPassword = 'your_secret_password'; // Set your password here

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'DELETE' => [],
];

// Add a route for a specific HTTP method
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

// Match the request URL and method, then handle it
function dispatch(string $url, string $method): void
{
    global $routes;

    if (!isset($routes[$method])) {
        http_response_code(405); // Method Not Allowed
        echo "Method $method Not Allowed";
        return;
    }

    foreach ($routes[$method] as $path => $handler) {
        if (preg_match("#^$path$#", $url, $matches)) {
            array_shift($matches); // Remove full match
            call_user_func_array($handler, $matches);
            return;
        }
    }
    
    http_response_code(404);
    handleNotFound();
}

// Default 404 handler
function handleNotFound(): void
{
    echo "404 Not Found";
}

// Register routes for the CRUD Bookmark Manager

get('/', 'listBookmarks'); // List all bookmarks
get('/login', 'showLogin'); // Show login page
post('/login', 'processLogin'); // Process login form
post('/logout', 'logout'); // Logout user
post('/bookmark', 'authGuard', 'createBookmark'); // Create a new bookmark (requires auth)
put('/bookmark/(\d+)', 'authGuard', 'updateBookmark'); // Update a bookmark (requires auth)
delete('/bookmark/(\d+)', 'authGuard', 'deleteBookmark'); // Delete a bookmark (requires auth)

// Middleware for checking if the user is authenticated
function authGuard(callable $next, ...$args)
{
    if (!isAuthenticated()) {
        header('Location: /login');
        exit;
    }

    // If authenticated, continue to the requested action
    call_user_func_array($next, $args);
}

function isAuthenticated(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Authentication Handlers

function showLogin(): void
{
    if (isAuthenticated()) {
        header('Location: /');
        exit;
    }

    echo "<h1>Login</h1>";
    echo "<form action='/login' method='POST'>
            <label>Password:</label><br>
            <input type='password' name='password' required><br><br>
            <button type='submit'>Login</button>
          </form>";
}

function processLogin(): void
{
    global $authPassword;

    if ($_POST['password'] === $authPassword) {
        $_SESSION['logged_in'] = true;
        header('Location: /');
        exit;
    }

    echo "Incorrect password. <a href='/login'>Try again</a>";
}

function logout(): void
{
    session_destroy();
    header('Location: /login');
}

// CRUD Handlers

function listBookmarks(): void
{
    global $conn;

    echo "<h1>Bookmarks</h1>";

    if (isAuthenticated()) {
        echo "<form action='/logout' method='POST'><button type='submit'>Logout</button></form>";
    } else {
        echo "<a href='/login'>Login</a>";
    }

    $sql = "SELECT * FROM bookmarks";
    $result = $conn->query($sql);
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['title']) . " - <a href='" . htmlspecialchars($row['url']) . "'>" . htmlspecialchars($row['url']) . "</a>";
        
        // Only show edit and delete options to authenticated users
        if (isAuthenticated()) {
            echo " | <a href='/bookmark/edit/" . $row['id'] . "'>Edit</a> | " . 
                "<form action='/bookmark/" . $row['id'] . "' method='POST' style='display:inline;'>".
                "<input type='hidden' name='_method' value='DELETE'>".
                "<button type='submit'>Delete</button></form>";
        }

        echo "</li>";
    }
    echo "</ul>";

    // Only show the add form to authenticated users
    if (isAuthenticated()) {
        echo "<h2>Add Bookmark</h2>";
        echo "<form action='/bookmark' method='POST'>
                <input type='text' name='title' placeholder='Title' required><br>
                <input type='text' name='url' placeholder='URL' required><br>
                <button type='submit'>Add Bookmark</button>
              </form>";
    }
}

function createBookmark(): void
{
    global $conn;
    $title = $_POST['title'];
    $url = $_POST['url'];

    $sql = "INSERT INTO bookmarks (title, url) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $title, $url);
    $stmt->execute();
    
    header('Location: /');
}

function updateBookmark(int $id): void
{
    global $conn;
    parse_str(file_get_contents("php://input"), $_PUT);

    $title = $_PUT['title'] ?? null;
    $url = $_PUT['url'] ?? null;

    if ($title && $url) {
        $sql = "UPDATE bookmarks SET title=?, url=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $title, $url, $id);
        $stmt->execute();
        echo "Bookmark updated successfully!";
    } else {
        echo "Invalid input data!";
    }
}

function deleteBookmark(int $id): void
{
    global $conn;
    $sql = "DELETE FROM bookmarks WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo "Bookmark deleted successfully!";
}

// Dispatch request
function listen(): void
{
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle method override for PUT and DELETE
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }

    // Dispatch the request
    dispatch($url, $method);
}

listen();

$conn->close();
