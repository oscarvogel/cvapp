// Este archivo ha sido deshabilitado porque las validaciones del formulario
// ahora se manejan directamente en index.php
// Si necesitas validaciones adicionales, agrégalas en el DOMContentLoaded de index.php

/*
(function(){
  const form = document.getElementById('cvForm');
  if (!form) return;
  const fileInput = document.getElementById('cv');
  const maxBytes = 5 * 1024 * 1024;
  const allowed = ['pdf','doc','docx'];

  form.addEventListener('submit', function(e){
    const f = fileInput.files[0];
    if (!f) { 
      Swal.fire({ text: 'Adjunta tu CV.', icon: 'warning' });
      e.preventDefault(); return; 
    }
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!allowed.includes(ext)) {
      Swal.fire({ text: 'Tipo de archivo no permitido. Solo PDF, DOC, DOCX.', icon: 'error' });
      e.preventDefault(); return;
    }
    if (f.size > maxBytes) {
      Swal.fire({ text: 'El archivo supera 5MB.', icon: 'error' });
      e.preventDefault(); return;
    }
    // Validación básica de email/teléfono
    const email = document.getElementById('email').value.trim();
    const tel = document.getElementById('telefono').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      Swal.fire({ text: 'Email inválido.', icon: 'warning' });
      e.preventDefault(); return;
    }
    if (!/^[0-9+\s().-]{7,25}$/.test(tel)) {
      Swal.fire({ text: 'Teléfono inválido.', icon: 'warning' });
      e.preventDefault(); return;
    }
  }, false);
})();
*/

console.log('📄 app.js cargado (validaciones deshabilitadas - se usan las de index.php)');
