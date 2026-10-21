# src/Core

Request signing (HMAC-SHA256, `Authorization: JOP …`), the signed client and the connection test of the JLC Open
Platform. Plain PHP 8.2, **no WordPress call** — HTTP goes through `Api\HttpTransport`, credentials through
`Api\CredentialSource`, which the boundary implements. Concept:
[`docs/pakete/jlc/konzept.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md).
