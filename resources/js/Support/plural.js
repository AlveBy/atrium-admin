/**
 * Which "a|b|c" form to take — the same rules Laravel's MessageSelector uses
 * for these languages, so a string reads the same from PHP and from Vue.
 */
export function pluralIndex(code, n) {
    const tens = Math.trunc(n) % 10;
    const hundreds = Math.trunc(n) % 100;

    switch (code) {
        case 'ja':
        case 'zh_CN':
        case 'tr':
        case 'kk':
        case 'uz':
            return 0;
        case 'fr':
            return n === 0 || n === 1 ? 0 : 1;
        case 'ru':
        case 'uk':
        case 'be':
            if (tens === 1 && hundreds !== 11) return 0;
            if (tens >= 2 && tens <= 4 && (hundreds < 10 || hundreds >= 20)) return 1;
            return 2;
        case 'pl':
            if (n === 1) return 0;
            if (tens >= 2 && tens <= 4 && (hundreds < 12 || hundreds > 14)) return 1;
            return 2;
        default:
            return n === 1 ? 0 : 1;
    }
}
