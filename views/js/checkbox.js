
document.addEventListener('DOMContentLoaded', function () {
  var cb = document.getElementById('arc_checkbox');
  if (!cb) return;

  var confirmBtn = document.querySelector('#payment-confirmation button, button[name="confirm-addresses"]');
  var toggleBtn = function () {
    if (!confirmBtn) return;
    confirmBtn.disabled = !cb.checked;
  };
  toggleBtn();

  cb.addEventListener('change', function () {
    toggleBtn();
    var xhr = new XMLHttpRequest();
    var base = (window.prestashop && prestashop.urls && prestashop.urls.base_url) ? prestashop.urls.base_url : '/';
    var url = base + 'index.php?fc=module&module=atechrefurbconsent&controller=save';
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('accepted=' + (cb.checked ? '1' : '0'));
  });
});
