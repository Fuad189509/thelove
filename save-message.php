<?php
$message = $_POST['message'];
$timestamp = date('Y-m-d H:i:s');

$data = "[$timestamp]\n$message\n\n";

file_put_contents('messages.txt', $data, FILE_APPEND);

header('Location: index.html?success=1');
?>
