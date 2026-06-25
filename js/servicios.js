document.addEventListener('DOMContentLoaded', function(){

    const encargado = document.getElementById('encargado');
    const servicio = document.getElementById('tipo_servicio');

    if(!encargado || !servicio) return;

    encargado.addEventListener('change', function(){

        let encargado_id = this.value;

        servicio.innerHTML = '<option value="">Cargando...</option>';

        if(encargado_id === ''){
            servicio.innerHTML = '<option value="">Seleccione un servicio</option>';
            return;
        }

        fetch('get_servicios.php?encargado_id=' + encargado_id)
        .then(res => res.json())
        .then(data => {

            servicio.innerHTML = '<option value="">Seleccione un servicio</option>';

            if(data.length === 0){
                servicio.innerHTML += '<option>No hay servicios</option>';
                return;
            }

            data.forEach(s => {
                let option = document.createElement('option');
                option.value = s.nombre;
                option.textContent = s.nombre;
                servicio.appendChild(option);
            });

        })
        .catch(err => {
            console.error(err);
            servicio.innerHTML = '<option>Error al cargar</option>';
        });

    });

});