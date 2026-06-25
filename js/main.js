// Bootstrap 5 
document.addEventListener('DOMContentLoaded', function() {

    /* =========================
       BOOTSTRAP
    ========================= */
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(el => new bootstrap.Popover(el));

    // Auto cerrar alertas
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            if (alert.classList.contains('show')) {
                new bootstrap.Alert(alert).close();
            }
        }, 5000);
    });

    /* =========================
       SOLO NÚMEROS — TODOS LOS CAMPOS NUMÉRICOS
       (incluye campos FERUM con inputmode="numeric")
    ========================= */
    document.querySelectorAll('input[inputmode="numeric"]').forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });

        input.addEventListener('keypress', function (e) {
            if (!/\d/.test(e.key)) e.preventDefault();
        });

        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const texto = (e.clipboardData || window.clipboardData).getData('text');
            this.value = texto.replace(/\D/g, '').substring(0, this.maxLength || 999);
        });
    });

    /* =========================
       VALIDACIÓN CÉDULA SOLICITANTE EN TIEMPO REAL 🇪🇨
    ========================= */
    const cedulaInput    = document.getElementById('cedula_ruc');
    const cedulaFeedback = document.getElementById('cedula-feedback');

    if (cedulaInput) {
        cedulaInput.addEventListener('input', function () {
            this.classList.remove('is-valid', 'is-invalid');
            this.setCustomValidity('');
            if (cedulaFeedback) cedulaFeedback.style.display = 'none';
            if (this.value.length < 10) return;

            if (validarCedulaEcuatoriana(this.value)) {
                this.classList.add('is-valid');
            } else {
                this.classList.add('is-invalid');
                this.setCustomValidity('Cédula ecuatoriana inválida');
                if (cedulaFeedback) cedulaFeedback.style.display = 'block';
            }
        });

        cedulaInput.addEventListener('blur', function () {
            if (this.value.length === 10 && !validarCedulaEcuatoriana(this.value)) {
                this.classList.add('is-invalid');
                if (cedulaFeedback) cedulaFeedback.style.display = 'block';
            }
        });
    }

    /* =========================
       VALIDACIÓN CÉDULAS FERUM
       (Presidente y Coordinador)
    ========================= */
    const camposCedulaFerum = [
        {
            input:      document.querySelector('[name="ferum_presidente_cedula"]'),
            feedbackId: 'cedula-presidente-feedback'
        },
        {
            input:      document.querySelector('[name="ferum_coordinador_cedula"]'),
            feedbackId: 'cedula-coordinador-feedback'
        },
    ];

    camposCedulaFerum.forEach(({ input, feedbackId }) => {
        if (!input) return;
        const feedback = document.getElementById(feedbackId);

        input.addEventListener('input', function () {
            this.classList.remove('is-valid', 'is-invalid');
            this.setCustomValidity('');
            if (feedback) feedback.style.display = 'none';
            if (this.value.length < 10) return;

            if (validarCedulaEcuatoriana(this.value)) {
                this.classList.add('is-valid');
            } else {
                this.classList.add('is-invalid');
                this.setCustomValidity('Cédula ecuatoriana inválida');
                if (feedback) {
                    feedback.textContent = 'Cédula ecuatoriana inválida';
                    feedback.style.display = 'block';
                }
            }
        });

        input.addEventListener('blur', function () {
            if (this.value.length === 0) return;

            if (this.value.length < 10) {
                this.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = 'La cédula debe tener 10 dígitos';
                    feedback.style.display = 'block';
                }
                return;
            }

            if (!validarCedulaEcuatoriana(this.value)) {
                this.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = 'Cédula ecuatoriana inválida';
                    feedback.style.display = 'block';
                }
            }
        });
    });

    /* =========================
       VALIDACIÓN CELULARES FERUM
       (Presidente y Coordinador)
    ========================= */
    const camposCelularFerum = [
        {
            input:      document.querySelector('[name="ferum_presidente_celular"]'),
            feedbackId: 'celular-presidente-feedback'
        },
        {
            input:      document.querySelector('[name="ferum_coordinador_celular"]'),
            feedbackId: 'celular-coordinador-feedback'
        },
    ];

    camposCelularFerum.forEach(({ input, feedbackId }) => {
        if (!input) return;
        const feedback = document.getElementById(feedbackId);

        input.addEventListener('input', function () {
            this.classList.remove('is-valid', 'is-invalid');
            if (feedback) feedback.style.display = 'none';
            if (this.value.length < 10) return;

            if (/^09\d{8}$/.test(this.value)) {
                this.classList.add('is-valid');
            } else {
                this.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = 'Debe empezar en 09 y tener 10 dígitos';
                    feedback.style.display = 'block';
                }
            }
        });

        input.addEventListener('blur', function () {
            if (this.value.length === 0) return;

            if (/^09\d{8}$/.test(this.value)) {
                this.classList.add('is-valid');
            } else {
                this.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = 'Debe empezar en 09 y tener 10 dígitos';
                    feedback.style.display = 'block';
                }
            }
        });
    });

    /* =========================
       VALIDACIÓN DE FORMULARIOS AL ENVIAR
    ========================= */
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function (e) {

            let valido = true;

            // Campos requeridos
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    valido = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Validación final cédula solicitante
            if (cedulaInput && cedulaInput.value.length === 10 && !validarCedulaEcuatoriana(cedulaInput.value)) {
                cedulaInput.classList.add('is-invalid');
                valido = false;
            }

            // Validación final cédulas FERUM (solo si tienen valor)
            camposCedulaFerum.forEach(({ input, feedbackId }) => {
                if (!input || input.value.length === 0) return;
                const feedback = document.getElementById(feedbackId);
                if (!validarCedulaEcuatoriana(input.value)) {
                    input.classList.add('is-invalid');
                    if (feedback) {
                        feedback.textContent = 'Cédula ecuatoriana inválida';
                        feedback.style.display = 'block';
                    }
                    valido = false;
                }
            });

            // Validación final celulares FERUM (solo si tienen valor)
            camposCelularFerum.forEach(({ input, feedbackId }) => {
                if (!input || input.value.length === 0) return;
                const feedback = document.getElementById(feedbackId);
                if (!/^09\d{8}$/.test(input.value)) {
                    input.classList.add('is-invalid');
                    if (feedback) {
                        feedback.textContent = 'Debe empezar en 09 y tener 10 dígitos';
                        feedback.style.display = 'block';
                    }
                    valido = false;
                }
            });

            if (!valido) {
                e.preventDefault();
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });
    });

    /* =========================
        VALIDACIÓN ARCHIVOS 
    ========================= */
const MAX_SIZE = 50 * 1024 * 1024; // 50 MB
const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'zip'];

document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function () {
        const files = Array.from(this.files);
        if (!files.length) return;

        let errores = [];
        files.forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            if (file.size > MAX_SIZE)    errores.push(`❌ "${file.name}" supera 50 MB`);
            if (!ALLOWED_EXT.includes(ext)) errores.push(`❌ "${file.name}" tipo no permitido`);
        });

        if (errores.length) {
            alert(errores.join('\n') + '\n\n📄 Formatos aceptados: PDF, Word, Excel, JPG, PNG, ZIP');
            this.value = '';
            this.classList.add('is-invalid');
            return;
        }

        this.classList.remove('is-invalid');
        this.classList.add('is-valid');

        // Mostrar nombres en el label correspondiente
        const nameMap = {
            archivoInput:       'fileName',
            archivoInputFerum:  'fileNameFerum',
            croquisInput:       'croquisName',
            gadInput:           'gadName',
            beneficiariosInput: 'beneficiariosName'
        };
        const spanId = nameMap[this.id];
        if (spanId) {
            const span = document.getElementById(spanId);
            if (span) span.textContent = files.map(f => '📎 ' + f.name).join('  ');
        }
    });
});

});

/* =========================
   FUNCIÓN CÉDULA ECUATORIANA 🇪🇨
========================= */
function validarCedulaEcuatoriana(cedula) {
    if (!/^\d{10}$/.test(cedula)) return false;

    const provincia = parseInt(cedula.substring(0, 2));
    if (provincia < 1 || provincia > 24) return false;

    const coef = [2,1,2,1,2,1,2,1,2];
    let suma = 0;

    for (let i = 0; i < 9; i++) {
        let val = parseInt(cedula[i]) * coef[i];
        if (val >= 10) val -= 9;
        suma += val;
    }

    const digito = (10 - (suma % 10)) % 10;
    return digito == parseInt(cedula[9]);
}