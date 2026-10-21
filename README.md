# Taxmod JLCPCB

JLCPCB connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin),
over the [JLC Open API](https://api.jlcpcb.com).

**Status 0.0.5: keys, connection test (works on fambach.net), a read-only probe that lists the order numbers of the last year and shows the details of one, and price completion of JLCPCB proposals in the modeller's inbox (modeller 0.1.35 or later). Completion is not yet tested against JLC.**
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

## AI disclosure

This repository is created and maintained with the help of AI (Claude by Anthropic). All changes are reviewed by the owner.
