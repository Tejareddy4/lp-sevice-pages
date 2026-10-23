<?php

// Support both naming styles used by page files.
$origin = $origin ?? ($Origin ?? "");
$destination = $destination ?? ($Destination ?? "");
$excerpt = $excerpt ?? ($Excerpt ?? "");
$Updatedhtml = $Updatedhtml ?? "";
$Relposts = $Relposts ?? "";
$FAQ = $FAQ ?? "";
$faqScript = $faqScript ?? ($FAQscript ?? "");
$reviewSchema = $reviewSchema ?? ($Review_Schema ?? "");
$featuredImage = $featuredImage ?? ($FeaturedImage ?? "");
$featuredImageAlt = $featuredImageAlt ?? ($FeaturedImageAlt ?? "");

function slugify_text(string $text): string
{
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
  return trim($text, '-');
}

function add_read_more_shortcode(string $html): string
{
  $trimmed_html = trim($html);

  if ($trimmed_html === '' || strpos($trimmed_html, '[read more]') !== false || substr($trimmed_html, -7) === '[/read]') {
    return $html;
  }

  $with_read_more = preg_replace_callback(
    '/(<[^>]+>)([\s\S]*?)(<\/[^>]+>)/',
    static function (array $matches): string {
      $inner_text = trim($matches[2]);

      if ($inner_text === '') {
        return $matches[0];
      }

      $words = preg_split('/\s+/', $inner_text) ?: [];
      $insert_index = max(count($words) - 5, 1);

      $before = implode(' ', array_slice($words, 0, $insert_index));
      $after = implode(' ', array_slice($words, $insert_index));

      return $matches[1] . $before . ' <!--...-->[read more] ' . $after . $matches[3];
    },
    $trimmed_html,
    1
  );

  if ($with_read_more === null) {
    return $html;
  }

  return rtrim($with_read_more) . '[/read]';
}

function render_read_more_shortcode(string $html): string
{
  if (strpos($html, '[read more]') === false) {
    return $html;
  }

  $result = preg_replace_callback(
    '/(<([a-z0-9]+)[^>]*>)([\s\S]*?) <!--\.\.\.-->\[read more\] ([\s\S]*?)(<\/\2>)\[\/read\]/i',
    static function (array $m): string {
      return $m[1] . $m[3] . $m[5]
        . '<div class="rmwr-wrapper">'
        . '<button type="button" class="read-link" onclick="toggleReadMore(this)">Read More</button>'
        . '<div class="read-more-content" style="display:none;">'
        . $m[1] . $m[4] . $m[5]
        . '</div></div>';
    },
    $html
  );

  return $result ?? $html;
}

$Updatedhtml = add_read_more_shortcode($Updatedhtml);
$Updatedhtml = render_read_more_shortcode($Updatedhtml);

if (trim($featuredImage) === '' && trim($origin) !== '' && trim($destination) !== '') {
  // Build slug via regex: lowercase, collapse non-alphanumeric runs to hyphens.
  $page_slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim("$origin to $destination")));
  $page_slug = trim($page_slug ?? '', '-');

  // Production path: http://shipglobal.in/services/images/{Destination}/{origin-slug}-to-{destination-slug}.webp
  // Destination folder uses the original $destination casing (e.g. "Croatia").
  $featuredImage = '/services/images/' . rawurlencode($destination) . '/' . rawurlencode($page_slug) . '.webp';
}

if (trim($featuredImageAlt) === '') {
  $featuredImageAlt = $origin . ' to ' . $destination . ' courier featured image';
}


$featuredImageSeoUrl = '';
if (trim($featuredImage) !== '') {
  if (preg_match('/^https?:\/\//i', $featuredImage) === 1) {
    $featuredImageSeoUrl = $featuredImage;
  } else {
    $featuredImageSeoUrl = 'https://shipglobal.in' . (strpos($featuredImage, '/') === 0 ? '' : '/') . $featuredImage;
  }
}

$title = "$origin to $destination Courier Shipment";

$meta_description = "Ship internationally from $origin to $destination with transparent pricing, fast delivery, and customs support.";

// Instant price: the same rate card the public shipping rate calculator reads.
// Same origin as /services/, so the page fetches it directly. Destinations that
// are not a column in the sheet (e.g. Australia) fall back to "expert will call".
$rateSheetUrl = '/wp-content/uploads/2025/04/rate_ship.csv';

$phoneTel = '+919906599065';
$phoneText = '+91 99065 99065';
$signupUrl = 'https://v2.app.shipglobal.in/auth/signup';
$leadWebhook = 'https://flow.zoho.in/60030990815/flow/webhook/incoming?zapikey=1001.13056985b9392ffd01f82ea7b29ecd8c.274c0a48499a6dfba88a38d975d9ad65&isdebug=false';

// $FAQ arrives as <p><strong>Question</strong><br>Answer</p> blocks; split them for the accordion.
$faqItems = [];
if (preg_match_all('/<p>\s*<strong>(.*?)<\/strong>\s*(?:<br\s*\/?>)?(.*?)<\/p>/is', $FAQ, $faqMatches, PREG_SET_ORDER)) {
  foreach ($faqMatches as $faqMatch) {
    $faqItems[] = ['q' => trim(strip_tags($faqMatch[1])), 'a' => trim($faqMatch[2])];
  }
}

// $Relposts ends with the FAQ section's <h2>; move it onto the FAQ section.
$faqHeading = "Questions about shipping to $destination";
if (preg_match('/<h2>(.*?)<\/h2>\s*$/is', $Relposts, $faqHeadingMatch)) {
  $faqHeading = $faqHeadingMatch[1];
  $Relposts = substr($Relposts, 0, -strlen($faqHeadingMatch[0]));
}

// Quote form: value = existing Zoho CRM Product_Type picklist value.
$quoteItems = [
  'Clothes' => ['Clothes', 'fa-shirt'],
  'Documents' => ['Documents', 'fa-file-lines'],
  'Food products' => ['Food items', 'fa-bowl-food'],
  'Spices' => ['Spices', 'fa-pepper-hot'],
  'Handicraft' => ['Handicrafts', 'fa-palette'],
  'Imitation Jewellery' => ['Jewellery', 'fa-gem'],
  'Hair & Skin Care' => ['Hair &amp; skin care', 'fa-pump-soap'],
  'Herbal Products' => ['Herbal products', 'fa-leaf'],
  'Others' => ['Something else', 'fa-box-open'],
];

function render_quote_wizard(string $id, string $origin, string $destination): void
{
  global $quoteItems, $leadWebhook, $phoneTel, $phoneText, $signupUrl;
  $o = htmlspecialchars($origin, ENT_QUOTES, 'UTF-8');
  $d = htmlspecialchars($destination, ENT_QUOTES, 'UTF-8');
  ?>
  <div class="qw" data-qw="<?php echo $id; ?>" data-step="1">
    <div class="qw-head">
      <div class="qw-head-top">
        <h2 class="qw-title">Get your <span>price</span> in 30 seconds</h2>
        <span class="qw-count">Step <b data-qw-num>1</b> of 3</span>
      </div>
      <div class="qw-progress" role="progressbar" aria-label="Quote progress" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1"><i></i></div>
    </div>

    <form class="qw-form" method="POST" accept-charset="UTF-8" enctype="multipart/form-data" novalidate
      action='<?php echo $leadWebhook; ?>'>
      <input type="hidden" name="zf_referrer_name" value="">
      <input type="hidden" name="zf_redirect_url" value="">
      <input type="hidden" name="zc_gad" value="">
      <input type="hidden" name="From" value="<?php echo $o; ?>">
      <input type="hidden" name="To" value="<?php echo $d; ?>">
      <input type="hidden" name="First_Name" value="">
      <input type="hidden" name="Last_Name" value="">
      <input type="hidden" name="Packet_Weight" value="">
      <input class="qw-hp" type="text" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="qw-step" data-qw-step="1" role="group" aria-labelledby="qw-<?php echo $id; ?>-q1">
        <p class="qw-q" id="qw-<?php echo $id; ?>-q1">What are you sending?</p>
        <div class="qw-group" data-qw-check="item">
          <div class="qw-tiles" role="radiogroup" aria-labelledby="qw-<?php echo $id; ?>-q1">
<?php foreach ($quoteItems as $value => [$label, $icon]): ?>
            <label class="qw-tile"><input type="radio" name="Product_Type" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><span><i class="fas <?php echo $icon; ?>"></i><?php echo $label; ?></span></label>
<?php endforeach; ?>
          </div>
          <p class="qw-err">Choose what you're sending</p>
        </div>
        <button type="button" class="qw-btn" data-qw-next>Continue <i class="fas fa-arrow-right"></i></button>
      </div>

      <div class="qw-step" data-qw-step="2" role="group" aria-labelledby="qw-<?php echo $id; ?>-q2" hidden>
        <p class="qw-q" id="qw-<?php echo $id; ?>-q2">How heavy is your parcel?</p>
        <div class="qw-group" data-qw-check="weight">
          <div class="qw-weight">
            <button type="button" class="qw-stepper" data-qw-dec aria-label="Decrease weight"><i class="fas fa-minus"></i></button>
            <label class="qw-weight-field">
              <input type="number" inputmode="decimal" min="0.05" max="100" step="any" value="1" data-qw-weight aria-label="Parcel weight in kg">
              <span>kg</span>
            </label>
            <button type="button" class="qw-stepper" data-qw-inc aria-label="Increase weight"><i class="fas fa-plus"></i></button>
          </div>
          <div class="qw-chips">
            <button type="button" data-qw-w="0.5">500 g</button>
            <button type="button" data-qw-w="1">1 kg</button>
            <button type="button" data-qw-w="2">2 kg</button>
            <button type="button" data-qw-w="5">5 kg</button>
            <button type="button" data-qw-w="10">10 kg</button>
          </div>
          <p class="qw-err">Enter a weight between 50 g and 100 kg</p>
        </div>
        <p class="qw-hint" data-qw-hint aria-live="polite"><i class="fas fa-circle-info"></i><span>Not sure? A rough estimate is fine.</span></p>
        <button type="button" class="qw-btn" data-qw-next>Continue <i class="fas fa-arrow-right"></i></button>
      </div>

      <div class="qw-step" data-qw-step="3" role="group" aria-labelledby="qw-<?php echo $id; ?>-q3" hidden>
        <p class="qw-q" id="qw-<?php echo $id; ?>-q3">Where should we send your price?</p>
        <div class="qw-summary"><span data-qw-summary></span><button type="button" data-qw-edit>Edit</button></div>
        <div class="qw-field qw-group" data-qw-check="name">
          <label for="qw-<?php echo $id; ?>-name">Your name</label>
          <input class="qw-input" id="qw-<?php echo $id; ?>-name" type="text" autocomplete="name" maxlength="80" placeholder="e.g. Rahul Sharma">
          <p class="qw-err">Please enter your name</p>
        </div>
        <div class="qw-field qw-group" data-qw-check="phone">
          <label for="qw-<?php echo $id; ?>-phone">Mobile number</label>
          <div class="qw-phone"><span>+91</span><input class="qw-input" id="qw-<?php echo $id; ?>-phone" type="tel" name="Phone" inputmode="numeric" autocomplete="tel-national" maxlength="14" placeholder="10-digit number"></div>
          <p class="qw-err">Enter a valid 10-digit mobile number</p>
        </div>
        <button type="submit" class="qw-btn qw-btn-cta" data-qw-submit><span data-qw-submit-label>Get my price</span> <i class="fas fa-arrow-right"></i></button>
        <p class="qw-legal">By continuing, you agree to be contacted by ShipGlobal about this shipment.</p>
      </div>
    </form>

    <div class="qw-foot">
      <button type="button" class="qw-back" data-qw-back><i class="fas fa-arrow-left"></i> Back</button>
      <span><i class="fas fa-lock"></i> Free, no-obligation quote</span>
    </div>
    <p class="qw-fail" role="alert">We couldn't send your request. Please call us on <a href="tel:<?php echo $phoneTel; ?>"><?php echo $phoneText; ?></a>.</p>

    <div class="qw-done" role="status">
      <div class="qw-done-icon"><i class="fas fa-check"></i></div>
      <h3 tabindex="-1" data-qw-done-title>Thanks, <span data-qw-name></span>!</h3>
      <div class="qw-price" data-qw-price hidden>
        <small>Estimated price for <b data-qw-price-weight></b> to <?php echo $d; ?></small>
        <strong>₹<span data-qw-price-value></span></strong>
        <small>All-inclusive estimate from our rate card, fuel surcharge included.</small>
      </div>
      <p data-qw-done-msg></p>
      <div class="qw-next">
        <p class="qw-next-title">What happens next</p>
        <ol>
          <li><i class="fas fa-phone-volume"></i><span><b>Expert call</b>We confirm your price and paperwork</span></li>
          <li><i class="fas fa-truck-fast"></i><span><b>Doorstep pickup</b>From your address in <?php echo $o; ?></span></li>
          <li><i class="fas fa-location-crosshairs"></i><span><b>Tracked delivery</b>All the way to <?php echo $d; ?></span></li>
        </ol>
      </div>
      <div class="qw-done-actions">
        <a class="qw-btn qw-btn-cta" href="<?php echo $signupUrl; ?>" target="_blank" rel="noopener">Book my shipment <i class="fas fa-arrow-right"></i></a>
        <a class="qw-btn qw-btn-ghost" href="tel:<?php echo $phoneTel; ?>"><i class="fas fa-phone"></i> Call <?php echo $phoneText; ?></a>
      </div>
    </div>
  </div>
  <?php
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $title; ?></title>
<meta name="description" content="<?php echo $meta_description; ?>">
<link rel="canonical" href="https://shipglobal.in/services/<?php echo slugify_text($destination); ?>/<?php echo slugify_text($origin); ?>/">
<?php if (trim($featuredImageSeoUrl) !== ''): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($featuredImageSeoUrl, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:image:alt" content="<?php echo htmlspecialchars($featuredImageAlt, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?php echo htmlspecialchars($featuredImageSeoUrl, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:image:alt" content="<?php echo htmlspecialchars($featuredImageAlt, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Montserrat:wght@600;700&family=Roboto:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php if (trim($reviewSchema) !== ''): ?>
<?php echo $reviewSchema; ?>
<?php endif; ?>

<script type="text/javascript" src="https://crm.zoho.in/crm/javascript/zcga.js"> </script>

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-WMNT29VQ');</script>
<!-- End Google Tag Manager -->

<style>

/* ===== Announcement Bar ===== */
.announcement-bar{
  background:linear-gradient(90deg,#8434E4 0%,#2765D0 100%);
  padding:5px 0;
  position:relative;
  z-index:30;
}
.announcement-bar-inner{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
}
.announcement-bar a{
  display:flex;
  align-items:center;
  gap:10px;
  text-decoration:none;
}
.announcement-bar-icon{
  width:25px;
  height:25px;
  flex-shrink:0;
}
.announcement-bar-text{
  color:#fff;
  font-family:'Roboto',sans-serif;
  font-size:14px;
  font-weight:400;
}
@media(max-width:768px){
  .announcement-bar-text{ font-size:10px; }
  .announcement-bar-icon{ width:15px; height:15px; }
}

/* ===== Site Header ===== */
.site-header{
  background:#fff;
  box-shadow:0 0 5px rgba(0,0,0,0.5);
  position:sticky;
  top:0;
  z-index:10;
  padding:5px 0;
}
.site-header-inner{
  max-width:1300px;
  margin:0 auto;
  padding:0 20px;
  display:flex;
  align-items:center;
}
.header-logo-wrap{
  width:20%;
  display:flex;
  align-items:center;
}
.header-logo{
  max-width:100%;
  height:auto;
  display:block;
}
.header-nav-wrap{
  width:60%;
  display:flex;
  justify-content:space-around;
  align-items:center;
}
.header-nav{
  list-style:none;
  margin:0;
  padding:0;
  display:flex;
  align-items:center;
  gap:32px;
}
.header-nav a{
  font-family:'Montserrat',sans-serif;
  font-size:18px;
  font-weight:600;
  color:#111827;
  text-decoration:none;
  white-space:nowrap;
}
.header-nav a:hover{ color:#2765D0; }
.header-cta-wrap{
  width:20%;
  display:flex;
  justify-content:space-around;
  align-items:flex-end;
}
.header-ship-btn{
  font-family:'Montserrat',sans-serif;
  font-weight:700;
  font-size:15px;
  color:#fff;
  background:linear-gradient(120deg,#000 0%,#2765D0 100%);
  border:none;
  border-radius:8px;
  padding:10px 24px;
  cursor:pointer;
  text-decoration:none;
  display:inline-block;
  transition:background 0.2s;
}
.header-ship-btn:hover{
  background:linear-gradient(120deg,#2765D0 0%,#2765D0 100%);
}

/* Floating bulk-order icon */
.bulk-order-float{
  position:fixed;
  bottom:0;
  right:0;
  z-index:20;
  width:70px;
}
.bulk-order-float img{ width:100%; display:block; }

/* Hamburger button */
.hamburger{
  display:none;
  flex-direction:column;
  justify-content:center;
  gap:5px;
  background:none;
  border:none;
  cursor:pointer;
  padding:6px;
  margin-left:auto;
}
.hamburger span{
  display:block;
  width:24px;
  height:2px;
  background:#111827;
  border-radius:2px;
  transition:transform 0.25s, opacity 0.25s;
}
.hamburger.open span:nth-child(1){ transform:translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2){ opacity:0; }
.hamburger.open span:nth-child(3){ transform:translateY(-7px) rotate(-45deg); }

/* Mobile nav drawer */
.mobile-nav{
  display:none;
  flex-direction:column;
  background:#fff;
  border-top:1px solid #e5e7eb;
  padding:0;
  max-height:0;
  overflow:hidden;
  transition:max-height 0.3s ease, padding 0.3s ease;
}
.mobile-nav.open{
  max-height:400px;
  padding:12px 0;
}
.mobile-nav a{
  font-family:'Montserrat',sans-serif;
  font-size:16px;
  font-weight:600;
  color:#111827;
  text-decoration:none;
  padding:12px 24px;
  display:block;
  border-bottom:1px solid #f3f4f6;
}
.mobile-nav a:last-child{ border-bottom:none; }
.mobile-nav a:hover{ color:#2765D0; background:#f9fafb; }
.mobile-nav .mobile-ship-btn{
  margin:12px 24px 4px;
  display:inline-block;
  font-family:'Montserrat',sans-serif;
  font-weight:700;
  font-size:15px;
  color:#fff;
  background:linear-gradient(120deg,#000 0%,#2765D0 100%);
  border-radius:8px;
  padding:10px 24px;
  text-align:center;
  text-decoration:none;
  border-bottom:none;
}
.mobile-nav .mobile-ship-btn:hover{
  background:linear-gradient(120deg,#2765D0 0%,#2765D0 100%);
  color:#fff;
}

@media(max-width:1024px){
  .header-logo-wrap{ width:auto; flex:1; }
  .header-nav-wrap{ width:auto; justify-content:flex-start; flex:1; }
  .header-cta-wrap{ display:none; }
  .header-nav a{ font-size:15px; }
  .header-nav{ gap:20px; }
}
@media(max-width:768px){
  .header-nav-wrap{ display:none; }
  .header-logo-wrap{ flex:1; width:auto; }
  .hamburger{ display:flex; }
  .mobile-nav{ display:flex; }
  .bulk-order-float{ width:50px; }
}

/* ===== WhatsApp Community Bar ===== */
.wa-bar{
  background:#fff;
  box-shadow:0 0 10px rgba(0,0,0,0.2);
  padding:16px 20px;
}
.wa-bar-inner{
  max-width:1300px;
  margin:0 auto;
  display:flex;
  align-items:center;
}
.wa-logo-col{
  width:20%;
  display:flex;
  justify-content:flex-end;
  align-items:center;
  flex-shrink:0;
}
.wa-logo-col img{
  width:60%;
  max-width:130px;
  height:auto;
}
.wa-text-col{
  width:60%;
  padding:0 24px;
  border-left:2px solid #D8DDE5;
}
.wa-text-col p{
  font-family:'Poppins',sans-serif;
  font-size:26px;
  font-weight:500;
  margin:0;
  text-transform:capitalize;
  line-height:1.2;
  text-align:center;
  color:#111827;
}
.wa-btn-col{
  width:20%;
  display:flex;
  justify-content:center;
  align-items:center;
}
.wa-join-btn{
  font-family:'Poppins',sans-serif;
  font-size:20px;
  font-weight:600;
  color:#fff;
  background:linear-gradient(120deg,#25D366 0%,#46AF55 70%);
  border-radius:10px;
  padding:12px 28px;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:12px;
  transition:box-shadow 0.2s,transform 0.2s;
  white-space:nowrap;
}
.wa-join-btn:hover{
  box-shadow:1px 1px 5px 1px rgba(0,0,0,0.4);
  transform:scale(1.02);
  color:#fff;
}

/* ===== Main Footer ===== */
.site-footer{
  background:linear-gradient(90deg,#000002 0%,#001B51 100%);
  padding:60px 8%;
  color:#fff;
}
.footer-inner{
  max-width:1300px;
  margin:0 auto;
  display:flex;
  gap:40px;
  align-items:flex-start;
}
.footer-brand{
  width:40%;
  display:flex;
  flex-direction:column;
  gap:24px;
}
.footer-logo{
  width:60%;
  max-width:200px;
  height:auto;
}
.footer-offices{
  font-family:'Poppins',sans-serif;
  font-size:16px;
  font-weight:400;
  line-height:1.5;
  color:rgba(255,255,255,0.75);
  text-align:justify;
  margin:0;
}
.footer-social{
  display:flex;
  gap:20px;
  align-items:center;
  flex-wrap:wrap;
}
.footer-social a{
  color:rgba(255,255,255,0.75);
  font-size:22px;
  text-decoration:none;
  transition:color 0.2s;
}
.footer-social a:hover{ color:#C8D5DC; }
.footer-col{
  flex:1;
  display:flex;
  flex-direction:column;
  gap:20px;
  min-width:0;
}
.footer-col-title{
  font-family:'Poppins',sans-serif;
  font-size:20px;
  font-weight:600;
  color:rgba(255,255,255,0.95);
  text-transform:capitalize;
  margin:0;
  line-height:1.2;
}
.footer-links{
  list-style:none;
  margin:0;
  padding:0;
  display:flex;
  flex-direction:column;
  gap:18px;
}
.footer-links li{
  font-family:'Poppins',sans-serif;
  font-size:15px;
  font-weight:400;
  color:rgba(255,255,255,0.7);
  line-height:1.5;
  display:flex;
  align-items:center;
  gap:10px;
}
.footer-links a{
  color:rgba(255,255,255,0.7);
  text-decoration:none;
  font-size:15px;
  font-family:'Poppins',sans-serif;
  line-height:1.5;
  transition:color 0.2s;
}
.footer-links a:hover{ color:#4d8ef7; }
.contact-icon{
  color:#4d8ef7;
  font-size:18px;
  flex-shrink:0;
}

/* Plane animation strip */
.footer-plane-strip{
  background:url('https://shipglobal.in/wp-content/uploads/2025/05/footer-line-scaled.png') center/auto no-repeat,
             linear-gradient(90deg,#000002 0%,#001B51 100%);
  height:80px;
  position:relative;
  overflow:hidden;
}
.footer-plane-track{
  position:absolute;
  top:50%;
  transform:translateY(-50%);
  animation:planeScroll 15s linear infinite;
}
.footer-plane-track img{ height:70px; }
@keyframes planeScroll{
  0%  { left:-150px; }
  100%{ left:100%; }
}

/* Copyright bar */
.footer-copyright{
  background:linear-gradient(90deg,#000002 0%,#001B51 100%);
  padding:20px;
  text-align:center;
  border-top:1px solid rgba(255,255,255,0.08);
}
.footer-copyright p{
  font-family:'Poppins',sans-serif;
  font-size:15px;
  font-weight:300;
  color:rgba(255,255,255,0.8);
  margin:0;
  line-height:1.5;
}

/* Footer responsive */
@media(max-width:1024px){
  .wa-text-col p{ font-size:18px; }
  .wa-join-btn{ font-size:17px; padding:10px 20px; }
  .footer-inner{ flex-wrap:wrap; gap:40px; }
  .footer-brand{ width:100%; }
  .footer-col{ width:calc(33% - 28px); flex:none; }
}
@media(max-width:768px){
  .wa-bar-inner{ flex-wrap:wrap; gap:16px; }
  .wa-logo-col{ width:30%; justify-content:center; }
  .wa-text-col{ width:65%; }
  .wa-text-col p{ font-size:13px; }
  .wa-btn-col{ width:100%; }
  .wa-join-btn{ width:100%; justify-content:center; font-size:20px; }
  .footer-inner{ gap:32px; }
  .footer-col{ width:calc(50% - 16px); flex:none; }
  .footer-contact-col{ width:100%; }
  .footer-col-title{ font-size:18px; }
  .footer-links{ gap:12px; }
  .footer-links li,.footer-links a{ font-size:12px; }
}

/* ===== Design tokens ===== */
:root{
  --sg-navy:#0B1F5C;
  --sg-blue:#2D52CC;
  --sg-blue-dark:#2442A8;
  --sg-sky:#EEF3FF;
  --sg-sky-2:#DCE6FF;
  --sg-amber:#F59E0B;
  --sg-amber-light:#FBBF24;
  --sg-ink:#0F172A;
  --sg-text:#334155;
  --sg-muted:#64748B;
  --sg-line:#E2E8F0;
  --sg-bg:#F8FAFC;
  --sg-ok:#16A34A;
  --sg-err:#DC2626;
  --sg-radius:14px;
  --sg-radius-lg:20px;
  --sg-shadow-sm:0 1px 2px rgba(15,23,42,.06),0 1px 3px rgba(15,23,42,.08);
  --sg-shadow:0 14px 34px -12px rgba(15,23,42,.2);
  --sg-shadow-lg:0 30px 60px -20px rgba(8,22,68,.45);
  --sg-head:'Montserrat',system-ui,sans-serif;
  --sg-body:'Poppins',system-ui,sans-serif;
}

*{box-sizing:border-box}

body{
  margin:0;
  font-family:var(--sg-body);
  color:var(--sg-ink);
  background:#fff;
  -webkit-font-smoothing:antialiased;
}

.sg-wrap{max-width:1200px;margin:0 auto;padding:0 24px}

/* Header: quote button next to Ship Now */
.header-nav-wrap{width:auto;flex:1}
.header-cta-wrap{width:auto;flex:none;gap:10px;justify-content:flex-end;align-items:center}
.header-quote-btn{
  padding:10px 20px;
  border:0;
  border-radius:8px;
  background:var(--sg-amber);
  color:#111827;
  font:700 15px var(--sg-head);
  cursor:pointer;
  transition:background .15s;
}
.header-quote-btn:hover{background:var(--sg-amber-light)}
@media(max-width:1024px){
  .header-cta-wrap{display:flex}
  .header-ship-btn{display:none}
}
@media(max-width:768px){
  .header-cta-wrap{display:none}
}

/* ===== Hero ===== */
.sg-hero{
  position:relative;
  overflow:hidden;
  color:#fff;
  background:
    radial-gradient(900px 480px at 88% -10%,rgba(91,124,250,.45),transparent 60%),
    radial-gradient(700px 400px at -10% 110%,rgba(245,158,11,.14),transparent 60%),
    linear-gradient(160deg,#071540 0%,#0B1F5C 48%,#1B3CA3 100%);
}
.sg-hero-grid-bg{
  position:absolute;
  inset:0;
  background-image:
    linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);
  background-size:48px 48px;
  -webkit-mask-image:radial-gradient(ellipse at 25% 35%,#000 15%,transparent 70%);
  mask-image:radial-gradient(ellipse at 25% 35%,#000 15%,transparent 70%);
  pointer-events:none;
}
.sg-hero-grid{
  position:relative;
  display:grid;
  grid-template-columns:minmax(0,1fr) minmax(380px,460px);
  grid-template-areas:"head form" "proof form";
  column-gap:64px;
  padding-top:64px;
  padding-bottom:72px;
}
.sg-hero-head{grid-area:head;align-self:end}
.sg-hero-form{grid-area:form;scroll-margin-top:96px}
.sg-hero-proof{grid-area:proof}
.sg-eyebrow{
  display:inline-flex;
  align-items:center;
  gap:8px;
  margin:0 0 20px;
  padding:8px 14px;
  border-radius:999px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.16);
  color:#C7D5FF;
  font:600 13px/1 var(--sg-body);
}
.sg-eyebrow i{color:var(--sg-amber-light)}
.sg-hero h1{
  font:800 56px/1.06 var(--sg-head);
  letter-spacing:-.02em;
  margin:0 0 18px;
  color:#fff;
}
.sg-hero h1 span{display:block;color:var(--sg-amber-light)}
.sg-hero-lead{
  font-size:18px;
  line-height:1.6;
  color:#C9D4F2;
  margin:0;
  max-width:560px;
}
.sg-from{
  display:inline-flex;
  flex-wrap:wrap;
  align-items:center;
  gap:6px 8px;
  margin:22px 0 0;
  padding:10px 16px;
  border-radius:12px;
  background:rgba(251,191,36,.12);
  border:1px solid rgba(251,191,36,.35);
  color:#FDE68A;
  font-size:15px;
}
.sg-from[hidden]{display:none}
.sg-from b{color:#fff;font:800 19px var(--sg-head)}
.sg-from-note{font-size:12.5px;color:#C9D4F2}
.sg-hero-quote{
  max-width:560px;
  margin:30px 0 0;
  padding:18px 20px;
  border-left:3px solid var(--sg-amber-light);
  border-radius:0 var(--sg-radius) var(--sg-radius) 0;
  background:rgba(255,255,255,.05);
}
.sg-hero-quote blockquote{margin:0 0 12px;font-size:15px;line-height:1.6;font-style:italic;color:#E2E8FF}
.sg-hero-quote figcaption{display:flex;align-items:center;gap:12px;font-size:13px;line-height:1.4;color:#93A5D8}
.sg-hero-quote b{color:#fff;font-weight:600}
.sg-hero-quote-avatar{
  display:grid;
  place-items:center;
  flex:none;
  width:34px;
  height:34px;
  border-radius:50%;
  background:var(--sg-amber-light);
  color:var(--sg-navy);
  font:800 12px var(--sg-head);
}
.sg-route{
  display:flex;
  align-items:center;
  gap:18px;
  max-width:560px;
  margin:34px 0 28px;
  padding:18px 22px;
  border-radius:var(--sg-radius);
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.12);
}
.sg-route-end{display:flex;flex-direction:column;min-width:0}
.sg-route-end b{
  font:700 17px/1.3 var(--sg-head);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.sg-route-end small{font-size:12px;color:#93A5D8}
.sg-route-to{align-items:flex-end;text-align:right}
.sg-route-line{position:relative;flex:1;min-width:60px;height:24px}
.sg-route-line::before{
  content:"";
  position:absolute;
  left:0;
  right:0;
  top:50%;
  border-top:2px dashed rgba(255,255,255,.35);
}
.sg-route-line i{
  position:absolute;
  top:50%;
  left:0;
  transform:translate(-50%,-50%);
  color:var(--sg-amber-light);
  font-size:16px;
  animation:sgFly 4.5s ease-in-out infinite;
}
@keyframes sgFly{
  0%{left:0;opacity:0}
  12%{opacity:1}
  88%{opacity:1}
  100%{left:100%;opacity:0}
}
.sg-hero-points{
  list-style:none;
  margin:0 0 30px;
  padding:0;
  display:flex;
  flex-wrap:wrap;
  gap:12px 24px;
  font-size:15px;
  font-weight:500;
  color:#E2E8FF;
}
.sg-hero-points i{color:var(--sg-amber-light);margin-right:8px}
.sg-hero-stats{display:flex;flex-wrap:wrap;gap:44px}
.sg-hero-stats b{display:block;font:800 30px/1.1 var(--sg-head)}
.sg-hero-stats span{font-size:13px;color:#93A5D8}

/* ===== Quote wizard ===== */
.qw{
  position:relative;
  padding:26px;
  border-radius:var(--sg-radius-lg);
  background:#fff;
  color:var(--sg-ink);
  box-shadow:var(--sg-shadow-lg);
}
.qw-head-top{display:flex;justify-content:space-between;align-items:baseline;gap:12px}
.qw-title{font:800 21px/1.25 var(--sg-head);margin:0;color:var(--sg-ink)}
.qw-title span{color:var(--sg-blue)}
.qw-count{font-size:12px;font-weight:600;color:var(--sg-muted);white-space:nowrap}
.qw-progress{height:6px;margin:14px 0 22px;border-radius:99px;background:#EEF2F7;overflow:hidden}
.qw-progress i{
  display:block;
  width:33.33%;
  height:100%;
  border-radius:inherit;
  background:linear-gradient(90deg,var(--sg-blue),#5B7CFA);
  transition:width .35s ease;
}
.qw[data-step="2"] .qw-progress i{width:66.66%}
.qw[data-step="3"] .qw-progress i{width:100%}
.qw-step{display:grid;gap:16px}
.qw-step[hidden]{display:none}
.qw-step.qw-in{animation:qwIn .3s ease}
@keyframes qwIn{
  from{opacity:0;transform:translateX(14px)}
  to{opacity:1;transform:none}
}
.qw-q{font:700 16px/1.3 var(--sg-head);margin:0;color:var(--sg-ink)}
.qw-group{display:grid;gap:10px}
.qw-tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.qw-tile{position:relative;margin:0}
.qw-tile input{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer}
.qw-tile span{
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:8px;
  min-height:84px;
  padding:12px 6px;
  border:1.5px solid var(--sg-line);
  border-radius:12px;
  background:#fff;
  color:var(--sg-text);
  font:600 13px/1.25 var(--sg-body);
  text-align:center;
  transition:border-color .15s,background .15s,box-shadow .15s,transform .15s;
}
.qw-tile i{font-size:20px;color:var(--sg-blue)}
.qw-tile:hover span{border-color:#A5B8F0;transform:translateY(-1px);box-shadow:var(--sg-shadow-sm)}
.qw-tile input:checked + span{
  border-color:var(--sg-blue);
  background:var(--sg-sky);
  color:var(--sg-navy);
  box-shadow:0 0 0 3px rgba(45,82,204,.15);
}
.qw-tile input:focus-visible + span{outline:3px solid rgba(45,82,204,.45);outline-offset:2px}
.qw-weight{display:grid;grid-template-columns:56px minmax(0,1fr) 56px;gap:10px}
.qw-stepper{
  height:58px;
  border:1.5px solid var(--sg-line);
  border-radius:12px;
  background:#fff;
  color:var(--sg-blue);
  font-size:16px;
  cursor:pointer;
  transition:border-color .15s,background .15s;
}
.qw-stepper:hover{border-color:var(--sg-blue);background:var(--sg-sky)}
.qw-weight-field{position:relative;display:block}
.qw-weight-field input{
  width:100%;
  height:58px;
  padding:0 46px 0 16px;
  border:1.5px solid var(--sg-line);
  border-radius:12px;
  font:800 26px var(--sg-head);
  color:var(--sg-ink);
  text-align:center;
  -moz-appearance:textfield;
}
.qw-weight-field input::-webkit-outer-spin-button,
.qw-weight-field input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.qw-weight-field span{
  position:absolute;
  right:16px;
  top:50%;
  transform:translateY(-50%);
  font-weight:600;
  color:var(--sg-muted);
}
.qw-chips{display:flex;flex-wrap:wrap;gap:8px}
.qw-chips button{
  height:36px;
  padding:0 14px;
  border-radius:99px;
  border:1.5px solid var(--sg-line);
  background:#fff;
  color:var(--sg-text);
  font:600 13px var(--sg-body);
  cursor:pointer;
}
.qw-chips button:hover{border-color:var(--sg-blue)}
.qw-chips button[aria-pressed="true"]{background:var(--sg-blue);border-color:var(--sg-blue);color:#fff}
.qw-hint{display:flex;gap:8px;margin:0;font-size:13px;line-height:1.5;color:var(--sg-muted)}
.qw-hint i{margin-top:3px}
.qw-hint.is-ok{color:#047857}
.qw-summary{
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 14px;
  border:1px solid var(--sg-line);
  border-radius:12px;
  background:var(--sg-bg);
  font-size:13px;
  color:var(--sg-text);
}
.qw-summary b{color:var(--sg-ink)}
.qw-summary button{
  margin-left:auto;
  padding:0;
  border:0;
  background:none;
  color:var(--sg-blue);
  font:600 13px var(--sg-body);
  cursor:pointer;
}
.qw-field{gap:6px}
.qw-field label{font-size:13px;font-weight:600;color:var(--sg-text)}
.qw-input{
  width:100%;
  height:52px;
  padding:0 14px;
  border:1.5px solid var(--sg-line);
  border-radius:12px;
  background:#fff;
  color:var(--sg-ink);
  font:500 16px var(--sg-body);
  transition:border-color .15s,box-shadow .15s;
}
.qw-input:focus,
.qw-weight-field input:focus{outline:none;border-color:var(--sg-blue);box-shadow:0 0 0 4px rgba(45,82,204,.15)}
.qw-phone{position:relative}
.qw-phone span{
  position:absolute;
  left:14px;
  top:50%;
  transform:translateY(-50%);
  padding-right:10px;
  border-right:1px solid var(--sg-line);
  font-weight:600;
  color:var(--sg-text);
}
.qw-phone .qw-input{padding-left:64px}
.qw-err{display:none;margin:0;font-size:12.5px;color:var(--sg-err)}
.qw-bad .qw-err{display:block}
.qw-bad .qw-input,
.qw-bad .qw-weight-field input{border-color:var(--sg-err)}
.qw-bad .qw-tile span{border-color:#FCA5A5}
.qw-hp{position:absolute!important;left:-9999px;width:1px;height:1px;opacity:0}
.qw-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  width:100%;
  height:54px;
  border:0;
  border-radius:12px;
  background:var(--sg-blue);
  color:#fff;
  font:700 16px var(--sg-body);
  text-decoration:none;
  cursor:pointer;
  box-shadow:0 10px 20px -10px rgba(45,82,204,.8);
  transition:background .15s,transform .15s;
}
.qw-btn:hover{background:var(--sg-blue-dark)}
.qw-btn:active{transform:translateY(1px)}
.qw-btn-cta{background:var(--sg-amber);color:#111827;box-shadow:0 10px 20px -10px rgba(245,158,11,.9)}
.qw-btn-cta:hover{background:var(--sg-amber-light)}
.qw-btn-ghost{background:#fff;color:var(--sg-blue);border:1.5px solid var(--sg-line);box-shadow:none}
.qw-btn-ghost:hover{background:var(--sg-sky)}
.qw-btn[disabled]{opacity:.75;cursor:progress}
.qw-spin{
  width:18px;
  height:18px;
  border:2.5px solid rgba(17,24,39,.25);
  border-top-color:#111827;
  border-radius:50%;
  animation:qwSpin .7s linear infinite;
}
@keyframes qwSpin{to{transform:rotate(360deg)}}
.qw-legal{margin:-4px 0 0;font-size:11.5px;line-height:1.5;color:var(--sg-muted);text-align:center}
.qw-foot{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-top:16px;
  font-size:12.5px;
  color:var(--sg-muted);
}
.qw-foot i{margin-right:4px}
.qw-back{
  padding:6px 0;
  border:0;
  background:none;
  color:var(--sg-muted);
  font:600 13px var(--sg-body);
  cursor:pointer;
}
.qw-back:hover{color:var(--sg-blue)}
.qw[data-step="1"] .qw-back{visibility:hidden}
.qw-fail{display:none;margin:12px 0 0;font-size:13.5px;color:var(--sg-err);text-align:center}
.qw-fail a{color:inherit;font-weight:600}
.qw.is-failed .qw-fail{display:block}
.qw-done{display:none;text-align:center}
.qw.is-done .qw-head,
.qw.is-done .qw-form,
.qw.is-done .qw-foot,
.qw.is-done .qw-fail{display:none}
.qw.is-done .qw-done{display:block;animation:qwIn .35s ease}
.qw-done-icon{
  display:grid;
  place-items:center;
  width:64px;
  height:64px;
  margin:4px auto 14px;
  border-radius:50%;
  background:#DCFCE7;
  color:var(--sg-ok);
  font-size:28px;
  animation:qwPop .5s cubic-bezier(.2,1.4,.4,1);
}
@keyframes qwPop{from{transform:scale(.4);opacity:0}to{transform:none;opacity:1}}
.qw-done h3{font:800 22px/1.3 var(--sg-head);margin:0 0 14px;outline:none}
.qw-price{
  margin:0 0 14px;
  padding:18px 16px;
  border:1px solid var(--sg-sky-2);
  border-radius:var(--sg-radius);
  background:linear-gradient(135deg,var(--sg-sky),#fff);
}
.qw-price[hidden]{display:none}
.qw-price small{display:block;font-size:12.5px;line-height:1.5;color:var(--sg-muted)}
.qw-price strong{display:block;margin:4px 0;font:800 38px/1.15 var(--sg-head);color:var(--sg-navy)}
.qw-done p{margin:0 0 18px;font-size:14.5px;line-height:1.6;color:var(--sg-text)}
.qw-done-actions{display:grid;gap:10px}
.qw-next{
  margin:0 0 18px;
  padding:14px 16px;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius);
  background:var(--sg-bg);
  text-align:left;
}
.qw-next-title{margin:0 0 10px;font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--sg-muted)}
.qw-next ol{list-style:none;display:grid;gap:12px;margin:0;padding:0}
.qw-next li{display:grid;grid-template-columns:32px minmax(0,1fr);gap:12px;align-items:center}
.qw-next i{
  display:grid;
  place-items:center;
  width:32px;
  height:32px;
  border-radius:50%;
  background:#fff;
  color:var(--sg-blue);
  font-size:13px;
  box-shadow:var(--sg-shadow-sm);
}
.qw-next span{font-size:13px;line-height:1.4;color:var(--sg-muted)}
.qw-next b{display:block;font-size:14px;color:var(--sg-ink)}
.qw-pulse{animation:qwPulse 1.2s ease-out}
@keyframes qwPulse{
  0%{box-shadow:0 0 0 0 rgba(251,191,36,.75),var(--sg-shadow-lg)}
  100%{box-shadow:0 0 0 22px rgba(251,191,36,0),var(--sg-shadow-lg)}
}

/* ===== Sections ===== */
.sg-section{padding:96px 0}
.sg-section-alt{background:var(--sg-bg)}
.sg-section-head{max-width:740px;margin:0 auto 52px;text-align:center}
.sg-section-head p{margin:0;font-size:17px;line-height:1.65;color:var(--sg-muted)}
.sg-kicker{
  margin:0 0 12px;
  font-size:12.5px;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--sg-blue);
}
.sg-h2{
  margin:0 0 14px;
  font:800 40px/1.15 var(--sg-head);
  letter-spacing:-.02em;
  color:var(--sg-ink);
}
.sg-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  height:52px;
  padding:0 26px;
  border:0;
  border-radius:12px;
  font:700 16px var(--sg-body);
  text-decoration:none;
  cursor:pointer;
  transition:background .15s,transform .15s,border-color .15s;
}
.sg-btn-primary{background:var(--sg-amber);color:#111827;box-shadow:0 10px 20px -10px rgba(245,158,11,.9)}
.sg-btn-primary:hover{background:var(--sg-amber-light);transform:translateY(-1px)}
.sg-btn-outline{background:#fff;color:var(--sg-blue);border:1.5px solid #C7D2FE}
.sg-btn-outline:hover{border-color:var(--sg-blue)}
.sg-btn-light{background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.3)}
.sg-btn-light:hover{background:rgba(255,255,255,.18)}
.sg-cta-row{display:flex;justify-content:center;flex-wrap:wrap;gap:12px;margin-top:48px}

/* Marketplaces + press marquee */
.sg-logos{padding:36px 0;border-bottom:1px solid var(--sg-line)}
.sg-logos > p{
  margin:0 0 22px;
  font-size:12.5px;
  font-weight:600;
  letter-spacing:.1em;
  text-transform:uppercase;
  color:var(--sg-muted);
  text-align:center;
}
.sg-marquee{
  overflow:hidden;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);
  mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);
}
.sg-marquee-track{
  display:flex;
  align-items:center;
  gap:64px;
  width:max-content;
  animation:sgMarquee 40s linear infinite;
}
.sg-marquee:hover .sg-marquee-track{animation-play-state:paused}
.sg-logos img{height:40px;width:auto;filter:grayscale(1);opacity:.65;transition:filter .2s,opacity .2s}
.sg-logos img:hover{filter:none;opacity:1}
@keyframes sgMarquee{to{transform:translateX(-50%)}}

/* Features */
.sg-features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.sg-feature{
  display:flex;
  flex-direction:column;
  overflow:hidden;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius-lg);
  background:#fff;
  transition:transform .2s,box-shadow .2s;
}
.sg-feature:hover{transform:translateY(-4px);box-shadow:var(--sg-shadow)}
.sg-feature-img{
  aspect-ratio:4/3;
  padding:20px;
  background:linear-gradient(160deg,var(--sg-sky),#F8FAFF);
}
.sg-feature-img img{width:100%;height:100%;object-fit:contain;display:block}
.sg-feature-body{display:flex;flex-direction:column;gap:10px;flex:1;padding:24px}
.sg-feature h3{margin:0;font:700 20px/1.3 var(--sg-head);color:var(--sg-ink)}
.sg-feature p{margin:0;font-size:15px;line-height:1.65;color:var(--sg-text)}
.sg-link{
  display:inline-flex;
  align-items:center;
  gap:8px;
  margin-top:auto;
  padding:8px 0 0;
  border:0;
  background:none;
  color:var(--sg-blue);
  font:600 15px var(--sg-body);
  text-align:left;
  cursor:pointer;
}
.sg-link i{transition:transform .2s}
.sg-link:hover i{transform:translateX(4px)}
.sg-caps{
  list-style:none;
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:16px;
  margin:28px 0 0;
  padding:0;
}
.sg-caps li{
  display:grid;
  grid-template-columns:44px 1fr;
  grid-template-rows:auto auto;
  column-gap:14px;
  align-items:center;
  padding:18px;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius);
}
.sg-caps i{
  grid-row:1/3;
  display:grid;
  place-items:center;
  width:44px;
  height:44px;
  border-radius:12px;
  background:var(--sg-sky);
  color:var(--sg-blue);
  font-size:18px;
}
.sg-caps b{font:700 15px var(--sg-head);color:var(--sg-ink)}
.sg-caps span{font-size:13px;color:var(--sg-muted)}

/* Steps */
.sg-steps{
  list-style:none;
  position:relative;
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:24px;
  margin:0;
  padding:0;
}
.sg-steps::before{
  content:"";
  position:absolute;
  top:28px;
  left:17%;
  right:17%;
  border-top:2px dashed #C7D2FE;
}
.sg-step{position:relative;padding:0 16px;text-align:center}
.sg-step-num{
  display:grid;
  place-items:center;
  width:56px;
  height:56px;
  margin:0 auto 20px;
  border-radius:50%;
  background:var(--sg-blue);
  color:#fff;
  font:800 20px var(--sg-head);
  box-shadow:0 0 0 8px var(--sg-bg),0 12px 24px -8px rgba(45,82,204,.6);
}
.sg-step h3{margin:0 0 8px;font:700 19px var(--sg-head)}
.sg-step p{margin:0;font-size:15px;line-height:1.6;color:var(--sg-muted)}

/* Reviews */
.sg-reviews-grid{display:grid;grid-template-columns:minmax(0,380px) minmax(0,1fr);gap:56px;align-items:center}
.sg-reviews-copy p:not(.sg-kicker){margin:0;font-size:17px;line-height:1.65;color:var(--sg-muted)}
.sg-reviews-nav{display:flex;gap:10px;margin-top:28px}
.sg-reviews-nav button{
  width:48px;
  height:48px;
  border:1.5px solid var(--sg-line);
  border-radius:50%;
  background:#fff;
  color:var(--sg-ink);
  cursor:pointer;
  transition:border-color .15s,color .15s;
}
.sg-reviews-nav button:hover{border-color:var(--sg-blue);color:var(--sg-blue)}
.sg-reviews-track{
  display:grid;
  grid-auto-flow:column;
  grid-auto-columns:min(520px,85%);
  gap:20px;
  overflow-x:auto;
  scroll-snap-type:x mandatory;
  scroll-behavior:smooth;
  padding:6px 4px 18px;
  scrollbar-width:none;
}
.sg-reviews-track::-webkit-scrollbar{display:none}
.sg-reviews-track:focus-visible{outline:3px solid rgba(45,82,204,.35);border-radius:12px}
.sg-reviews-track figure{
  margin:0;
  padding:22px;
  scroll-snap-align:start;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius-lg);
  background:#fff;
  box-shadow:var(--sg-shadow-sm);
}
.sg-reviews-track img{display:block;width:100%;height:auto}

/* Guide */
.sg-guide-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:56px;align-items:start}
.sg-prose{font-size:16px;line-height:1.8;color:var(--sg-text)}
.sg-prose h2,.sg-prose h3,.sg-prose h4,.sg-prose h5{
  margin:32px 0 12px;
  font-family:var(--sg-head);
  font-weight:700;
  line-height:1.3;
  color:var(--sg-ink);
}
.sg-prose h2{font-size:26px}
.sg-prose h3{font-size:21px}
.sg-prose h4{font-size:18px}
.sg-prose p{margin:0 0 16px}
.sg-prose ul,.sg-prose ol{margin:0 0 20px;padding-left:22px}
.sg-prose li + li{margin-top:6px}
.sg-prose a{color:var(--sg-blue)}
.sg-prose table{width:100%;border-collapse:collapse;margin:0 0 20px;font-size:15px}
.sg-prose th,.sg-prose td{padding:10px 12px;border:1px solid var(--sg-line);text-align:left}
.sg-prose th{background:var(--sg-sky)}
.rmwr-wrapper{display:inline}
.read-link{
  display:inline;
  padding:0;
  border:none;
  background:none;
  color:var(--sg-blue);
  font:inherit;
  font-weight:600;
  text-decoration:underline;
  cursor:pointer;
}
.read-link:hover{color:var(--sg-navy)}
.read-more-content{display:inline}
.sg-guide-aside{position:sticky;top:110px}
.sg-aside-card{
  display:grid;
  gap:14px;
  padding:28px;
  border-radius:var(--sg-radius-lg);
  background:linear-gradient(160deg,#0B1F5C,#1B3CA3);
  color:#fff;
  box-shadow:var(--sg-shadow);
}
.sg-aside-icon{
  display:grid;
  place-items:center;
  width:48px;
  height:48px;
  border-radius:12px;
  background:rgba(255,255,255,.12);
  color:var(--sg-amber-light);
  font-size:20px;
}
.sg-aside-card h3{margin:0;font:800 21px/1.3 var(--sg-head)}
.sg-aside-card p{margin:0;font-size:14.5px;line-height:1.6;color:#C9D4F2}
.sg-aside-card .sg-aside-from{color:#FDE68A}
.sg-aside-from b{color:#fff;font:800 18px var(--sg-head)}
.sg-aside-call{color:#C9D4F2;font-size:14px;text-align:center;text-decoration:none}
.sg-aside-call:hover{color:#fff}

/* FAQ */
.sg-faq-grid{display:grid;grid-template-columns:minmax(0,380px) minmax(0,1fr);gap:56px;align-items:start}
.sg-faq-intro{position:sticky;top:110px}
.sg-faq-intro .sg-h2{font-size:32px}
.sg-faq-intro p:not(.sg-kicker){margin:0 0 24px;font-size:17px;line-height:1.65;color:var(--sg-muted)}
.sg-faq-list{display:grid;gap:12px}
.sg-faq-item{
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius);
  background:#fff;
  transition:border-color .15s,box-shadow .15s;
}
.sg-faq-item[open]{border-color:#C7D2FE;box-shadow:var(--sg-shadow-sm)}
.sg-faq-item summary{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:16px;
  padding:18px 20px;
  font:600 16px/1.45 var(--sg-head);
  color:var(--sg-ink);
  list-style:none;
  cursor:pointer;
}
.sg-faq-item summary::-webkit-details-marker{display:none}
.sg-faq-item summary i{flex:none;color:var(--sg-blue);transition:transform .2s}
.sg-faq-item[open] summary i{transform:rotate(45deg)}
.sg-faq-a{padding:0 20px 18px;font-size:15px;line-height:1.7;color:var(--sg-text)}

/* Related routes ($Relposts: h4 + ul of links) */
.sg-related{padding:0 0 96px}
.sg-related h4{margin:0 0 18px;font:700 20px var(--sg-head);color:var(--sg-ink)}
.sg-related ul{
  list-style:none;
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
  gap:12px;
  margin:0;
  padding:0;
}
.sg-related li a{
  display:flex;
  align-items:center;
  height:100%;
  padding:14px 16px;
  border:1px solid var(--sg-line);
  border-radius:12px;
  color:var(--sg-text);
  font-size:14.5px;
  line-height:1.45;
  text-decoration:none;
  transition:border-color .15s,color .15s,background .15s;
}
.sg-related li a::before{
  content:"\f072";
  margin-right:12px;
  font:900 13px 'Font Awesome 6 Free';
  color:var(--sg-blue);
}
.sg-related li a:hover{border-color:var(--sg-blue);background:var(--sg-sky);color:var(--sg-navy)}

/* Press */
.sg-press{background:var(--sg-bg)}
.sg-press .sg-section-head{margin-bottom:40px}
.sg-press img{width:240px;height:auto;padding:12px;border-radius:var(--sg-radius);background:#fff;box-shadow:var(--sg-shadow-sm)}
.sg-press-marquee .sg-marquee-track{gap:24px}

/* Final CTA */
.sg-final{
  padding:72px 0;
  color:#fff;
  background:
    radial-gradient(700px 300px at 90% 0%,rgba(91,124,250,.4),transparent 60%),
    linear-gradient(135deg,#071540,#1B3CA3);
}
.sg-final-inner{display:flex;justify-content:space-between;align-items:center;gap:32px}
.sg-final h2{margin:0 0 10px;font:800 34px/1.2 var(--sg-head);letter-spacing:-.01em}
.sg-final p{margin:0;font-size:17px;color:#C9D4F2}
.sg-final-actions{display:flex;flex-wrap:wrap;gap:12px;flex:none}

/* ===== Quote modal ===== */
.sg-modal{
  width:100%;
  max-width:480px;
  padding:0;
  border:0;
  background:transparent;
  overflow:visible;
}
.sg-modal::backdrop{background:rgba(7,21,64,.6);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)}
.sg-modal[open]{animation:sgModalIn .25s ease}
@keyframes sgModalIn{from{opacity:0;transform:translateY(16px) scale(.98)}to{opacity:1;transform:none}}
.sg-modal .qw{box-shadow:0 40px 80px -20px rgba(7,21,64,.6)}
.sg-modal-close{
  position:absolute;
  top:-14px;
  right:-14px;
  z-index:2;
  width:38px;
  height:38px;
  border:0;
  border-radius:50%;
  background:#fff;
  color:var(--sg-ink);
  font-size:16px;
  cursor:pointer;
  box-shadow:var(--sg-shadow);
}
body.sg-modal-open{overflow:hidden}

/* ===== Sticky mobile CTA ===== */
.sg-sticky{display:none}

/* ===== Motion preferences ===== */
@media (prefers-reduced-motion:reduce){
  .sg-marquee-track,.sg-route-line i{animation:none}
  .qw-step.qw-in,.qw.is-done .qw-done,.sg-modal[open]{animation:none}
}

/* ===== Responsive ===== */
@media(max-width:1100px){
  .sg-hero-grid{grid-template-columns:minmax(0,1fr) minmax(340px,420px);column-gap:40px}
  .sg-hero h1{font-size:46px}
  .sg-caps{grid-template-columns:repeat(2,minmax(0,1fr))}
  .sg-guide-grid{grid-template-columns:minmax(0,1fr) 300px;gap:40px}
}
@media(max-width:900px){
  .sg-hero-grid{
    grid-template-columns:minmax(0,1fr);
    grid-template-areas:"head" "form" "proof";
    row-gap:24px;
    padding-top:32px;
    padding-bottom:44px;
  }
  .sg-hero h1{font-size:38px}
  .sg-route{margin:8px 0 22px}
  .sg-section{padding:64px 0}
  .sg-h2{font-size:30px}
  .sg-features{grid-template-columns:minmax(0,1fr);gap:18px}
  .sg-feature{display:grid;grid-template-columns:200px minmax(0,1fr)}
  .sg-feature-img{aspect-ratio:auto;height:100%}
  .sg-reviews-grid,.sg-faq-grid,.sg-guide-grid{grid-template-columns:minmax(0,1fr);gap:32px}
  .sg-guide-aside,.sg-faq-intro{position:static}
  .sg-final-inner{flex-direction:column;align-items:flex-start}
  .sg-related{padding-bottom:64px}

  body{padding-bottom:76px}
  .sg-sticky{
    display:flex;
    gap:10px;
    position:fixed;
    left:0;
    right:0;
    bottom:0;
    z-index:30;
    padding:10px 12px calc(10px + env(safe-area-inset-bottom,0px));
    background:#fff;
    box-shadow:0 -8px 24px rgba(15,23,42,.12);
    transition:transform .25s ease;
  }
  .sg-sticky.is-hidden{transform:translateY(110%)}
  .sg-sticky-quote{
    flex:1;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    height:52px;
    border:0;
    border-radius:12px;
    background:var(--sg-amber);
    color:#111827;
    font:700 16px var(--sg-body);
    cursor:pointer;
  }
  .sg-sticky-quote small{margin-left:-4px;font-size:13.5px;font-weight:600}
  .sg-sticky-call{
    display:grid;
    place-items:center;
    width:52px;
    height:52px;
    border:1.5px solid #C7D2FE;
    border-radius:12px;
    color:var(--sg-blue);
    font-size:18px;
  }
  .bulk-order-float{display:none}
}
@media(max-width:600px){
  .sg-wrap{padding:0 16px}
  .sg-hero-grid{padding-top:22px;padding-bottom:36px;row-gap:20px}
  .sg-eyebrow{margin-bottom:14px;padding:7px 12px;font-size:12px}
  .sg-hero h1{font-size:30px;margin-bottom:10px}
  .sg-hero-lead{font-size:15px}
  .sg-from{margin-top:14px;padding:8px 12px;font-size:13.5px}
  .sg-from b{font-size:16px}
  .sg-hero-quote{margin-top:24px;padding:14px 16px}
  .sg-hero-quote blockquote{font-size:14px}
  .sg-route{padding:14px 16px;gap:12px}
  .sg-route-end b{font-size:15px}
  .sg-hero-points{gap:10px 18px;font-size:14px}
  .sg-hero-stats{gap:20px;justify-content:space-between}
  .sg-hero-stats b{font-size:22px}
  .qw{padding:20px 16px;border-radius:18px}
  .qw-title{font-size:18px}
  .qw-tiles{gap:8px}
  .qw-tile span{min-height:76px;padding:10px 4px;font-size:12.5px}
  .sg-section{padding:52px 0}
  .sg-section-head{margin-bottom:32px}
  .sg-section-head p{font-size:15.5px}
  .sg-h2{font-size:26px}
  .sg-feature{grid-template-columns:minmax(0,1fr)}
  .sg-feature-img{aspect-ratio:16/10;height:auto}
  .sg-feature-body{padding:20px}
  .sg-caps{gap:10px}
  .sg-caps li{grid-template-columns:minmax(0,1fr);row-gap:6px;padding:14px}
  .sg-caps i{grid-row:auto;width:38px;height:38px;font-size:16px}
  .sg-steps{grid-template-columns:minmax(0,1fr);gap:22px}
  .sg-steps::before{top:28px;bottom:28px;left:28px;right:auto;border-top:0;border-left:2px dashed #C7D2FE}
  .sg-step{display:grid;grid-template-columns:56px minmax(0,1fr);column-gap:16px;padding:0;text-align:left}
  .sg-step-num{grid-row:1/3;margin:0}
  .sg-step h3{align-self:end;margin-bottom:4px}
  .sg-cta-row{flex-direction:column;margin-top:32px}
  .sg-cta-row .sg-btn{width:100%}
  .sg-reviews-track{grid-auto-columns:88%}
  .sg-reviews-track figure{padding:16px}
  .sg-aside-card{padding:22px}
  .sg-faq-intro .sg-h2{font-size:24px}
  .sg-faq-item summary{padding:16px;font-size:15px}
  .sg-faq-a{padding:0 16px 16px}
  .sg-final{padding:52px 0}
  .sg-final h2{font-size:26px}
  .sg-final-actions{width:100%;flex-direction:column}
  .sg-final-actions .sg-btn{width:100%}
  .sg-logos img{height:32px}
  .sg-marquee-track{gap:40px}
  .sg-press img{width:200px}

  .sg-modal{max-width:100%;margin:auto 0 0}
  .sg-modal .qw{border-radius:20px 20px 0 0;max-height:92vh;overflow:auto;padding-top:24px}
  .sg-modal-close{top:12px;right:12px;box-shadow:none;background:var(--sg-bg)}
  .sg-modal .qw-head-top{padding-right:40px}
}

</style>
</head>

<body>
    
    <!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WMNT29VQ"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<!-- Announcement Bar -->
<div class="announcement-bar">
  <div class="announcement-bar-inner">
    <a href="#form">
      <img class="announcement-bar-icon" src="https://shipglobal.in/wp-content/uploads/2025/05/Layer_1.svg" alt="">
      <span class="announcement-bar-text">Save Up to 15% on Your First Order | Limited-Time Offer*</span>
    </a>
  </div>
</div>

<!-- Sticky Site Header -->
<header class="site-header">
  <div class="site-header-inner">

    <!-- Logo -->
    <div class="header-logo-wrap">
      <a href="https://shipglobal.in/">
        <img class="header-logo" src="https://shipglobal.in/wp-content/uploads/2025/05/blue-logo-1.webp" alt="ShipGlobal.in">
      </a>
    </div>

    <!-- Nav Menu -->
    <nav class="header-nav-wrap">
      <ul class="header-nav">
        <li><a href="https://shipglobal.in/">Home</a></li>
        <li><a href="https://shipglobal.in/tracking/">Tracking</a></li>
        <li><a href="https://shipglobal.in/about/">About</a></li>
        <li><a href="https://shipglobal.in/contact/">Contact</a></li>
      </ul>
    </nav>

    <!-- CTA Button (desktop only) -->
    <div class="header-cta-wrap">
      <button type="button" class="header-quote-btn" data-quote-open="header">Get my price</button>
      <a class="header-ship-btn" href="https://v2.app.shipglobal.in/auth/signup" target="_blank" rel="noopener">Ship Now</a>
    </div>

    <!-- Hamburger (mobile only) -->
    <button class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>

  </div>

  <!-- Mobile Nav Drawer -->
  <nav class="mobile-nav" id="mobile-nav" aria-hidden="true">
    <a href="https://shipglobal.in/">Home</a>
    <a href="https://shipglobal.in/tracking/">Tracking</a>
    <a href="https://shipglobal.in/about/">About</a>
    <a href="https://shipglobal.in/contact/">Contact</a>
    <a class="mobile-ship-btn" href="https://v2.app.shipglobal.in/auth/signup" target="_blank" rel="noopener">Ship Now</a>
  </nav>

</header>

<!-- Floating Bulk Order Icon -->
<div class="bulk-order-float">
  <a href="#form">
    <img src="https://shipglobal.in/wp-content/uploads/2025/05/Bulk-order-icon.webp" alt="Bulk Order">
  </a>
</div>

<main>

<!-- HERO -->
<section class="sg-hero">
  <div class="sg-hero-grid-bg" aria-hidden="true"></div>
  <div class="sg-wrap sg-hero-grid">
    <div class="sg-hero-head">
      <p class="sg-eyebrow"><i class="fas fa-plane-departure"></i> International courier from <?php echo $origin; ?></p>
      <h1><?php echo $origin; ?> to <?php echo $destination; ?> <span>Courier Shipment</span></h1>
      <p class="sg-hero-lead">Doorstep pickup in <?php echo $origin; ?>, export paperwork support and tracked delivery to <?php echo $destination; ?>. Ship packets from just 50 g, no bulk needed.</p>
      <p class="sg-from" data-sg-from hidden><i class="fas fa-tag"></i> Rates to <?php echo $destination; ?> from <b>₹<span data-sg-from-price></span></b> <span class="sg-from-note">all-inclusive</span></p>
    </div>

    <div class="sg-hero-form" id="form">
      <?php render_quote_wizard('hero', $origin, $destination); ?>
    </div>

    <div class="sg-hero-proof">
      <div class="sg-route" aria-hidden="true">
        <div class="sg-route-end"><b><?php echo $origin; ?></b><small>India</small></div>
        <div class="sg-route-line"><i class="fas fa-plane"></i></div>
        <div class="sg-route-end sg-route-to"><b><?php echo $destination; ?></b><small>Destination</small></div>
      </div>
      <ul class="sg-hero-points">
        <li><i class="fas fa-truck-fast"></i> Doorstep pickup</li>
        <li><i class="fas fa-file-shield"></i> Customs &amp; documentation support</li>
        <li><i class="fas fa-location-crosshairs"></i> Shipment tracking</li>
      </ul>
      <div class="sg-hero-stats">
        <div><b>25,000+</b><span>Exporters</span></div>
        <div><b>1 Crore+</b><span>Orders shipped</span></div>
        <div><b>220+</b><span>Countries</span></div>
      </div>
      <figure class="sg-hero-quote">
        <blockquote>“ShipGlobal's tracking system works seamlessly and they offer reliable door-to-door delivery.”</blockquote>
        <figcaption><span class="sg-hero-quote-avatar" aria-hidden="true">MK</span><span><b>Mayank Kapoor</b><br>Owner, Aariv Impex, Gurugram</span></figcaption>
      </figure>
    </div>
  </div>
</section>

<!-- MARKETPLACES -->
<section class="sg-logos" aria-label="Marketplaces">
  <p>Helping our partners grow globally on</p>
  <div class="sg-marquee">
    <div class="sg-marquee-track">
<?php
$marketplaces = [
  'https://shipglobal.in/wp-content/uploads/2025/04/Walmart-Marketplace-Logo-Stacked-Color-RGB.webp' => 'Walmart Marketplace',
  'https://shipglobal.in/wp-content/uploads/2025/12/shopify.png' => 'Shopify',
  'https://shipglobal.in/wp-content/uploads/2025/12/noon.png' => 'noon',
  'https://shipglobal.in/wp-content/uploads/2025/12/myshopeurope.png' => 'MyShopEurope',
  'https://shipglobal.in/wp-content/uploads/2025/12/joom.png' => 'Joom',
  'https://shipglobal.in/wp-content/uploads/2025/12/etsy.png' => 'Etsy',
  'https://shipglobal.in/wp-content/uploads/2025/12/ebay.png' => 'eBay',
  'https://shipglobal.in/wp-content/uploads/2025/12/amazon.png' => 'Amazon',
];
// Rendered twice for a seamless loop; the copy is hidden from screen readers.
foreach ([false, true] as $copy) {
  foreach ($marketplaces as $src => $alt) {
    echo '      <img src="' . $src . '" alt="' . ($copy ? '' : $alt) . '" loading="lazy"' . ($copy ? ' aria-hidden="true"' : '') . ">\n";
  }
}
?>
    </div>
  </div>
</section>

<!-- WHY SHIPGLOBAL -->
<section class="sg-section">
  <div class="sg-wrap">
    <div class="sg-section-head">
      <p class="sg-kicker">Why ShipGlobal</p>
      <h2 class="sg-h2">Everything you need to ship from <?php echo $origin; ?> to <?php echo $destination; ?></h2>
      <p>Affordable, reliable, and built for Indian businesses &amp; consumers, from pickup to final delivery.</p>
    </div>

    <div class="sg-features">
      <article class="sg-feature">
        <div class="sg-feature-img"><img src="https://shipglobal.in/wp-content/uploads/2025/06/Benefit-1.webp" alt="Doorstep pickup" loading="lazy"></div>
        <div class="sg-feature-body">
          <h3>Door-to-door delivery</h3>
          <p>No more “not serviceable” excuses. We pick up from your doorstep in <?php echo $origin; ?> and deliver straight to your buyer in <?php echo $destination; ?>.</p>
          <button type="button" class="sg-link" data-quote-open="feature_pickup">Schedule my pickup <i class="fas fa-arrow-right"></i></button>
        </div>
      </article>
      <article class="sg-feature">
        <div class="sg-feature-img"><img src="https://shipglobal.in/wp-content/uploads/2025/06/Benefits-4.webp" alt="eCommerce shipping" loading="lazy"></div>
        <div class="sg-feature-body">
          <h3>Dedicated supply chain for eCommerce</h3>
          <p>Seamless marketplace integration, zero platform fees and shipping from just 50 g. No need to wait for bulk volumes to sell in <?php echo $destination; ?>.</p>
          <button type="button" class="sg-link" data-quote-open="feature_ecom">Grow my eCommerce business <i class="fas fa-arrow-right"></i></button>
        </div>
      </article>
      <article class="sg-feature">
        <div class="sg-feature-img"><img src="https://shipglobal.in/wp-content/uploads/2025/06/Image-2-480x474.webp" alt="Export documentation support" loading="lazy"></div>
        <div class="sg-feature-body">
          <h3>Documentation &amp; customs support</h3>
          <p>Our Export Experts walk you through exactly what you need, so your parcel doesn't get stuck at <?php echo $destination; ?> customs.</p>
          <button type="button" class="sg-link" data-quote-open="feature_docs">Simplify my export documentation <i class="fas fa-arrow-right"></i></button>
        </div>
      </article>
    </div>

    <ul class="sg-caps">
      <li><i class="fas fa-weight-hanging"></i><b>From 50 g</b><span>Ship single packets</span></li>
      <li><i class="fas fa-store"></i><b>Zero platform fees</b><span>Pay only for shipping</span></li>
      <li><i class="fas fa-location-crosshairs"></i><b>Track every parcel</b><span>Pickup to delivery</span></li>
      <li><i class="fas fa-headset"></i><b>Export experts</b><span>Help at every step</span></li>
    </ul>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="sg-section sg-section-alt">
  <div class="sg-wrap">
    <div class="sg-section-head">
      <p class="sg-kicker">How it works</p>
      <h2 class="sg-h2">Your product deserves a global stage</h2>
      <p>All it takes is a few minutes to kick-start your <?php echo $origin; ?> to <?php echo $destination; ?> export journey. Our dashboard guides you from signup to booking your first shipment.</p>
    </div>
    <ol class="sg-steps">
      <li class="sg-step">
        <span class="sg-step-num">1</span>
        <h3>Create your order</h3>
        <p>Enter your shipment details in a few clicks.</p>
      </li>
      <li class="sg-step">
        <span class="sg-step-num">2</span>
        <h3>Compare rates</h3>
        <p>Choose the service that fits your speed and budget.</p>
      </li>
      <li class="sg-step">
        <span class="sg-step-num">3</span>
        <h3>Book your pickup</h3>
        <p>Ship confidently and leave the rest to us.</p>
      </li>
    </ol>
    <div class="sg-cta-row">
      <button type="button" class="sg-btn sg-btn-primary" data-quote-open="steps">Get my price <i class="fas fa-arrow-right"></i></button>
      <a class="sg-btn sg-btn-outline" href="<?php echo $signupUrl; ?>" target="_blank" rel="noopener">Create free account</a>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="sg-section sg-reviews">
  <div class="sg-wrap sg-reviews-grid">
    <div class="sg-reviews-copy">
      <p class="sg-kicker">Customer stories</p>
      <h2 class="sg-h2">1 Crore+ successful shipments</h2>
      <p>From mothers sending love abroad to new sellers starting their journey, ShipGlobal is for everyone.</p>
      <div class="sg-reviews-nav">
        <button type="button" data-reviews="-1" aria-label="Previous review"><i class="fas fa-chevron-left"></i></button>
        <button type="button" data-reviews="1" aria-label="Next review"><i class="fas fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="sg-reviews-track" id="sg-reviews" tabindex="0" aria-label="Customer reviews">
      <figure><img src="https://shipglobal.in/wp-content/uploads/2025/06/Mayak.webp" alt="Customer review" loading="lazy"></figure>
      <figure><img src="https://shipglobal.in/wp-content/uploads/2025/05/review-3-1.png" alt="Customer review" loading="lazy"></figure>
      <figure><img src="https://shipglobal.in/wp-content/uploads/2025/05/review-2-1.png" alt="Customer review" loading="lazy"></figure>
      <figure><img src="https://shipglobal.in/wp-content/uploads/2025/06/Seema-ji.webp" alt="Customer review" loading="lazy"></figure>
    </div>
  </div>
</section>

<!-- GUIDE (page content) -->
<section class="sg-section sg-section-alt sg-guide">
  <div class="sg-wrap sg-guide-grid">
    <article class="sg-article">
      <p class="sg-kicker">Shipping guide</p>
      <h2 class="sg-h2"><?php echo $origin; ?> to <?php echo $destination; ?> courier guide</h2>
      <div class="sg-prose">
        <?php echo $Updatedhtml; ?>
      </div>
    </article>
    <aside class="sg-guide-aside">
      <div class="sg-aside-card">
        <span class="sg-aside-icon"><i class="fas fa-calculator"></i></span>
        <h3>What will it cost to ship to <?php echo $destination; ?>?</h3>
        <p>Tell us what you're sending and how much it weighs. It takes 30 seconds.</p>
        <p class="sg-aside-from" data-sg-from hidden>Rates from <b>₹<span data-sg-from-price></span></b>, all-inclusive</p>
        <button type="button" class="sg-btn sg-btn-primary" data-quote-open="guide">Get my price <i class="fas fa-arrow-right"></i></button>
        <a class="sg-aside-call" href="tel:<?php echo $phoneTel; ?>"><i class="fas fa-phone"></i> or call <?php echo $phoneText; ?></a>
      </div>
    </aside>
  </div>
</section>

<!-- FAQ -->
<section class="sg-section sg-faq">
  <div class="sg-wrap sg-faq-grid">
    <div class="sg-faq-intro">
      <p class="sg-kicker">FAQ</p>
      <h2 class="sg-h2"><?php echo $faqHeading; ?></h2>
      <p>Can't find your answer? Our export experts are a call away.</p>
      <a class="sg-btn sg-btn-outline" href="tel:<?php echo $phoneTel; ?>"><i class="fas fa-phone"></i> <?php echo $phoneText; ?></a>
    </div>
    <div class="sg-faq-list">
<?php if ($faqItems): ?>
<?php foreach ($faqItems as $i => $item): ?>
      <details class="sg-faq-item"<?php echo $i === 0 ? ' open' : ''; ?>>
        <summary><?php echo $item['q']; ?><i class="fas fa-plus" aria-hidden="true"></i></summary>
        <div class="sg-faq-a"><?php echo $item['a']; ?></div>
      </details>
<?php endforeach; ?>
<?php else: ?>
      <div class="sg-prose"><?php echo $FAQ; ?></div>
<?php endif; ?>
    </div>
  </div>
</section>

<!-- RELATED ROUTES -->
<?php if (trim($Relposts) !== ''): ?>
<section class="sg-related">
  <div class="sg-wrap">
    <?php echo $Relposts; ?>
  </div>
</section>
<?php endif; ?>

<!-- PRESS -->
<section class="sg-section sg-press">
  <div class="sg-wrap">
    <div class="sg-section-head">
      <p class="sg-kicker">Press highlights</p>
      <h2 class="sg-h2">Spotlighting our journey across media</h2>
      <p>How we're making an impact and leading innovation in cross-border shipping.</p>
    </div>
  </div>
  <div class="sg-marquee sg-press-marquee">
    <div class="sg-marquee-track">
<?php
$press = [
  'https://shipglobal.in/wp-content/uploads/2025/05/5-3.webp',
  'https://shipglobal.in/wp-content/uploads/2025/05/5-2.webp',
  'https://shipglobal.in/wp-content/uploads/2025/05/5-1.webp',
  'https://shipglobal.in/wp-content/uploads/2025/05/5.webp',
];
foreach ([false, true] as $copy) {
  foreach ($press as $src) {
    echo '      <img src="' . $src . '" alt="' . ($copy ? '' : 'Press coverage') . '" loading="lazy"' . ($copy ? ' aria-hidden="true"' : '') . ">\n";
  }
}
?>
    </div>
  </div>
</section>

<!-- FINAL CTA -->
<section class="sg-final">
  <div class="sg-wrap sg-final-inner">
    <div>
      <h2>Ready to ship from <?php echo $origin; ?> to <?php echo $destination; ?>?</h2>
      <p>Get your price in 30 seconds. Doorstep pickup, customs support and tracking included.</p>
    </div>
    <div class="sg-final-actions">
      <button type="button" class="sg-btn sg-btn-primary" data-quote-open="final">Get my price <i class="fas fa-arrow-right"></i></button>
      <a class="sg-btn sg-btn-light" href="tel:<?php echo $phoneTel; ?>"><i class="fas fa-phone"></i> Talk to an expert</a>
    </div>
  </div>
</section>

</main>

<!-- ===== WhatsApp Community Bar ===== -->
<div class="wa-bar">
  <div class="wa-bar-inner">
    <div class="wa-logo-col">
      <img src="https://shipglobal.in/wp-content/uploads/2025/05/Export-Expert-Logo.webp" alt="Export Expert">
    </div>
    <div class="wa-text-col">
      <p>Join India's leading WhatsApp community for exporters!</p>
    </div>
    <div class="wa-btn-col">
      <a class="wa-join-btn" href="https://zfrmz.in/QRONFYXImG3HoY3p4iz3" target="_blank" rel="noopener">
        <i class="fab fa-whatsapp"></i> Join Now
      </a>
    </div>
  </div>
</div>

<!-- ===== Main Footer ===== -->
<footer class="site-footer">
  <div class="footer-inner">

    <!-- Brand + Social -->
    <div class="footer-brand">
      <img class="footer-logo" src="https://shipglobal.in/wp-content/uploads/2025/05/white-logo-1.webp" alt="ShipGlobal">
      <p class="footer-offices">
        <b>Regional Offices:</b> Delhi | Jaipur | Bhopal | Surat | Ahmedabad | Chennai | Agra | Moradabad | Mumbai | Kanpur | Lucknow | Udaipur | Meerut | Roorkee | Jalandhar | Ludhiana<br><br>
        PAN India Pickup
      </p>
      <div class="footer-social">
        <a href="https://www.facebook.com/shipglobalindia/" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="https://www.youtube.com/@ShipGlobal_in" target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        <a href="https://x.com/shipglobal_in?lang=en" target="_blank" rel="noopener" aria-label="X / Twitter"><i class="fab fa-x-twitter"></i></a>
        <a href="https://www.instagram.com/shipglobal.in/" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="https://in.linkedin.com/company/shipglobalin" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
      </div>
    </div>

    <!-- Useful Links -->
    <div class="footer-col">
      <h6 class="footer-col-title">Useful Links</h6>
      <ul class="footer-links">
        <li><a href="https://shipglobal.in/">Home</a></li>
        <li><a href="https://shipglobal.in/about/">About</a></li>
        <li><a href="https://shipglobal.in/tracking/">Tracking</a></li>
        <li><a href="https://shipglobal.in/contact/">Contact</a></li>
      </ul>
    </div>

    <!-- Quick Links -->
    <div class="footer-col">
      <h6 class="footer-col-title">Quick Links</h6>
      <ul class="footer-links">
        <li><a href="https://shipglobal.in/privacy-policy/">Privacy Policy</a></li>
        <li><a href="https://shipglobal.in/terms-and-conditions/">Terms &amp; Conditions</a></li>
        <li><a href="https://shipglobal.in/refunds-cancellation-policy/">Refunds &amp; Cancellation Policy</a></li>
      </ul>
    </div>

    <!-- Contact -->
    <div class="footer-col footer-contact-col">
      <h6 class="footer-col-title">Contact</h6>
      <ul class="footer-links">
        <li><i class="fas fa-phone-square-alt contact-icon"></i> +91 99065 99065</li>
        <li><i class="fas fa-envelope-open contact-icon"></i> support@shipglobal.in</li>
      </ul>
    </div>

  </div>
</footer>

<!-- Plane Animation Strip -->
<div class="footer-plane-strip">
  <div class="footer-plane-track">
    <img src="https://shipglobal.in/wp-content/uploads/2025/05/plane.png" alt="">
  </div>
</div>

<!-- Copyright -->
<div class="footer-copyright">
  <p>©<?php echo date('Y'); ?> Ship Global Express Private Limited | All Rights Reserved</p>
</div>

<!-- Quote modal (opened by every "Get my price" button below the hero) -->
<dialog class="sg-modal" id="sg-quote-modal" aria-label="Get your price">
  <button type="button" class="sg-modal-close" data-modal-close aria-label="Close"><i class="fas fa-xmark"></i></button>
  <?php render_quote_wizard('modal', $origin, $destination); ?>
</dialog>

<!-- Sticky mobile CTA -->
<div class="sg-sticky" id="sg-sticky">
  <button type="button" class="sg-sticky-quote" data-quote-open="sticky">Get my price<small data-sg-from hidden>· from ₹<span data-sg-from-price></span></small> <i class="fas fa-arrow-right"></i></button>
  <a class="sg-sticky-call" href="tel:<?php echo $phoneTel; ?>" aria-label="Call ShipGlobal"><i class="fas fa-phone"></i></a>
</div>

<script>
window.SG_QUOTE = {
  origin: <?php echo json_encode($origin); ?>,
  destination: <?php echo json_encode($destination); ?>,
  rateSheet: <?php echo json_encode($rateSheetUrl); ?>
};
</script>

<script>
// Mobile nav toggle
(function(){
  var btn = document.getElementById('hamburger');
  var nav = document.getElementById('mobile-nav');
  if(!btn || !nav) return;
  btn.addEventListener('click', function(){
    var open = nav.classList.toggle('open');
    btn.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open);
    nav.setAttribute('aria-hidden', !open);
  });
})();

function toggleReadMore(btn) {
  const content = btn.nextElementSibling;
  const isHidden = content.style.display === 'none';
  content.style.display = isHidden ? 'inline' : 'none';
  btn.textContent = isHidden ? 'Read Less' : 'Read More';
}

// Quote wizard: item -> weight -> name + phone, posted to the Zoho Flow webhook.
// GTM events: quote_open, quote_start, quote_step, generate_lead.
(function(){
  var cfg = window.SG_QUOTE || {};
  var dataLayer = window.dataLayer = window.dataLayer || [];
  var wizards = [];
  var rates = null;

  function session(key, value) {
    try {
      if (value === undefined) return sessionStorage.getItem(key);
      sessionStorage.setItem(key, value);
    } catch (e) { return null; }
  }

  // ---- Rate card: rows of [startGrams, endGrams, price] for this destination
  function loadRates() {
    if (!cfg.rateSheet || !window.fetch) return;
    fetch(cfg.rateSheet, { credentials: 'same-origin' })
      .then(function(r){ if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function(text){
        var lines = text.trim().split(/\r?\n/);
        var want = String(cfg.destination || '').trim().toLowerCase();
        var col = -1;
        lines[0].split(',').forEach(function(h, i){
          var m = h.trim().match(/^(.*?)\s*\(([A-Z]{2})\)$/);
          if (i > 2 && m && (m[1].toLowerCase() === want || m[2].toLowerCase() === want)) col = i;
        });
        if (col < 0) return;
        var rows = [];
        for (var i = 1; i < lines.length; i++) {
          var c = lines[i].split(',');
          var start = parseFloat(c[1]), end = parseFloat(c[2]), price = parseFloat(c[col]);
          if (isFinite(start) && isFinite(end) && price > 0) rows.push([start, end, price]);
        }
        if (!rows.length) return;
        rates = rows;
        wizards.forEach(function(w){ w.refresh(); });

        // "Rates from ₹X" hooks: the lightest slab is the starting price.
        var from = Math.min.apply(null, rows.map(function(r){ return r[2]; })).toLocaleString('en-IN');
        document.querySelectorAll('[data-sg-from]').forEach(function(el){
          el.querySelector('[data-sg-from-price]').textContent = from;
          el.hidden = false;
        });
      })
      .catch(function(){});
  }

  function priceFor(kg) {
    if (!rates || !(kg > 0)) return null;
    var g = Math.round(kg * 1000);
    for (var i = 0; i < rates.length; i++) {
      if (g >= rates[i][0] && g <= rates[i][1]) return rates[i][2];
    }
    return null;
  }

  // Exact weight -> Zoho CRM Packet_Weight picklist value
  function weightBand(kg) {
    var g = kg * 1000;
    if (g <= 100) return '50-100 gms';
    if (g <= 250) return '100-250 gms';
    if (g <= 500) return '250-500 gms';
    if (g <= 1000) return '500-1000 gms';
    if (g <= 2000) return '1-2 Kg';
    if (g <= 5000) return '2-5 Kg';
    if (g <= 10000) return '5-10 Kg';
    return '10Kg+';
  }

  function formatWeight(kg) {
    return kg < 1
      ? Math.round(kg * 1000) + ' g'
      : (Math.round(kg * 100) / 100).toLocaleString('en-IN') + ' kg';
  }

  function digits(v) {
    v = v.replace(/\D/g, '');
    if (v.length > 10) v = v.replace(/^(91|0)/, '');
    return v.slice(0, 10);
  }

  function Wizard(root) {
    var id = root.getAttribute('data-qw');
    var form = root.querySelector('.qw-form');
    var steps = root.querySelectorAll('.qw-step');
    var weightInput = root.querySelector('[data-qw-weight]');
    var nameInput = root.querySelector('.qw-field[data-qw-check="name"] input');
    var phoneInput = form.elements.Phone;
    var submitBtn = root.querySelector('[data-qw-submit]');
    var submitLabel = root.querySelector('[data-qw-submit-label]');
    var hint = root.querySelector('[data-qw-hint]');
    var current = 1;
    var started = false;

    function kg() { return parseFloat(weightInput.value); }
    function item() { var r = form.querySelector('[name=Product_Type]:checked'); return r ? r : null; }

    function valid(check) {
      switch (check) {
        case 'item':   return !!item();
        case 'weight': var w = kg(); return w >= 0.05 && w <= 100;
        case 'name':   return nameInput.value.trim().length >= 2;
        case 'phone':  return /^[6-9]\d{9}$/.test(phoneInput.value);
      }
      return true;
    }

    // Mark invalid groups in a step and focus the first one.
    function validate(step) {
      var first = null;
      step.querySelectorAll('[data-qw-check]').forEach(function(group){
        var ok = valid(group.getAttribute('data-qw-check'));
        group.classList.toggle('qw-bad', !ok);
        if (!ok && !first) first = group;
      });
      if (first) first.querySelector('input').focus();
      return !first;
    }

    function go(n, focus, silent) {
      steps.forEach(function(s, i){ s.hidden = i !== n - 1; });
      var step = steps[n - 1];
      step.classList.remove('qw-in');
      void step.offsetWidth;
      step.classList.add('qw-in');
      current = n;
      root.setAttribute('data-step', n);
      root.querySelector('[data-qw-num]').textContent = n;
      root.querySelector('.qw-progress').setAttribute('aria-valuenow', n);
      if (n === 3) {
        root.querySelector('[data-qw-summary]').innerHTML =
          '<b>' + item().parentNode.textContent.trim() + '</b> · <b>' + formatWeight(kg()) + '</b> · ' +
          cfg.origin + ' → ' + cfg.destination;
      }
      if (focus) {
        var target = step.querySelector(n === 1 ? 'input:checked, input' : 'input:not([type=hidden])');
        if (target) target.focus({ preventScroll: true });
      }
      if (!silent) dataLayer.push({ event: 'quote_step', form_id: id, step: n });
    }

    function next() {
      if (!validate(steps[current - 1])) return;
      if (current < 3) go(current + 1, true);
    }

    function refresh() {
      var w = kg();
      var price = priceFor(w);
      root.querySelectorAll('[data-qw-w]').forEach(function(chip){
        chip.setAttribute('aria-pressed', parseFloat(chip.getAttribute('data-qw-w')) === w);
      });
      var text = 'Not sure? A rough estimate is fine.';
      if (w >= 0.05 && w <= 100) {
        text = price
          ? 'Instant price ready for ' + formatWeight(w) + ' to ' + cfg.destination + '.'
          : 'We ship ' + formatWeight(w) + ' parcels to ' + cfg.destination + '. Our expert will confirm your price.';
      }
      hint.classList.toggle('is-ok', !!price);
      hint.querySelector('i').className = price ? 'fas fa-circle-check' : 'fas fa-circle-info';
      hint.querySelector('span').textContent = text;
      submitLabel.textContent = price ? 'See my price' : 'Get my price';
    }

    function setWeight(v) {
      v = Math.min(100, Math.max(0.05, Math.round(v * 100) / 100));
      weightInput.value = v;
      weightInput.closest('.qw-group').classList.remove('qw-bad');
      refresh();
    }

    function stepSize(v) { return v < 1 ? 0.1 : v < 10 ? 0.5 : 1; }

    function start() {
      if (started) return;
      started = true;
      window.SG_QUOTE_STARTED = true;
      dataLayer.push({ event: 'quote_start', form_id: id });
    }

    function done(name, price) {
      var w = kg();
      root.querySelector('[data-qw-name]').textContent = name.split(' ')[0];
      var box = root.querySelector('[data-qw-price]');
      box.hidden = !price;
      if (price) {
        root.querySelector('[data-qw-price-weight]').textContent = formatWeight(w);
        root.querySelector('[data-qw-price-value]').textContent = price.toLocaleString('en-IN');
      }
      var phone = phoneInput.value.replace(/(\d{5})(\d{5})/, '$1 $2');
      root.querySelector('[data-qw-done-msg]').textContent = price
        ? 'Our export expert will call you on ' + phone + ' shortly to confirm your price and help you book a pickup.'
        : 'Our export expert will call you on ' + phone + ' shortly with the best ' + cfg.origin + ' to ' + cfg.destination + ' price.';
      root.classList.remove('is-failed');
      root.classList.add('is-done');
      root.querySelector('[data-qw-done-title]').focus({ preventScroll: true });
      session('sg_lead', '1');
    }

    root.addEventListener('pointerdown', start);
    root.addEventListener('focusin', start);

    root.addEventListener('input', function(e){
      if (e.target === phoneInput) phoneInput.value = digits(phoneInput.value);
      if (e.target === weightInput) refresh();
      var group = e.target.closest('[data-qw-check]');
      if (group && group.classList.contains('qw-bad') && valid(group.getAttribute('data-qw-check'))) {
        group.classList.remove('qw-bad');
      }
    });

    // Tapping an item tile moves straight on; keyboard users use Continue.
    form.querySelectorAll('[name=Product_Type]').forEach(function(radio){
      radio.addEventListener('click', function(e){
        if (e.detail > 0) setTimeout(function(){ if (current === 1) go(2, false); }, 220);
      });
    });

    root.querySelectorAll('[data-qw-next]').forEach(function(b){ b.addEventListener('click', next); });
    root.querySelector('[data-qw-back]').addEventListener('click', function(){ if (current > 1) go(current - 1, true); });
    root.querySelector('[data-qw-edit]').addEventListener('click', function(){ go(1, true); });
    root.querySelector('[data-qw-inc]').addEventListener('click', function(){ var w = kg() || 0; setWeight(w + stepSize(w)); });
    root.querySelector('[data-qw-dec]').addEventListener('click', function(){ var w = kg() || 0; setWeight(w - stepSize(w - 0.001)); });
    root.querySelectorAll('[data-qw-w]').forEach(function(chip){
      chip.addEventListener('click', function(){ setWeight(parseFloat(chip.getAttribute('data-qw-w'))); });
    });

    form.addEventListener('submit', function(e){
      e.preventDefault();
      if (current < 3) { next(); return; }
      if (!validate(steps[2])) return;

      var name = nameInput.value.trim().replace(/\s+/g, ' ');
      var cut = name.lastIndexOf(' ');
      form.elements.First_Name.value = cut > 0 ? name.slice(0, cut) : '';
      form.elements.Last_Name.value = cut > 0 ? name.slice(cut + 1) : name;
      form.elements.Packet_Weight.value = weightBand(kg());
      var price = priceFor(kg());

      // Honeypot filled: a bot. Show success, send nothing.
      if (root.querySelector('.qw-hp').value) { done(name, price); return; }

      var data = new FormData(form);
      var label = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="qw-spin" aria-hidden="true"></span> ' + (price ? 'Calculating your price…' : 'Sending…');

      var sent = window.SG_PREVIEW
        ? new Promise(function(resolve){ setTimeout(resolve, 700); })
        : fetch(form.action, { method: 'POST', body: data, mode: 'no-cors' });

      sent.then(function(){
        dataLayer.push({
          event: 'generate_lead',
          form_id: id,
          product_type: data.get('Product_Type'),
          packet_weight: data.get('Packet_Weight'),
          weight_kg: kg(),
          price_shown: !!price
        });
        done(name, price);
      }).catch(function(){
        submitBtn.disabled = false;
        submitBtn.innerHTML = label;
        root.classList.add('is-failed');
      });
    });

    refresh();
    return {
      root: root,
      refresh: refresh,
      title: root.querySelector('.qw-title'),
      state: function(){
        var it = item();
        return {
          started: started,
          done: root.classList.contains('is-done'),
          item: it ? it.value : '',
          kg: weightInput.value,
          name: nameInput.value,
          phone: phoneInput.value,
          step: current
        };
      },
      // Continue a quote started in another wizard (hero -> modal).
      load: function(s){
        form.querySelectorAll('[name=Product_Type]').forEach(function(r){ r.checked = r.value === s.item; });
        weightInput.value = s.kg;
        nameInput.value = s.name;
        phoneInput.value = s.phone;
        refresh();
        go(s.item ? s.step : 1, false, true);
      }
    };
  }

  document.querySelectorAll('.qw').forEach(function(el){ wizards.push(Wizard(el)); });
  loadRates();

  // ---- Modal: every "Get my price" button below the hero opens the quote in a dialog
  var modal = document.getElementById('sg-quote-modal');
  var hero = document.querySelector('.sg-hero-form .qw');
  var modalWizard = wizards.filter(function(w){ return w.root.closest('dialog'); })[0];
  var heroWizard = wizards.filter(function(w){ return w.root === hero; })[0];
  var modalTitle = modalWizard ? modalWizard.title.innerHTML : '';
  var heroVisible = true;

  function showHero() {
    hero.scrollIntoView({ behavior: 'smooth', block: 'center' });
    hero.classList.remove('qw-pulse');
    void hero.offsetWidth;
    hero.classList.add('qw-pulse');
  }

  // While the hero quote is on screen, point to it; otherwise open the dialog,
  // carrying over anything already entered in the hero.
  function openQuote(source, title) {
    dataLayer.push({ event: 'quote_open', source: source });
    var canModal = modal && modalWizard && typeof modal.showModal === 'function';
    if (hero && (!canModal || (heroVisible && source !== 'exit_intent'))) {
      showHero();
      return;
    }
    if (!canModal) return;
    if (heroWizard) {
      var hs = heroWizard.state(), ms = modalWizard.state();
      if (hs.started && !hs.done && !ms.started && !ms.done) modalWizard.load(hs);
    }
    modalWizard.title.innerHTML = title || modalTitle;
    if (!modal.open) {
      modal.showModal();
      document.body.classList.add('sg-modal-open');
    }
  }

  if (modal) {
    modal.addEventListener('close', function(){ document.body.classList.remove('sg-modal-open'); });
    modal.querySelector('[data-modal-close]').addEventListener('click', function(){ modal.close(); });
    modal.addEventListener('click', function(e){ if (e.target === modal) modal.close(); });
  }

  document.querySelectorAll('[data-quote-open]').forEach(function(b){
    b.addEventListener('click', function(){ openQuote(b.getAttribute('data-quote-open')); });
  });

  // Announcement bar and bulk-order icon link to #form.
  document.querySelectorAll('a[href="#form"]').forEach(function(a){
    a.addEventListener('click', function(e){
      e.preventDefault();
      openQuote('link');
    });
  });

  // ---- Sticky mobile CTA: hidden while the hero quote is on screen
  var sticky = document.getElementById('sg-sticky');
  var ticking = false;
  function updateSticky() {
    ticking = false;
    if (!hero) return;
    var r = hero.getBoundingClientRect();
    heroVisible = r.bottom > 80 && r.top < window.innerHeight - 80;
    if (sticky) sticky.classList.toggle('is-hidden', heroVisible);
  }
  window.addEventListener('scroll', function(){
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(updateSticky);
  }, { passive: true });
  window.addEventListener('resize', updateSticky);
  updateSticky();

  // ---- Exit intent (desktop): offer the quote once per session
  if (window.matchMedia && window.matchMedia('(pointer: fine) and (min-width: 901px)').matches) {
    var armed = false;
    setTimeout(function(){ armed = true; }, 8000);
    document.addEventListener('mouseout', function(e){
      if (!armed || e.relatedTarget || e.clientY > 0) return;
      if (window.SG_QUOTE_STARTED || session('sg_lead') || session('sg_exit')) return;
      session('sg_exit', '1');
      openQuote('exit_intent', 'Before you go: get your <span>' + cfg.destination + '</span> price in 30 seconds');
    });
  }

  // ---- Reviews carousel buttons
  var track = document.getElementById('sg-reviews');
  document.querySelectorAll('[data-reviews]').forEach(function(b){
    b.addEventListener('click', function(){
      if (!track || !track.firstElementChild) return;
      var step = track.firstElementChild.getBoundingClientRect().width + 20;
      track.scrollBy({ left: step * parseInt(b.getAttribute('data-reviews'), 10), behavior: 'smooth' });
    });
  });
})();
</script>


<?php if (trim($faqScript) !== ''): ?>
<?php echo $faqScript; ?>
<?php else: ?>
<script type="application/ld+json">
{
"@context": "https://schema.org",
"@type": "FAQPage",
"mainEntity": [{
"@type": "Question",
"name": "How to send courier from <?php echo $origin; ?> to <?php echo $destination; ?>?",
"acceptedAnswer": {
"@type": "Answer",
"text": "You can send courier from <?php echo $origin; ?> to <?php echo $destination; ?> using ShipGlobal with affordable pricing and fast international delivery."
}
}]
}
</script>
<?php endif; ?>
</body>
</html>