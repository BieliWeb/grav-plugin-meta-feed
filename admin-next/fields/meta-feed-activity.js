/**
 * Meta Feed activity: read-only list of the most recent fetch and token refresh runs.
 * Admin Next assigns the tag name via window.__GRAV_FIELD_TAG.
 */
const TAG = window.__GRAV_FIELD_TAG;

const LABELS = { fetch: 'Fetch posts', token_refresh: 'Token refresh' };

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
  { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));

class MetaFeedActivity extends HTMLElement {
  constructor() {
    super();
    this._field = null;
    this._value = null;
  }

  set field(v) { this._field = v; }
  get field() { return this._field; }
  set value(v) { this._value = v; }
  get value() { return this._value; }

  connectedCallback() {
    this._onClick = (e) => {
      if (e.target.closest('[data-reload]')) {
        e.preventDefault();
        this._load();
      }
    };
    this.addEventListener('click', this._onClick);
    this._load();
  }

  disconnectedCallback() {
    this.removeEventListener('click', this._onClick);
  }

  async _load() {
    this._draw('<div class="mfa-muted">Loading…</div>');

    const base = (window.__GRAV_API_SERVER_URL || '') + (window.__GRAV_API_PREFIX || '/api/v1');
    const headers = {};
    if (window.__GRAV_API_TOKEN) headers['X-API-Token'] = window.__GRAV_API_TOKEN;

    try {
      const res = await fetch(base + '/meta-feed/activity', { headers });
      if (!res.ok) throw new Error('Request failed: ' + res.status);
      this._drawItems((await res.json()).data || []);
    } catch (err) {
      this._draw('<div class="mfa-fail">' + esc(err.message) + '</div>');
    }
  }

  _drawItems(items) {
    if (!items.length) {
      this._draw('<div class="mfa-muted">No runs recorded yet.</div>');
      return;
    }

    const rows = items.map((it) => {
      const time = new Date(it.time * 1000).toLocaleString('de-CH');
      const status = it.success ? '<span class="mfa-ok">OK</span>' : '<span class="mfa-fail">Failed</span>';

      return '<tr><td>' + esc(time) + '</td><td>' + esc(LABELS[it.type] || it.type) +
        '</td><td>' + status + '</td><td>' + esc(it.message) + '</td></tr>';
    }).join('');

    this._draw('<table><thead><tr><th>Time</th><th>Action</th><th>Status</th><th>Details</th></tr></thead><tbody>' +
      rows + '</tbody></table>');
  }

  _draw(content) {
    this.innerHTML = '<style>' +
      '.mfa { font-size: 13px; text-align: left; }' +
      '.mfa table { border-collapse: collapse; width: 100%; }' +
      '.mfa th, .mfa td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--border, #e5e7eb); vertical-align: top; }' +
      '.mfa-muted { color: var(--muted-foreground, #6b7280); padding: 6px 0; }' +
      '.mfa-ok { color: #16a34a; font-weight: 600; }' +
      '.mfa-fail { color: #dc2626; font-weight: 600; }' +
      '.mfa a { color: #3b82f6; cursor: pointer; }' +
      '</style><div class="mfa">' + content +
      '<p><a data-reload href="#">Reload</a></p></div>';
  }
}

if (TAG && !customElements.get(TAG)) {
  customElements.define(TAG, MetaFeedActivity);
}
