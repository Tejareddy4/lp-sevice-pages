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
  // Only Croatia has images so far; skip the og:image rather than point at a 404.
  if (is_file(__DIR__ . '/images/' . $destination . '/' . $page_slug . '.webp')) {
    $featuredImage = '/services/images/' . rawurlencode($destination) . '/' . rawurlencode($page_slug) . '.webp';
  }
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

require_once __DIR__ . '/includes/site.php';

// URL path of this folder (template.php, lead.php): "/services" on production,
// "" on dev. Pages live two levels down, at {country}/{city}/index.php.
$sgBase = sg_base_path(2);
// Same-origin relay that forwards leads to $leadWebhook (see lead.php).
$leadEndpoint = $sgBase . '/lead.php';

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
<?php sg_render_head_links(); ?>

<?php if (trim($reviewSchema) !== ''): ?>
<?php echo $reviewSchema; ?>
<?php endif; ?>

<?php sg_render_head_scripts(); ?>

<?php sg_render_styles(); ?>
</head>

<body>
<?php sg_render_site_header(); ?>

<main>

<!-- HERO -->
<section class="sg-hero">
  <div class="sg-hero-grid-bg" aria-hidden="true"></div>
  <div class="sg-wrap sg-hero-grid">
    <div class="sg-hero-head">
      <p class="sg-eyebrow"><i class="fas fa-plane-departure"></i> International courier from <?php echo $origin; ?></p>
      <h1><?php echo $origin; ?> to <?php echo $destination; ?> <span>Courier Shipment</span></h1>
      <p class="sg-hero-lead">Doorstep pickup in <?php echo $origin; ?>, export paperwork support and tracked delivery to <?php echo $destination; ?>. Ship packets from just 50 g, no bulk needed.</p>
    </div>

    <div class="sg-hero-form" id="form">
      <?php render_quote_wizard('hero', $origin, $destination, $quoteCtx); ?>
    </div>

<?php sg_render_hero_proof($origin, 'India', $destination, 'Destination'); ?>
  </div>
</section>

<?php sg_render_marketplaces(); ?>

<?php sg_render_features($origin, $destination); ?>

<?php sg_render_steps($origin, $destination, $signupUrl); ?>

<?php sg_render_reviews(); ?>

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
        <button type="button" class="sg-btn sg-btn-primary" data-quote-open="guide">Get my price <i class="fas fa-arrow-right"></i></button>
        <a class="sg-aside-call" href="tel:<?php echo $phoneTel; ?>"><i class="fas fa-phone"></i> or call <?php echo $phoneText; ?></a>
      </div>
    </aside>
  </div>
</section>

<?php sg_render_faq($faqHeading, $faqItems, $FAQ, $quoteCtx); ?>

<!-- RELATED ROUTES -->
<?php if (trim($Relposts) !== ''): ?>
<section class="sg-related">
  <div class="sg-wrap">
    <?php echo $Relposts; ?>
  </div>
</section>
<?php endif; ?>

<?php sg_render_press(); ?>

<?php sg_render_final_cta($origin, $destination, $phoneTel); ?>

</main>

<?php sg_render_site_footer(); ?>

<?php sg_render_quote_overlays($origin, $destination, $quoteCtx); ?>

<?php sg_render_quote_script([
  'origin' => $origin,
  'destination' => $destination,
  'leadEndpoint' => $leadEndpoint,
  'promptAfter' => (int) $quotePromptDelay,
]); ?>


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