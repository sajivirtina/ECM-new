/**
 * "Find Matching Requests" — the Rights Request intake meta box's lookup
 * against Care Leads by the requester's email/phone (inc/rights-queue.php).
 */
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('ecm_rights_find_leads_btn');
  if (!btn || !window.ECM_RIGHTS_ADMIN) return;

  var resultsEl = document.getElementById('ecm_rights_find_leads_results');
  var idField = document.getElementById('ecm_rights_related_lead_id');

  btn.addEventListener('click', function () {
    var email = (document.getElementById('ecm_rights_requester_email') || {}).value || '';
    var phone = (document.getElementById('ecm_rights_requester_phone') || {}).value || '';

    if (!email.trim() && !phone.trim()) {
      resultsEl.innerHTML = '<p style="color:#b91c1c;">Enter an email or phone number first.</p>';
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Searching…';
    resultsEl.innerHTML = '';

    var body = new URLSearchParams();
    body.set('action', 'ecm_rights_find_leads');
    body.set('nonce', ECM_RIGHTS_ADMIN.nonce);
    body.set('email', email);
    body.set('phone', phone);

    fetch(ECM_RIGHTS_ADMIN.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        btn.disabled = false;
        btn.textContent = 'Find Matching Requests';

        if (!json.success) {
          resultsEl.innerHTML = '<p style="color:#b91c1c;">' + escapeHtml(json.data || 'Search failed.') + '</p>';
          return;
        }
        var rows = json.data;
        if (!rows.length) {
          resultsEl.innerHTML = '<p>No matching Care Leads found.</p>';
          return;
        }

        var html = '<table class="widefat striped" style="margin-top:8px;max-width:700px;"><thead><tr>'
          + '<th>ID</th><th>Request</th><th>Email</th><th>Phone</th><th>Picked providers</th><th>Date</th><th></th>'
          + '</tr></thead><tbody>';
        rows.forEach(function (r) {
          html += '<tr>'
            + '<td>' + r.id + '</td>'
            + '<td>' + escapeHtml(r.title) + '</td>'
            + '<td>' + escapeHtml(r.email) + '</td>'
            + '<td>' + escapeHtml(r.phone) + '</td>'
            + '<td>' + escapeHtml(r.picked) + '</td>'
            + '<td>' + escapeHtml(r.date) + '</td>'
            + '<td><button type="button" class="button button-small ecm-rights-select-lead" data-id="' + r.id + '">Use this</button></td>'
            + '</tr>';
        });
        html += '</tbody></table>';
        resultsEl.innerHTML = html;

        resultsEl.querySelectorAll('.ecm-rights-select-lead').forEach(function (b) {
          b.addEventListener('click', function () {
            idField.value = b.getAttribute('data-id');
            resultsEl.querySelectorAll('tr').forEach(function (tr) { tr.style.background = ''; });
            b.closest('tr').style.background = '#eafbe7';
          });
        });
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Find Matching Requests';
        resultsEl.innerHTML = '<p style="color:#b91c1c;">Network error. Please try again.</p>';
      });
  });
});

function escapeHtml(str) {
  var div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}
