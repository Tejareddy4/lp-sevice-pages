<?php

// The destination directory shown by index.php (every destination) and
// country-template.php (one country): which pages exist, flags, schema and the
// styles those pages add to site.php. Only defines functions, so requesting it
// directly prints nothing.

// Every {country}/{city}/index.php under $root, as
// [country slug => ['slug' => ..., 'name' => ..., 'cities' => [city slug => name]]],
// countries and cities sorted by name. Names come from each page's $Destination
// and $Origin, so new pages show up here without any other change.
function sg_destinations(string $root): array
{
  $countries = [];
  foreach (glob($root . '/*/*/index.php') ?: [] as $file) {
    $citySlug = basename(dirname($file));
    $countrySlug = basename(dirname($file, 2));
    if (!preg_match('/^[a-z0-9-]+$/', $countrySlug) || !preg_match('/^[a-z0-9-]+$/', $citySlug)) {
      continue;
    }
    // The variables sit at the top of each page.
    $head = (string) file_get_contents($file, false, null, 0, 600);
    if (!isset($countries[$countrySlug])) {
      $countries[$countrySlug] = [
        'slug' => $countrySlug,
        'name' => sg_page_var($head, 'Destination') ?: ucwords(str_replace('-', ' ', $countrySlug)),
        'cities' => [],
      ];
    }
    $countries[$countrySlug]['cities'][$citySlug] = sg_page_var($head, 'Origin') ?: ucwords(str_replace('-', ' ', $citySlug));
  }

  foreach ($countries as &$country) {
    asort($country['cities'], SORT_NATURAL | SORT_FLAG_CASE);
  }
  unset($country);
  uasort($countries, static function (array $a, array $b): int {
    return strnatcasecmp($a['name'], $b['name']);
  });
  return $countries;
}

// $Name = "Value"; from the top of a page file.
function sg_page_var(string $php, string $name): string
{
  return preg_match('/\$' . $name . '\s*=\s*(["\'])(.*?)\1/', $php, $m) ? trim($m[2]) : '';
}

// Country names offered when the customer picks "Other country", with flags.
function sg_country_codes(): array
{
  return [
    'United States' => 'us', 'United Kingdom' => 'gb', 'Canada' => 'ca', 'Australia' => 'au',
    'UAE' => 'ae', 'Germany' => 'de', 'France' => 'fr', 'Italy' => 'it', 'Netherlands' => 'nl',
    'Spain' => 'es', 'Portugal' => 'pt', 'Belgium' => 'be', 'Austria' => 'at', 'Switzerland' => 'ch',
    'Ireland' => 'ie', 'Sweden' => 'se', 'Norway' => 'no', 'Denmark' => 'dk', 'Finland' => 'fi',
    'Poland' => 'pl', 'Czech Republic' => 'cz', 'Croatia' => 'hr', 'Bulgaria' => 'bg',
    'Romania' => 'ro', 'Greece' => 'gr', 'Hungary' => 'hu', 'Singapore' => 'sg', 'Malaysia' => 'my',
    'Hong Kong' => 'hk', 'Japan' => 'jp', 'South Korea' => 'kr', 'New Zealand' => 'nz',
    'Saudi Arabia' => 'sa', 'Qatar' => 'qa', 'Kuwait' => 'kw', 'Oman' => 'om', 'Bahrain' => 'bh',
    'Israel' => 'il', 'South Africa' => 'za', 'Mauritius' => 'mu', 'Nepal' => 'np',
    'Sri Lanka' => 'lk', 'Bangladesh' => 'bd', 'Thailand' => 'th', 'Philippines' => 'ph',
    'Indonesia' => 'id', 'Vietnam' => 'vn', 'Mexico' => 'mx', 'Brazil' => 'br',
  ];
}

// Flag image for a destination name, or '' when we don't know its code.
function sg_flag_url(string $name): string
{
  $aliases = [
    'us' => 'us', 'usa' => 'us', 'united states of america' => 'us', 'uk' => 'gb',
    'great britain' => 'gb', 'united arab emirates' => 'ae', 'czechia' => 'cz', 'europe' => 'eu',
  ];
  $key = strtolower(trim($name));
  $code = $aliases[$key] ?? (array_change_key_case(sg_country_codes())[$key] ?? '');
  return $code !== '' ? 'https://flagcdn.com/' . $code . '.svg' : '';
}

// Destination tiles for the quote form on index.php: the usual favourites, then
// the countries we have pages for, 8 in all (plus "Other country").
function sg_quote_destinations(array $countries): array
{
  $names = array_merge(
    ['United States', 'United Kingdom', 'Canada', 'Australia', 'UAE', 'Germany'],
    array_column($countries, 'name')
  );
  $tiles = [];
  foreach ($names as $name) {
    $flag = sg_flag_url($name);
    $key = $flag !== '' ? $flag : strtolower($name);
    if (!isset($tiles[$key]) && count($tiles) < 8) {
      $tiles[$key] = ['name' => $name, 'flag' => $flag];
    }
  }
  return array_values($tiles);
}

// Up to $limit of $cities ([slug => name]), biggest shipping hubs first.
function sg_popular_cities(array $cities, int $limit): array
{
  $order = ['mumbai', 'new-delhi', 'bengaluru', 'chennai', 'hyderabad', 'kolkata', 'ahmedabad', 'pune', 'jaipur', 'surat', 'ludhiana', 'agra'];
  // Hubs we have pages for, in $order, then the rest in their given order.
  $popular = array_intersect_key(array_flip($order), $cities);
  return array_slice(array_replace($popular, $cities), 0, $limit, true);
}

function sg_h(string $text): string
{
  return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function sg_json_ld(array $data): void
{
  echo '<script type="application/ld+json">' . json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}

// $faqItems: [['q' => question, 'a' => answer HTML], ...]
function sg_render_faq_schema(array $faqItems): void
{
  sg_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static function (array $item): array {
      return [
        '@type' => 'Question',
        'name' => $item['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(html_entity_decode(strip_tags($item['a']), ENT_QUOTES, 'UTF-8'))],
      ];
    }, $faqItems),
  ]);
}

// $crumbs: [[name, absolute URL], ...]
function sg_render_breadcrumb_schema(array $crumbs): void
{
  $items = [];
  foreach (array_values($crumbs) as $i => [$name, $url]) {
    $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
  }
  sg_json_ld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
}

// $crumbs: [[name, URL or '' for the current page], ...]
function sg_render_crumbs(array $crumbs): void
{
  ?>
      <nav class="sg-crumbs" aria-label="Breadcrumb">
        <ol>
<?php foreach ($crumbs as [$name, $url]): ?>
          <li><?php if ($url !== ''): ?><a href="<?php echo sg_h($url); ?>"><?php echo sg_h($name); ?></a><?php else: ?><span aria-current="page"><?php echo sg_h($name); ?></span><?php endif; ?></li>
<?php endforeach; ?>
        </ol>
      </nav>
  <?php
}

// Small flag (or globe) in front of a destination name.
function sg_flag(string $name, string $class = 'sg-flag'): string
{
  $url = sg_flag_url($name);
  return $url !== ''
    ? '<img class="' . $class . '" src="' . sg_h($url) . '" alt="" width="30" height="20" loading="lazy">'
    : '<i class="fas fa-earth-asia ' . $class . '" aria-hidden="true"></i>';
}

// Filters the city grid on country pages as the customer types.
function sg_render_city_search_script(): void
{
?>
<script>
(function(){
  var input = document.querySelector('[data-city-search]');
  var grid = document.querySelector('[data-city-grid]');
  if (!input || !grid) return;
  var items = grid.querySelectorAll('li');
  var count = document.querySelector('[data-city-count]');
  var empty = document.querySelector('[data-city-empty]');
  input.addEventListener('input', function(){
    var q = input.value.trim().toLowerCase();
    var shown = 0;
    items.forEach(function(li){
      var hit = li.querySelector('b').textContent.toLowerCase().indexOf(q) !== -1;
      li.hidden = !hit;
      if (hit) shown++;
    });
    grid.hidden = !shown;
    empty.hidden = !!shown;
    empty.querySelector('[data-city-query]').textContent = input.value.trim();
    count.textContent = q ? shown + ' of ' + items.length + ' cities' : items.length + ' cities';
  });
})();
</script>
<?php
}

// Styles for index.php and country-template.php, on top of sg_render_styles().
function sg_render_directory_styles(): void
{
?>
<style>
/* ===== Quote wizard: destination step and 4-step progress ===== */
.qw[data-steps="4"] .qw-progress i{width:25%}
.qw[data-steps="4"][data-step="2"] .qw-progress i{width:50%}
.qw[data-steps="4"][data-step="3"] .qw-progress i{width:75%}
.qw[data-steps="4"][data-step="4"] .qw-progress i{width:100%}
.qw-flag{
  width:30px;
  height:20px;
  object-fit:cover;
  border-radius:4px;
  box-shadow:0 0 0 1px rgba(15,23,42,.12);
}
.qw-other[hidden]{display:none}

/* ===== Hero extras ===== */
.sg-crumbs ol{
  display:flex;
  flex-wrap:wrap;
  gap:6px;
  margin:0 0 18px;
  padding:0;
  list-style:none;
  font-size:13px;
  color:#93A5D8;
}
.sg-crumbs li + li::before{content:"\203A";margin-right:6px;color:#5B6FA8}
.sg-crumbs a{color:#C7D5FF;text-decoration:none}
.sg-crumbs a:hover{color:#fff;text-decoration:underline}
.sg-crumbs [aria-current]{color:#fff}
.sg-hero-chips{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:8px;
  margin:24px 0 0;
  padding:0;
  list-style:none;
}
.sg-hero-chips li:first-child{margin-right:4px;font-size:13px;color:#93A5D8}
.sg-hero-chips a{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:7px 14px 7px 10px;
  border-radius:999px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.16);
  color:#fff;
  font-size:14px;
  font-weight:500;
  text-decoration:none;
  transition:background .15s,border-color .15s;
}
.sg-hero-chips a:hover{background:rgba(255,255,255,.16);border-color:rgba(251,191,36,.6)}
.sg-hero-chips i{color:var(--sg-amber-light)}
.sg-hero-chips .sg-flag{width:22px;height:15px;border-radius:3px;object-fit:cover}

/* ===== Route finder ===== */
.sg-finder{
  display:grid;
  grid-template-columns:minmax(0,1fr) auto minmax(0,1fr) auto;
  align-items:end;
  gap:14px;
  margin:0 0 40px;
  padding:22px;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius-lg);
  background:#fff;
  box-shadow:var(--sg-shadow);
}
.sg-finder label{display:grid;gap:6px;font-size:13px;font-weight:600;color:var(--sg-text)}
.sg-finder select{
  width:100%;
  height:52px;
  padding:0 40px 0 14px;
  border:1.5px solid var(--sg-line);
  border-radius:12px;
  background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%2364748B' stroke-width='2'/%3E%3C/svg%3E") no-repeat right 16px center;
  color:var(--sg-ink);
  font:500 16px var(--sg-body);
  -webkit-appearance:none;
  appearance:none;
  cursor:pointer;
}
.sg-finder select:focus{outline:none;border-color:var(--sg-blue);box-shadow:0 0 0 4px rgba(45,82,204,.15)}
.sg-finder-plane{
  display:grid;
  place-items:center;
  width:44px;
  height:52px;
  color:var(--sg-blue);
  font-size:18px;
}
.sg-finder .sg-btn{height:52px}

/* ===== Country cards ===== */
.sg-countries{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:20px}
.sg-country{
  display:flex;
  flex-direction:column;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius-lg);
  background:#fff;
  overflow:hidden;
  transition:transform .2s,box-shadow .2s,border-color .2s;
}
.sg-country:hover{transform:translateY(-4px);box-shadow:var(--sg-shadow);border-color:#C7D2FE}
.sg-country-head{
  display:flex;
  align-items:center;
  gap:14px;
  padding:22px 22px 18px;
  background:linear-gradient(160deg,var(--sg-sky),#fff 75%);
}
.sg-country-head .sg-flag{
  flex:none;
  width:54px;
  height:36px;
  border-radius:6px;
  object-fit:cover;
  box-shadow:0 0 0 1px rgba(15,23,42,.1),0 6px 14px -6px rgba(15,23,42,.35);
}
.sg-country-head i.sg-flag{display:grid;place-items:center;font-size:26px;color:var(--sg-blue);box-shadow:none}
.sg-country h3{margin:0;font:800 21px/1.25 var(--sg-head);color:var(--sg-ink)}
.sg-country h3 a{color:inherit;text-decoration:none}
.sg-country h3 a:hover{color:var(--sg-blue)}
.sg-country-meta{margin:2px 0 0;font-size:13px;color:var(--sg-muted)}
.sg-country-body{flex:1;padding:4px 22px 18px}
.sg-country-label{margin:0 0 10px;font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--sg-muted)}
.sg-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;list-style:none}
.sg-chips a{
  display:inline-block;
  padding:6px 12px;
  border:1px solid var(--sg-line);
  border-radius:999px;
  color:var(--sg-text);
  font-size:13px;
  text-decoration:none;
  transition:border-color .15s,background .15s,color .15s;
}
.sg-chips a:hover{border-color:var(--sg-blue);background:var(--sg-sky);color:var(--sg-navy)}
.sg-country-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 22px 22px}
.sg-country-actions .sg-btn{height:46px;padding:0 12px;font-size:14.5px}
.sg-country-any{
  justify-content:center;
  gap:12px;
  padding:28px 24px;
  background:linear-gradient(160deg,#0B1F5C,#1B3CA3);
  border-color:transparent;
  color:#fff;
}
.sg-country-any:hover{border-color:transparent}
.sg-country-any .sg-aside-icon{margin-bottom:4px}
.sg-country-any h3{color:#fff}
.sg-country-any p{margin:0 0 8px;font-size:14.5px;line-height:1.6;color:#C9D4F2}

/* ===== City directory ===== */
.sg-search{position:relative;max-width:520px;margin:0 auto 28px}
.sg-search i{position:absolute;left:18px;top:50%;transform:translateY(-50%);color:var(--sg-muted)}
.sg-search input{
  width:100%;
  height:56px;
  padding:0 18px 0 48px;
  border:1.5px solid var(--sg-line);
  border-radius:999px;
  background:#fff;
  color:var(--sg-ink);
  font:500 16px var(--sg-body);
  box-shadow:var(--sg-shadow-sm);
}
.sg-search input:focus{outline:none;border-color:var(--sg-blue);box-shadow:0 0 0 4px rgba(45,82,204,.15)}
.sg-search-count{margin:-16px 0 24px;font-size:13px;color:var(--sg-muted);text-align:center}
.sg-city-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(210px,1fr));
  gap:12px;
  margin:0;
  padding:0;
  list-style:none;
}
.sg-city-grid[hidden],.sg-city-grid li[hidden]{display:none}
.sg-city{
  display:flex;
  align-items:center;
  gap:12px;
  height:100%;
  padding:14px 16px;
  border:1px solid var(--sg-line);
  border-radius:12px;
  background:#fff;
  color:var(--sg-ink);
  text-decoration:none;
  transition:border-color .15s,background .15s,box-shadow .15s;
}
.sg-city > i:first-child{
  display:grid;
  place-items:center;
  flex:none;
  width:36px;
  height:36px;
  border-radius:10px;
  background:var(--sg-sky);
  color:var(--sg-blue);
  font-size:14px;
}
.sg-city span{flex:1;min-width:0}
.sg-city b{display:block;font:600 15px/1.3 var(--sg-head)}
.sg-city small{font-size:12.5px;color:var(--sg-muted)}
.sg-city > i:last-child{color:#A5B8F0;font-size:13px;transition:transform .2s,color .2s}
.sg-city:hover{border-color:var(--sg-blue);background:var(--sg-sky);box-shadow:var(--sg-shadow-sm)}
.sg-city:hover > i:last-child{color:var(--sg-blue);transform:translateX(3px)}
.sg-city-empty{
  max-width:520px;
  margin:0 auto;
  padding:24px;
  border:1px dashed #C7D2FE;
  border-radius:var(--sg-radius);
  text-align:center;
  color:var(--sg-text);
}
.sg-city-empty[hidden]{display:none}
.sg-city-empty p{margin:0 0 14px;line-height:1.6}
.sg-city-more{margin:28px 0 0;font-size:15px;color:var(--sg-muted);text-align:center}
.sg-city-more button{
  padding:0;
  border:0;
  background:none;
  color:var(--sg-blue);
  font:600 15px var(--sg-body);
  text-decoration:underline;
  cursor:pointer;
}

/* ===== What are you sending ===== */
.sg-items{display:grid;grid-template-columns:repeat(9,minmax(0,1fr));gap:12px}
.sg-item{
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:12px;
  padding:20px 8px;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius);
  background:#fff;
  color:var(--sg-ink);
  font:600 14px/1.3 var(--sg-head);
  text-align:center;
  cursor:pointer;
  transition:transform .2s,box-shadow .2s,border-color .2s;
}
.sg-item i{
  display:grid;
  place-items:center;
  width:52px;
  height:52px;
  border-radius:50%;
  background:var(--sg-sky);
  color:var(--sg-blue);
  font-size:20px;
  transition:background .2s,color .2s;
}
.sg-item small{font:500 12.5px var(--sg-body);color:var(--sg-blue)}
.sg-item:hover{transform:translateY(-3px);box-shadow:var(--sg-shadow);border-color:#C7D2FE}
.sg-item:hover i{background:var(--sg-blue);color:#fff}

/* ===== Other destinations ===== */
.sg-more{padding:0 0 96px}
.sg-more h2{margin:0 0 18px;font:700 20px var(--sg-head);color:var(--sg-ink)}
.sg-dests{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin:0;padding:0;list-style:none}
.sg-dests a{
  display:flex;
  align-items:center;
  gap:14px;
  height:100%;
  padding:16px 18px;
  border:1px solid var(--sg-line);
  border-radius:var(--sg-radius);
  color:var(--sg-ink);
  text-decoration:none;
  transition:border-color .15s,background .15s;
}
.sg-dests a:hover{border-color:var(--sg-blue);background:var(--sg-sky)}
.sg-dests .sg-flag{flex:none;width:36px;height:24px;border-radius:4px;object-fit:cover;box-shadow:0 0 0 1px rgba(15,23,42,.1)}
.sg-dests i.sg-flag{display:grid;place-items:center;font-size:20px;color:var(--sg-blue);box-shadow:none}
.sg-dests b{display:block;font:700 16px var(--sg-head)}
.sg-dests small{font-size:13px;color:var(--sg-muted)}

@media(max-width:900px){
  .sg-items{grid-template-columns:repeat(3,minmax(0,1fr))}
  .sg-more{padding-bottom:64px}
  .sg-finder{grid-template-columns:minmax(0,1fr);gap:12px}
  .sg-finder-plane{display:none}
}
@media(max-width:600px){
  .sg-hero-chips{margin-top:18px}
  .sg-hero-chips a{padding:6px 12px 6px 8px;font-size:13px}
  .sg-finder{padding:18px;margin-bottom:28px}
  .sg-countries{grid-template-columns:minmax(0,1fr)}
  .sg-city-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .sg-city{gap:10px;padding:12px}
  .sg-city > i:first-child,.sg-city > i:last-child{display:none}
  .sg-items{gap:10px}
  .sg-item{padding:16px 6px;font-size:13px;gap:10px}
  .sg-item i{width:44px;height:44px;font-size:17px}
  .sg-item small{display:none}
}
</style>
<?php
}
