/**
 * Global DateTime Formatter for Asia/Kolkata (IST)
 * Formats timestamps into "DD/MM/YYYY hh:mm AM/PM" (e.g. 25/09/2026 03:30 PM)
 */
function formatDateTime(val) {
    if (!val || val === '—' || val === 'never' || val === 'null' || val === 'undefined') return '—';
    const str = String(val).trim();
    if (!str || str === '—' || str === 'never') return '—';

    // Already in dd/mm/yyyy hh:mm AM/PM
    if (/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}\s+(?:AM|PM)$/i.test(str)) {
        return str;
    }

    // Date-only YYYY-MM-DD
    const dateOnly = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (dateOnly) {
        return `${dateOnly[3]}/${dateOnly[2]}/${dateOnly[1]}`;
    }

    // Standard MySQL datetime string: "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD HH:MM"
    const sqlMatch = str.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (sqlMatch) {
        const yyyy = sqlMatch[1];
        const mm   = sqlMatch[2];
        const dd   = sqlMatch[3];
        const h24  = parseInt(sqlMatch[4], 10);
        const min  = sqlMatch[5];
        const h12  = h24 % 12 === 0 ? 12 : h24 % 12;
        const hh   = String(h12).padStart(2, '0');
        const ampm = h24 >= 12 ? 'PM' : 'AM';
        return `${dd}/${mm}/${yyyy} ${hh}:${min} ${ampm}`;
    }

    // Handles timestamps, ISO 8601 with Z, offsets, etc.
    let d = /^\d+$/.test(str)
        ? new Date(parseInt(str, 10) > 1e11 ? parseInt(str, 10) : parseInt(str, 10) * 1000)
        : new Date(str);

    if (isNaN(d.getTime())) return str;

    try {
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Kolkata',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        const parts = formatter.formatToParts(d);
        let day = '', month = '', year = '', hour = '', minute = '', dayPeriod = '';
        for (const p of parts) {
            if (p.type === 'day') day = p.value;
            if (p.type === 'month') month = p.value;
            if (p.type === 'year') year = p.value;
            if (p.type === 'hour') hour = p.value;
            if (p.type === 'minute') minute = p.value;
            if (p.type === 'dayPeriod') dayPeriod = p.value.toUpperCase();
        }
        return `${day}/${month}/${year} ${hour}:${minute} ${dayPeriod}`;
    } catch (e) {
        return str;
    }
}
