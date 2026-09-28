<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Admin API

The catalog lives in one lazy `IAppConfig` key, `catalog`. Administrators read and replace it over OCS. In the browser, GET and PUT require a CSRF token. Other OCS clients can pass `OCS-APIREQUEST: true` or a Bearer token. PUT and icon upload also require a recent password confirmation.

```
GET /ocs/v2.php/apps/dashboard_links/api/v1/catalog
PUT /ocs/v2.php/apps/dashboard_links/api/v1/catalog
```

The body is `{revision, categories, links}`:

```json
{
  "revision": "3f9a0c1b2d4e",
  "categories": [],
  "links": [
    {
      "id": "6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d",
      "title": "Intranet",
      "href": "https://intranet.example.com/",
      "icon": "core:places/link.svg",
      "categoryId": null,
      "enabled": true
    }
  ]
}
```

A category is `{id, title}`. A link is `{id, title, href, icon, categoryId, enabled}`. `categoryId` is null for the default list. `icon` is null, a stored file name, or a `core:` Nextcloud icon. Do not send `importance`, `featured`, `normal`, or `reference`. Ids are lowercase UUIDv4 minted by the client. Keep the id when editing a row. Mint a new one when adding.

`200` returns the saved catalog. Saving the catalog that is already stored succeeds and writes nothing, even if `revision` is stale.

`400` returns `{ "errors": [ { "index": 2, "field": "href", "message": "…" } ] }`. Every field error is collected.

`412` means someone else saved first. The body is the current catalog.

`revision` is the first 12 hex characters of SHA-256 over the canonical categories and links. It is not stored as its own config key. A schema 1 catalog is read as the default list. The next save writes schema 2.

Import from the External sites app runs in the browser. The control is shown only when that app is enabled and has at least one site. An iframe site is stored as `/apps/external/{id}/`. A redirect site is stored as its https URL. Nothing is stored until Save. This app does not read or write the External sites configuration on the server.
