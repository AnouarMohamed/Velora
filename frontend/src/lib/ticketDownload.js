import { getEventImageUrl } from './eventImage';
import { formatDate, formatPrice } from './format';

function escapeHtml(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function buildTicketHtml(registration, participantName, origin = '') {
  const ev = registration.event;
  const imageUrl = getEventImageUrl(ev);
  const imageSrc = imageUrl
    ? imageUrl.startsWith('http')
      ? imageUrl
      : `${origin}${imageUrl}`
    : '';

  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Billet — ${escapeHtml(ev?.title)}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Georgia, 'Times New Roman', serif; background: #f5f0e8; padding: 24px; color: #1c1917; }
    .ticket { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,.12); border: 1px solid #e7e5e4; }
    .head { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: linear-gradient(90deg,#f5f0e8,#fff); border-bottom: 1px solid #e7e5e4; }
    .brand { font-size: 22px; font-weight: 600; letter-spacing: .14em; color: #7d6b54; }
    .badge { font-family: system-ui,sans-serif; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .12em; padding: 6px 12px; border-radius: 999px; background: #f5f0e8; color: #5e5040; border: 1px solid #d4c4a8; }
    .banner img { width: 100%; display: block; aspect-ratio: 21/9; object-fit: cover; }
    .body { padding: 24px; }
    h1 { font-size: 28px; line-height: 1.2; margin-bottom: 8px; }
    .desc { font-size: 16px; line-height: 1.55; color: #57534e; margin-bottom: 20px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
    .cell { background: #fafaf9; border-radius: 8px; padding: 10px 12px; font-family: system-ui,sans-serif; }
    .cell dt { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #78716c; font-weight: 600; }
    .cell dd { margin-top: 4px; font-size: 14px; font-weight: 600; color: #1c1917; }
    .footer { border-top: 1px dashed #d6d3d1; padding-top: 16px; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; font-family: system-ui,sans-serif; }
    .code { text-align: center; border: 1px solid #e7e5e4; background: #fafaf9; border-radius: 12px; padding: 12px 16px; }
    .code span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .15em; color: #78716c; }
    .code strong { display: block; margin-top: 6px; font-family: ui-monospace, monospace; font-size: 14px; letter-spacing: .08em; }
    .price { font-size: 20px; font-weight: 700; color: #5e5040; margin-top: 4px; }
  </style>
</head>
<body>
  <div class="ticket">
    <div class="head">
      <span class="brand">VELORA</span>
      <span class="badge">Billet officiel</span>
    </div>
    ${imageSrc ? `<div class="banner"><img src="${escapeHtml(imageSrc)}" alt="" /></div>` : ''}
    <div class="body">
      <h1>${escapeHtml(ev?.title)}</h1>
      ${ev?.description ? `<p class="desc">${escapeHtml(ev.description)}</p>` : ''}
      <dl class="grid">
        <div class="cell"><dt>Début</dt><dd>${escapeHtml(formatDate(ev?.start_at))}</dd></div>
        <div class="cell"><dt>Fin</dt><dd>${escapeHtml(formatDate(ev?.end_at))}</dd></div>
        <div class="cell"><dt>Lieu</dt><dd>${escapeHtml(ev?.location || '—')}</dd></div>
        <div class="cell"><dt>Salle</dt><dd>${escapeHtml(ev?.room || '—')}</dd></div>
      </dl>
      <div class="footer">
        <div>
          <p style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#78716c;font-weight:600">Participant</p>
          <p style="font-weight:600;margin-top:4px">${escapeHtml(participantName)}</p>
          <p style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#78716c;font-weight:600;margin-top:12px">Prix payé</p>
          <p class="price">${escapeHtml(formatPrice(registration.amount ?? ev?.ticket_price))}</p>
        </div>
        <div class="code">
          <span>Code billet</span>
          <strong>${escapeHtml(registration.ticket_code)}</strong>
        </div>
      </div>
    </div>
  </div>
</body>
</html>`;
}

export function downloadTicketHtml(registration, participantName) {
  const html = buildTicketHtml(registration, participantName, window.location.origin);
  const slug = (registration.event?.title || 'evenement')
    .replace(/[^\w\u00C0-\u024f]+/gi, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 40);
  const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `billet-velora-${slug || registration.id}.html`;
  a.click();
  URL.revokeObjectURL(url);
}
