<?php
session_start(); // Start the session

// Check if the election_id exists in the session
if (!isset($_SESSION['election_id'])) {
    die("No election data available. Please upload a file first.");
}

$electionId = $_SESSION['election_id']; // Get election ID from session

// Database connection
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'vote';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to get the election level and division names for the specific election
$sql = "
SELECT 
    e.level,
    e.ed_name,
    e.pd_name
FROM 
    elections e
WHERE 
    e.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $electionId);
$stmt->execute();
$result = $stmt->get_result();

// Variables to hold the title and subtitle
$title = "President Election Results-2024";
$subtitle = "";

// Display election level and division names
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $level = $row['level'];
        if ($level === 'ALL-ISLAND') {
            $subtitle = "ALL-ISLAND";
        } elseif ($level === 'POSTAL-VOTE') {
            $subtitle = "POSTAL-VOTE: " . $row['ed_name'];
        } elseif ($level === 'POLLING-DIVISION') {
            $subtitle = $row['ed_name'] . ", Polling Division: " . $row['pd_name'];
        } elseif ($level === 'ELECTORAL-DISTRICT') {
            $subtitle = $row['ed_name'];
        }
    }
} else {
    $subtitle = "No data available";
}

// Query to get the top candidates by votes
$sql_candidates = "
SELECT 
    p.candidate, 
    p.votes,
    p.percentage
FROM 
    parties p
JOIN 
    elections e ON p.election_id = e.id
WHERE 
    e.id = ?
ORDER BY 
    p.votes DESC
LIMIT 4
";

$stmt = $conn->prepare($sql_candidates);
$stmt->bind_param("i", $electionId);
$stmt->execute();
$top_four_result = $stmt->get_result();

// Determine the minimum number of votes among the top four
$top_votes = [];
while ($row = $top_four_result->fetch_assoc()) {
    $top_votes[] = $row['votes'];
}
$top_votes = array_unique($top_votes); // Unique vote counts
sort($top_votes, SORT_DESC); // Sort in descending order

// Query to get candidates with the same number of votes as the top four
$sql_all_candidates = "
SELECT 
    p.candidate, 
    p.votes,
    p.percentage
FROM 
    parties p
JOIN 
    elections e ON p.election_id = e.id
WHERE 
    e.id = ? AND p.votes IN (" . implode(',', array_fill(0, count($top_votes), '?')) . ")
ORDER BY 
    p.votes DESC
";

$stmt = $conn->prepare($sql_all_candidates);
$params = array_merge([$electionId], $top_votes);
$stmt->bind_param(str_repeat('i', count($params)), ...$params);
$stmt->execute();
$candidate_result = $stmt->get_result();

// Get the total valid votes to calculate percentages
$sql_total_votes = "
SELECT 
    SUM(s.valid) AS total_valid_votes,
    SUM(s.rejected) AS total_rejected_votes,
    SUM(s.electors) AS total_electors,
    AVG(s.percent_valid) AS avg_percent_valid
FROM 
    summary s 
JOIN 
    elections e ON s.election_id = e.id
WHERE 
    e.id = ?
";

$stmt = $conn->prepare($sql_total_votes);
$stmt->bind_param("i", $electionId);
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();

$total_valid_votes = $total_result['total_valid_votes'];
$total_rejected_votes = $total_result['total_rejected_votes'];
$total_electors = $total_result['total_electors'];
$avg_percent_valid = $total_result['avg_percent_valid'];

// Output the styles and layout
echo '<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #f4f4f4;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-height: 100vh;
        box-sizing: border-box;
        position: relative;
    }
    .background-image {
        position: absolute;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-image: url("image/5.png");
        background-size: cover;
        background-position: center;
        opacity: 0.1;
        z-index: -1;
    }
    .header {
        text-align: center;
        margin-bottom: 20px;
    }
    .header h1, .header h2 {
        font-weight: bold; /* Bold text */
    }
    .container {
        display: flex;
        justify-content: center; 
        flex-wrap: wrap; 
        gap: 20px; 
        max-width: 1200px;
        margin: auto;
    }
    .card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        width: 280px; 
        padding: 20px;
        text-align: center;
        flex: 1; 
        max-width: calc(25% - 20px); 
        box-sizing: border-box; 
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card h3 {
        font-weight: bold; 
    }
    .card p {
        font-weight: bold; 
    }
    .progress-circle {
        position: relative;
        width: 100px;
        height: 100px;
        margin: 0 auto;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 10px;
        box-sizing: border-box;
        animation: fillAnimation 2s ease-out;
    }
    @keyframes fillAnimation {
        from {
            transform: scale(0);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    .progress-circle::before {
        content: "";
        position: absolute;
        width: 80px;
        height: 80px;
        background-color: white;
        border-radius: 50%;
        z-index: 1;
    }
    .progress-text {
        position: relative;
        font-size: 24px;
        font-weight: bold; 
        z-index: 2; 
    }
    .charts {
        display: flex;
        justify-content: center;
        max-width: 800px;
        width: 100%;
        margin: 20px auto;
        margin-bottom: 40px;
    }
    .circle-progress-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 40px; 
        margin: 20px auto;
    }
    .circle-progress {
        width: 120px; 
        height: 120px; 
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        animation: fillAnimation 2s ease-out; 
    }
    .circle-progress .circle {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 12px solid #ddd; 
    }
    .circle-progress .circle-fill {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: conic-gradient(#36A2EB calc(var(--progress) * 1%), #ddd 0);
        animation: fillAnimation 2s ease-out; 
    }
    .circle-progress .circle-text {
        position: relative;
        font-size: 28px; 
        font-weight: bold; 
    }
    .circle-title {
        text-align: center;
        font-size: 18px;
        font-weight: bold; 
        margin-top: 10px;
    }
    .summary-section {
        max-width: 800px;
        margin: 20px auto;
    }
    .summary-list {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .summary-list li {
        width: calc(50% - 10px); 
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        box-sizing: border-box;
        font-weight: bold; 
    }
</style>';

echo '<div class="background-image"></div>';

echo '<div class="header">';
echo '<h1>President Election Results-2024</h1>';
echo '<h2>' . $subtitle . '</h2>';
echo '</div>'; // Close header div

echo '<div class="container">';

// Function to get the color based on candidate name
function getCandidateColor($candidate) {
    switch ($candidate) {
        case 'ANURA KUMARA DISSANAYAKE':
            return 'red';
        case 'SAJITH PREMADASA':
            return 'yellow';
        case 'DILITH JAYAWEERA':
            return 'orange';
        case 'RANIL WICKREMESINGHE':
            return 'green';
        case 'NAMAL RAJAPAKSA':
            return 'pink';
        default:
            return 'blue'; // Default color
    }
}

// Display progress cards
while ($row = $candidate_result->fetch_assoc()) {
    $candidate = $row['candidate'];
    $votes = $row['votes'];
    $percentage = $row['percentage'];
    $color = getCandidateColor($candidate); // Get color based on candidate name

    echo '<div class="card">';
    echo '<h3>' . $candidate . '</h3>';
    echo '<div class="progress-circle" style="--progress: ' . $percentage . '; background: conic-gradient(' . $color . ' calc(var(--progress) * 1%), #eee 0);">';
    echo '<div class="progress-text">' . $percentage . '%</div>';
    echo '</div>';
    echo '<p>Votes: ' . $votes . '</p>';
    echo '</div>';
}

echo '</div>'; // Close container

// Final Election Summary section
echo '<div class="summary-section">';
echo '<h2>Final Election Summary</h2>';
echo '<ul class="summary-list">';
echo "<li>Total Valid Votes: " . $total_valid_votes . "</li>";
echo "<li>Total Rejected Votes: " . $total_rejected_votes . "</li>";
echo "<li>Total Electors: " . $total_electors . "</li>";
echo "<li>Average Percent Valid Votes: " . $avg_percent_valid . "%</li>";
echo '</ul>';
echo '</div>'; // Close summary-section

// Circle progress for valid votes and rejected votes
echo '<div class="circle-progress-container">';
echo '<div class="circle-progress">';
echo '<div class="circle"></div>';
echo '<div class="circle-fill" style="background: conic-gradient(#36A2EB calc(' . ($total_valid_votes / $total_electors * 100) . '%), #ddd 0);"></div>';
echo '<div class="circle-text">' . round($total_valid_votes / $total_electors * 100, 1) . '%</div>';
echo '</div>';
echo '<div class="circle-title">Total Valid Votes Percentage</div>';

echo '<div class="circle-progress">';
echo '<div class="circle"></div>';
echo '<div class="circle-fill" style="background: conic-gradient(#FF6384 calc(' . ($total_rejected_votes / $total_electors * 100) . '%), #ddd 0);"></div>';
echo '<div class="circle-text">' . round($total_rejected_votes / $total_electors * 100, 1) . '%</div>';
echo '</div>';
echo '<div class="circle-title">Total Rejected Votes Percentage</div>';
echo '</div>';

// Close the database connection
$conn->close();

function hexToRgb($hex) {
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 6) {
        list($r, $g, $b) = str_split($hex, 2);
        return hexdec($r) . ", " . hexdec($g) . ", " . hexdec($b);
    }
    return "0, 0, 0";
}
?>
