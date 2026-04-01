<?php
// index.php — Listings Page with Search Form

require_once 'config.php';

try {
    // Get search parameters
    $city      = $_GET['city'] ?? '';
    $checkIn   = $_GET['checkIn'] ?? '';
    $checkOut  = $_GET['checkOut'] ?? '';
    $guests    = $_GET['guests'] ?? '';
    $cursor    = $_GET['cursor'] ?? null;
    $limit     = 12;

    $params = ['limit' => $limit];

    // Only add location filter if city is provided
    if ($city) {
        $params['city'] = $city;
    }
    if ($checkIn) {
        $params['checkIn'] = $checkIn;
    }
    if ($checkOut) {
        $params['checkOut'] = $checkOut;
    }
    // NOTE: Do NOT send 'guests' to the API — it's not a valid parameter.
    // We filter client-side using the 'accommodates' field instead.

    if ($cursor) {
        $params['cursor'] = $cursor;
    }

    $response   = guestyGet('/api/listings', $params);
    $listings   = $response['results'] ?? [];
    $pagination = $response['pagination'] ?? [];
    $nextCursor = $pagination['cursor']['next'] ?? null;

    // Client-side filter: remove listings that can't accommodate the requested guests
    if ($guests && is_numeric($guests)) {
        $listings = array_filter($listings, function($listing) use ($guests) {
            $accommodates = $listing['accommodates'] ?? 0;
            return $accommodates >= (int)$guests;
        });
        $listings = array_values($listings); // Re-index array
    }

} catch (Exception $e) {
    $error    = $e->getMessage();
    $listings = [];
}

// Build "Load More" query string preserving search params
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
    <title>MRMVR — Vacation Rentals</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        /* ── Hero / Header ───────────────────────────── */
        .hero {
            background: #fff;
            text-align: center;
            padding: 50px 20px 30px;
        }
        .hero h1 {
            font-size: 56px;
            font-weight: 700;
            color: #333;
            letter-spacing: 2px;
            margin-bottom: 30px;
        }

        /* ── Search Bar ──────────────────────────────── */
        .search-bar {
            max-width: 960px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 50px;
            display: flex;
            align-items: center;
            padding: 8px 8px 8px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            position: relative;
        }
        .search-field {
            flex: 1;
            min-width: 0;
            padding: 0 16px;
        }
        .search-field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .search-field input,
        .search-field select {
            width: 100%;
            border: none;
            outline: none;
            font-size: 14px;
            color: #666;
            background: transparent;
            padding: 2px 0;
            cursor: pointer;
        }
        .search-field input::placeholder { color: #aaa; }
        .search-divider {
            width: 1px;
            height: 36px;
            background: #e0e0e0;
            flex-shrink: 0;
        }
        .search-btn {
            background: #e53e3e;
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .search-btn:hover { background: #c53030; }

        /* ── Destination Autocomplete ───────────────── */
        .destination-wrapper { position: relative; }
        .destination-wrapper .icon {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
            pointer-events: none;
        }
        .destination-wrapper input { padding-left: 22px !important; }
        .city-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            max-height: 220px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 8px;
        }
        .city-dropdown .city-item {
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }
        .city-dropdown .city-item:hover { background: #f5f5f5; }

        /* ── Date Picker Trigger ─────────────────────── */
        .date-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            user-select: none;
            padding: 2px 0;
        }
        .date-trigger .cal-icon { color: #999; font-size: 16px; }
        .date-trigger .arrow { color: #999; font-size: 12px; margin-left: auto; }

        /* ── Calendar Popup ──────────────────────────── */
        .calendar-popup {
            display: none;
            position: absolute;
            top: calc(100% + 12px);
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 24px;
            z-index: 1001;
            width: 620px;
        }
        .calendar-popup.open { display: block; }
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .calendar-nav button {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #333;
            padding: 4px 8px;
            border-radius: 50%;
        }
        .calendar-nav button:hover { background: #f0f0f0; }
        .calendar-months {
            display: flex;
            gap: 32px;
        }
        .cal-month {
            flex: 1;
        }
        .cal-month-title {
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            color: #333;
            margin-bottom: 12px;
        }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            text-align: center;
        }
        .cal-grid .day-header {
            font-size: 12px;
            color: #999;
            font-weight: 500;
            padding: 4px 0 8px;
        }
        .cal-grid .day-cell {
            padding: 8px 2px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 50%;
            transition: background 0.15s, color 0.15s;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 0;
        }
        .cal-grid .day-cell:hover:not(.empty):not(.past):not(.disabled) {
            background: #e8f4fd;
        }
        .cal-grid .day-cell.empty { cursor: default; }
        .cal-grid .day-cell.past {
            color: #ccc;
            cursor: default;
            text-decoration: line-through;
        }
        .cal-grid .day-cell.today {
            font-weight: 700;
            border: 2px solid #4a90d9;
            border-radius: 50%;
        }
        .cal-grid .day-cell.selected-start,
        .cal-grid .day-cell.selected-end {
            background: #4a90d9;
            color: #fff;
            border-radius: 50%;
            font-weight: 600;
            z-index: 1;
        }
        .cal-grid .day-cell.in-range {
            background: #e8f4fd;
            color: #333;
        }
        .cal-grid .day-cell.in-range.first-in-row { border-radius: 50% 0 0 50%; }
        .cal-grid .day-cell.in-range.last-in-row  { border-radius: 0 50% 50% 0; }

        /* ── Guests Dropdown ─────────────────────────── */
        .guests-select {
            appearance: none;
            -webkit-appearance: none;
            background: transparent;
            padding-right: 16px !important;
        }

        /* ── Container & Listings ────────────────────── */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .results-info {
            font-size: 14px;
            color: #888;
            margin-bottom: 20px;
        }
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .listing-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .listing-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .listing-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #ddd;
        }
        .listing-info { padding: 16px; }
        .listing-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .listing-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .listing-meta {
            display: flex;
            gap: 12px;
            font-size: 13px;
            color: #888;
        }
        .listing-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .listing-price {
            margin-top: 12px;
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .listing-price small {
            font-weight: 400;
            font-size: 13px;
            color: #999;
        }
        .pagination {
            text-align: center;
            margin-top: 40px;
        }
        .pagination a {
            display: inline-block;
            padding: 12px 32px;
            background: #1a1a2e;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }
        .pagination a:hover { background: #16213e; }
        .no-listings {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-size: 18px;
        }

        /* ── Responsive ──────────────────────────────── */
        @media (max-width: 768px) {
            .hero h1 { font-size: 36px; }
            .search-bar {
                flex-direction: column;
                border-radius: 16px;
                padding: 16px;
                gap: 12px;
            }
            .search-divider {
                width: 100%;
                height: 1px;
            }
            .search-field { padding: 8px 0; }
            .search-btn { width: 100%; }
            .calendar-popup {
                width: 95vw;
                left: 50%;
                padding: 16px;
            }
            .calendar-months { flex-direction: column; gap: 20px; }
        }
    </style>
</head>
<body>

<!-- ── Hero + Search Form ────────────────────────── -->
<div class="hero">
    <h1>MRMVR</h1>

    <form class="search-bar" id="searchForm" method="GET" action="">
        <!-- Destination -->
        <div class="search-field destination-wrapper">
            <label>Destination</label>
            <span class="icon">&#9737;</span>
            <input type="text"
                   id="destinationInput"
                   name="city"
                   placeholder="Where to?"
                   autocomplete="off"
                   value="<?= htmlspecialchars($city) ?>">
            <div class="city-dropdown" id="cityDropdown"></div>
        </div>

        <div class="search-divider"></div>

        <!-- Check-in / Check-out -->
        <div class="search-field">
            <label>Check-in / check-out</label>
            <div class="date-trigger" id="dateTrigger">
                <span class="cal-icon">&#128197;</span>
                <span id="dateLabel">
                    <?php
                        if ($checkIn && $checkOut) {
                            echo htmlspecialchars(date('M j', strtotime($checkIn)) . ' – ' . date('M j', strtotime($checkOut)));
                        } else {
                            echo 'Add dates';
                        }
                    ?>
                </span>
                <span class="arrow">&#9662;</span>
            </div>
            <input type="hidden" name="checkIn"  id="checkInInput"  value="<?= htmlspecialchars($checkIn) ?>">
            <input type="hidden" name="checkOut" id="checkOutInput" value="<?= htmlspecialchars($checkOut) ?>">
        </div>

        <div class="search-divider"></div>

        <!-- Guests -->
        <div class="search-field">
            <label>Guests</label>
            <select name="guests" class="guests-select" id="guestsSelect">
                <option value="">Any</option>
                <?php for ($i = 1; $i <= 16; $i++): ?>
                    <option value="<?= $i ?>" <?= ($guests == $i) ? 'selected' : '' ?>>
                        <?= $i ?> Guest<?= $i > 1 ? 's' : '' ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <!-- Search button -->
        <button type="submit" class="search-btn">Search</button>

        <!-- Calendar popup -->
        <div class="calendar-popup" id="calendarPopup">
            <div class="calendar-nav">
                <button type="button" id="calPrev">&lsaquo;</button>
                <div class="calendar-months" id="calendarMonths"></div>
                <button type="button" id="calNext">&rsaquo;</button>
            </div>
        </div>
    </form>
</div>

<!-- ── Listings ──────────────────────────────────── -->
<div class="container">
    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($listings)): ?>
        <div class="no-listings">No listings found. Try adjusting your search.</div>
    <?php else: ?>

        <?php
            $infoFragments = [];
            if ($city)                 $infoFragments[] = htmlspecialchars($city);
            if ($checkIn && $checkOut) $infoFragments[] = htmlspecialchars(date('M j', strtotime($checkIn)) . ' – ' . date('M j, Y', strtotime($checkOut)));
            if ($guests)               $infoFragments[] = htmlspecialchars($guests) . ' guest' . ($guests > 1 ? 's' : '');
        ?>
        <?php if ($infoFragments): ?>
            <div class="results-info">
                Showing results <?= implode(' &middot; ', $infoFragments) ?>
            </div>
        <?php endif; ?>

        <div class="listings-grid">
            <?php foreach ($listings as $listing): ?>
                <?php
                    $id        = $listing['_id'] ?? '';
                    $title     = $listing['title'] ?? 'Untitled Listing';
                    $address   = $listing['address']['full'] ?? ($listing['address']['city'] ?? 'Location not available');
                    $bedrooms  = $listing['bedrooms'] ?? '—';
                    $bathrooms = $listing['bathrooms'] ?? '—';
                    $guestsNum = $listing['accommodates'] ?? '—';
                    $image     = $listing['pictures'][0]['thumbnail'] ?? $listing['pictures'][0]['original'] ?? '';
                    $price     = $listing['prices']['basePrice'] ?? null;
                    $currency  = $listing['prices']['currency'] ?? 'USD';
                ?>
                <a href="listing.php?id=<?= urlencode($id) ?>" class="listing-card">
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
                            <div class="listing-price">
                                <?= htmlspecialchars($currency) ?> <?= number_format($price) ?>
                                <small>/ night</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($nextCursor): ?>
            <div class="pagination">
                <a href="?<?= $loadMoreQuery ?>">Load More Listings &rarr;</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ── JavaScript ────────────────────────────────── -->
<script>
(function() {
    // ── Destination autocomplete ────────────────
    const destInput    = document.getElementById('destinationInput');
    const cityDropdown = document.getElementById('cityDropdown');
    let debounceTimer  = null;

    destInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { cityDropdown.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => {
            fetch('cities_api.php?searchText=' + encodeURIComponent(q) + '&limit=10')
                .then(r => r.json())
                .then(cities => {
                    cityDropdown.innerHTML = '';
                    if (!cities.length) { cityDropdown.style.display = 'none'; return; }
                    cities.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'city-item';
                        div.textContent = c;
                        div.addEventListener('click', () => {
                            destInput.value = c;
                            cityDropdown.style.display = 'none';
                        });
                        cityDropdown.appendChild(div);
                    });
                    cityDropdown.style.display = 'block';
                })
                .catch(() => { cityDropdown.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.destination-wrapper')) cityDropdown.style.display = 'none';
    });

    // ── Calendar ────────────────────────────────
    const calPopup     = document.getElementById('calendarPopup');
    const dateTrigger  = document.getElementById('dateTrigger');
    const dateLabel    = document.getElementById('dateLabel');
    const checkInField = document.getElementById('checkInInput');
    const checkOutField= document.getElementById('checkOutInput');
    const calMonths    = document.getElementById('calendarMonths');
    const prevBtn      = document.getElementById('calPrev');
    const nextBtn      = document.getElementById('calNext');

    const today = new Date();
    today.setHours(0,0,0,0);

    let baseMonth = today.getMonth();
    let baseYear  = today.getFullYear();
    let startDate = null;
    let endDate   = null;

    // Restore from hidden fields
    if (checkInField.value) startDate = parseYMD(checkInField.value);
    if (checkOutField.value) endDate  = parseYMD(checkOutField.value);

    function parseYMD(str) {
        const [y,m,d] = str.split('-').map(Number);
        return new Date(y, m-1, d);
    }

    function fmtYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    function fmtLabel(d) {
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate();
    }

    function renderCalendar() {
        calMonths.innerHTML = '';
        for (let offset = 0; offset < 2; offset++) {
            let m = baseMonth + offset;
            let y = baseYear;
            if (m > 11) { m -= 12; y++; }

            const monthDiv = document.createElement('div');
            monthDiv.className = 'cal-month';

            const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            monthDiv.innerHTML = '<div class="cal-month-title">' + monthNames[m] + ' ' + y + '</div>';

            const grid = document.createElement('div');
            grid.className = 'cal-grid';

            // Day headers
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
                const hdr = document.createElement('div');
                hdr.className = 'day-header';
                hdr.textContent = d;
                grid.appendChild(hdr);
            });

            const firstDay = new Date(y, m, 1).getDay();
            const daysInMonth = new Date(y, m+1, 0).getDate();

            // Empty cells
            for (let i = 0; i < firstDay; i++) {
                const empty = document.createElement('div');
                empty.className = 'day-cell empty';
                grid.appendChild(empty);
            }

            // Day cells
            for (let d = 1; d <= daysInMonth; d++) {
                const cell = document.createElement('div');
                cell.className = 'day-cell';
                cell.textContent = d;

                const cellDate = new Date(y, m, d);
                cellDate.setHours(0,0,0,0);

                // Past dates
                if (cellDate < today) {
                    cell.classList.add('past');
                } else {
                    // Today
                    if (cellDate.getTime() === today.getTime()) {
                        cell.classList.add('today');
                    }

                    // Selection styling
                    if (startDate && cellDate.getTime() === startDate.getTime()) {
                        cell.classList.add('selected-start');
                    }
                    if (endDate && cellDate.getTime() === endDate.getTime()) {
                        cell.classList.add('selected-end');
                    }
                    if (startDate && endDate && cellDate > startDate && cellDate < endDate) {
                        cell.classList.add('in-range');
                    }

                    cell.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        handleDateClick(cellDate);
                    });
                }

                grid.appendChild(cell);
            }

            monthDiv.appendChild(grid);
            calMonths.appendChild(monthDiv);
        }
    }

    function handleDateClick(date) {
        if (!startDate || (startDate && endDate) || date < startDate) {
            // Start fresh selection
            startDate = date;
            endDate = null;
            checkInField.value  = fmtYMD(date);
            checkOutField.value = '';
            dateLabel.textContent = fmtLabel(date) + ' – ...';
        } else {
            // Set end date
            endDate = date;
            checkOutField.value = fmtYMD(date);
            dateLabel.textContent = fmtLabel(startDate) + ' – ' + fmtLabel(endDate);
            // Close calendar after selecting end date
            setTimeout(() => { calPopup.classList.remove('open'); }, 200);
        }
        renderCalendar();
    }

    dateTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        calPopup.classList.toggle('open');
        renderCalendar();
    });

    prevBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        baseMonth--;
        if (baseMonth < 0) { baseMonth = 11; baseYear--; }
        renderCalendar();
    });

    nextBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        baseMonth++;
        if (baseMonth > 11) { baseMonth = 0; baseYear++; }
        renderCalendar();
    });

    // Close calendar when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.calendar-popup') && !e.target.closest('#dateTrigger')) {
            calPopup.classList.remove('open');
        }
    });

    // Initial render
    renderCalendar();
})();
</script>
</body>
</html>