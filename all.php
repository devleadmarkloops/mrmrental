<?php
// all.php — Search Results / Listings Page
require_once 'config.php';

$city     = $_GET['city']     ?? '';
$checkIn  = $_GET['checkIn']  ?? '';
$checkOut = $_GET['checkOut'] ?? '';
$guests   = $_GET['guests']   ?? '';
$cursor   = $_GET['cursor']   ?? null;
$limit    = 12;

try {
    $params = ['limit' => $limit];
    if ($city)     $params['city']     = $city;
    if ($checkIn)  $params['checkIn']  = $checkIn;
    if ($checkOut) $params['checkOut'] = $checkOut;
    if ($cursor)   $params['cursor']   = $cursor;

    $response   = guestyGet('/api/listings', $params);
    $listings   = $response['results'] ?? [];
    $pagination = $response['pagination'] ?? [];
    $nextCursor = $pagination['cursor']['next'] ?? null;

    if ($guests && is_numeric($guests)) {
        $listings = array_values(array_filter($listings, function($l) use ($guests) {
            return ($l['accommodates'] ?? 0) >= (int)$guests;
        }));
    }
} catch (Exception $e) {
    $error    = $e->getMessage();
    $listings = [];
}

$loadMoreParams = [];
if ($city)       $loadMoreParams['city']     = $city;
if ($checkIn)    $loadMoreParams['checkIn']  = $checkIn;
if ($checkOut)   $loadMoreParams['checkOut'] = $checkOut;
if ($guests)     $loadMoreParams['guests']   = $guests;
if ($nextCursor) $loadMoreParams['cursor']   = $nextCursor;
$loadMoreQuery = http_build_query($loadMoreParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MRMVR — Listings</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;color:#333}
.site-header{background:#fdf6e3;padding:12px 40px;text-align:center;border-bottom:1px solid #eee}
.site-header a{text-decoration:none}
.site-header h1{font-size:28px;font-weight:700;color:#b8860b}
.container{max-width:1200px;margin:30px auto;padding:0 20px}
.results-info{font-size:14px;color:#888;margin-bottom:20px}
.error{background:#fee;border:1px solid #fcc;color:#c33;padding:15px;border-radius:8px;margin-bottom:20px}
.listings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px}
.listing-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);transition:transform .2s,box-shadow .2s;text-decoration:none;color:inherit;display:block}
.listing-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.12)}
.listing-image{width:100%;height:200px;object-fit:cover;background:#ddd}
.listing-info{padding:16px}
.listing-title{font-size:18px;font-weight:600;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.listing-address{font-size:14px;color:#666;margin-bottom:10px}
.listing-meta{display:flex;gap:12px;font-size:13px;color:#888}
.listing-meta span{display:flex;align-items:center;gap:4px}
.listing-price{margin-top:12px;font-size:18px;font-weight:700;color:#1a1a2e}
.listing-price small{font-weight:400;font-size:13px;color:#999}
.pagination{text-align:center;margin-top:40px}
.pagination a{display:inline-block;padding:12px 32px;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:8px;font-weight:500}
.pagination a:hover{background:#16213e}
.no-listings{text-align:center;padding:60px 20px;color:#999;font-size:18px}
</style>
</head>
<body>
<div class="site-header"><a href="index.php"><h1>MRMVR</h1></a></div>

<div class="container">
    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php
        $infoFragments = [];
        if ($city)                 $infoFragments[] = htmlspecialchars($city);
        if ($checkIn && $checkOut) $infoFragments[] = htmlspecialchars(date('M j', strtotime($checkIn)) . ' – ' . date('M j, Y', strtotime($checkOut)));
        if ($guests)               $infoFragments[] = htmlspecialchars($guests) . ' guest' . ($guests > 1 ? 's' : '');
    ?>
    <?php if ($infoFragments): ?>
        <div class="results-info">Showing results for <?= implode(' &middot; ', $infoFragments) ?></div>
    <?php endif; ?>

    <?php if (empty($listings)): ?>
        <div class="no-listings">No listings found. <a href="index.php">Try a new search.</a></div>
    <?php else: ?>
        <div class="listings-grid">
            <?php foreach ($listings as $listing):
                $id        = $listing['_id'] ?? '';
                $title     = $listing['title'] ?? 'Untitled Listing';
                $address   = $listing['address']['full'] ?? ($listing['address']['city'] ?? 'Location not available');
                $bedrooms  = $listing['bedrooms'] ?? '—';
                $bathrooms = $listing['bathrooms'] ?? '—';
                $guestsNum = $listing['accommodates'] ?? '—';
                $image     = $listing['pictures'][0]['thumbnail'] ?? $listing['pictures'][0]['original'] ?? '';
                $price     = $listing['prices']['basePrice'] ?? null;
                $currency  = $listing['prices']['currency'] ?? 'USD';
                $listingParams = ['id' => $id];
                if ($checkIn)  $listingParams['checkIn']  = $checkIn;
                if ($checkOut) $listingParams['checkOut'] = $checkOut;
                if ($guests)   $listingParams['guests']   = $guests;
            ?>
            <a href="listing.php?<?= http_build_query($listingParams) ?>" class="listing-card">
                <?php if ($image): ?>
                    <img class="listing-image" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
                <?php else: ?>
                    <div class="listing-image" style="display:flex;align-items:center;justify-content:center;color:#aaa;">No Image</div>
                <?php endif; ?>
                <div class="listing-info">
                    <div class="listing-title"><?= htmlspecialchars($title) ?></div>
                    <div class="listing-address"><?= htmlspecialchars($address) ?></div>
                    <div class="listing-meta">
                        <span><?= htmlspecialchars($bedrooms) ?> bed<?= $bedrooms != 1 ? 's' : '' ?></span>
                        <span><?= htmlspecialchars($bathrooms) ?> bath<?= $bathrooms != 1 ? 's' : '' ?></span>
                        <span><?= htmlspecialchars($guestsNum) ?> guest<?= $guestsNum != 1 ? 's' : '' ?></span>
                    </div>
                    <?php if ($price): ?>
                        <div class="listing-price"><?= htmlspecialchars($currency) ?> <?= number_format($price) ?><small> / night</small></div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($nextCursor): ?>
            <div class="pagination"><a href="?<?= $loadMoreQuery ?>">Load More Listings &rarr;</a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
