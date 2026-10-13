{{-- Chatbot FAQs section of the System Settings page. Needs $faqs, $unanswered, $categories. --}}
<div id="chatbot-faqs" style="scroll-margin-top:80px;">
<h3 class="set-title mt-2"><i class="ph ph-robot" aria-hidden="true"></i> Chatbot FAQs</h3>
<p class="set-sub">The AWAS Assistant chatbot answers residents from these FAQs first. Add, edit, or disable entries to control exactly what it can say — it never edits bills, payments, or meter readings.</p>

<div class="card">
  <div class="card-header">
    <h3>Chatbot FAQs ({{ count($faqs) }})</h3>
    <button class="btn btn-primary btn-sm" id="addFaqBtn" data-bs-toggle="modal" data-bs-target="#faqModal">+ Add FAQ</button>
  </div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Question</th><th>Category</th><th>Keywords</th><th>Asked</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($faqs as $f)
          <tr>
            <td style="max-width:280px;">{{ $f->question }}</td>
            <td>{{ $categories[$f->category] ?? $f->category }}</td>
            <td class="text-muted" style="font-size:11.5px;max-width:200px;">{{ $f->keywords ?: '—' }}</td>
            <td>{{ number_format((int)$f->hit_count) }}×</td>
            <td><span class="badge {{ $f->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $f->status === 'active' ? 'Active' : 'Disabled' }}</span></td>
            <td class="actions">
              <button class="btn btn-secondary btn-sm" onclick="openEditFaq({{ json_encode($f) }}, '{{ route('admin.faqs.update', $f) }}')">Edit</button>
              <form method="POST" action="{{ route('admin.faqs.toggle', $f) }}" class="d-inline">
                @csrf
                <button class="btn btn-warning btn-sm" type="submit">{{ $f->status === 'active' ? 'Disable' : 'Enable' }}</button>
              </form>
              <form method="POST" action="{{ route('admin.faqs.destroy', $f) }}" class="d-inline" data-confirm="Delete this FAQ permanently?">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="6">No FAQs yet. Add one to start building the assistant's knowledge base.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Recently Unanswered Questions ({{ count($unanswered) }})</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Question</th><th>Asked By</th><th>When</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($unanswered as $u)
          <tr>
            <td>{{ $u->question }}</td>
            <td>{{ $u->asked_by_name ?: '—' }}</td>
            <td>{{ formatDateTime($u->created_at) }}</td>
            <td class="actions">
              <button class="btn btn-primary btn-sm" onclick="useUnanswered({{ json_encode(['id' => $u->id, 'question' => $u->question]) }})">Create FAQ</button>
              <form method="POST" action="{{ route('admin.faqs.dismiss', $u->id) }}" class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-secondary btn-sm" type="submit">Dismiss</button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="4">Nothing unanswered recently — the FAQ knowledge base is covering resident questions well.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>{{-- #chatbot-faqs --}}

<div class="modal fade" id="faqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.faqs.store') }}" id="faqForm">
        <div class="modal-header"><h3 class="h6 mb-0" id="faqModalTitle">Add FAQ</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <input type="hidden" name="_method" id="faqMethod" value="POST">
        <input type="hidden" name="source_unanswered_id" id="source_unanswered_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Question *</label>
            <input type="text" name="question" id="question" class="form-control" required placeholder="e.g. How can I pay my water bill?">
          </div>
          <div class="mb-3">
            <label class="form-label">Answer *</label>
            <textarea name="answer" id="answer" class="form-control" rows="4" required placeholder="Keep it short, friendly, and accurate."></textarea>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select name="category" id="category" class="form-select">
                @foreach ($categories as $key => $label)
                  <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Keywords (comma-separated)</label>
              <input type="text" name="keywords" id="keywords" class="form-control" placeholder="e.g. pay, gcash, bank transfer">
            </div>
          </div>
          <div class="mb-3 mt-3">
            <label><input type="checkbox" name="is_active" id="is_active" value="1" checked> Active (chatbot may use this answer)</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save FAQ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const faqStoreUrl = {{ Js::from(route('admin.faqs.store')) }};
function resetFaqForm(title) {
  const form = document.getElementById('faqForm');
  form.reset();
  form.action = faqStoreUrl;
  document.getElementById('faqMethod').value = 'POST';
  document.getElementById('source_unanswered_id').value = '';
  document.getElementById('is_active').checked = true;
  document.getElementById('faqModalTitle').textContent = title;
}
function openEditFaq(f, action) {
  resetFaqForm('Edit FAQ');
  document.getElementById('faqForm').action = action;
  document.getElementById('faqMethod').value = 'PUT';
  document.getElementById('question').value = f.question;
  document.getElementById('answer').value = f.answer;
  document.getElementById('category').value = f.category;
  document.getElementById('keywords').value = f.keywords || '';
  document.getElementById('is_active').checked = f.status === 'active';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('faqModal')).show();
}
function useUnanswered(u) {
  resetFaqForm('Create FAQ from Unanswered Question');
  document.getElementById('source_unanswered_id').value = u.id;
  document.getElementById('question').value = u.question;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('faqModal')).show();
}
document.getElementById('addFaqBtn').addEventListener('click', () => resetFaqForm('Add FAQ'));
</script>
