/**
 * CSRF для «ручных» fetch-запросов админки (загрузка файлов, медиабиблиотека).
 *
 * Формы Inertia не ломаются потому, что axios под ними шлёт куку XSRF-TOKEN,
 * а Laravel обновляет её на каждом ответе. Мета-тег csrf-token, наоборот,
 * попадает в <head> один раз — при полной загрузке страницы. Дальше Inertia
 * меняет только тело документа, и после перевыпуска токена (повторный вход,
 * истёкшая сессия) мета остаётся с прежним значением, хотя сессия жива.
 *
 * Слать оба заголовка нельзя: VerifyCsrfToken читает X-CSRF-TOKEN раньше
 * X-XSRF-TOKEN, поэтому протухшая мета побеждает свежую куку и запрос
 * получает 419 «CSRF token mismatch». Отсюда порядок: кука, а мета — только
 * если куки нет.
 */

export const SESSION_EXPIRED_MESSAGE = 'Сессия истекла. Обновите страницу и войдите заново.';

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
 * Заголовки для запроса, изменяющего данные.
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

/** Тот же набор плюс то, что ждёт от нас Laravel при ajax-запросе. */
export function ajaxHeaders(extra = {}) {
  return {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...csrfHeaders(),
    ...extra,
  };
}

/**
 * Текст ошибки для человека.
 *
 * 419 приходит от VerifyCsrfToken со служебным «CSRF token mismatch.» —
 * показывать его бессмысленно: нужно знать не название проверки, а что делать.
 */
export async function responseErrorMessage(response, fallback = 'Запрос не удался') {
  if (response.status === 419) {
    return SESSION_EXPIRED_MESSAGE;
  }

  if (response.status === 413) {
    return 'Файл слишком большой — сервер не принял загрузку.';
  }

  const data = await response.json().catch(() => ({}));

  return data.message || fallback;
}
