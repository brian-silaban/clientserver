/**
 * Utilitas pemformatan mata uang, waktu WIB, tanggal Indonesia, status badge, & fallback poster.
 */

export const POSTER_FALLBACK_SVG =
  'data:image/svg+xml;charset=UTF-8,' +
  encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="300" height="450" viewBox="0 0 300 450">
      <rect width="300" height="450" fill="#1C1917"/>
      <rect x="20" y="20" width="260" height="410" rx="10" fill="none" stroke="#44403C" stroke-width="1.5" stroke-dasharray="6 6"/>
      <text x="150" y="214" text-anchor="middle" fill="#D6D3D1" font-family="Georgia, serif" font-size="20" font-weight="bold">AniCine</text>
      <text x="150" y="240" text-anchor="middle" fill="#78716C" font-family="sans-serif" font-size="12">Poster Arsip Katalog</text>
    </svg>
  `);

export function formatRupiah(amount) {
  return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
}

export function formatTimeWib(timeStr) {
  if (!timeStr) return '-';
  return String(timeStr).slice(0, 5) + ' WIB';
}

export function formatDateId(dateStr) {
  if (!dateStr) return '-';
  const parsed = new Date(dateStr.includes('T') ? dateStr : `${dateStr}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return dateStr;

  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(parsed);
}

export function formatDateTimeId(dateTimeStr) {
  if (!dateTimeStr) return '-';
  const parsed = new Date(dateTimeStr);
  if (Number.isNaN(parsed.getTime())) return dateTimeStr;

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed);
}

export function getScreeningBadge(status) {
  if (status === 'NOW_SHOWING') {
    return { label: 'Sedang Tayang', className: 'bg-crimson text-white' };
  }
  if (status === 'UPCOMING') {
    return { label: 'Akan Tayang', className: 'bg-ambercine text-white' };
  }
  return { label: 'Selesai Tayang', className: 'bg-stone-600 text-stone-100' };
}

export function getPaymentBadge(status) {
  switch (status) {
    case 'PAID':
      return { label: 'Lunas', className: 'bg-emeraldok-soft text-emeraldok border-emerald-200' };
    case 'PENDING':
      return { label: 'Menunggu Bayar', className: 'bg-ambercine-soft text-ambercine border-amber-200' };
    case 'CANCELLED':
      return { label: 'Dibatalkan', className: 'bg-stone-100 text-ink-muted border-stone-200' };
    case 'EXPIRED':
      return { label: 'Kedaluwarsa', className: 'bg-crimson-soft text-crimson border-rose-200' };
    default:
      return { label: status || '-', className: 'bg-stone-100 text-ink-body border-stone-200' };
  }
}

export function onPosterError(event) {
  if (event?.target && event.target.src !== POSTER_FALLBACK_SVG) {
    event.target.src = POSTER_FALLBACK_SVG;
  }
}
