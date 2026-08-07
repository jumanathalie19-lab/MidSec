<script>
function selectRole(el, role) {
  document.querySelectorAll('.role-pill').forEach(r => r.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('selectedRole').value = role;
  document.getElementById('loginBtn').textContent = 'Sign in as ' + el.textContent;
}

function showMsg(text, type) {
  const msg = document.getElementById('msg');
  msg.textContent = text;
  msg.className = 'msg ' + type;
  msg.style.display = 'block';
}

function doLogin() {
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value.trim();
  const btn      = document.getElementById('loginBtn');

  if (!email || !password) {
    showMsg('Please enter your email and password.', 'error');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Signing in...';

  const formData = new FormData();
  formData.append('action',   'login');
  formData.append('email',    email);
  formData.append('password', password);

  fetch('../../controllers/AuthController.php', {
    method: 'POST',
    body:   formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showMsg('Login successful! Redirecting...', 'success');
      setTimeout(() => { window.location.href = data.redirect; }, 800);
    } else {
      showMsg(data.message || 'Login failed. Try again.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Sign in as ' + document.querySelector('.role-pill.on').textContent;
    }
  })
  .catch(() => {
    showMsg('Connection error. Check your server is running.', 'error');
    btn.disabled    = false;
    btn.textContent = 'Sign in';
  });
}

// Allow Enter key to submit
document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>