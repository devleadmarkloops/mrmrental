<?php
// listing.php — Single Listing Page
require_once 'config.php';

$id       = $_GET['id'] ?? '';
$checkIn  = $_GET['checkIn'] ?? '';
$checkOut = $_GET['checkOut'] ?? '';
$guests   = $_GET['guests'] ?? '';

if (!$id) { header('Location: index.php'); exit; }

try {
    $listing = guestyGet('/api/listings/' . urlencode($id));
} catch (Exception $e) {
    $error = $e->getMessage();
    $listing = [];
}

$quote = null;
$quoteError = '';
$hasParams = ($checkIn && $checkOut && $guests);

if ($hasParams && !empty($listing)) {
    try {
        $quote = guestyPost('/api/reservations/quotes', [
            'listingId'             => $id,
            'checkInDateLocalized'  => $checkIn,
            'checkOutDateLocalized' => $checkOut,
            'guestsCount'           => (int)$guests,
        ]);
    } catch (Exception $e) {
        $quoteError = $e->getMessage();
    }
}

$title        = $listing['title'] ?? 'Untitled';
$description  = $listing['publicDescription']['summary'] ?? '';
$space        = $listing['publicDescription']['space'] ?? '';
$access       = $listing['publicDescription']['access'] ?? '';
$neighborhood = $listing['publicDescription']['neighborhood'] ?? '';
$interaction  = $listing['publicDescription']['interactionWithGuests'] ?? '';
$transit      = $listing['publicDescription']['transit'] ?? '';
$notes        = $listing['publicDescription']['notes'] ?? '';
$bedrooms     = $listing['bedrooms'] ?? '—';
$bathrooms    = $listing['bathrooms'] ?? '—';
$beds         = $listing['beds'] ?? '—';
$pictures     = $listing['pictures'] ?? [];
$amenities    = $listing['amenities'] ?? [];
$checkInTime  = $listing['defaultCheckInTime'] ?? '';
$checkOutTime = $listing['defaultCheckOutTime'] ?? '';
$lat          = $listing['address']['lat'] ?? null;
$lng          = $listing['address']['lng'] ?? null;

$quoteId = '';
$subtotal = $feesTotal = $taxesTotal = $total = $nights = 0;
$feesList = $taxesList = [];
$cancelPolicy = '';

if ($quote) {
    $quoteId  = $quote['_id'] ?? '';
    $ratePlan = $quote['rates']['ratePlans'][0] ?? [];
    $money    = $ratePlan['money'] ?? [];

    $subtotal   = $money['fareAccommodation'] ?? 0;
    $feesTotal  = $money['totalFees'] ?? 0;
    $taxesTotal = $money['totalTaxes'] ?? 0;
    $total      = $money['hostPayout'] ?? 0;

    $d1 = new DateTime($checkIn);
    $d2 = new DateTime($checkOut);
    $nights = $d1->diff($d2)->days;

    $cancelPolicy = $ratePlan['cancellationPolicy'] ?? '';

    foreach (($money['invoiceItems'] ?? []) as $item) {
        $t = $item['type'] ?? '';
        if (str_contains($t, 'FEE'))      $feesList[]  = $item;
        elseif (str_contains($t, 'TAX'))   $taxesList[] = $item;
    }
}

function fmtMoney($a) { return '$' . number_format((float)$a, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — MRMVR</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f9f9f9;color:#333}
.site-header{background:#fdf6e3;padding:12px 40px;text-align:center;border-bottom:1px solid #eee}
.site-header a{text-decoration:none}
.site-header h1{font-size:28px;font-weight:700;color:#b8860b}
.gallery{max-width:1280px;margin:0 auto;display:flex;gap:8px;padding:20px 20px 0}
.gallery-main{flex:2;position:relative;border-radius:12px;overflow:hidden;max-height:500px}
.gallery-main img{width:100%;height:100%;object-fit:cover;display:block}
.gallery-side{flex:1;display:flex;flex-direction:column;gap:8px}
.gallery-side img{width:100%;flex:1;object-fit:cover;border-radius:12px;cursor:pointer}
.gallery-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.85);border:none;width:40px;height:40px;border-radius:50%;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.gallery-nav.prev{left:12px} .gallery-nav.next{right:12px}
.gallery-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:6px}
.gallery-dots .dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.5);cursor:pointer}
.gallery-dots .dot.active{background:#fff}
.content-wrap{max-width:1280px;margin:0 auto;padding:24px 20px 60px;display:flex;gap:40px;align-items:flex-start}
.content-main{flex:1;min-width:0}
.content-sidebar{width:360px;flex-shrink:0;position:sticky;top:20px}
.breadcrumb{font-size:14px;color:#666;margin-bottom:12px}
.breadcrumb a{color:#333;text-decoration:none}
.listing-title{font-size:28px;font-weight:700;margin-bottom:24px;line-height:1.3}
.section-heading{font-size:16px;font-weight:700;margin:24px 0 8px}
.section-text{font-size:15px;line-height:1.7;color:#444;white-space:pre-line;margin-bottom:16px}
.section-divider{border:none;border-top:1px solid #e0e0e0;margin:24px 0}
.features{display:flex;gap:32px;margin:16px 0}
.feature-item{text-align:center} .feature-icon{font-size:28px;margin-bottom:4px;color:#555} .feature-label{font-size:14px;color:#333}
.amenities-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:16px 0}
.amenity-item{text-align:center;font-size:13px;color:#444}
.amenity-icon{font-size:20px;margin-bottom:4px;display:block;color:#666}
.show-all-link{color:#1a73e8;font-size:14px;cursor:pointer;text-decoration:none;display:inline-block;margin-top:8px}
.map-container{width:100%;height:260px;border-radius:12px;overflow:hidden;margin:16px 0}
.map-container iframe{width:100%;height:100%;border:0}
.sidebar-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.sidebar-search-title{font-size:18px;font-weight:700;margin-bottom:20px}
.sidebar-field{border:1px solid #ccc;border-radius:8px;padding:12px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;cursor:pointer;position:relative}
.sidebar-field .field-icon{color:#888;font-size:18px}
.sidebar-field input,.sidebar-field select{border:none;outline:none;font-size:15px;color:#333;background:transparent;width:100%}
.sidebar-search-btn{width:100%;padding:14px;background:#aaa;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;margin-top:4px}
.sidebar-search-btn.active-btn{background:#4a90d9}
.sidebar-cal-popup{display:none;position:absolute;top:calc(100% + 8px);left:-1px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.15);padding:20px;z-index:1001;width:520px}
.sidebar-cal-popup.open{display:block}
.cal-nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.cal-nav button{background:none;border:none;font-size:18px;cursor:pointer;color:#333;padding:4px 8px}
.cal-months-row{display:flex;gap:20px}
.cal-m{flex:1} .cal-m-title{text-align:center;font-weight:600;font-size:14px;margin-bottom:8px}
.cal-g{display:grid;grid-template-columns:repeat(7,1fr);text-align:center}
.cal-g .dh{font-size:11px;color:#999;padding:4px 0 6px}
.cal-g .dc{padding:6px 2px;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;aspect-ratio:1}
.cal-g .dc:hover:not(.empty):not(.past){background:#e8f4fd}
.cal-g .dc.empty{cursor:default} .cal-g .dc.past{color:#ccc;cursor:default;text-decoration:line-through}
.cal-g .dc.today{font-weight:700;border:2px solid #4a90d9;border-radius:50%}
.cal-g .dc.sel-start,.cal-g .dc.sel-end{background:#4a90d9;color:#fff;border-radius:50%;font-weight:600}
.cal-g .dc.in-range{background:#e8f4fd}
.quote-title{font-size:17px;font-weight:700;color:#b8860b;margin-bottom:16px;line-height:1.3}
.quote-dates{display:flex;margin-bottom:16px}
.quote-date-col{flex:1} .quote-date-col .label{font-size:12px;color:#888;display:block;margin-bottom:2px} .quote-date-col .value{font-size:14px;font-weight:600}
.cancel-select{width:100%;padding:10px 12px;border:2px solid #4a90d9;border-radius:8px;font-size:14px;background:#fff;margin-bottom:12px}
.coupon-row{font-size:14px;color:#555;margin-bottom:16px;display:flex;align-items:center;gap:6px}
.price-row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px}
.price-row .price-amount{font-weight:600}
.price-row.total-row{border-top:1px solid #e0e0e0;margin-top:4px;padding-top:12px}
.price-row.total-row .price-label{font-size:16px;font-weight:700} .price-row.total-row .price-amount{font-size:18px;font-weight:700}
.price-row.sub-total{font-weight:600}
.expandable-label{cursor:pointer;display:flex;align-items:center;gap:4px}
.expandable-label .arrow{font-size:10px;transition:transform .2s} .expandable-label .arrow.open{transform:rotate(180deg)}
.expand-details{display:none;padding-left:12px} .expand-details.open{display:block}
.expand-detail-row{display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:#666}
.book-row{display:flex;gap:8px;margin-top:16px}
.back-btn{width:44px;height:44px;border:1px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;color:#333;flex-shrink:0;text-decoration:none}
.book-btn{flex:1;padding:12px;background:#4a90d9;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center}
.error-box{background:#fee;border:1px solid #fcc;color:#c33;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px}
@media(max-width:900px){.content-wrap{flex-direction:column}.content-sidebar{width:100%;position:static}.gallery{flex-direction:column}.gallery-side{flex-direction:row}.sidebar-cal-popup{width:100%}.amenities-grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="site-header"><a href="index.php"><h1>MRMVR</h1></a></div>

<?php if (!empty($error)): ?>
<div style="max-width:1280px;margin:20px auto;padding:0 20px;"><div class="error-box"><?= htmlspecialchars($error) ?></div></div>
<?php endif; ?>

<?php if (!empty($listing)): ?>
<div class="gallery">
    <div class="gallery-main">
        <?php if (!empty($pictures)): ?>
            <img id="mainImage" src="<?= htmlspecialchars($pictures[0]['original'] ?? $pictures[0]['thumbnail'] ?? '') ?>" alt="<?= htmlspecialchars($title) ?>">
            <?php if (count($pictures) > 1): ?>
                <button class="gallery-nav prev" onclick="slideGallery(-1)">&lsaquo;</button>
                <button class="gallery-nav next" onclick="slideGallery(1)">&rsaquo;</button>
                <div class="gallery-dots" id="galleryDots">
                    <?php foreach ($pictures as $i => $p): ?><span class="dot<?= $i===0?' active':'' ?>" onclick="goToSlide(<?= $i ?>)"></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="height:400px;background:#ddd;display:flex;align-items:center;justify-content:center;color:#aaa;">No images</div>
        <?php endif; ?>
    </div>
    <?php if (count($pictures) > 1): ?>
    <div class="gallery-side">
        <?php for ($i=1; $i<=min(3,count($pictures)-1); $i++): ?>
            <img src="<?= htmlspecialchars($pictures[$i]['original'] ?? $pictures[$i]['thumbnail'] ?? '') ?>" alt="" onclick="goToSlide(<?= $i ?>)">
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div class="content-wrap">
<div class="content-main">
    <div class="breadcrumb"><a href="index.php">Home</a> &rsaquo; <?= htmlspecialchars($title) ?></div>
    <h2 class="listing-title"><?= htmlspecialchars($title) ?></h2>

    <?php if ($description): ?><h3 class="section-heading">Description</h3><div class="section-text"><?= nl2br(htmlspecialchars($description)) ?></div><?php endif; ?>
    <?php if ($space): ?><h3 class="section-heading">The Space</h3><div class="section-text"><?= nl2br(htmlspecialchars($space)) ?></div><?php endif; ?>
    <?php if ($access): ?><h3 class="section-heading">Guest Access</h3><div class="section-text"><?= nl2br(htmlspecialchars($access)) ?></div><?php endif; ?>
    <?php if ($neighborhood): ?><h3 class="section-heading">Neighborhood</h3><div class="section-text"><?= nl2br(htmlspecialchars($neighborhood)) ?></div><?php endif; ?>
    <?php if ($interaction): ?><h3 class="section-heading">Interaction</h3><div class="section-text"><?= nl2br(htmlspecialchars($interaction)) ?></div><?php endif; ?>
    <?php if ($notes): ?><h3 class="section-heading">Other things to note</h3><div class="section-text"><?= nl2br(htmlspecialchars($notes)) ?></div><?php endif; ?>
    <?php if ($transit): ?><h3 class="section-heading">Getting Around</h3><div class="section-text"><?= nl2br(htmlspecialchars($transit)) ?></div><?php endif; ?>
    <?php if ($checkInTime || $checkOutTime): ?><h3 class="section-heading">Check in and out</h3><div class="section-text"><?php if($checkInTime):?>Check in: <?= htmlspecialchars($checkInTime)?><br><?php endif;?><?php if($checkOutTime):?>Check out: <?= htmlspecialchars($checkOutTime)?><?php endif;?></div><?php endif; ?>

    <hr class="section-divider">
    <h3 class="section-heading">Property features</h3>
    <div class="features">
        <div class="feature-item"><div class="feature-icon">&#128719;</div><div class="feature-label"><?= $bedrooms ?> Bedroom<?= $bedrooms!=1?'s':'' ?></div></div>
        <div class="feature-item"><div class="feature-icon">&#128716;</div><div class="feature-label"><?= $beds ?> Bed<?= $beds!=1?'s':'' ?></div></div>
        <div class="feature-item"><div class="feature-icon">&#128704;</div><div class="feature-label"><?= $bathrooms ?> Bathroom<?= $bathrooms!=1?'s':'' ?></div></div>
    </div>

    <?php if (!empty($amenities)): ?>
    <hr class="section-divider">
    <h3 class="section-heading">Amenities</h3>
    <div class="amenities-grid">
        <?php foreach (array_slice($amenities,0,8) as $a): ?><div class="amenity-item"><span class="amenity-icon">&#10003;</span><?= htmlspecialchars($a) ?></div><?php endforeach; ?>
    </div>
    <?php if (count($amenities)>8): ?>
        <div id="amenitiesExtra" style="display:none"><div class="amenities-grid"><?php foreach(array_slice($amenities,8) as $a):?><div class="amenity-item"><span class="amenity-icon">&#10003;</span><?= htmlspecialchars($a)?></div><?php endforeach;?></div></div>
        <a class="show-all-link" onclick="document.getElementById('amenitiesExtra').style.display='block';this.style.display='none'">Show all <?= count($amenities) ?> amenities</a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($lat && $lng): ?>
    <hr class="section-divider">
    <div class="map-container"><iframe src="https://www.openstreetmap.org/export/embed.html?bbox=<?=($lng-0.01)?>,<?=($lat-0.008)?>,<?=($lng+0.01)?>,<?=($lat+0.008)?>&layer=mapnik&marker=<?=$lat?>,<?=$lng?>" loading="lazy"></iframe></div>
    <?php endif; ?>
</div>
<div class="content-sidebar">
<div class="sidebar-card">

<?php if ($hasParams && $quote && !$quoteError): ?>
    <div class="quote-title"><?= htmlspecialchars($title) ?></div>
    <div class="quote-dates">
        <div class="quote-date-col"><span class="label">Check In</span><span class="value"><?= date('M j, Y', strtotime($checkIn)) ?></span></div>
        <div class="quote-date-col"><span class="label">Check Out</span><span class="value"><?= date('M j, Y', strtotime($checkOut)) ?></span></div>
        <div class="quote-date-col"><span class="label">Nights</span><span class="value"><?= $nights ?> Night<?= $nights!=1?'s':'' ?></span></div>
        <div class="quote-date-col"><span class="label">Guests</span><span class="value"><?= htmlspecialchars($guests) ?></span></div>
    </div>
    <?php if ($cancelPolicy): ?><select class="cancel-select"><option><?= htmlspecialchars($cancelPolicy) ?></option></select><?php endif; ?>
    <label class="coupon-row"><input type="checkbox"> I have a coupon</label>
    <div class="price-row"><span class="price-label">Subtotal</span><span class="price-amount"><?= fmtMoney($subtotal) ?></span></div>
    <?php if ($feesTotal > 0): ?>
        <div class="price-row"><span class="price-label expandable-label" onclick="toggleExpand('feesD',this)">Fees <span class="arrow">&#9662;</span></span><span class="price-amount"><?= fmtMoney($feesTotal) ?></span></div>
        <div class="expand-details" id="feesD"><?php foreach($feesList as $f):?><div class="expand-detail-row"><span><?= htmlspecialchars($f['title']??'Fee')?></span><span><?= fmtMoney($f['amount']??0)?></span></div><?php endforeach;?></div>
    <?php endif; ?>
    <div class="price-row sub-total"><span class="price-label">Subtotal before taxes</span><span class="price-amount"><?= fmtMoney($subtotal + $feesTotal) ?></span></div>
    <?php if ($taxesTotal > 0): ?>
        <div class="price-row"><span class="price-label expandable-label" onclick="toggleExpand('taxD',this)">Taxes <span class="arrow">&#9662;</span></span><span class="price-amount"><?= fmtMoney($taxesTotal) ?></span></div>
        <div class="expand-details" id="taxD"><?php foreach($taxesList as $t):?><div class="expand-detail-row"><span><?= htmlspecialchars($t['title']??'Tax')?></span><span><?= fmtMoney($t['amount']??0)?></span></div><?php endforeach;?></div>
    <?php endif; ?>
    <div class="price-row total-row"><span class="price-label">Total</span><span class="price-amount"><?= fmtMoney($total) ?></span></div>
    <div class="book-row">
        <a class="back-btn" href="listing.php?id=<?= urlencode($id) ?>">&lsaquo;</a>
        <a class="book-btn" href="book.php?id=<?= urlencode($id) ?>&quoteId=<?= urlencode($quoteId) ?>">Book now</a>
    </div>

<?php elseif ($hasParams && $quoteError): ?>
    <div class="sidebar-search-title">Pricing Error</div>
    <div class="error-box"><?= htmlspecialchars($quoteError) ?></div>
    <a class="back-btn" href="listing.php?id=<?= urlencode($id) ?>" style="display:inline-flex;text-decoration:none;margin-top:8px">&lsaquo; Search again</a>

<?php else: ?>
    <div class="sidebar-search-title">Search for available dates</div>
    <form id="searchForm" method="GET" action="listing.php">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
        <div class="sidebar-field" id="dateField">
            <span class="field-icon">&#128197;</span>
            <span id="dateLabel" style="font-size:15px;color:#999;flex:1;cursor:pointer">Start date - End date</span>
            <span style="color:#999;font-size:12px">&#9662;</span>
            <input type="hidden" name="checkIn" id="sCheckIn">
            <input type="hidden" name="checkOut" id="sCheckOut">
            <div class="sidebar-cal-popup" id="sCalPopup">
                <div class="cal-nav">
                    <button type="button" id="sCalPrev">&lsaquo;</button>
                    <div class="cal-months-row" id="sCalMonths"></div>
                    <button type="button" id="sCalNext">&rsaquo;</button>
                </div>
            </div>
        </div>
        <div class="sidebar-field">
            <span class="field-icon">&#128100;</span>
            <select name="guests" id="guestsInput">
                <option value="">Guests</option>
                <?php for($g=1;$g<=16;$g++):?><option value="<?=$g?>"><?=$g?> Guest<?=$g>1?'s':''?></option><?php endfor;?>
            </select>
        </div>
        <button type="submit" class="sidebar-search-btn" id="searchBtn">Search</button>
    </form>
<?php endif; ?>

</div>
</div>
</div>
<?php endif; ?>

<script>
(function(){
    var pics=<?= json_encode(array_map(function($p){return $p['original']??$p['thumbnail']??'';}, $pictures))?>;
    var idx=0, mainImg=document.getElementById('mainImage'), dots=document.querySelectorAll('#galleryDots .dot');
    window.goToSlide=function(i){idx=i;if(mainImg)mainImg.src=pics[idx];dots.forEach(function(d,j){d.classList.toggle('active',j===idx)})};
    window.slideGallery=function(dir){idx=(idx+dir+pics.length)%pics.length;goToSlide(idx)};
    window.toggleExpand=function(id,el){var d=document.getElementById(id);if(!d)return;var o=d.classList.toggle('open');var a=el.querySelector('.arrow');if(a)a.classList.toggle('open',o)};

    var calPopup=document.getElementById('sCalPopup');
    if(!calPopup)return;
    var dateField=document.getElementById('dateField'),dateLabel=document.getElementById('dateLabel');
    var ciF=document.getElementById('sCheckIn'),coF=document.getElementById('sCheckOut');
    var calM=document.getElementById('sCalMonths'),searchBtn=document.getElementById('searchBtn');
    var today=new Date();today.setHours(0,0,0,0);
    var bM=today.getMonth(),bY=today.getFullYear(),sD=null,eD=null;
    var mn=['January','February','March','April','May','June','July','August','September','October','November','December'];
    var sm=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function fY(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
    function fL(d){return sm[d.getMonth()]+' '+d.getDate()}

    function render(){
        calM.innerHTML='';
        for(var o=0;o<2;o++){
            var m=bM+o,y=bY;if(m>11){m-=12;y++}
            var md=document.createElement('div');md.className='cal-m';
            md.innerHTML='<div class="cal-m-title">'+mn[m]+' '+y+'</div>';
            var g=document.createElement('div');g.className='cal-g';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(d){var h=document.createElement('div');h.className='dh';h.textContent=d;g.appendChild(h)});
            var f=new Date(y,m,1).getDay(),dim=new Date(y,m+1,0).getDate();
            for(var e=0;e<f;e++){var ec=document.createElement('div');ec.className='dc empty';g.appendChild(ec)}
            for(var d=1;d<=dim;d++){
                var c=document.createElement('div');c.className='dc';c.textContent=d;
                var cd=new Date(y,m,d);cd.setHours(0,0,0,0);
                if(cd<today){c.classList.add('past')}else{
                    if(cd.getTime()===today.getTime())c.classList.add('today');
                    if(sD&&cd.getTime()===sD.getTime())c.classList.add('sel-start');
                    if(eD&&cd.getTime()===eD.getTime())c.classList.add('sel-end');
                    if(sD&&eD&&cd>sD&&cd<eD)c.classList.add('in-range');
                    (function(dt){c.addEventListener('click',function(ev){ev.preventDefault();ev.stopPropagation();pick(dt)})})(cd);
                }
                g.appendChild(c);
            }
            md.appendChild(g);calM.appendChild(md);
        }
    }

    function pick(dt){
        if(!sD||(sD&&eD)||dt<sD){sD=dt;eD=null;ciF.value=fY(dt);coF.value='';dateLabel.textContent=fL(dt)+' - ...';dateLabel.style.color='#333'}
        else{eD=dt;coF.value=fY(dt);dateLabel.textContent=fL(sD)+' - '+fL(eD);setTimeout(function(){calPopup.classList.remove('open')},200)}
        upBtn();render();
    }

    function upBtn(){
        if(ciF.value&&coF.value&&document.getElementById('guestsInput').value)searchBtn.classList.add('active-btn');
        else searchBtn.classList.remove('active-btn');
    }

    dateField.addEventListener('click',function(ev){if(ev.target.closest('.sidebar-cal-popup'))return;ev.preventDefault();ev.stopPropagation();calPopup.classList.toggle('open');render()});
    document.getElementById('sCalPrev').addEventListener('click',function(ev){ev.preventDefault();ev.stopPropagation();bM--;if(bM<0){bM=11;bY--}render()});
    document.getElementById('sCalNext').addEventListener('click',function(ev){ev.preventDefault();ev.stopPropagation();bM++;if(bM>11){bM=0;bY++}render()});
    document.addEventListener('click',function(ev){if(!ev.target.closest('#dateField')&&!ev.target.closest('.sidebar-cal-popup'))calPopup.classList.remove('open')});
    var gi=document.getElementById('guestsInput');if(gi)gi.addEventListener('change',upBtn);
    render();
})();
</script>
</body>
</html>

