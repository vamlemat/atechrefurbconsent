
document.addEventListener('DOMContentLoaded', function () {
  var cb = document.getElementById('arc_checkbox');
  if (!cb) return;

  // Buscar botón de confirmación con selectores más robustos
  var confirmBtn = document.querySelector(
    '#payment-confirmation button[type="submit"], ' +
    'button[name="confirm-addresses"], ' +
    '.js-payment-confirmation button, ' +
    '#payment-confirmation .btn-primary'
  );
  
  var toggleBtn = function () {
    if (!confirmBtn) return;
    confirmBtn.disabled = !cb.checked;
  };
  toggleBtn();

  cb.addEventListener('change', function () {
    var isChecked = cb.checked;
    
    // Deshabilitar botón mientras se guarda
    if (confirmBtn) {
      confirmBtn.disabled = true;
    }
    
    var xhr = new XMLHttpRequest();
    var base = (window.prestashop && prestashop.urls && prestashop.urls.base_url) ? prestashop.urls.base_url : '/';
    var url = base + 'index.php?fc=module&module=atechrefurbconsent&controller=save';
    
    xhr.onload = function() {
      if (xhr.status === 200) {
        try {
          var resp = JSON.parse(xhr.responseText);
          if (resp.ok) {
            console.log('[ARC] Consentimiento guardado correctamente:', resp);
            toggleBtn(); // Solo habilitar si guardó bien
          } else {
            console.error('[ARC] Error al guardar consentimiento:', resp);
            alert('Error al guardar el consentimiento. Por favor, inténtelo de nuevo.');
            cb.checked = false; // Desmarcar si falló
            toggleBtn();
          }
        } catch (e) {
          console.error('[ARC] Error parsing response:', e);
          cb.checked = false;
          toggleBtn();
        }
      } else {
        console.error('[ARC] HTTP Error:', xhr.status);
        cb.checked = false;
        toggleBtn();
      }
    };
    
    xhr.onerror = function() {
      console.error('[ARC] Error de red al guardar consentimiento');
      alert('Error de conexión. Por favor, verifique su conexión e inténtelo de nuevo.');
      cb.checked = false;
      toggleBtn();
    };
    
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('accepted=' + (isChecked ? '1' : '0'));
  });
});
