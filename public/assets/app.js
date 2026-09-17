document.querySelectorAll('[data-scroll]').forEach((link) => link.addEventListener('click', (event) => {
  const target = document.querySelector(link.getAttribute('href'));
  if (target) { event.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
}));

document.querySelectorAll('[data-reveal-password]').forEach((button) => button.addEventListener('click', () => {
  const field = document.querySelector(button.dataset.revealPassword);
  field.type = field.type === 'password' ? 'text' : 'password';
  button.textContent = field.type === 'password' ? 'Show' : 'Hide';
}));

document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(button.dataset.copy); button.textContent = 'Copied'; setTimeout(() => { button.textContent = 'Copy link'; }, 1500); } catch { window.prompt('Copy this link:', button.dataset.copy); }
}));

const uploadInput = document.querySelector('#certificateFile');
if (uploadInput) uploadInput.addEventListener('change', () => {
  const label = document.querySelector('#selectedFile');
  label.textContent = uploadInput.files[0] ? uploadInput.files[0].name : 'PDF, JPG, or PNG · maximum 5 MB';
});
