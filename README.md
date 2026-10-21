# Taxmod JLCPCB

JLCPCB connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin),
over the [JLC Open API](https://api.jlcpcb.com).

**Status 0.0.1: step 1 only — the keys and a connection test. Nothing is fetched or written yet; not tested against JLC.**
The draft concept (German) lives with the modeller:
[`docs/pakete/jlc/konzept.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md).
It is meant to become the first source on the modeller's shared import interface
([`docs/pakete/import/konzept.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md)).

## Idea in short

- JLCPCB mails (read by [Taxmod Mail](https://github.com/sfambach/wp_tax_mail)) name order number, boards and tracking, but no prices.
- This module asks the JLC API for the details of such an order (costs, shipping, production state) and offers them as a completion of the proposal.
- The PCB API has no order list, only details by order (batch) number — so the order number comes from the mail or is entered by hand.
- Only picked data is written, through the modeller's `taxmod/write-data`. The shipping address is never stored.
- It never orders anything.

## Setup (step 1)

1. In the JLC API console (api.jlcpcb.com) the app exists and its service is started; create an API key for it.
2. Put the three values into `wp-config.php` — never into a chat:

   ```php
   define('TAXJLCPCB_APP_ID', '…');
   define('TAXJLCPCB_ACCESS_KEY', '…');
   define('TAXJLCPCB_SECRET_KEY', '…');
   // define('TAXJLCPCB_BASE_URL', 'https://open.jlcpcb.com'); // only if JLC names another host
   ```

3. *Taxonomy Modeller → Configuration → JLCPCB* → «Test connection». It requests the public detail of component
   C25804, a read that changes nothing. Success proves keys and signature.

The plugin stores no key itself. A form with encrypted storage comes with the modeller's shared vault (D-951).
The IP whitelist of the app may stay empty; if you fill it, add the outgoing IP of the web server.

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The signature test uses the worked example
of JLC's documentation.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## AI disclosure

This repository is created and maintained with the help of AI (Claude by Anthropic). All changes are reviewed by the owner.
