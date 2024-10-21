<?php
session_start(); // Start the session

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vote";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    $jsonFile = $_FILES['json_file']['tmp_name'];
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);

    // Remove milliseconds from the timestamp
    $timestamp = preg_replace('/\.\d+/', '', $data['timestamp']);

    // Insert data into elections table
    $stmt = $conn->prepare("INSERT INTO elections (timestamp, level, ed_code, ed_name, pd_code, pd_name, type, sequence_number, reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sssssssss",
        $timestamp,
        $data['level'],
        $data['ed_code'],
        $data['ed_name'],
        $data['pd_code'],
        $data['pd_name'],
        $data['type'],
        $data['sequence_number'],
        $data['reference']
    );
    $stmt->execute();
    $electionId = $stmt->insert_id;

    // Save election ID in session
    $_SESSION['election_id'] = $electionId;

    // Insert data into parties table
    $stmt = $conn->prepare("INSERT INTO parties (election_id, party_code, votes, percentage, party_name, candidate) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($data['by_party'] as $party) {
        $stmt->bind_param(
            "isidss",
            $electionId,
            $party['party_code'],
            $party['votes'],
            $party['percentage'],
            $party['party_name'],
            $party['candidate']
        );
        $stmt->execute();
    }

    // Insert data into summary table
    $stmt = $conn->prepare("INSERT INTO summary (election_id, valid, rejected, polled, electors, percent_valid, percent_rejected, percent_polled) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "iiiiiiii",
        $electionId,
        $data['summary']['valid'],
        $data['summary']['rejected'],
        $data['summary']['polled'],
        $data['summary']['electors'],
        $data['summary']['percent_valid'],
        $data['summary']['percent_rejected'],
        $data['summary']['percent_polled']
    );
    $stmt->execute();

    // After successful data insertion, redirect to view.php
    header("Location: view.php");
    exit(); // Stop further script execution after redirect
}
$conn->close();
?>

<!-- HTML Form to upload JSON file -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload JSON</title>
    <style>
        body {
            width: 1920px;
            height: 1080px;
            background-image: url('image/5.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white; /* Optional: change text color for better contrast */
            font-family: Arial, sans-serif; /* Optional: set a font-family */
        }
        form {
            margin: 20px;
        }
    </style>
</head>
<body>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="json_file" accept="application/json">
        <input type="submit" value="Upload">
        <br>
        <br>
        <br>
        <br>
        <input type="button" value="Enter data" onclick="window.location.href='manual_entry.php'">
    </form>
</body>
</html>
