<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva de Solicitudes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configuración opcional de Tailwind (para colores, etc.)
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#0A4D8B', // Un azul como ejemplo
                        'secondary': '#E0E0E0',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">

    <div class="max-w-5xl mx-auto p-6 sm:p-10">

        <h1 class="text-3xl font-bold tracking-tight text-gray-900 mb-8">Carga Masiva de Solicitudes</h1>

        <form action="{{ route('solicitud.masiva.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="bg-gray-800 text-white p-4">
                    <h2 class="text-lg font-semibold">1. Datos Comunes de la Solicitud</h2>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="fecha_conflicto" class="block text-sm font-medium text-gray-700">Fecha de Conflicto *</label>
                            <input type="date" name="fecha_conflicto" id="fecha_conflicto" value="{{ old('fecha_conflicto') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="tipo_solicitud_id" class="block text-sm font-medium text-gray-700">Tipo de Solicitud *</label>
                            <select name="tipo_solicitud_id" id="tipo_solicitud_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @foreach($tipo_solicitudes ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('tipo_solicitud_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="giro_comercial_id" class="block text-sm font-medium text-gray-700">Giro Comercial *</label>
                            <select name="giro_comercial_id" id="giro_comercial_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @foreach($giros_comerciales ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('giro_comercial_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2">
                            <label for="objeto_solicitudes" class="block text-sm font-medium text-gray-700">Objeto(s) de Solicitud *</label>
                            <select name="objeto_solicitudes[]" id="objeto_solicitudes" multiple required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @foreach($objeto_solicitudes ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ (is_array(old('objeto_solicitudes')) && in_array($id, old('objeto_solicitudes'))) ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Modalidad de Audiencia *</label>
                            <div class="mt-2 flex items-center space-x-6">
                                <div class="flex items-center">
                                    <input id="virtual_no" name="virtual" type="radio" value="0" {{ old('virtual', '0') == '0' ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <label for="virtual_no" class="ml-2 block text-sm text-gray-900">Presencial</label>
                                </div>
                                <div class="flex items-center">
                                    <input id="virtual_si" name="virtual" type="radio" value="1" {{ old('virtual') == '1' ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <label for="virtual_si" class="ml-2 block text-sm text-gray-900">Virtual</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="bg-gray-800 text-white p-4">
                    <h2 class="text-lg font-semibold">2. Datos del Solicitante (Único)</h2>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo de Persona *</label>
                        <div class="mt-2 flex items-center space-x-6">
                            <div class="flex items-center">
                                <input id="tipo_persona_fisica" name="solicitante[tipo_persona_id]" type="radio" value="1" {{ old('solicitante.tipo_persona_id', '1') == '1' ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <label for="tipo_persona_fisica" class="ml-2 block text-sm text-gray-900">Persona Física</label>
                            </div>
                            <div class="flex items-center">
                                <input id="tipo_persona_moral" name="solicitante[tipo_persona_id]" type="radio" value="2" {{ old('solicitante.tipo_persona_id') == '2' ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <label for="tipo_persona_moral" class="ml-2 block text-sm text-gray-900">Persona Moral</label>
                            </div>
                        </div>
                    </div>

                    <div id="persona_moral_fields" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-2">
                                <label for="solicitante_nombre_comercial" class="block text-sm font-medium text-gray-700">Razón Social *</label>
                                <input type="text" name="solicitante[nombre_comercial]" id="solicitante_nombre_comercial" value="{{ old('solicitante.nombre_comercial') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="solicitante_rfc_moral" class="block text-sm font-medium text-gray-700">RFC (Moral) *</label>
                                <input type="text" name="solicitante[rfc]" id="solicitante_rfc_moral" value="{{ old('solicitante.rfc') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <div id="persona_fisica_fields" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="solicitante_nombre" class="block text-sm font-medium text-gray-700">Nombre(s) *</label>
                                <input type="text" name="solicitante[nombre]" id="solicitante_nombre" value="{{ old('solicitante.nombre') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="solicitante_primer_apellido" class="block text-sm font-medium text-gray-700">Primer Apellido *</label>
                                <input type="text" name="solicitante[primer_apellido]" id="solicitante_primer_apellido" value="{{ old('solicitante.primer_apellido') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="solicitante_segundo_apellido" class="block text-sm font-medium text-gray-700">Segundo Apellido</label>
                                <input type="text" name="solicitante[segundo_apellido]" id="solicitante_segundo_apellido" value="{{ old('solicitante.segundo_apellido') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                <label for="solicitante_curp" class="block text-sm font-medium text-gray-700">CURP</label>
                                <input type="text" name="solicitante[curp]" id="solicitante_curp" value="{{ old('solicitante.curp') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="solicitante_rfc_fisica" class="block text-sm font-medium text-gray-700">RFC (Física)</label>
                                <input type="text" name="solicitante[rfc]" id="solicitante_rfc_fisica" value="{{ old('solicitante.rfc') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <h3 class="text-base font-medium text-gray-800">Datos de Contacto del Solicitante</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="solicitante_telefono" class="block text-sm font-medium text-gray-700">Teléfono Celular *</label>
                            <input type="tel" name="solicitante[contactos][0][contacto]" id="solicitante_telefono" value="{{ old('solicitante.contactos.0.contacto') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <input type="hidden" name="solicitante[contactos][0][tipo_contacto_id]" value="1"> </div>
                         <div>
                            <label for="solicitante_email" class="block text-sm font-medium text-gray-700">Email *</label>
                            <input type="email" name="solicitante[contactos][1][contacto]" id="solicitante_email" value="{{ old('solicitante.contactos.1.contacto') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <input type="hidden" name="solicitante[contactos][1][tipo_contacto_id]" value="3"> </div>
                    </div>
                    
                    <hr class="border-gray-200">

                    <h3 class="text-base font-medium text-gray-800">Domicilio del Solicitante *</h3>
                     <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="solicitante_estado" class="block text-sm font-medium text-gray-700">Estado *</label>
                            <select name="solicitante[domicilios][0][estado_id]" id="solicitante_estado" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @foreach($estados ?? [] as $estado)
                                    <option value="{{ $estado->id }}" {{ old('solicitante.domicilios.0.estado_id') == $estado->id ? 'selected' : '' }}>{{ $estado->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="solicitante_municipio" class="block text-sm font-medium text-gray-700">Municipio / Alcaldía *</label>
                            <input type="text" name="solicitante[domicilios][0][municipio]" id="solicitante_municipio" value="{{ old('solicitante.domicilios.0.municipio') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="solicitante_cp" class="block text-sm font-medium text-gray-700">Código Postal *</label>
                            <input type="text" name="solicitante[domicilios][0][cp]" id="solicitante_cp" value="{{ old('solicitante.domicilios.0.cp') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="solicitante_tipo_vialidad" class="block text-sm font-medium text-gray-700">Tipo de Vialidad *</label>
                            <select name="solicitante[domicilios][0][tipo_vialidad_id]" id="solicitante_tipo_vialidad" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @foreach($tipo_vialidades ?? [] as $vialidad)
                                    <option value="{{ $vialidad->id }}" {{ old('solicitante.domicilios.0.tipo_vialidad_id') == $vialidad->id ? 'selected' : '' }}>{{ $vialidad->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="solicitante_vialidad" class="block text-sm font-medium text-gray-700">Nombre de la Vialidad o Calle *</label>
                            <input type="text" name="solicitante[domicilios][0][vialidad]" id="solicitante_vialidad" value="{{ old('solicitante.domicilios.0.vialidad') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="solicitante_num_ext" class="block text-sm font-medium text-gray-700">Número Exterior *</label>
                            <input type="text" name="solicitante[domicilios][0][num_ext]" id="solicitante_num_ext" value="{{ old('solicitante.domicilios.0.num_ext') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="solicitante_num_int" class="block text-sm font-medium text-gray-700">Número Interior</label>
                            <input type="text" name="solicitante[domicilios][0][num_int]" id="solicitante_num_int" value="{{ old('solicitante.domicilios.0.num_int') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="solicitante_asentamiento" class="block text-sm font-medium text-gray-700">Colonia / Asentamiento *</label>
                            <input type="text" name="solicitante[domicilios][0][asentamiento]" id="solicitante_asentamiento" value="{{ old('solicitante.domicilios.0.asentamiento') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="bg-gray-800 text-white p-4">
                    <h2 class="text-lg font-semibold">3. Archivo de Citados (Múltiples)</h2>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label for="archivo_citados" class="block text-sm font-medium text-gray-700">Subir Archivo (CSV, XLSX) *</label>
                        <input type="file" name="archivo_citados" id="archivo_citados" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required
                               class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    
                    <div class="rounded-md bg-blue-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 flex-1 md:flex md:justify-between">
                                <div class="text-sm text-blue-700">
                                    <h3 class="font-medium text-blue-800">Instrucciones Importantes</h3>
                                    <p class="mt-1">El archivo que subas debe contener <strong>únicamente a los citados</strong>.</p>
                                    <p class="mt-1">Asegúrate de que tu archivo CSV o Excel tenga las <strong>30 columnas</strong> en el orden correcto que espera el sistema.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <button type="button" onclick="window.history.back()"
                        class="inline-flex justify-center py-2 px-6 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Cancelar
                </button>
                <button type="submit"
                        class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    Procesar Carga Masiva
                </button>
            </div>

        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            function togglePersona(tipo) {
                const moralFields = document.getElementById('persona_moral_fields');
                const fisicaFields = document.getElementById('persona_fisica_fields');
                
                // Inputs
                const moralRazon = document.getElementById('solicitante_nombre_comercial');
                const moralRfc = document.getElementById('solicitante_rfc_moral');
                const fisicaNombre = document.getElementById('solicitante_nombre');
                const fisicaPaterno = document.getElementById('solicitante_primer_apellido');
                const fisicaRfc = document.getElementById('solicitante_rfc_fisica');

                if (tipo == '1') { // --- Persona Física ---
                    fisicaFields.style.display = 'block';
                    moralFields.style.display = 'none';
                    
                    // Habilitar campos físicos
                    fisicaNombre.required = true;
                    fisicaPaterno.required = true;
                    fisicaRfc.disabled = false;

                    // Deshabilitar campos morales
                    moralRazon.required = false;
                    moralRfc.disabled = true;
                    // moralRfc.value = ''; // Limpiar valor

                } else if (tipo == '2') { // --- Persona Moral ---
                    fisicaFields.style.display = 'none';
                    moralFields.style.display = 'block';

                    // Habilitar campos morales
                    moralRazon.required = true;
                    moralRfc.disabled = false;

                    // Deshabilitar campos físicos
                    fisicaNombre.required = false;
                    fisicaPaterno.required = false;
                    fisicaRfc.disabled = true;
                    // fisicaRfc.value = ''; // Limpiar valor
                }
            }

            // Listeners
            const radios = document.querySelectorAll('input[name="solicitante[tipo_persona_id]"]');
            radios.forEach(radio => {
                radio.addEventListener('change', (e) => togglePersona(e.target.value));
            });

            // Llamada inicial al cargar la página
            const tipoSeleccionado = document.querySelector('input[name="solicitante[tipo_persona_id]"]:checked').value;
            togglePersona(tipoSeleccionado);
        });
    </script>

</body>
</html>