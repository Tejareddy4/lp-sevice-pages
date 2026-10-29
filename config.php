<?php

// Shared settings for template.php and lead.php.
return [
  // Zoho Flow incoming webhook that creates the CRM lead.
  'lead_webhook' => 'https://flow.zoho.in/60030990815/flow/webhook/incoming?zapikey=1001.13056985b9392ffd01f82ea7b29ecd8c.274c0a48499a6dfba88a38d975d9ad65&isdebug=false',

  // Rate card used by the public shipping rate calculator.
  // path:   site-root path, read from disk (DOCUMENT_ROOT) and used by the browser fallback.
  // remote: absolute URL the server downloads when the file isn't on this server (dev, local).
  'rate_sheet' => [
    'path' => '/wp-content/uploads/2025/04/rate_ship.csv',
    'remote' => 'https://shipglobal.in/wp-content/uploads/2025/04/rate_ship.csv',
    'cache_ttl' => 6 * 3600,
  ],

  // Other names a destination may have as a rate-card column ("Czechia (CZ)").
  'rate_aliases' => [
    'czech republic' => ['czechia'],
    'uk' => ['united kingdom', 'great britain'],
    'us' => ['united states', 'usa', 'united states of america'],
    'uae' => ['united arab emirates'],
    'europe' => ['eu'],
  ],
];
