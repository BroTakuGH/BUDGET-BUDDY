<?php
// Check if form submitted with a search term
$searchTerm = $_GET['q'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>BlueCart Product Search</title>
</head>
<body>
    <h1>Search Products</h1>
    <form method="GET" action="search.php">
        <input type="text" name="q" placeholder="Enter search term" value="<?php echo htmlspecialchars($searchTerm); ?>" required />
        <button type="submit">Search</button>
    </form>

    <?php
    if ($searchTerm) {
        // Build query string for BlueCart API
        $queryString = http_build_query([
            'api_key' => '86F0B34FB70E43D4874888E5840D20AB',
            'walmart_domain' => 'walmart.com',
            'type' => 'search',
            'search_term' => $searchTerm
        ]);

        $url = 'https://api.bluecartapi.com/request?' . $queryString;

        // cURL call to BlueCart API
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);

        
        $response = curl_exec($ch);
        echo "<h3>Raw API JSON Response</h3>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";

        if (curl_errno($ch)) {
            echo '<p>Error: ' . curl_error($ch) . '</p>';
        }

        curl_close($ch);

        // Decode API response
        $data = json_decode($response, true);

        if (!$data || !isset($data['search_results']) || empty($data['search_results'])) {
    echo '<p>No products found for "' . htmlspecialchars($searchTerm) . '".</p>';
} else {
    echo '<h2>Results for "' . htmlspecialchars($searchTerm) . '"</h2>';
    echo '<ul style="list-style:none; padding:0;">';
    foreach ($data['search_results'] as $item) {
        $title = htmlspecialchars($item['product']['title'] ?? 'No title');
        $price = isset($item['offers']['primary']['price']) ? $item['offers']['primary']['price'] : 'No price';
        $link = htmlspecialchars($item['product']['link'] ?? '#');
        $image = htmlspecialchars($item['product']['main_image'] ?? '');

        echo '<li style="margin-bottom:20px; display:flex; align-items:center;">';
        if ($image) {
            echo "<a href=\"$link\" target=\"_blank\"><img src=\"$image\" alt=\"$title\" style=\"width:100px; height:auto; margin-right:15px; border:1px solid #ccc; padding:3px;\" /></a>";
        }
        echo "<div><a href=\"$link\" target=\"_blank\" style=\"font-weight:bold; font-size:1.1em; text-decoration:none; color:#333;\">$title</a><br />";
        echo "Price: \$$price</div>";
        echo '</li>';
    }
    echo '</ul>';
}


    }
    ?>
</body>
</html>