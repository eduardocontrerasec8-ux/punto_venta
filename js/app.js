// Inicializar componentes de Materialize
document.addEventListener('DOMContentLoaded', function() {
    // Sidenav para móviles
    var sidenav = document.querySelectorAll('.sidenav');
    M.Sidenav.init(sidenav);
    
    // Dropdown
    var dropdown = document.querySelectorAll('.dropdown-trigger');
    M.Dropdown.init(dropdown);
    
    // Modals
    var modals = document.querySelectorAll('.modal');
    M.Modal.init(modals);
    
    // Select
    var selects = document.querySelectorAll('select');
    M.FormSelect.init(selects);
    
    // Datepicker
    var datepickers = document.querySelectorAll('.datepicker');
    M.Datepicker.init(datepickers, {
        format: 'yyyy-mm-dd',
        i18n: {
            months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
            monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
            weekdays: ["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"],
            weekdaysShort: ["Dom","Lun","Mar","Mié","Jue","Vie","Sáb"],
            weekdaysAbbrev: ["D","L","M","M","J","V","S"],
            cancel: "Cancelar",
            clear: "Limpiar",
            done: "Aceptar"
        }
    });
});

// Función para confirmar eliminaciones
function confirmarEliminacion(mensaje) {
    return confirm(mensaje || '¿Está seguro de eliminar este registro?');
}

// Función para formatear moneda
function formatoMoneda(numero) {
    return '$' + parseFloat(numero).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}