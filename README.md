# Taxmod JLCPCB

JLCPCB connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin),
over the [JLC Open API](https://api.jlcpcb.com).

**Status 0.1.9 (needs modeller 0.1.49 or later): keys in the common «Access» part of the tab with Save, Test connection and Delete (D-981), a «How to connect» box beside the tab (modeller after 0.1.40), a read-only probe that lists the order numbers of the last year and shows the details of one with every field JLC sends (address excluded). JLCPCB orders come into the modeller's inbox from the API itself (D-965, modeller 0.1.39 or later): as often as chosen in the modeller's «Fetching» part (default once a day), the first run reaches back a year; mail only adds the status. JLCPCB proposals of other sources still get their prices completed. Not yet tested against JLC. Customs go to `fees`, shipping to `shippingCost`, so the modeller spreads both into the landed unit price (D-933). Amounts stay in USD; conversion to euros belongs to the modeller. A batch with stencil orders gets no total until stencils are read.**
The draft concept (German) lives with the modeller:
[`docs/pakete/jlc/konzept.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md).
It is meant to become the first source on the modeller's shared import interface
([`docs/pakete/import/konzept.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md)).

## Idea in short

- JLCPCB mails (read by [Taxmod Mail](https://github.com/sfambach/wp_tax_mail)) name order number, boards and tracking, but no prices.
- This module asks the JLC API for the details of such an order (Query Order Details by Batch Number V2: fee per board order, shipping, customs, tracking) and completes the proposal: unit prices, shipping, total in USD, order date, tracking. It only fills empty fields and adds no line (D-953).
- The PCB API lists batches (order numbers `W…`) by creation time; prices and contents come from the detail call per batch.
- Only picked data is written, through the modeller's `taxmod/write-data`. The shipping address is never stored.
- It never orders anything.

## Setup (step 1)

1. In the JLC API console (api.jlcpcb.com) the app exists and its service is started; create an API key for it.
2. Enter App ID, AccessKey and SecretKey on *Taxonomy Modeller → Configuration → JLCPCB*. The SecretKey is sealed in
   the modeller's vault (D-951, needs the modeller 0.1.31 or later). Or define them in `wp-config.php`, which takes
   precedence. Never post them in a chat:

   ```php
   define('TAXJLCPCB_APP_ID', '…');
   define('TAXJLCPCB_ACCESS_KEY', '…');
   define('TAXJLCPCB_SECRET_KEY', '…');
   // define('TAXJLCPCB_BASE_URL', 'https://open.jlcpcb.com'); // only if JLC names another host
   ```

3. *Taxonomy Modeller → Configuration → JLCPCB* → «Test connection». It requests the public detail of component
   C25804, a read that changes nothing. Success proves keys and signature.

Changing the salts in `wp-config.php` makes the stored SecretKey unreadable; the tab says so and asks for it again.
The IP whitelist of the app may stay empty; if you fill it, add the outgoing IP of the web server.

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The signature test uses the worked example
of JLC's documentation.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## Authors

- **Stefan Fambach** (SteFam) – idea and concept, review and testing – https://www.fambach.net
- **Claude** (Anthropic AI) – implementation, via Claude Code

## AI disclosure

This project is built with AI assistance. Stefan Fambach had the idea, wrote the concept and reviews and tests every change; the implementation (code and documentation) is written largely by Claude (Anthropic) via Claude Code.
