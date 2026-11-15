// Read the token rendered into the shared authentication form.
const csrf = document.querySelector('input[name="csrf"]')?.value || '';
const authForm = document.getElementById('authForm');
const authError = document.getElementById('authError');

// Keep the authentication modal fields in sync with the selected mode.
const setAuthMode = mode => {
  const register = mode === 'register';
  authForm.mode.value = mode;
  document.getElementById('authTitle').textContent = register ? 'Join us' : 'Welcome back';
  document.getElementById('nameField').classList.toggle('d-none', !register);
  authForm.name.required = register;
  authForm.password.autocomplete = register ? 'new-password' : 'current-password';
  document.getElementById('switchAuth').textContent = register
    ? 'Already have an account? Sign in'
    : 'New here? Create an account';
  authError.textContent = '';
  authError.classList.add('d-none');
};
document.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', () => setAuthMode(button.dataset.mode)));

// Show server and client-side errors without interrupting the current page.
const toast = (message, error = false) => {
  const element = document.getElementById('toast');
  element.querySelector('.toast-body').textContent = message;
  element.classList.toggle('text-bg-danger', error);
  bootstrap.Toast.getOrCreateInstance(element).show();
};

// Send all state-changing requests through the shared CSRF-protected endpoint.
const send = async (action, data) => {
  const body = data instanceof FormData ? data : new URLSearchParams(data);
  body.set('csrf', csrf);
  const response = await fetch('?action=' + action, {method: 'POST', body});
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || 'Something went wrong.');
  return result;
};

// Handle buttons for actions such as signing out or deleting a record.
document.querySelectorAll('.ajax-action').forEach(button => button.addEventListener('click', async () => {
  if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) return;
  try {
    const data = {...button.dataset};
    delete data.action;
    delete data.confirm;
    await send(button.dataset.action, data);
    if (button.dataset.reload === 'page') location.reload();
    else location.href = './';
  } catch (error) {
    toast(error.message, true);
  }
}));

// Validate and submit the login and registration form.
document.getElementById('switchAuth')?.addEventListener('click', () => setAuthMode(authForm.mode.value === 'login' ? 'register' : 'login'));
authForm?.addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.target;
  const register = form.mode.value === 'register';
  const name = form.name.value.trim();
  const email = form.email.value.trim();
  const password = form.password.value;
  authError.classList.add('d-none');
  authError.textContent = '';
  if ((register && !name) || !email || !email.includes('@') || !email.includes('.') || !password || password.length < 6) {
    authError.textContent = register && !name
      ? 'Please enter your name.'
      : !email
        ? 'Please enter your email address.'
        : !email.includes('@') || !email.includes('.')
          ? 'Please enter a valid email address.'
          : !password
            ? 'Please enter your password.'
            : 'Password must be at least 6 characters.';
    authError.classList.remove('d-none');
    return;
  }
  try {
    const result = await send(form.mode.value, Object.fromEntries(new FormData(form)));
    location.href = result.redirect;
  } catch (error) {
    authError.textContent = error.message;
    authError.classList.remove('d-none');
  }
});

// Submit forms that use the AJAX action convention, including rich-text editors.
document.querySelectorAll('.ajax-form').forEach(form => form.addEventListener('submit', async event => {
  event.preventDefault();
  try {
    const data = new FormData(form);
    if (form.editor) data.set('description', form.editor.value);
    const result = await send(form.dataset.action, data);
    location.href = result.redirect || window.location.href;
  } catch (error) {
    toast(error.message, true);
  }
}));