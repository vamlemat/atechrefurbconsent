
(function() {
  'use strict';
  
  console.log('[ARC] Módulo de consentimiento cargado');
  
  var cb = null;
  var confirmBtn = null;
  var formBlocked = false;
  
  // Función para buscar el checkbox
  function findCheckbox() {
    cb = document.getElementById('arc_checkbox');
    if (cb) {
      console.log('[ARC] Checkbox encontrado:', cb);
      return true;
    }
    console.warn('[ARC] Checkbox NO encontrado');
    return false;
  }
  
  // Función para buscar el botón de confirmación
  function findConfirmButton() {
    var selectors = [
      '#payment-confirmation button[type="submit"]',
      'button[type="submit"][name="confirmDeliveryOption"]',
      '.payment-confirmation button',
      '#js-delivery button[type="submit"]',
      'button[name="confirm-addresses"]',
      '.js-payment-confirmation button'
    ];
    
    for (var i = 0; i < selectors.length; i++) {
      var btn = document.querySelector(selectors[i]);
      if (btn && btn.offsetParent !== null) { // Verificar que sea visible
        confirmBtn = btn;
        console.log('[ARC] Botón de confirmación encontrado con selector:', selectors[i], btn);
        return true;
      }
    }
    
    console.warn('[ARC] Botón de confirmación NO encontrado con selectores específicos.');
    console.log('[ARC] Intentando buscar botones submit, excluyendo login...');
    
    var allSubmitBtns = document.querySelectorAll('button[type="submit"]');
    console.log('[ARC] Botones submit encontrados:', allSubmitBtns.length, allSubmitBtns);
    
    // Filtrar botones de login, búsqueda, etc.
    var excludeIds = ['submit-login', 'submit_search', 'submitNewsletter'];
    var excludeSelectors = [
      '#login-form button',
      '#customer-form button',
      '.user-login button',
      '.login-form button'
    ];
    
    for (var i = allSubmitBtns.length - 1; i >= 0; i--) {
      var btn = allSubmitBtns[i];
      var isExcluded = false;
      
      // Verificar ID
      if (btn.id && excludeIds.indexOf(btn.id) !== -1) {
        console.log('[ARC] Botón excluido por ID:', btn.id);
        continue;
      }
      
      // Verificar si coincide con selectores de exclusión
      for (var j = 0; j < excludeSelectors.length; j++) {
        if (btn.matches && btn.matches(excludeSelectors[j])) {
          console.log('[ARC] Botón excluido por selector:', excludeSelectors[j]);
          isExcluded = true;
          break;
        }
      }
      
      if (!isExcluded && btn.offsetParent !== null) {
        confirmBtn = btn;
        console.log('[ARC] Botón submit válido encontrado:', btn);
        return true;
      }
    }
    
    console.warn('[ARC] No se encontró ningún botón submit válido');
    return false;
  }
  
  // Función para habilitar/deshabilitar botón
  function toggleButton() {
    if (!cb) return;
    
    var shouldEnable = cb.checked;
    
    if (confirmBtn) {
      confirmBtn.disabled = !shouldEnable;
      console.log('[ARC] Botón', shouldEnable ? 'HABILITADO' : 'DESHABILITADO');
    } else {
      console.warn('[ARC] No se puede toggle el botón porque no se encontró');
    }
  }
  
  // Función para guardar consentimiento vía AJAX
  function saveConsent(accepted) {
    console.log('[ARC] Guardando consentimiento:', accepted ? 'SÍ' : 'NO');
    
    var xhr = new XMLHttpRequest();
    var base = (window.prestashop && prestashop.urls && prestashop.urls.base_url) 
      ? prestashop.urls.base_url 
      : window.location.origin + '/';
    var url = base + 'index.php?fc=module&module=atechrefurbconsent&controller=save';
    
    xhr.onload = function() {
      if (xhr.status === 200) {
        try {
          var resp = JSON.parse(xhr.responseText);
          if (resp.ok) {
            console.log('[ARC] ✓ Consentimiento guardado correctamente:', resp);
            toggleButton();
          } else {
            console.error('[ARC] ✗ Error al guardar consentimiento:', resp);
            var errorMsg = 'Error al guardar el consentimiento';
            if (resp.error) {
              errorMsg += ': ' + resp.error;
              console.error('[ARC] Detalles del error:', resp);
            }
            alert(errorMsg + '\n\nAbre la consola (F12) para ver más detalles.');
            cb.checked = false;
            toggleButton();
          }
        } catch (e) {
          console.error('[ARC] ✗ Error parsing response:', e);
          console.error('[ARC] Respuesta del servidor:', xhr.responseText);
          alert('Error al procesar la respuesta del servidor.\n\nAbre la consola (F12) para ver más detalles.');
          cb.checked = false;
          toggleButton();
        }
      } else {
        console.error('[ARC] ✗ HTTP Error:', xhr.status);
        console.error('[ARC] Respuesta:', xhr.responseText);
        alert('Error HTTP ' + xhr.status + '.\n\nAbre la consola (F12) para ver más detalles.');
        cb.checked = false;
        toggleButton();
      }
    };
    
    xhr.onerror = function() {
      console.error('[ARC] ✗ Error de red al guardar consentimiento');
      alert('Error de conexión. Por favor, verifique su conexión e inténtelo de nuevo.');
      cb.checked = false;
      toggleButton();
    };
    
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('accepted=' + (accepted ? '1' : '0'));
  }
  
  // Bloquear formularios como fallback
  function blockFormSubmit(e) {
    if (!cb || !cb.checked) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      alert('Debes aceptar la condición obligatoria para productos reacondicionados antes de continuar.');
      console.warn('[ARC] ✗ Formulario bloqueado - checkbox no marcado');
      
      // Scroll al checkbox
      if (cb) {
        cb.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var label = cb.closest('label');
        if (label) {
          label.style.backgroundColor = '#ffe6e6';
          setTimeout(function() {
            label.style.backgroundColor = '';
          }, 2000);
        }
      }
      
      return false;
    }
    console.log('[ARC] ✓ Formulario permitido - checkbox marcado');
    return true;
  }
  
  // Inicializar cuando el DOM esté listo
  function init() {
    console.log('[ARC] Inicializando...');
    
    if (!findCheckbox()) {
      console.log('[ARC] Checkbox no presente en esta página, saliendo...');
      return;
    }
    
    // Buscar botón inmediatamente
    findConfirmButton();
    
    // Deshabilitar botón inicialmente
    toggleButton();
    
    // Listener para cambios en el checkbox
    cb.addEventListener('change', function() {
      var isChecked = cb.checked;
      console.log('[ARC] Checkbox cambiado a:', isChecked);
      
      // Deshabilitar botón mientras guarda
      if (confirmBtn) {
        confirmBtn.disabled = true;
      }
      
      saveConsent(isChecked);
    });
    
    // Intentar buscar el botón varias veces (por si se carga con AJAX)
    var attempts = 0;
    var maxAttempts = 10;
    var interval = setInterval(function() {
      attempts++;
      if (!confirmBtn) {
        console.log('[ARC] Reintentando buscar botón... intento', attempts);
        if (findConfirmButton()) {
          toggleButton();
          clearInterval(interval);
        }
      }
      
      if (attempts >= maxAttempts) {
        console.warn('[ARC] No se pudo encontrar el botón después de', maxAttempts, 'intentos');
        clearInterval(interval);
      }
    }, 500);
    
    // Bloquear TODOS los formularios como fallback
    setTimeout(function() {
      var forms = document.querySelectorAll('form');
      console.log('[ARC] Bloqueando', forms.length, 'formularios como fallback');
      
      forms.forEach(function(form) {
        form.addEventListener('submit', blockFormSubmit, true);
      });
      
      // Listener global para clicks en botones submit
      document.addEventListener('click', function(e) {
        var target = e.target;
        if (target.tagName === 'BUTTON' && (target.type === 'submit' || target.getAttribute('type') === 'submit')) {
          if (!cb || !cb.checked) {
            console.warn('[ARC] Click en botón submit bloqueado');
            return blockFormSubmit(e);
          }
        }
      }, true);
      
      formBlocked = true;
      console.log('[ARC] ✓ Fallback de bloqueo activado');
    }, 1000);
  }
  
  // Esperar a que el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  
})();
