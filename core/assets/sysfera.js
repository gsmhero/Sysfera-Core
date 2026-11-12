/* Sysfera: tiny helpers. Forms/buttons with data-confirm ask before submitting. */
document.addEventListener('submit', function (e) {
  var f = e.target, sub = e.submitter, msg = (sub && sub.dataset.confirm) || f.dataset.confirm;
  if (msg && !window.confirm(msg)) e.preventDefault();
}, true);
