<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Publish to the Nextcloud app store

The repository can build a store tarball. The store still needs a certificate that only the app owner can request.

## Build the archive

```sh
npm ci
npm run build
make appstore
```

`make appstore` copies an allowlist into `build/appstore/dashboard_links`: `appinfo`, `lib`, `templates`, `css`, `js`, `img`, `l10n`, `LICENSES`, `CHANGELOG.md`, and `CHANGELOG.en.md`. Source maps (`*.map`) are excluded. It then runs the staged and archive release checks in `tests/Release/`. The archive is `build/appstore/dashboard_links.tar.gz`. The top-level folder is `dashboard_links`, as the store requires.

`make appstore` still skips `occ integrity:sign-app` when the key, certificate, or `occ` is missing. The store upload form still needs that signature; skip only means the local build continues without signing.

## Certificate (once)

1. Create a 4096-bit key and CSR. Keep the key off this repository.

```sh
mkdir -p ~/.nextcloud/certificates
openssl req -nodes -newkey rsa:4096 \
  -keyout ~/.nextcloud/certificates/dashboard_links.key \
  -out ~/.nextcloud/certificates/dashboard_links.csr \
  -subj "/CN=dashboard_links"
```

2. Open a pull request on [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests) with `dashboard_links/dashboard_links.csr` and this public repository URL. Show your email on GitHub.
3. Store the signed `dashboard_links.crt` they return next to the key.
4. Register the app at [apps.nextcloud.com/developer/apps/new](https://apps.nextcloud.com/developer/apps/new). Sign the app id:

```sh
echo -n "dashboard_links" \
  | openssl dgst -sha512 -sign ~/.nextcloud/certificates/dashboard_links.key \
  | openssl base64
```

## Sign a release

With `NEXTCLOUD_ROOT` pointing at a Nextcloud 33 to 35 checkout that can run `occ`:

```sh
export NEXTCLOUD_ROOT=/path/to/nextcloud
make appstore
```

`make appstore` runs `occ integrity:sign-app` when the key, certificate, and `occ` exist. It then prints the SHA-512 signature of the tar.gz for the store upload form.

Upload the public `dashboard_links.tar.gz` URL and that signature at [apps.nextcloud.com/developer/apps/releases/new](https://apps.nextcloud.com/developer/apps/releases/new).

## Screenshots

The admin screenshot is `docs/screenshots/admin.png`. `appinfo/info.xml` points at the raw GitHub URL for that file on `main`. The store downloads the URL. The file stays out of the app tarball, because `docs/` is not in the `make appstore` allowlist. The URL answers only after this file is on `main`.

A Dashboard tile screenshot is still missing. Host it over HTTPS, at most 2 MiB, and add another `<screenshot>` before signing.

## Changelog heading

The store parser matches `^## (\d+\.\d+\.\d+)` for a release and `^## \[Unreleased\]` for nightlies. Use `## 1.0.0 - YYYY-MM-DD`, not `## [1.0.0]`.
