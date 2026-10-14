# EstateHDR SEO and acquisition fixes — review package

The signup link currently loses the selected order return path, auth fields have no associated labels, pasted script wrappers create invalid JSON-LD, RSS links resolve to a 404, and historical service URLs return 404. This package contains scoped fixes against the sanitized source ZIP at commit `5804f777cdeb8901a5a77a46c54a8e71f2c67042`.

## How to review and apply

The repository currently stores a ZIP rather than an expanded app. `files/` is a review overlay, not a deployed web root. Unzip the original source into an isolated staging checkout and run `git apply --check fixes.patch`, then `git apply fixes.patch` from that extracted root. Compare the production source with the snapshot before applying. Do not upload the whole sanitized source ZIP over production: it contains dummy configuration, demo records and omitted media.

Changed files:

- `includes/client-return.php`: allowlisted local portal redirects, including the selected quote URI.
- `includes/auth.php`, `login.php`, `register.php`: retain return intent through signup/signin, validate redirects and associate form labels.
- `includes/jsonld.php`, `blog-post.php`: accept valid JSON or one pasted script wrapper; escape script-ending text; use existing article fallback for invalid data.
- `.htaccess`: add the advertised RSS route, route legacy PHP content links before generic extension redirects, and add eight redirects to equivalent existing pages.
- `pricing.php`: remove stale percentage tiers from the FAQ without changing prices, billing algorithms, ledgers or commercial offers.
- `tests/seo-acquisition-regression.php`: regression coverage for return intent, unsafe destinations, wrapped schemas, invalid JSON and script breakouts.

## Validation completed

- PHP 8.2.33 (WebAssembly runtime) syntax check: all eight affected PHP files passed.
- 26 redirect/JSON-LD behavior checks passed.
- PHP-rendered signup/login forms preserve the selected order link and associate every visible auth label with a field.
- Ten static rewrite checks passed (eight retired URLs, legacy-rule ordering, RSS rule presence).
- `git apply --check` passes against the exact original source, preserving CRLF files.
- Live redirect destinations were inspected and return HTTP 200 with their expected canonical URL.

## Still required before production

No production change has been deployed. Verify actual Apache/LiteSpeed rewrites on staging, auth cookies and signup/login with a dedicated test account, and updated JSON-LD using the current CMS record. The PHP WebAssembly CLI supports parsing/rendering; it is not a Hostinger integration test. The full authenticated order/upload/delivery/download/payment journey has not been tested.

The calculator still omits selected SLA/add-on details from its order link; fixing server-authoritative quote storage and billable units is a separate P1 ticket. No quote amount is trusted as a billed amount here. `single-exposure/` remains unmapped because an equivalent live page has not been verified. Live duplicate service content, unverified rating/review claims, mobile layout, GA4 conversion measurement, and actual payment-provider readiness remain separate work.

Get an explicit production approval after staging verification and a deployment/rollback path are available. Preserve current production config and data, back up the changed files, deploy only this overlay, and verify the public URLs and conversion journey after deployment.
