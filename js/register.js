<script>
function toggleHouse() {
  const role        = document.getElementById('role').value;
  const houseField  = document.getElementById('houseField');
  houseField.style.display = (role === 'resident') ? 'block' : 'none';
}

function showMsg(text, type) {
  const msg = document.getElementById('msg');
  msg.textContent    = text;
  msg.className      = 'msg ' + type;
  msg.style.display  = 'block';
  msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function doRegister() {
  const first_name       = document.getElementById('first_name').value.trim();
  const last_name        = document.getElementById('last_name').value.trim();
  const email            = document.getElementById('email').value.trim();
  const phone_no         = document.getElementById('phone_no').value.trim();
  const role             = document.getElementById('role').value;
  const house_no         = document.getElementById('house_no').value.trim();
  const password         = document.getElementById('password').value.trim();
  const confirm_password = document.getElementById('confirm_password').value.trim();
  const btn              = document.getElementById('registerBtn');

  if (!first_name || !last_name || !email || !phone_no || !password) {
    showMsg('All fields are required.', 'error'); return;
  }
  if (password !== confirm_password) {
    showMsg('Passwords do not match.', 'error'); return;
  }
  if (password.length < 6) {
    showMsg('Password must be at least 6 characters.', 'error'); return;
  }
  if (role === 'resident' && !house_no) {
    showMsg('House number is required for residents.', 'error'); return;
  }

  btn.disabled    = true;
  btn.textContent = 'Creating account...';

  const formData = new FormData();
  formData.append('action',     'register');
  formData.append('first_name', first_name);
  formData.append('last_name',  last_name);
  formData.append('email',      email);
  formData.append('phone_no',   phone_no);
  formData.append('password',   password);
  formData.append('role',       role);
  formData.append('house_no',   house_no);

  fetch('../../controllers/AuthController.php', {
    method: 'POST',
    body:   formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showMsg('Account created! Redirecting to login...', 'success');
      setTimeout(() => { window.location.href = 'login.php'; }, 1200);
    } else {
      showMsg(data.message || 'Registration failed. Try again.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Create account';
    }
  })
  .catch(() => {
    showMsg('Connection error. Check your server is running.', 'error');
    btn.disabled    = false;
    btn.textContent = 'Create account';
  });
}
</script>