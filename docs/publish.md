<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Publish a release

One path. A GitHub release from a `vX.Y.Z` tag builds the store archive, signs it, attaches it to that release, and uploads it to the Nextcloud app store. The workflow is `.github/workflows/release.yml`. It runs only after the release is published, and only from the workflow file on `main`.

The private key stays in `~/.nextcloud/certificates/dashboard_links.key` and in the Actions secret `APP_PRIVATE_KEY`. It is not a repository file.

## Once

1. On GitHub, open **Settings → Secrets and variables → Actions** for this repository.
2. Create `APP_PRIVATE_KEY`. Paste the full PEM from `~/.nextcloud/certificates/dashboard_links.key`, including the `BEGIN` and `END` lines.
3. Create `APPSTORE_TOKEN`. On [apps.nextcloud.com](https://apps.nextcloud.com), open the account menu and copy the API token.
4. Push this workflow to `main` before publishing a release. GitHub reads the workflow from `main`, then checks out the tag.

The app id is already registered. The public certificate is [dashboard_links.crt](https://github.com/nextcloud/app-certificate-requests/blob/master/dashboard_links/dashboard_links.crt). The workflow downloads that file itself.

## Each release

1. Put the same version in `appinfo/info.xml` and `package.json`.
2. Add a heading `## X.Y.Z - YYYY-MM-DD` at the top of `CHANGELOG.md`. The store reads that shape. `## [X.Y.Z]` does not match. Copy `CHANGELOG.md` over `CHANGELOG.en.md` so the two files stay identical.
3. Commit that on `main` and push `main`.
4. Tag that exact commit and push the tag:

```sh
git tag vX.Y.Z
git push origin vX.Y.Z
```

5. Open a GitHub release from that tag and publish it. Leave "Set as a pre-release" off. A draft does not start the workflow. A prerelease stops it.
6. Wait until the **Release** workflow is green. The store page is [apps.nextcloud.com/apps/dashboard_links](https://apps.nextcloud.com/apps/dashboard_links).

The tag, `info.xml`, and `package.json` must all say the same version. `v1.2.0` ships version `1.2.0`. The workflow builds the frontend, then runs `make appstore SIGN=required`. That refuses to upload when `appinfo/signature.json` is missing. Signing uses an unpacked Nextcloud 33 tree only as `occ`. The log line "Nextcloud is not installed" is expected. The next line is "Successfully signed", and `occ integrity:check-app` must pass before the upload.

`make appstore` copies an allowlist into `build/appstore/dashboard_links`: `appinfo`, `lib`, `templates`, `css`, `js`, `img`, `l10n`, `LICENSES`, `CHANGELOG.md`, and `CHANGELOG.en.md`. Source maps stay out. The top-level folder is `dashboard_links`. The GitHub source archive is a different layout and is not the store upload.

## First release

`main` is already version `1.0.0`. After the workflow and the two secrets are on the repository, tag the current `main` as `v1.0.0` and publish that release. No version bump.

## Local check

`make appstore` without `SIGN=required` still builds an unsigned archive for CI. A signed archive needs a Nextcloud 33 tree and the key:

```sh
export NEXTCLOUD_ROOT=/path/to/nextcloud
make appstore SIGN=required
```

The command prints the detached SHA-512 signature of the tar.gz. The store upload uses that kind of signature. The workflow's app store action calculates it from `APP_PRIVATE_KEY`.

## Screenshots

`appinfo/info.xml` points at `docs/screenshots/admin.png` on `main`. The store downloads that URL. `docs/` is not part of the tarball. A second screenshot, for the Dashboard tile, can be added as another `<screenshot>` before the release is tagged.
