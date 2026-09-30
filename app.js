const API = 'api/';

const form = document.getElementById('reportForm');
const cameraInput = document.getElementById('cameraInput');
const galleryInput = document.getElementById('galleryInput');
const preview = document.getElementById('photoPreview');
const category = document.getElementById('category');
const locationSelect = document.getElementById('location');
const message = document.getElementById('formMessage');
const submitBtn = document.getElementById('submitBtn');

async function getJson(url) {
  const res = await fetch(url);
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || 'Request failed');
  return data;
}

async function loadOptions() {
  try {
    const [cats, locs] = await Promise.all([getJson(API + 'categories.php'), getJson(API + 'locations.php')]);
    category.innerHTML = '<option value="">Select category</option>' + cats.data.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
    locationSelect.innerHTML = '<option value="">Select location</option>' + locs.data.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
  } catch (e) {
    message.textContent = 'Could not load campus options. Check that XAMPP/Apache/MySQL are running.';
    message.className = 'form-message error';
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function handleImage(file) {
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    message.textContent = 'Image must be 5 MB or smaller.';
    message.className = 'form-message error';
    return;
  }
  const allowed = ['image/jpeg','image/png','image/webp'];
  if (!allowed.includes(file.type)) {
    message.textContent = 'Please select a JPG, PNG or WEBP image.';
    message.className = 'form-message error';
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    preview.innerHTML = `<img src="${reader.result}" alt="Selected issue photo"><button type="button" class="remove-photo" id="removePhoto">×</button>`;
    document.getElementById('removePhoto').onclick = () => {
      cameraInput.value = '';
      galleryInput.value = '';
      preview.innerHTML = '<span>📷</span><p>No photo selected</p>';
    };
  };
  reader.readAsDataURL(file);
}

cameraInput.addEventListener('change', e => {
  if (e.target.files[0]) {
    galleryInput.value = '';
    handleImage(e.target.files[0]);
  }
});
galleryInput.addEventListener('change', e => {
  if (e.target.files[0]) {
    cameraInput.value = '';
    handleImage(e.target.files[0]);
  }
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  message.textContent = '';
  message.className = 'form-message';
  submitBtn.disabled = true;
  submitBtn.textContent = 'Submitting...';

  const description = form.elements.description.value.trim();
  if (!description) {
    message.textContent = 'Please enter a problem description before submitting.';
    message.className = 'form-message error';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Report';
    form.elements.description.focus();
    return;
  }

  const fd = new FormData(form);
  fd.set('description', description);
  const photo = cameraInput.files[0] || galleryInput.files[0];
  if (photo) fd.set('photo', photo);

  try {
    const res = await fetch(API + 'reports.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Could not submit report');
    document.getElementById('reportId').textContent = data.report_id;
    document.getElementById('successModal').classList.remove('hidden');
    form.reset();
    preview.innerHTML = '<span>📷</span><p>No photo selected</p>';
  } catch (err) {
    message.textContent = err.message;
    message.className = 'form-message error';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Report';
  }
});

loadOptions();
