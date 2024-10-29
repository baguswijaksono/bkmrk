<?php
$servername = "localhost";
$username = "skibidi";
$password = "skibidi";
$dbname = "skibidi";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
