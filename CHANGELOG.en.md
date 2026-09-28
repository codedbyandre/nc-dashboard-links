<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 - 2026-09-23

First version of Company links (`dashboard_links`).

### Added

- Dashboard tile of admin-configured company links for Nextcloud 33 to 35 and PHP 8.2 to 8.5.
- `IAPIWidget` tile. The browser Dashboard loads the dashboard script and shows each category as a heading. The line under a link is the host. Links without a category stay in that list and get no heading. Phone and desktop clients receive a flat list, and a categorized link there shows the category in front of the host.
- All links uses the same headings. The line under a link is the host.
- `/open/{id}` responds with 303 to the https URL and sets `Referrer-Policy: no-referrer`, so bookmarks keep working.
- Admin OCS `GET`/`PUT` `/ocs/v2.php/apps/dashboard_links/api/v1/catalog`. The body is `{revision, categories, links}`.
- Catalog stored in one lazy `IAppConfig` key `catalog`. One list, optional custom categories, at most 200 links and 40 categories. A schema 1 catalog is read as that list and written as schema 2 on the next save.
- Admin settings page. Save confirms the password, then shows a success note. Admins pick a Nextcloud icon or upload one.
- Import from External sites appears only when that app has at least one site. An iframe site stores `/apps/external/{id}/`. A redirect site stores the https URL.
- Privacy notice for opening a link.
- German translations (`l10n/de`).
- Uninstall deletes the catalog and uploaded icons.
