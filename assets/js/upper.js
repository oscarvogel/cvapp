(function(){
  function toUpperCaseInput(e){
    const el = e.target;
    if (!el) return;

    const tag = el.tagName.toLowerCase();

    // Excluir campos específicos o con clase especial
    if (el.classList.contains('no-uppercase')) {
      return; // No hacer nada si tiene la clase 'no-uppercase'
    }

    let type = '';
    if (tag === 'input') {
      type = (el.getAttribute('type') || 'text').toLowerCase();
    }

    // Tipos de input a excluir (además de los convencionales como email, password)
    const excludedInputTypes = ['email', 'password', 'number', 'file', 'checkbox', 'radio', 'hidden', 'submit', 'reset', 'button'];
    if (excludedInputTypes.includes(type)) {
      return; // No hacer nada si es un tipo excluido
    }

    // Solo afectar inputs de texto, textarea y tipos de texto permitidos
    const allowedInputTypes = ['text', 'search', 'tel', 'url', 'textarea']; // 'text' está implícito si no tiene type
    if (!(tag === 'textarea' || (tag === 'input' && allowedInputTypes.includes(type)))) {
      return;
    }

    // Guardar la posición del cursor antes de cambiar el valor
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const oldValue = el.value;

    // Convertir valor a mayúsculas
    el.value = el.value.toUpperCase();

    // Intentar restaurar la posición del cursor
    // Solo si la longitud del texto no cambió drásticamente (por ejemplo, al pegar)
    if (el.value.length === oldValue.length) {
      try {
        el.setSelectionRange(start, end);
      } catch (err) {
        console.warn("No se pudo restaurar la posición del cursor:", err); // Opcional: para debug
      }
    } else {
      // Si la longitud cambió (por ejemplo, al pegar), mover el cursor al final
      try {
        el.setSelectionRange(el.value.length, el.value.length);
      } catch (err) {
        console.warn("No se pudo mover el cursor al final:", err); // Opcional: para debug
      }
    }
  }

  // Escuchar el evento 'input' para cambios generales (teclado, cortar, pegar, etc.)
  document.addEventListener('input', toUpperCaseInput, true);

  // Escuchar el evento 'paste' para manejar específicamente el pegado
  document.addEventListener('paste', function(e){
    const el = e.target;
    if (!el) return;

    // Aplicar la misma lógica de exclusión que en 'input'
    if (el.classList.contains('no-uppercase')) {
      return;
    }

    const tag = el.tagName ? el.tagName.toLowerCase() : '';
    let type = '';
    if (tag === 'input') {
      type = (el.getAttribute('type') || 'text').toLowerCase();
    }

    const excludedInputTypes = ['email', 'password', 'number', 'file', 'checkbox', 'radio', 'hidden', 'submit', 'reset', 'button'];
    if (excludedInputTypes.includes(type) || tag === 'select') { // Añadido 'select' como campo no editable en texto
      return;
    }

    const allowedInputTypes = ['text', 'search', 'tel', 'url', 'textarea'];
    if (!(tag === 'textarea' || (tag === 'input' && allowedInputTypes.includes(type)))) {
      return;
    }

    // El cambio real se hará en el evento 'input' que se dispara después de pegar.
    // Aquí no es necesario hacer nada más, ya que 'input' lo manejará.
    // Si se quiere hacer inmediatamente al pegar (antes de 'input'), se puede usar setTimeout.
    // setTimeout(()=>{
    //   if (el && el.value) {
    //     const start = el.selectionStart;
    //     const end = el.selectionEnd;
    //     el.value = el.value.toUpperCase();
    //     try { el.setSelectionRange(start, end); } catch (err) {} // Ajustar cursor si es posible
    //   }
    // }, 0);
    // Pero usar 'input' es más estándar y eficiente para este caso.
  }, true);
})();