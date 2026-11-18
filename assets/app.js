// Confirmações, edição de anotações e textareas que crescem sozinhas.
document.addEventListener('click', function (e) {
  var c = e.target.closest('[data-confirma]');
  if (c && !confirm(c.getAttribute('data-confirma'))) { e.preventDefault(); return; }
  var ed = e.target.closest('[data-editar]');
  if (ed) {
    var n = ed.closest('.nota');
    n.querySelector('.nota-corpo').hidden = true;
    var f = n.querySelector('.nota-edit'); f.hidden = false;
    var t = f.querySelector('textarea'); cresce(t); t.focus();
  }
  var ca = e.target.closest('[data-cancelar]');
  if (ca) {
    var n2 = ca.closest('.nota');
    n2.querySelector('.nota-corpo').hidden = false;
    n2.querySelector('.nota-edit').hidden = true;
  }
});
function cresce(t) { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; }
document.querySelectorAll('textarea[data-auto]').forEach(function (t) {
  cresce(t);
  t.addEventListener('input', function () { cresce(t); var f = t.closest('form'); if (f) f.classList.add('sujo'); });
  t.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') t.form.requestSubmit(); });
});
var drop = document.querySelector('.drop input');
if (drop) drop.addEventListener('change', function () { if (drop.files[0]) drop.nextElementSibling.textContent = drop.files[0].name; });
