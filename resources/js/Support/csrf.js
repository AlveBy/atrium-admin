/**
 * CSRF for the admin's "manual" fetch requests (file uploads, media library).
 *
 * Inertia forms don't break because axios underneath sends the XSRF-TOKEN
 * cookie, and Laravel refreshes it on every response. The csrf-token meta tag,
 * by contrast, lands in <head> once — on a full page load. After that Inertia
 * swaps only the document body, and once the token is reissued (re-login,
 * expired session) the meta keeps the old value even though the session is alive.
 *
 * Sending both headers is not an option: VerifyCsrfToken reads X-CSRF-TOKEN
 * before X-XSRF-TOKEN, so a stale meta beats a fresh cookie and the request
 * gets 419 "CSRF token mismatch". Hence the order: the cookie, and the meta
 * only if there is no cookie.
 */

import { t } from './i18n';

/** A function, not a constant: the dictionary is not loaded at import time. */
export function sessionExpiredMessage() {
  return t('fields.upload.session_expired');
}

function readCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));

  if (!match) {
    return null;
  }

  try {
    return decodeURIComponent(match[1]);
  } catch (e) {
    return match[1];
  }
}

/**
 * Headers for a request that changes data.
 *
 * @returns {Record<string, string>}
 */
export function csrfHeaders() {
  const cookie = readCookie('XSRF-TOKEN');

  if (cookie) {
    return { 'X-XSRF-TOKEN': cookie };
  }

  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  return meta ? { 'X-CSRF-TOKEN': meta } : {};
}

/** The same set plus what Laravel expects from an ajax request. */
export function ajaxHeaders(extra = {}) {
  return {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...csrfHeaders(),
    ...extra,
  };
}

/**
 * A human-readable error message.
 *
 * 419 comes from VerifyCsrfToken with the technical "CSRF token mismatch." —
 * showing it is pointless: the user needs to know what to do, not the check's name.
 */
export async function responseErrorMessage(response, fallback = t('fields.upload.request_failed')) {
  if (response.status === 419) {
    return sessionExpiredMessage();
  }

  if (response.status === 413) {
    return t('fields.upload.too_large');
  }

  const data = await response.json().catch(() => ({}));

  return data.message || fallback;
}
