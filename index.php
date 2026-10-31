<?php

// Every destination: /services/. Lists the countries that have
// {country}/{city}/index.php pages, with a route finder and the quote form.

require_once __DIR__ . '/includes/site.php';
require_once __DIR__ . '/includes/directory.php';

$destinations = sg_destinations(__DIR__);

// URL path of this folder: "/services" on production, "" on dev.
$sgBase = sg_base_path(0);
$leadEndpoint = $sgBase . '/lead.php';

// Route finder (works without JavaScript): ?to=australia&from=agra goes to
// that page, or to the country page when there's no page for the city.
$to = $_GET['to'] ?? '';
if (is_string($to) && isset($destinations[$to])) {
  $from = $_GET['from'] ?? '';
  $path = is_string($from) && isset($destinations[$to]['cities'][$from]) ? "$to/$from/" : "$to/";
  header('Location: ' . $sgBase . '/' . $path, true, 302);
  exit;
}

$allCities = [];
foreach ($destinations as $country) {
  $allCities += $country['cities'];
}
asort($allCities, SORT_NATURAL | SORT_FLAG_CASE);
$cityCount = count($allCities);

$title = 'International Courier Services from India | ShipGlobal';
$meta_description = 'Ship from India to 220+ countries with doorstep pickup, customs support and tracking. Choose your destination and city, or get a free quote in 30 seconds.';
$canonical = 'https://shipglobal.in/services/';

$faqItems = [
  ['q' => 'Which countries can I ship to from India?', 'a' => 'ShipGlobal delivers to 220+ countries, including the United States, United Kingdom, Canada, Australia, the UAE and across Europe. Pick a destination above for city-by-city shipping guides, or get a quote for any country.'],
  ['q' => 'Do you pick up from my city?', 'a' => 'Yes. We offer doorstep pickup across India, so you never have to visit a courier office.'],
  ['q' => 'What is the minimum weight I can ship?', 'a' => "You can ship single packets from just 50 g. There's no minimum order and no need to wait for bulk volumes."],
  ['q' => 'How long does international courier from India take?', 'a' => 'It depends on the destination and the service you choose (express or economy). Our export expert confirms the delivery timeline along with your quote.'],
  ['q' => 'What documents do I need to ship internationally?', 'a' => 'Usually an invoice, a packing list and KYC proofs. Our export experts walk you through exactly what your shipment needs, so it clears customs smoothly.'],
  ['q' => "What can't be shipped internationally?", 'a' => "Items in the restricted category, such as batteries, sharp objects, hazardous materials or anything prohibited by the destination country's law. Check with our experts before booking if you're unsure."],
  ['q' => 'How do I track my shipment?', 'a' => 'You get a tracking ID once your shipment is dispatched and can follow it on our <a href="https://shipglobal.in/tracking/">tracking page</a>.'],
  ['q' => 'How much does international shipping cost?', 'a' => "The price depends on the weight, the item, the destination and the service. Tell us what you're sending in the quote form above, and our representative will contact you with your price."],
];

$quoteCtx['destinations'] = sg_quote_destinations($destinations);
$quoteCtx['countries'] = array_keys(sg_country_codes());
$quoteCtx['cities'] = array_values(array_unique($allCities));

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
<?php sg_render_breadcrumb_schema([['Home', 'https://shipglobal.in/'], ['Services', $canonical]]); ?>
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
<?php sg_render_crumbs([['Home', 'https://shipglobal.in/'], ['Services', '']]); ?>
      <p class="sg-eyebrow"><i class="fas fa-plane-departure"></i> International courier services from India</p>
      <h1>Ship from India <span>to 220+ countries</span></h1>
      <p class="sg-hero-lead">Doorstep pickup across India, export paperwork support and tracked delivery worldwide. Ship packets from just 50 g, no bulk needed.</p>
<?php if ($destinations): ?>
      <ul class="sg-hero-chips">
        <li>Shipping guides:</li>
<?php foreach ($destinations as $country): ?>
        <li><a href="<?php echo sg_h($sgBase . '/' . $country['slug'] . '/'); ?>"><?php echo sg_flag($country['name']); ?><?php echo sg_h($country['name']); ?></a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </div>

    <div class="sg-hero-form" id="form">
      <?php render_quote_wizard('hero', '', '', $quoteCtx); ?>
    </div>

<?php sg_render_hero_proof('India', 'Pickup anywhere', '220+ countries', 'Worldwide'); ?>
  </div>
</section>

<?php sg_render_marketplaces(); ?>

<!-- DESTINATIONS -->
<section class="sg-section sg-section-alt" id="destinations">
  <div class="sg-wrap">
    <div class="sg-section-head">
      <p class="sg-kicker">Where are you shipping?</p>
      <h2 class="sg-h2">Pick your route</h2>
      <p>City-by-city shipping guides for <?php echo count($destinations); ?> destinations and <?php echo $cityCount; ?> pickup cities. Shipping somewhere else? We deliver to 220+ countries.</p>
    </div>

<?php if ($destinations): ?>
    <form class="sg-finder" method="get" action="<?php echo sg_h($sgBase . '/'); ?>">
      <label>Shipping from
        <select name="from" required>
          <option value="">Select your city</option>
<?php foreach ($allCities as $slug => $name): ?>
          <option value="<?php echo sg_h($slug); ?>"><?php echo sg_h($name); ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <span class="sg-finder-plane" aria-hidden="true"><i class="fas fa-plane"></i></span>
      <label>Shipping to
        <select name="to" required>
          <option value="">Select destination</option>
<?php foreach ($destinations as $country): ?>
          <option value="<?php echo sg_h($country['slug']); ?>"><?php echo sg_h($country['name']); ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="sg-btn sg-btn-primary">Show my route <i class="fas fa-arrow-right"></i></button>
    </form>
<?php endif; ?>

    <div class="sg-countries">
<?php foreach ($destinations as $country): ?>
<?php $url = $sgBase . '/' . $country['slug'] . '/'; ?>
      <article class="sg-country">
        <div class="sg-country-head">
          <?php echo sg_flag($country['name']); ?>
          <div>
            <h3><a href="<?php echo sg_h($url); ?>"><?php echo sg_h($country['name']); ?></a></h3>
            <p class="sg-country-meta"><?php echo count($country['cities']); ?> pickup cities · Doorstep pickup</p>
          </div>
        </div>
        <div class="sg-country-body">
          <p class="sg-country-label">Popular routes</p>
          <ul class="sg-chips">
<?php foreach (sg_popular_cities($country['cities'], 6) as $slug => $name): ?>
            <li><a href="<?php echo sg_h($url . $slug . '/'); ?>"><?php echo sg_h($name); ?></a></li>
<?php endforeach; ?>
          </ul>
        </div>
        <div class="sg-country-actions">
          <a class="sg-btn sg-btn-outline" href="<?php echo sg_h($url); ?>">All cities</a>
          <button type="button" class="sg-btn sg-btn-primary" data-quote-open="country_card" data-quote-dest="<?php echo sg_h($country['name']); ?>">Get price</button>
        </div>
      </article>
<?php endforeach; ?>
      <article class="sg-country sg-country-any">
        <span class="sg-aside-icon"><i class="fas fa-earth-asia"></i></span>
        <h3>Any other country</h3>
        <p>We deliver to 220+ countries. Tell us where and what, and get your price in 30 seconds.</p>
        <button type="button" class="sg-btn sg-btn-primary" data-quote-open="country_any">Get my price <i class="fas fa-arrow-right"></i></button>
      </article>
    </div>
  </div>
</section>

<?php sg_render_features('India', ''); ?>

<?php sg_render_steps('India', '', $signupUrl); ?>

<?php sg_render_reviews(); ?>

<?php sg_render_faq('Questions about international courier from India', $faqItems, '', $quoteCtx); ?>

<?php sg_render_press(); ?>

<?php sg_render_final_cta('India', '', $phoneTel); ?>

</main>

<?php sg_render_site_footer(); ?>

<?php sg_render_quote_overlays('', '', $quoteCtx); ?>

<?php sg_render_quote_script([
  'origin' => '',
  'destination' => '',
  'leadEndpoint' => $leadEndpoint,
  'promptAfter' => (int) $quotePromptDelay,
]); ?>
</body>
</html>
