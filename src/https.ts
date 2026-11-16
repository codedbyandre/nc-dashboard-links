// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Match HttpsUrl::normalized(). Non-https returns null.
 *
 * @param raw URL text
 */
export function normalizeHttps(raw: string): string | null {
	const value = raw.trim()
	if (value === '') {
		return null
	}
	let url: URL
	try {
		url = new URL(value)
	} catch {
		return null
	}
	if (url.protocol !== 'https:') {
		return null
	}
	if (url.username !== '' || url.password !== '') {
		return null
	}
	const host = url.hostname.toLowerCase()
	if (host === '') {
		return null
	}
	const port = url.port === '443' ? '' : url.port
	let path = url.pathname
	if (path === '/') {
		path = ''
	}
	return `https://${host}${port !== '' ? `:${port}` : ''}${path}${url.search}`
}
