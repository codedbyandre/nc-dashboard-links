// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * @param error Axios error
 */
export function axiosStatus(error: unknown): number {
	if (typeof error === 'object' && error !== null && 'response' in error) {
		const response = error.response as { status?: number } | undefined
		return typeof response?.status === 'number' ? response.status : 0
	}
	return 0
}

/**
 * @param error Axios error
 */
export function axiosBody(error: unknown): unknown {
	if (typeof error === 'object' && error !== null && 'response' in error) {
		const response = error.response as { data?: unknown } | undefined
		return response?.data
	}
	return undefined
}
