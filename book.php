<?php
// book.php — Booking Page
require_once 'config.php';

$id       = $_GET['id']       ?? '';
$quoteId  = $_GET['quoteId']  ?? '';
$checkIn  = $_GET['checkIn']  ?? '';
$checkOut = $_GET['checkOut'] ?? '';
$guests   = $_GET['guests']   ?? '';
$nights   = (int)($_GET['nights'] ?? 0);
$total    = (float)($_GET['total'] ?? 0);

if (!$id || !$quoteId || !$checkIn || !$checkOut) {
    header('Location: index.php');
    exit;
}

// Fetch listing for display (title + thumbnail)
$listing  = [];
$listingError = '';
try {
    $listing = guestyGet('/api/listings/' . urlencode($id));
} catch (Exception $e) {
    $listingError = $e->getMessage();
}

$title    = $listing['title'] ?? 'Untitled';
$pictures = $listing['pictures'] ?? [];
$thumbUrl = $pictures[0]['thumbnail'] ?? $pictures[0]['original'] ?? '';

// ── Handle POST: create reservation ──────────────────────────────────────────
$bookingSuccess = false;
$reservation    = null;
$bookingError   = '';
$validationErrors = [];
$formData = ['firstName' => '', 'lastName' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');

    $formData = compact('firstName', 'lastName', 'email', 'phone');

    if (!$firstName) $validationErrors[] = 'First name is required.';
    if (!$lastName)  $validationErrors[] = 'Last name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $validationErrors[] = 'A valid email address is required.';
    if (!$phone)     $validationErrors[] = 'Phone number is required.';

    if (empty($validationErrors)) {
        try {
            $reservation = guestyPost('/api/reservations', [
                'quoteId'        => $quoteId,
                'guestFirstName' => $firstName,
                'guestLastName'  => $lastName,
                'guestEmail'     => $email,
                'guestPhone'     => $phone,
            ]);

            if (!$reservation) {
                $bookingError = 'No response received from the booking service. Please try again.';
            } elseif (isset($reservation['statusCode']) && $reservation['statusCode'] >= 400) {
                $bookingError = $reservation['error']['message']
                    ?? $reservation['message']
                    ?? 'Booking failed (HTTP ' . $reservation['statusCode'] . ').';
            } elseif (isset($reservation['error'])) {
                $bookingError = $reservation['error']['message']
                    ?? $reservation['message']
                    ?? 'An error occurred while creating your booking.';
            } else {
                $bookingSuccess = true;
            }
        } catch (Exception $e) {
            $bookingError = $e->getMessage();
        }
    }
}

function fmtMoney($a) { return '$' . number_format((float)$a, 2); }

$confirmationCode = $reservation['confirmationCode'] ?? $reservation['_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $bookingSuccess ? 'Booking Confirmed' : 'Complete Your Booking' ?> — MRMVR</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f9f9f9;color:#333}
.site-header{background:#fdf6e3;padding:12px 40px;text-align:center;border-bottom:1px solid #eee}
.site-header a{text-decoration:none}
.site-header h1{font-size:28px;font-weight:700;color:#b8860b}
.page-wrap{max-width:960px;margin:40px auto;padding:0 20px 80px;display:flex;gap:40px;align-items:flex-start}
.form-col{flex:1;min-width:0}
.summary-col{width:320px;flex-shrink:0}
.card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.card+.card{margin-top:16px}
.card-title{font-size:18px;font-weight:700;margin-bottom:20px}
.listing-thumb{width:100%;height:180px;object-fit:cover;border-radius:8px;margin-bottom:16px}
.listing-thumb-placeholder{width:100%;height:180px;background:#ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;margin-bottom:16px}
.summary-title{font-size:15px;font-weight:600;margin-bottom:16px;line-height:1.4}
.summary-row{display:flex;justify-content:space-between;padding:7px 0;font-size:14px;border-bottom:1px solid #f0f0f0}
.summary-row:last-child{border-bottom:none}
.summary-row .label{color:#666}
.summary-row .value{font-weight:600}
.total-row{display:flex;justify-content:space-between;padding:12px 0 0;margin-top:4px;font-size:16px;font-weight:700}
.section-divider{border:none;border-top:1px solid #e0e0e0;margin:16px 0}
.form-row{display:flex;gap:16px;margin-bottom:16px}
.form-row .form-group{flex:1}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px}
.form-group input{width:100%;padding:12px 14px;border:1px solid #ccc;border-radius:8px;font-size:15px;color:#333;outline:none;transition:border-color .2s}
.form-group input:focus{border-color:#4a90d9}
.form-group input.error{border-color:#e33}
.error-box{background:#fee;border:1px solid #fcc;color:#c33;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;line-height:1.5}
.submit-btn{width:100%;padding:15px;background:#4a90d9;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:background .2s}
.submit-btn:hover{background:#3a7cc5}
.submit-btn:disabled{background:#aaa;cursor:not-allowed}
.back-link{display:inline-flex;align-items:center;gap:6px;color:#555;text-decoration:none;font-size:14px;margin-bottom:24px}
.back-link:hover{color:#333}
.step-indicator{display:flex;align-items:center;gap:0;margin-bottom:28px}
.step{display:flex;align-items:center;gap:8px;font-size:13px;color:#aaa}
.step.active{color:#4a90d9;font-weight:600}
.step.done{color:#2a9d4a}
.step-num{width:24px;height:24px;border-radius:50%;border:2px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.step.active .step-num{border-color:#4a90d9;background:#4a90d9;color:#fff}
.step.done .step-num{border-color:#2a9d4a;background:#2a9d4a;color:#fff}
.step-sep{flex:1;height:1px;background:#ddd;min-width:24px;margin:0 4px}
/* Confirmation */
.confirm-wrap{max-width:600px;margin:60px auto;padding:0 20px;text-align:center}
.confirm-icon{font-size:64px;margin-bottom:20px}
.confirm-title{font-size:28px;font-weight:700;color:#2a9d4a;margin-bottom:8px}
.confirm-subtitle{font-size:16px;color:#555;margin-bottom:32px}
.confirm-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px;text-align:left;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.confirm-card-title{font-size:14px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:16px}
.confirm-detail{display:flex;justify-content:space-between;padding:8px 0;font-size:15px;border-bottom:1px solid #f0f0f0}
.confirm-detail:last-child{border-bottom:none}
.confirm-detail .label{color:#555}
.confirm-detail .value{font-weight:600}
.confirm-code{display:inline-block;background:#f0f7ff;border:1px solid #c0d8f0;border-radius:8px;padding:10px 20px;font-size:20px;font-weight:700;color:#1a5fa8;letter-spacing:.1em;margin:16px 0}
.home-btn{display:inline-block;margin-top:28px;padding:14px 32px;background:#4a90d9;color:#fff;border-radius:8px;text-decoration:none;font-size:15px;font-weight:600}
@media(max-width:720px){
  .page-wrap{flex-direction:column}
  .summary-col{width:100%}
  .form-row{flex-direction:column;gap:0}
}
</style>
</head>
<body>
<div class="site-header"><a href="index.php"><h1>MRMVR</h1></a></div>

<?php if ($bookingSuccess): ?>
<!-- ── SUCCESS SCREEN ───────────────────────────────────────────────────── -->
<div class="confirm-wrap">
    <div class="confirm-icon">&#10003;</div>
    <div class="confirm-title">Booking Confirmed!</div>
    <div class="confirm-subtitle">Your reservation has been successfully submitted.<br>A confirmation will be sent to <strong><?= htmlspecialchars($formData['email']) ?></strong>.</div>

    <?php if ($confirmationCode): ?>
        <p style="font-size:14px;color:#666;margin-bottom:8px">Confirmation Number</p>
        <div class="confirm-code"><?= htmlspecialchars($confirmationCode) ?></div>
    <?php endif; ?>

    <div class="confirm-card" style="margin-top:24px">
        <div class="confirm-card-title">Booking Details</div>
        <div class="confirm-detail"><span class="label">Property</span><span class="value"><?= htmlspecialchars($title) ?></span></div>
        <div class="confirm-detail"><span class="label">Guest</span><span class="value"><?= htmlspecialchars($formData['firstName'] . ' ' . $formData['lastName']) ?></span></div>
        <div class="confirm-detail"><span class="label">Email</span><span class="value"><?= htmlspecialchars($formData['email']) ?></span></div>
        <?php if ($checkIn): ?><div class="confirm-detail"><span class="label">Check In</span><span class="value"><?= date('M j, Y', strtotime($checkIn)) ?></span></div><?php endif; ?>
        <?php if ($checkOut): ?><div class="confirm-detail"><span class="label">Check Out</span><span class="value"><?= date('M j, Y', strtotime($checkOut)) ?></span></div><?php endif; ?>
        <?php if ($nights): ?><div class="confirm-detail"><span class="label">Duration</span><span class="value"><?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?></span></div><?php endif; ?>
        <?php if ($guests): ?><div class="confirm-detail"><span class="label">Guests</span><span class="value"><?= htmlspecialchars($guests) ?></span></div><?php endif; ?>
        <?php if ($total): ?><div class="confirm-detail"><span class="label">Total Charged</span><span class="value"><?= fmtMoney($total) ?></span></div><?php endif; ?>
    </div>

    <a class="home-btn" href="index.php">Back to listings</a>
</div>

<?php else: ?>
<!-- ── BOOKING FORM ─────────────────────────────────────────────────────── -->
<div class="page-wrap">

    <!-- LEFT: Guest info form -->
    <div class="form-col">
        <a class="back-link" href="listing.php?id=<?= urlencode($id) ?>&checkIn=<?= urlencode($checkIn) ?>&checkOut=<?= urlencode($checkOut) ?>&guests=<?= urlencode($guests) ?>">&lsaquo; Back to listing</a>

        <div class="step-indicator">
            <div class="step done"><div class="step-num">&#10003;</div> Search</div>
            <div class="step-sep"></div>
            <div class="step done"><div class="step-num">&#10003;</div> Select dates</div>
            <div class="step-sep"></div>
            <div class="step active"><div class="step-num">3</div> Guest details</div>
            <div class="step-sep"></div>
            <div class="step"><div class="step-num">4</div> Confirmation</div>
        </div>

        <div class="card">
            <div class="card-title">Your details</div>

            <?php if (!empty($validationErrors) || $bookingError): ?>
                <div class="error-box">
                    <?php if (!empty($validationErrors)): ?>
                        <?= implode('<br>', array_map('htmlspecialchars', $validationErrors)) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($bookingError) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First name *</label>
                        <input type="text" id="firstName" name="firstName"
                               value="<?= htmlspecialchars($formData['firstName']) ?>"
                               placeholder="Jane"
                               autocomplete="given-name"
                               <?= (!empty($validationErrors) && !$formData['firstName']) ? 'class="error"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last name *</label>
                        <input type="text" id="lastName" name="lastName"
                               value="<?= htmlspecialchars($formData['lastName']) ?>"
                               placeholder="Smith"
                               autocomplete="family-name"
                               <?= (!empty($validationErrors) && !$formData['lastName']) ? 'class="error"' : '' ?>>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email address *</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($formData['email']) ?>"
                           placeholder="jane@example.com"
                           autocomplete="email"
                           <?= (!empty($validationErrors) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) ? 'class="error"' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="phone">Phone number *</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= htmlspecialchars($formData['phone']) ?>"
                           placeholder="+1 555 000 0000"
                           autocomplete="tel"
                           <?= (!empty($validationErrors) && !$formData['phone']) ? 'class="error"' : '' ?>>
                </div>

                <hr class="section-divider">
                <button type="submit" class="submit-btn" id="submitBtn">Confirm booking</button>
            </form>
        </div>
    </div>

    <!-- RIGHT: Booking summary -->
    <div class="summary-col">
        <div class="card">
            <?php if ($thumbUrl): ?>
                <img class="listing-thumb" src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= htmlspecialchars($title) ?>">
            <?php else: ?>
                <div class="listing-thumb-placeholder">No image</div>
            <?php endif; ?>

            <div class="summary-title"><?= htmlspecialchars($title) ?></div>
            <hr class="section-divider">

            <?php if ($checkIn): ?>
                <div class="summary-row">
                    <span class="label">Check in</span>
                    <span class="value"><?= date('M j, Y', strtotime($checkIn)) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($checkOut): ?>
                <div class="summary-row">
                    <span class="label">Check out</span>
                    <span class="value"><?= date('M j, Y', strtotime($checkOut)) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($nights): ?>
                <div class="summary-row">
                    <span class="label">Duration</span>
                    <span class="value"><?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?></span>
                </div>
            <?php endif; ?>
            <?php if ($guests): ?>
                <div class="summary-row">
                    <span class="label">Guests</span>
                    <span class="value"><?= htmlspecialchars($guests) ?> guest<?= (int)$guests !== 1 ? 's' : '' ?></span>
                </div>
            <?php endif; ?>

            <?php if ($total): ?>
                <hr class="section-divider">
                <div class="total-row">
                    <span>Total</span>
                    <span><?= fmtMoney($total) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:16px;font-size:13px;color:#666;line-height:1.6">
            <strong style="color:#333;display:block;margin-bottom:6px">&#128274; Secure booking</strong>
            Your information is encrypted and only shared with the property manager to confirm your reservation.
        </div>
    </div>

</div>
<?php endif; ?>

<script>
// Disable submit button on submit to prevent double-clicks
document.getElementById('submitBtn')?.closest('form')?.addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing…'; }
});
</script>
</body>
</html>
