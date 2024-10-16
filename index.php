<?php

declare(strict_types=1);

$routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'DELETE' => [],
];

// Database connection
function conn(): mysqli
{
    $servername = 'localhost';
    $username = 'root';  // Change this to your MySQL username
    $password = '';      // Change this to your MySQL password
    $dbname = 'bookmark_manager';

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

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

// Register all routes and handle the current request
function listen(): void
{
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Define routes
    get('/', 'home');
    get('/bookmarks', 'listBookmarks');
    post('/bookmarks', 'addBookmark');
    put('/bookmarks/(\d+)', 'updateBookmark');
    delete('/bookmarks/(\d+)', 'deleteBookmark');

    // Dispatch the request
    dispatch($url, $method);
}

// Example handlers
function home(): void
{
    echo "Welcome to the Bookmark Manager!";
}

// List all bookmarks
function listBookmarks(): void
{
    $conn = conn();
    $sql = "SELECT id, title, url FROM bookmarks";
    $result = $conn->query($sql);

    $bookmarks = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $bookmarks[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($bookmarks);
    
    $conn->close();
}

// Add a new bookmark
function addBookmark(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['title']) || !isset($input['url'])) {
        http_response_code(400); // Bad Request
        echo "Invalid input";
        return;
    }

    $conn = conn();
    $stmt = $conn->prepare("INSERT INTO bookmarks (title, url) VALUES (?, ?)");
    $stmt->bind_param("ss", $input['title'], $input['url']);
    
    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo "Bookmark added successfully";
    } else {
        http_response_code(500); // Server error
        echo "Error adding bookmark";
    }

    $stmt->close();
    $conn->close();
}

// Update an existing bookmark by ID
function updateBookmark(int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['title']) || !isset($input['url'])) {
        http_response_code(400); // Bad Request
        echo "Invalid input";
        return;
    }

    $conn = conn();
    $stmt = $conn->prepare("UPDATE bookmarks SET title = ?, url = ? WHERE id = ?");
    $stmt->bind_param("ssi", $input['title'], $input['url'], $id);
    
    if ($stmt->execute()) {
        echo "Bookmark updated successfully";
    } else {
        http_response_code(500); // Server error
        echo "Error updating bookmark";
    }

    $stmt->close();
    $conn->close();
}

// Delete a bookmark by ID
function deleteBookmark(int $id): void
{
    $conn = conn();
    $stmt = $conn->prepare("DELETE FROM bookmarks WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "Bookmark deleted successfully";
    } else {
        http_response_code(500); // Server error
        echo "Error deleting bookmark";
    }

    $stmt->close();
    $conn->close();
}

// Start listening for incoming requests
listen();
