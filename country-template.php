<?php

// One country: /services/{country}/. The page file sets $Destination and
// includes this; the cities listed are the {country}/{city}/index.php pages.

$destination = $destination ?? ($Destination ?? "");

require_once __DIR__ . '/includes/site.php';
require_once __DIR__ . '/includes/directory.php';

$countrySlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($destination)) ?? '', '-');
$destinations = sg_destinations(__DIR__);
if (!isset($destinations[$countrySlug])) {
  http_response_code(404);
  exit;
}
$country = $destinations[$countrySlug];
$cities = $country['cities'];
$cityCount = count($cities);

// URL path of the /services folder: "/services" on production, "" on dev.
$sgBase = sg_base_path(1);
$leadEndpoint = $sgBase . '/lead.php';
$countryUrl = $sgBase . '/' . $countrySlug . '/';
$canonical = 'https://shipglobal.in/services/' . $countrySlug . '/';
$d = sg_h($destination);

$title = "Courier to $destination from India | ShipGlobal";
$meta_description = "Send a courier from India to $destination with doorstep pickup in $cityCount+ cities, customs support and tracking. Pick your city or get a free quote in 30 seconds.";

$faqItems = [
  ['q' => "How long does a courier from India to $destination take?", 'a' => "It depends on the service you choose (express or economy) and the delivery address in $destination. Our export expert confirms the delivery timeline along with your quote."],
  ['q' => "Which cities can I ship to $destination from?", 'a' => "We pick up from your doorstep anywhere in India. This page lists $cityCount cities with a dedicated $destination shipping guide, and we cover the rest of India too."],
  ['q' => "Can I send commercial shipments like textiles or jewellery to $destination?", 'a' => "Yes. You can ship products like textiles, handicrafts or jewellery to $destination with the right export documents, such as an invoice, packing list and KYC proofs. Our export experts tell you exactly what you need."],
  ['q' => "What can't be shipped to $destination?", 'a' => "Items in the restricted category, such as batteries, sharp objects, hazardous materials or anything prohibited by the law of $destination, can't be shipped. Check with our experts before booking if you're unsure."],
  ['q' => "Will the receiver pay customs duty in $destination?", 'a' => "They may have to, depending on the value and category of the goods. We help you prepare the paperwork so your parcel clears $destination customs without delays."],
  ['q' => "How do I track my courier to $destination?", 'a' => "You get a tracking ID once your shipment is dispatched, so you can follow it all the way to $destination on our <a href=\"https://shipglobal.in/tracking/\">tracking page</a>."],
  ['q' => "How much does it cost to ship to $destination?", 'a' => "The price depends on the weight, the item and the service. Tell us what you're sending in the quote form above, and our representative will contact you with your price."],
];

$quoteCtx['cities'] = array_values($cities);
$otherCountries = array_diff_key($destinations, [$countrySlug => true]);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo sg_h($title); ?></title>
<meta name="description" content="<?php echo sg_h($meta_description); ?>">
<link rel="canonical" href="<?php echo $canonical; ?>">
<?php sg_render_head_links(); ?>
<?php sg_render_breadcrumb_schema([['Home', 'https://shipglobal.in/'], ['Services', 'https://shipglobal.in/services/'], [$destination, $canonical]]); ?>
<?php sg_render_faq_schema($faqItems); ?>

<?php sg_render_head_scripts(); ?>

<?php sg_render_styles(); ?>
<?php sg_render_directory_styles(); ?>
</head>

<body>
<?php sg_render_site_header(); ?>

<main>

<!-- HERO -->
<section class="sg-hero">
  <div class="sg-hero-grid-bg" aria-hidden="true"></div>
  <div class="sg-wrap sg-hero-grid">
    <div class="sg-hero-head">
<?php sg_render_crumbs([['Services', $sgBase . '/'], [$destination, '']]); ?>
      <p class="sg-eyebrow"><i class="fas fa-plane-departure"></i> International courier to <?php echo $d; ?></p>
      <h1>India to <?php echo $d; ?> <span>Courier Shipment</span></h1>
      <p class="sg-hero-lead">Doorstep pickup from <?php echo $cityCount; ?>+ cities across India, export paperwork support and tracked delivery to <?php echo $d; ?>. Ship packets from just 50 g, no bulk needed.</p>
      <ul class="sg-hero-chips">
        <li>Popular routes:</li>
<?php foreach (sg_popular_cities($cities, 5) as $slug => $name): ?>
        <li><a href="<?php echo sg_h($countryUrl . $slug . '/'); ?>"><i class="fas fa-location-dot"></i><?php echo sg_h($name); ?></a></li>
<?php endforeach; ?>
      </ul>
    </div>

    <div class="sg-hero-form" id="form">
      <?php render_quote_wizard('hero', '', $destination, $quoteCtx); ?>
    </div>

<?php sg_render_hero_proof('India', 'Pickup anywhere', $d, 'Destination'); ?>
  </div>
</section>

<?php sg_render_marketplaces(); ?>

<!-- CITIES -->
<section class="sg-section" id="cities">
  <div class="sg-wrap">
    <div class="sg-section-head">
      <p class="sg-kicker">Choose your pickup city</p>
      <h2 class="sg-h2">Ship to <?php echo $d; ?> from <?php echo $cityCount; ?> cities</h2>
      <p>Pick your city for a step-by-step <?php echo $d; ?> shipping guide, or get a quote straight away. We pick up from your doorstep.</p>
    </div>
    <div class="sg-search">
      <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
      <input type="search" placeholder="Search your city, e.g. Jaipur" aria-label="Search your city" autocomplete="off" data-city-search>
    </div>
    <p class="sg-search-count" aria-live="polite" data-city-count><?php echo $cityCount; ?> cities</p>
    <ul class="sg-city-grid" data-city-grid>
<?php foreach ($cities as $slug => $name): ?>
      <li><a class="sg-city" href="<?php echo sg_h($countryUrl . $slug . '/'); ?>"><i class="fas fa-location-dot"></i><span><b><?php echo sg_h($name); ?></b><small>to <?php echo $d; ?></small></span><i class="fas fa-arrow-right"></i></a></li>
<?php endforeach; ?>
    </ul>
    <div class="sg-city-empty" data-city-empty hidden>
      <p>No guide for “<b data-city-query></b>” yet, but we still pick up from there.</p>
      <button type="button" class="sg-btn sg-btn-primary" data-quote-open="city_search">Get my price <i class="fas fa-arrow-right"></i></button>
    </div>
    <p class="sg-city-more">Don't see your city? We pick up across India. <button type="button" data-quote-open="city_more">Get a quote for <?php echo $d; ?></button></p>
  </div>
</section>

<!-- WHAT ARE YOU SENDING -->
<section class="sg-section sg-section-alt">
  <div class="sg-wrap">
    <div class="sg-section-head">
      <p class="sg-kicker">What can you send</p>
      <h2 class="sg-h2">What are you sending to <?php echo $d; ?>?</h2>
      <p>From gifts for family to stock for your online store. Pick one to start your quote.</p>
    </div>
    <div class="sg-items">
<?php foreach ($quoteItems as $value => [$label, $icon]): ?>
      <button type="button" class="sg-item" data-quote-open="item_grid" data-quote-item="<?php echo sg_h($value); ?>"><i class="fas <?php echo $icon; ?>"></i><?php echo $label; ?><small>Get price</small></button>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php sg_render_features('India', $d); ?>

<?php sg_render_steps('India', $d, $signupUrl); ?>

<?php sg_render_reviews(); ?>

<?php sg_render_faq("Questions about shipping to $d", $faqItems, '', $quoteCtx); ?>

<?php if ($otherCountries): ?>
<!-- OTHER DESTINATIONS -->
<section class="sg-more">
  <div class="sg-wrap">
    <h2>More shipping destinations</h2>
    <ul class="sg-dests">
<?php foreach ($otherCountries as $other): ?>
      <li><a href="<?php echo sg_h($sgBase . '/' . $other['slug'] . '/'); ?>"><?php echo sg_flag($other['name']); ?><span><b><?php echo sg_h($other['name']); ?></b><small><?php echo count($other['cities']); ?> pickup cities</small></span></a></li>
<?php endforeach; ?>
      <li><a href="<?php echo sg_h($sgBase . '/'); ?>"><i class="fas fa-earth-asia sg-flag" aria-hidden="true"></i><span><b>All destinations</b><small>220+ countries</small></span></a></li>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php sg_render_press(); ?>

<?php sg_render_final_cta('India', $d, $phoneTel); ?>

</main>

<?php sg_render_site_footer(); ?>

<?php sg_render_quote_overlays('', $destination, $quoteCtx); ?>

<?php sg_render_quote_script([
  'origin' => '',
  'destination' => $destination,
  'leadEndpoint' => $leadEndpoint,
  'promptAfter' => (int) $quotePromptDelay,
]); ?>
<?php sg_render_city_search_script(); ?>
</body>
</html>
