<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva de Convenios | SINACOL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'sinacol-primary': '#1e3a8a',
                        'sinacol-secondary': '#1e40af',
                        'sinacol-accent': '#3b82f6',
                        'sinacol-dark': '#0f172a',
                        'sinacol-light': '#dbeafe',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.1), 0 2px 4px -1px rgba(30, 58, 138, 0.06);
        }
        .card-shadow-lg {
            box-shadow: 0 20px 25px -5px rgba(30, 58, 138, 0.1), 0 10px 10px -5px rgba(30, 58, 138, 0.04);
        }
        .gradient-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        .input-focus:focus {
            border-color: #1e3a8a;
            ring-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        .animate-pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        /* Modal de carga */
        .loading-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-modal.active {
            display: flex;
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.2);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-slate-50 min-h-screen">

    <!-- Modal de Carga -->
    <div id="loading-modal" class="loading-modal">
        <div class="bg-white rounded-2xl p-8 max-w-md mx-4 text-center card-shadow-lg animate-slide-up">
            <div class="flex justify-center mb-6">
                <div class="loading-spinner"></div>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Procesando Convenios</h3>
            <p class="text-gray-600 mb-4" id="loading-message">Subiendo archivo y validando datos...</p>
            <div class="bg-blue-50 rounded-lg p-4 mt-4">
                <div class="flex items-center justify-center text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Este proceso puede tardar varios minutos</span>
                </div>
            </div>
            <div class="mt-4">
                <div class="inline-flex items-center space-x-2">
                    <div class="w-2 h-2 bg-sinacol-primary rounded-full animate-pulse"></div>
                    <div class="w-2 h-2 bg-sinacol-primary rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                    <div class="w-2 h-2 bg-sinacol-primary rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="gradient-header shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
                        <i class="fas fa-handshake text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                            Carga Masiva de Convenios
                        </h1>
                        <p class="text-white/80 text-sm mt-1">Sistema de Gestión de Convenios Conciliatorios</p>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                    <i class="fas fa-shield-alt text-white/80"></i>
                    <span class="text-white/90 text-sm font-medium">Centro de Conciliación</span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Mensajes de éxito/error -->
        @if(session('success'))
        <div class="mb-6 bg-green-50 border-l-4 border-green-500 rounded-lg p-4 card-shadow animate-fade-in">
            <div class="flex items-start">
                <i class="fas fa-check-circle text-green-500 text-2xl mr-3 mt-1"></i>
                <div class="flex-1">
                    <p class="text-green-800 font-semibold text-lg">{{ session('success') }}</p>
                    
                    @if(session('archivo_info'))
                    <div class="mt-3 bg-white rounded-lg p-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-gray-600">Archivo:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ session('archivo_info')['nombre'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Tamaño:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ session('archivo_info')['tamaño'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Filas:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ session('archivo_info')['filas'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Hora:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ session('archivo_info')['timestamp'] }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso -->
                    <div id="progress-container" class="mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Procesando convenios...</span>
                            <span id="progress-percentage" class="text-sm font-bold text-sinacol-primary">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div id="progress-bar" class="bg-gradient-to-r from-sinacol-primary to-sinacol-accent h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                        </div>
                        <div id="progress-stats" class="mt-3 grid grid-cols-4 gap-2 text-xs text-gray-600">
                            <div class="bg-blue-50 rounded p-2 text-center">
                                <div class="font-bold text-blue-700" id="stat-solicitudes">0</div>
                                <div>Solicitudes</div>
                            </div>
                            <div class="bg-green-50 rounded p-2 text-center">
                                <div class="font-bold text-green-700" id="stat-expedientes">0</div>
                                <div>Expedientes</div>
                            </div>
                            <div class="bg-purple-50 rounded p-2 text-center">
                                <div class="font-bold text-purple-700" id="stat-audiencias">0</div>
                                <div>Audiencias</div>
                            </div>
                            <div class="bg-amber-50 rounded p-2 text-center">
                                <div class="font-bold text-amber-700" id="stat-conceptos">0</div>
                                <div>Conceptos</div>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Botón de descarga de documentos ZIP -->
                    <div id="download-container" class="mt-4" style="display: none;">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-xl p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="bg-blue-500 rounded-full p-3 mr-3">
                                        <i class="fas fa-file-archive text-white text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-bold text-blue-900">Documentos Listos</h4>
                                        <p class="text-sm text-blue-700">Los documentos PDF han sido generados exitosamente</p>
                                    </div>
                                </div>
                                <a href="{{ route('carga.descargar.zip') }}" 
                                   class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
                                    <i class="fas fa-download mr-2"></i>
                                    Descargar ZIP
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-lg p-4 card-shadow animate-fade-in">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-red-500 text-2xl mr-3 mt-1"></i>
                <div class="flex-1">
                    <p class="text-red-800 font-semibold text-lg">{{ session('error') }}</p>
                    @if(session('error_detalle'))
                    <div class="mt-2 bg-white rounded p-3">
                        <p class="text-sm text-red-700 font-mono">{{ session('error_detalle') }}</p>
                        @if(session('error_contexto'))
                        <p class="text-xs text-gray-600 mt-1">{{ session('error_contexto') }}</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($errors->any())
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-lg p-4 card-shadow">
            <div class="flex">
                <i class="fas fa-times-circle text-red-500 text-xl mr-3 mt-0.5"></i>
                <div>
                    <h3 class="text-red-800 font-semibold mb-2">Errores en el formulario:</h3>
                    <ul class="list-disc list-inside text-red-700 space-y-1">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif

        <form action="{{ route('solicitud.masiva.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Sección 1: Datos Comunes -->
            <div class="bg-white rounded-2xl overflow-hidden card-shadow-lg border border-gray-100">
                <div class="gradient-header px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-2">
                            <i class="fas fa-file-contract text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white">Datos Comunes del Convenio</h2>
                            <p class="text-white/70 text-sm">Información que aplicará a todos los convenios</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label for="fecha_conflicto" class="flex items-center text-sm font-semibold text-gray-700">
                                <i class="fas fa-calendar-alt text-sinacol-primary mr-2"></i>
                                Fecha de Conflicto <span class="text-red-500 ml-1">*</span>
                            </label>
                            <input type="date" name="fecha_conflicto" id="fecha_conflicto" value="{{ old('fecha_conflicto') }}" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                        </div>
                        <div class="space-y-2">
                            <label for="tipo_solicitud_id" class="flex items-center text-sm font-semibold text-gray-700">
                                <i class="fas fa-file-alt text-sinacol-primary mr-2"></i>
                                Tipo de Solicitud <span class="text-red-500 ml-1">*</span>
                            </label>
                            <select name="tipo_solicitud_id" id="tipo_solicitud_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
                                @foreach($tipo_solicitudes ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('tipo_solicitud_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label for="giro_comercial_id" class="flex items-center text-sm font-semibold text-gray-700">
                                <i class="fas fa-building text-sinacol-primary mr-2"></i>
                                Giro Comercial <span class="text-red-500 ml-1">*</span>
                            </label>
                            <select name="giro_comercial_id" id="giro_comercial_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
                                @foreach($giros_comerciales ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('giro_comercial_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2 space-y-2">
                            <label for="objeto_solicitudes" class="flex items-center text-sm font-semibold text-gray-700">
                                <i class="fas fa-list-check text-sinacol-primary mr-2"></i>
                                Objeto(s) de Solicitud <span class="text-red-500 ml-1">*</span>
                            </label>
                            <select name="objeto_solicitudes[]" id="objeto_solicitudes" multiple required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
                                @foreach($objeto_solicitudes ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ (is_array(old('objeto_solicitudes')) && in_array($id, old('objeto_solicitudes'))) ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1"><i class="fas fa-info-circle mr-1"></i>Mantén presionado Ctrl (Cmd en Mac) para seleccionar múltiples opciones</p>
                        </div>
                        <div class="space-y-2">
                            <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-video text-sinacol-primary mr-2"></i>
                                Modalidad de Audiencia <span class="text-red-500 ml-1">*</span>
                            </label>
                            <div class="flex gap-4">
                                <label class="flex items-center flex-1 bg-gray-50 hover:bg-sinacol-primary/5 border-2 border-gray-200 rounded-xl p-3 cursor-pointer transition-all duration-200">
                                    <input id="virtual_no" name="virtual" type="radio" value="0" {{ old('virtual', '0') == '0' ? 'checked' : '' }}
                                           class="w-5 h-5 text-sinacol-primary focus:ring-sinacol-primary">
                                    <span class="ml-3 text-sm font-medium text-gray-700">
                                        <i class="fas fa-handshake mr-1"></i>Presencial
                                    </span>
                                </label>
                                <label class="flex items-center flex-1 bg-gray-50 hover:bg-sinacol-primary/5 border-2 border-gray-200 rounded-xl p-3 cursor-pointer transition-all duration-200">
                                    <input id="virtual_si" name="virtual" type="radio" value="1" {{ old('virtual') == '1' ? 'checked' : '' }}
                                           class="w-5 h-5 text-sinacol-primary focus:ring-sinacol-primary">
                                    <span class="ml-3 text-sm font-medium text-gray-700">
                                        <i class="fas fa-laptop mr-1"></i>Virtual
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selector de Conciliador -->
                    <div class="grid grid-cols-1 gap-6">
                        <div class="space-y-2">
                            <label for="conciliador_id" class="flex items-center text-sm font-semibold text-gray-700">
                                <i class="fas fa-user-check text-sinacol-primary mr-2"></i>
                                Conciliador Asignado <span class="text-red-500 ml-1">*</span>
                            </label>
                            <select name="conciliador_id" id="conciliador_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
                                <option value="">-- Seleccione un conciliador --</option>
                                @foreach($conciliadores ?? [] as $conciliador)
                                    <option value="{{ $conciliador['id'] }}" {{ old('conciliador_id') == $conciliador['id'] ? 'selected' : '' }}>
                                        {{ $conciliador['nombre_completo'] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Este conciliador será asignado a todas las audiencias generadas
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Datos del Solicitante -->
            <div class="bg-white rounded-2xl overflow-hidden card-shadow-lg border border-gray-100">
                <div class="gradient-header px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-2">
                            <i class="fas fa-user-tie text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white">Datos del Solicitante</h2>
                            <p class="text-white/70 text-sm">Información de la persona que presenta la solicitud (único)</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-id-card text-sinacol-primary mr-2"></i>
                            Tipo de Persona <span class="text-red-500 ml-1">*</span>
                        </label>
                        <div class="flex gap-4">
                            <label class="flex items-center flex-1 bg-gradient-to-br from-blue-50 to-blue-100/50 hover:from-blue-100 hover:to-blue-200/50 border-2 border-blue-200 rounded-xl p-4 cursor-pointer transition-all duration-200 group">
                                <input id="tipo_persona_fisica" name="solicitante[tipo_persona_id]" type="radio" value="1" {{ old('solicitante.tipo_persona_id', '1') == '1' ? 'checked' : '' }}
                                       class="w-5 h-5 text-sinacol-primary focus:ring-sinacol-primary">
                                <span class="ml-3 text-sm font-semibold text-gray-700 group-hover:text-gray-900">
                                    <i class="fas fa-user mr-2 text-blue-600"></i>Persona Física
                                </span>
                            </label>
                            <label class="flex items-center flex-1 bg-gradient-to-br from-purple-50 to-purple-100/50 hover:from-purple-100 hover:to-purple-200/50 border-2 border-purple-200 rounded-xl p-4 cursor-pointer transition-all duration-200 group">
                                <input id="tipo_persona_moral" name="solicitante[tipo_persona_id]" type="radio" value="2" {{ old('solicitante.tipo_persona_id') == '2' ? 'checked' : '' }}
                                       class="w-5 h-5 text-sinacol-primary focus:ring-sinacol-primary">
                                <span class="ml-3 text-sm font-semibold text-gray-700 group-hover:text-gray-900">
                                    <i class="fas fa-building mr-2 text-purple-600"></i>Persona Moral
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Persona Moral -->
                    <div id="persona_moral_fields" class="space-y-6">
                        <div class="bg-purple-50/50 border-l-4 border-purple-500 rounded-r-xl p-4">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-briefcase text-purple-600 text-lg mr-3"></i>
                                <h3 class="text-base font-bold text-gray-800">Datos de Persona Moral</h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="md:col-span-2 space-y-2">
                                    <label for="solicitante_nombre_comercial" class="block text-sm font-semibold text-gray-700">
                                        Razón Social <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="solicitante[nombre_comercial]" id="solicitante_nombre_comercial" value="{{ old('solicitante.nombre_comercial') }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                </div>
                                <div class="space-y-2">
                                    <label for="solicitante_rfc_moral" class="block text-sm font-semibold text-gray-700">
                                        RFC (Moral) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="solicitante[rfc]" id="solicitante_rfc_moral" value="{{ old('solicitante.rfc') }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 uppercase">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Persona Física -->
                    <div id="persona_fisica_fields" class="space-y-6">
                        <div class="bg-blue-50/50 border-l-4 border-blue-500 rounded-r-xl p-4">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-user text-blue-600 text-lg mr-3"></i>
                                <h3 class="text-base font-bold text-gray-800">Datos de Persona Física</h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                <div class="space-y-2">
                                    <label for="solicitante_nombre" class="block text-sm font-semibold text-gray-700">
                                        Nombre(s) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="solicitante[nombre]" id="solicitante_nombre" value="{{ old('solicitante.nombre') }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                </div>
                                <div class="space-y-2">
                                    <label for="solicitante_primer_apellido" class="block text-sm font-semibold text-gray-700">
                                        Primer Apellido <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="solicitante[primer_apellido]" id="solicitante_primer_apellido" value="{{ old('solicitante.primer_apellido') }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                </div>
                                <div class="space-y-2">
                                    <label for="solicitante_segundo_apellido" class="block text-sm font-semibold text-gray-700">
                                        Segundo Apellido
                                    </label>
                                    <input type="text" name="solicitante[segundo_apellido]" id="solicitante_segundo_apellido" value="{{ old('solicitante.segundo_apellido') }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="solicitante_curp" class="block text-sm font-semibold text-gray-700">
                                        <i class="fas fa-id-badge text-gray-500 mr-1"></i>CURP
                                    </label>
                                    <input type="text" name="solicitante[curp]" id="solicitante_curp" value="{{ old('solicitante.curp') }}" maxlength="18"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 uppercase">
                                </div>
                                <div class="space-y-2">
                                    <label for="solicitante_rfc_fisica" class="block text-sm font-semibold text-gray-700">
                                        <i class="fas fa-file-invoice text-gray-500 mr-1"></i>RFC (Física)
                                    </label>
                                    <input type="text" name="solicitante[rfc]" id="solicitante_rfc_fisica" value="{{ old('solicitante.rfc') }}" maxlength="13"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 uppercase">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Contacto del Solicitante -->
                    <div class="bg-green-50/50 border-l-4 border-green-500 rounded-r-xl p-4">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-address-book text-green-600 text-lg mr-3"></i>
                            <h3 class="text-base font-bold text-gray-800">Datos de Contacto del Solicitante</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="solicitante_telefono" class="block text-sm font-semibold text-gray-700">
                                    <i class="fas fa-mobile-alt text-gray-500 mr-1"></i>Teléfono Celular <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="solicitante[contactos][0][contacto]" id="solicitante_telefono" value="{{ old('solicitante.contactos.0.contacto') }}" required
                                       placeholder="10 dígitos" maxlength="10"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                <input type="hidden" name="solicitante[contactos][0][tipo_contacto_id]" value="1">
                            </div>
                            <div class="space-y-2">
                                <label for="solicitante_email" class="block text-sm font-semibold text-gray-700">
                                    <i class="fas fa-envelope text-gray-500 mr-1"></i>Correo Electrónico <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="solicitante[contactos][1][contacto]" id="solicitante_email" value="{{ old('solicitante.contactos.1.contacto') }}" required
                                       placeholder="ejemplo@correo.com"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                <input type="hidden" name="solicitante[contactos][1][tipo_contacto_id]" value="3">
                            </div>
                        </div>
                    </div>
                    
                    <hr class="border-gray-200">

                    <!-- Domicilio del Solicitante -->
                    <div class="bg-amber-50/50 border-l-4 border-amber-500 rounded-r-xl p-4">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-map-marked-alt text-amber-600 text-lg mr-3"></i>
                            <h3 class="text-base font-bold text-gray-800">Domicilio del Solicitante</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="space-y-2">
                                <label for="solicitante_estado" class="block text-sm font-semibold text-gray-700">
                                    Estado <span class="text-red-500">*</span>
                                </label>
                                <select name="solicitante[domicilios][0][estado_id]" id="solicitante_estado" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
                                    @foreach($estados ?? [] as $estado)
                                        <option value="{{ $estado->id }}" {{ old('solicitante.domicilios.0.estado_id') == $estado->id ? 'selected' : '' }}>{{ $estado->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="solicitante_municipio" class="block text-sm font-semibold text-gray-700">
                                    Municipio / Alcaldía <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="solicitante[domicilios][0][municipio]" id="solicitante_municipio" value="{{ old('solicitante.domicilios.0.municipio') }}" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                            <div class="space-y-2">
                                <label for="solicitante_cp" class="block text-sm font-semibold text-gray-700">
                                    Código Postal <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="solicitante[domicilios][0][cp]" id="solicitante_cp" value="{{ old('solicitante.domicilios.0.cp') }}" required
                                       maxlength="5" placeholder="5 dígitos"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="space-y-2">
                                <label for="solicitante_tipo_vialidad" class="block text-sm font-semibold text-gray-700">
                                    Tipo de Vialidad <span class="text-red-500">*</span>
                                </label>
                                <select name="solicitante[domicilios][0][tipo_vialidad_id]" id="solicitante_tipo_vialidad" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
                                    @foreach($tipo_vialidades ?? [] as $vialidad)
                                        <option value="{{ $vialidad->id }}" {{ old('solicitante.domicilios.0.tipo_vialidad_id') == $vialidad->id ? 'selected' : '' }}>{{ $vialidad->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2 space-y-2">
                                <label for="solicitante_vialidad" class="block text-sm font-semibold text-gray-700">
                                    Nombre de la Vialidad o Calle <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="solicitante[domicilios][0][vialidad]" id="solicitante_vialidad" value="{{ old('solicitante.domicilios.0.vialidad') }}" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label for="solicitante_num_ext" class="block text-sm font-semibold text-gray-700">
                                    Número Exterior <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="solicitante[domicilios][0][num_ext]" id="solicitante_num_ext" value="{{ old('solicitante.domicilios.0.num_ext') }}" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                            <div class="space-y-2">
                                <label for="solicitante_num_int" class="block text-sm font-semibold text-gray-700">
                                    Número Interior
                                </label>
                                <input type="text" name="solicitante[domicilios][0][num_int]" id="solicitante_num_int" value="{{ old('solicitante.domicilios.0.num_int') }}"
                                       placeholder="Opcional"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                            <div class="space-y-2">
                                <label for="solicitante_asentamiento" class="block text-sm font-semibold text-gray-700">
                                    Colonia / Asentamiento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="solicitante[domicilios][0][asentamiento]" id="solicitante_asentamiento" value="{{ old('solicitante.domicilios.0.asentamiento') }}" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 3: Datos del Representante Legal (Opcional) -->
            <div class="bg-white rounded-2xl overflow-hidden card-shadow-lg border border-gray-100">
                <div class="gradient-header px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-2">
                            <i class="fas fa-user-tie text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white">Representante Legal del Solicitante</h2>
                            <p class="text-white/70 text-sm">Opcional: Complete si el solicitante actúa mediante representante</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Checkbox para habilitar representante -->
                    <div class="flex items-center space-x-3 bg-blue-50 rounded-xl p-4">
                        <input type="checkbox" id="tiene_representante" name="tiene_representante" value="1"
                               class="w-5 h-5 text-sinacol-primary focus:ring-sinacol-primary rounded">
                        <label for="tiene_representante" class="text-sm font-medium text-gray-700 cursor-pointer">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            El solicitante será representado por un apoderado legal
                        </label>
                    </div>

                    <div id="representante_fields" style="display: none;">
                        <!-- Nombre completo -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-sinacol-primary mr-1"></i>Nombre(s) <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="representante[nombre]" id="representante_nombre"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700"
                                       placeholder="Nombre del representante">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-sinacol-primary mr-1"></i>Primer Apellido <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="representante[primer_apellido]" id="representante_primer_apellido"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700"
                                       placeholder="Apellido paterno">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-sinacol-primary mr-1"></i>Segundo Apellido
                                </label>
                                <input type="text" name="representante[segundo_apellido]" id="representante_segundo_apellido"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700"
                                       placeholder="Apellido materno">
                            </div>
                        </div>

                        <!-- CURP y RFC -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-sinacol-primary mr-1"></i>CURP <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="representante[curp]" id="representante_curp"
                                       maxlength="18" pattern="[A-Z0-9]{18}"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 uppercase"
                                       placeholder="18 caracteres">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-sinacol-primary mr-1"></i>RFC
                                </label>
                                <input type="text" name="representante[rfc]" id="representante_rfc"
                                       maxlength="13" pattern="[A-Z0-9]{12,13}"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 uppercase"
                                       placeholder="12 o 13 caracteres">
                            </div>
                        </div>

                        <!-- Género y Fecha de Nacimiento -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-venus-mars text-sinacol-primary mr-1"></i>Género <span class="text-red-500">*</span>
                                </label>
                                <select name="representante[genero_id]" id="representante_genero_id"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                                    <option value="">Seleccione...</option>
                                    <option value="1">Masculino</option>
                                    <option value="2">Femenino</option>
                                    <option value="3">Otro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar text-sinacol-primary mr-1"></i>Fecha de Nacimiento
                                </label>
                                <input type="date" name="representante[fecha_nacimiento]" id="representante_fecha_nacimiento"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700">
                            </div>
                        </div>

                        <!-- Contacto -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-sinacol-primary mr-1"></i>Teléfono
                                </label>
                                <input type="tel" name="representante[telefono]" id="representante_telefono"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700"
                                       placeholder="10 dígitos">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-sinacol-primary mr-1"></i>Correo Electrónico
                                </label>
                                <input type="email" name="representante[correo_electronico]" id="representante_correo"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700"
                                       placeholder="ejemplo@correo.com">
                            </div>
                        </div>

                        <!-- Info adicional -->
                        <div class="bg-amber-50 border-l-4 border-amber-400 rounded-r-xl p-4">
                            <div class="flex">
                                <i class="fas fa-lightbulb text-amber-500 mr-3 mt-0.5"></i>
                                <div class="text-sm text-amber-800">
                                    <p class="font-semibold mb-1">Nota importante:</p>
                                    <p>El representante legal comparecerá en nombre del solicitante en todas las audiencias. Se generará automáticamente como compareciente en el sistema.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 4: Archivo de Citados -->
            <div class="bg-white rounded-2xl overflow-hidden card-shadow-lg border border-gray-100">
                <div class="gradient-header px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-2">
                            <i class="fas fa-users text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white">Archivo de Citados</h2>
                            <p class="text-white/70 text-sm">Sube el archivo Excel o CSV con los datos de las partes citadas</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-3">
                        <label for="archivo_citados" class="flex items-center text-sm font-semibold text-gray-700">
                            <i class="fas fa-file-excel text-sinacol-primary mr-2"></i>
                            Seleccionar Archivo (CSV, XLSX) <span class="text-red-500 ml-1">*</span>
                        </label>
                        <div class="relative">
                            <input type="file" name="archivo_citados" id="archivo_citados" 
                                   accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required
                                   class="block w-full text-sm text-gray-700 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-sinacol-primary file:text-white hover:file:bg-sinacol-secondary file:cursor-pointer cursor-pointer border-2 border-dashed border-gray-300 rounded-xl p-4 transition-all duration-200 hover:border-sinacol-primary">
                        </div>
                    </div>
                    
                    <!-- Instrucciones mejoradas -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-r-xl p-5">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-blue-100 rounded-full p-3">
                                    <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-base font-bold text-blue-900 mb-3">📋 Instrucciones para Carga de Convenios</h3>
                                <div class="space-y-2 text-sm text-blue-800">
                                    <div class="flex items-start">
                                        <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                                        <p>El archivo debe contener los <strong>datos de las partes citadas</strong> que participarán en los convenios</p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                                        <p>Asegúrate de incluir las <strong>55 columnas requeridas</strong>: datos personales, domicilio, datos laborales y conceptos de pago</p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                                        <p>Los <strong>conceptos de pago del convenio</strong> se capturan en: concepto_1, concepto_2, concepto_3, concepto_4, concepto_5, concepto_13</p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-lightbulb text-amber-600 mr-2 mt-0.5"></i>
                                        <p class="italic">Los datos del solicitante capturados arriba se aplicarán automáticamente a todos los convenios generados</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información adicional sobre columnas -->
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <button type="button" onclick="document.getElementById('columnas-info').classList.toggle('hidden')" 
                                class="flex items-center justify-between w-full text-left">
                            <span class="text-sm font-semibold text-gray-700">
                                <i class="fas fa-table text-gray-500 mr-2"></i>Ver estructura de columnas esperadas
                            </span>
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </button>
                        <div id="columnas-info" class="hidden mt-4 pt-4 border-t border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-600">
                                <div>
                                    <p class="font-semibold text-gray-700 mb-2"><i class="fas fa-user mr-1"></i>Datos Personales:</p>
                                    <ul class="space-y-1 ml-4">
                                        <li>• curp, nombre, primer_apellido, segundo_apellido</li>
                                        <li>• RFC, Genero, Nacionalidad, Estado de nacimiento</li>
                                        <li>• Fecha de nacimiento, Edad</li>
                                    </ul>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-700 mb-2"><i class="fas fa-map-marker-alt mr-1"></i>Domicilio y Contacto:</p>
                                    <ul class="space-y-1 ml-4">
                                        <li>• estado, municipio, colonia, cp</li>
                                        <li>• tipo_vialidad, vialidad, num_ext, num_int</li>
                                        <li>• correo, Teléfono, tipo_contacto</li>
                                    </ul>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-700 mb-2"><i class="fas fa-briefcase mr-1"></i>Datos Laborales:</p>
                                    <ul class="space-y-1 ml-4">
                                        <li>• nss, puesto, salario, periocidad</li>
                                        <li>• horas_sem, jornada</li>
                                        <li>• fecha_ingreso, fecha_salida</li>
                                        <li>• ¿Labora actualmente?</li>
                                    </ul>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-700 mb-2"><i class="fas fa-money-bill-wave mr-1"></i>Conceptos de Pago:</p>
                                    <ul class="space-y-1 ml-4">
                                        <li>• concepto_1 (Días de sueldo)</li>
                                        <li>• concepto_2 (Días de vacaciones)</li>
                                        <li>• concepto_3, concepto_4, concepto_5</li>
                                        <li>• concepto_13 (Deducción)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 pb-4">
                <button type="button" onclick="window.history.back()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-3 border-2 border-gray-300 shadow-sm text-sm font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Cancelar
                </button>
                <button type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-gradient-to-r from-sinacol-primary to-sinacol-secondary hover:from-sinacol-secondary hover:to-sinacol-accent focus:outline-none focus:ring-2 focus:ring-sinacol-primary focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-handshake mr-2 text-lg"></i>
                    Procesar Convenios Masivos
                </button>
            </div>

        </form>

        <!-- Footer info -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p><i class="fas fa-shield-alt mr-1"></i>Los convenios se procesarán de forma segura y confidencial</p>
            <p class="mt-1">Sistema de Gestión de Convenios Conciliatorios - SINACOL</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // ==================== TOGGLE TIPO DE PERSONA ====================
            function togglePersona(tipo) {
                const moralFields = document.getElementById('persona_moral_fields');
                const fisicaFields = document.getElementById('persona_fisica_fields');
                
                const moralRazon = document.getElementById('solicitante_nombre_comercial');
                const moralRfc = document.getElementById('solicitante_rfc_moral');
                const fisicaNombre = document.getElementById('solicitante_nombre');
                const fisicaPaterno = document.getElementById('solicitante_primer_apellido');
                const fisicaRfc = document.getElementById('solicitante_rfc_fisica');

                if (tipo == '1') { // Persona Física
                    fisicaFields.style.display = 'block';
                    moralFields.style.display = 'none';
                    
                    fisicaNombre.required = true;
                    fisicaPaterno.required = true;
                    fisicaRfc.disabled = false;

                    moralRazon.required = false;
                    moralRfc.disabled = true;

                } else if (tipo == '2') { // Persona Moral
                    fisicaFields.style.display = 'none';
                    moralFields.style.display = 'block';

                    moralRazon.required = true;
                    moralRfc.disabled = false;

                    fisicaNombre.required = false;
                    fisicaPaterno.required = false;
                    fisicaRfc.disabled = true;
                }
            }

            const radios = document.querySelectorAll('input[name="solicitante[tipo_persona_id]"]');
            radios.forEach(radio => {
                radio.addEventListener('change', (e) => togglePersona(e.target.value));
            });

            const tipoSeleccionado = document.querySelector('input[name="solicitante[tipo_persona_id]"]:checked').value;
            togglePersona(tipoSeleccionado);

            // ==================== MANEJO DE ARCHIVO ====================
            const form = document.querySelector('form');
            const fileInput = document.getElementById('archivo_citados');
            const loadingModal = document.getElementById('loading-modal');
            const loadingMessage = document.getElementById('loading-message');
            
            // Mostrar nombre del archivo seleccionado
            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                    console.log(`📄 Archivo seleccionado: ${fileName} (${fileSize} MB)`);
                }
            });

            // ==================== SUBMIT DEL FORMULARIO ====================
            form.addEventListener('submit', function(e) {
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    const fileName = file.name.toLowerCase();
                    const validExtensions = ['.csv', '.xlsx', '.xls'];
                    const isValid = validExtensions.some(ext => fileName.endsWith(ext));
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('⚠️ Por favor selecciona un archivo válido (CSV o Excel)');
                        return false;
                    }
                    
                    // Mostrar modal de carga
                    showLoadingModal();
                    
                    // Cambiar mensaje del botón
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';
                }
            });

            // ==================== MODAL DE CARGA ====================
            function showLoadingModal() {
                loadingModal.classList.add('active');
                
                const messages = [
                    'Validando formato del archivo...',
                    'Procesando datos de las partes...',
                    'Creando solicitudes...',
                    'Generando expedientes...',
                    'Programando audiencias...',
                    'Calculando conceptos de pago...',
                    'Finalizando proceso...'
                ];
                
                let messageIndex = 0;
                const messageInterval = setInterval(() => {
                    if (messageIndex < messages.length) {
                        loadingMessage.textContent = messages[messageIndex];
                        messageIndex++;
                    }
                }, 3000);
                
                // Guardar el interval para limpiarlo después
                loadingModal.dataset.intervalId = messageInterval;
            }

            function hideLoadingModal() {
                loadingModal.classList.remove('active');
                if (loadingModal.dataset.intervalId) {
                    clearInterval(parseInt(loadingModal.dataset.intervalId));
                }
            }

            // ==================== MONITOREO DE PROGRESO ====================
            const progressContainer = document.getElementById('progress-container');
            
            if (progressContainer) {
                // Iniciar monitoreo automático
                startProgressMonitoring();
            }

            let monitoringInterval = null;
            let checksRealizados = 0;
            const maxChecks = 60; // Máximo 5 minutos (60 checks * 5 segundos)

            function startProgressMonitoring() {
                checksRealizados = 0;
                
                console.log('🚀 Iniciando monitoreo de progreso...');
                
                monitoringInterval = setInterval(async () => {
                    checksRealizados++;
                    console.log(`📡 Check #${checksRealizados} - Consultando /api/carga-masiva/status...`);
                    
                    try {
                        const response = await fetch('/api/carga-masiva/status', {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        
                        console.log(`📥 Respuesta recibida:`, response.status, response.statusText);
                        
                        if (response.ok) {
                            const data = await response.json();
                            console.log('✅ Datos recibidos:', data);
                            updateProgressUI(data);
                            
                            // Si está completado o llegamos al máximo de checks
                            if (data.resumen.completado || checksRealizados >= maxChecks) {
                                console.log('🏁 Proceso completado o timeout alcanzado');
                                stopProgressMonitoring();
                                if (data.resumen.completado) {
                                    showCompletionMessage(data);
                                    // Verificar si hay documentos disponibles para descargar
                                    verificarDocumentosDisponibles();
                                }
                            }
                        } else {
                            console.error('❌ Error en respuesta:', response.status);
                        }
                    } catch (error) {
                        console.error('❌ Error al obtener estado:', error);
                    }
                }, 5000); // Cada 5 segundos
            }

            function stopProgressMonitoring() {
                if (monitoringInterval) {
                    clearInterval(monitoringInterval);
                    monitoringInterval = null;
                }
            }

            function updateProgressUI(data) {
                const { resumen } = data;
                
                // Actualizar barra de progreso
                const progressBar = document.getElementById('progress-bar');
                const progressPercentage = document.getElementById('progress-percentage');
                
                if (progressBar && progressPercentage) {
                    progressBar.style.width = resumen.progreso_porcentaje + '%';
                    progressPercentage.textContent = resumen.progreso_porcentaje + '%';
                }
                
                // Actualizar estadísticas
                document.getElementById('stat-solicitudes').textContent = resumen.total_solicitudes;
                document.getElementById('stat-expedientes').textContent = resumen.expedientes_creados;
                document.getElementById('stat-audiencias').textContent = resumen.audiencias_creadas;
                document.getElementById('stat-conceptos').textContent = resumen.conceptos_creados;
                
                // Mostrar jobs pendientes si existen
                if (resumen.jobs_pendientes > 0) {
                    const statusMessage = document.querySelector('#progress-container .flex.items-center.justify-between span');
                    if (statusMessage) {
                        statusMessage.textContent = `Procesando convenios... (${resumen.jobs_pendientes} pendientes)`;
                    }
                }
                
                console.log('📊 Progreso actualizado:', resumen);
            }

            function showCompletionMessage(data) {
                const { resumen } = data;
                const progressContainer = document.getElementById('progress-container');
                
                if (progressContainer && resumen.completado) {
                    const completionHTML = `
                        <div class="mt-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-500 rounded-xl p-4 animate-slide-up">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                                <h4 class="text-lg font-bold text-green-800">¡Proceso Completado!</h4>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="bg-white rounded-lg p-3">
                                    <div class="text-gray-600">Solicitudes Procesadas</div>
                                    <div class="text-2xl font-bold text-blue-700">${resumen.total_solicitudes}</div>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <div class="text-gray-600">Expedientes Creados</div>
                                    <div class="text-2xl font-bold text-green-700">${resumen.expedientes_creados}</div>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <div class="text-gray-600">Audiencias Programadas</div>
                                    <div class="text-2xl font-bold text-purple-700">${resumen.audiencias_creadas}</div>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <div class="text-gray-600">Conceptos Registrados</div>
                                    <div class="text-2xl font-bold text-amber-700">${resumen.conceptos_creados}</div>
                                </div>
                            </div>
                            ${resumen.errores > 0 ? `
                                <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <div class="flex items-center text-yellow-800">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <span class="text-sm"><strong>${resumen.errores}</strong> solicitud(es) con errores. Revisar logs para detalles.</span>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    progressContainer.insertAdjacentHTML('beforeend', completionHTML);
                }
            }
            
            // ==================== VERIFICAR DOCUMENTOS DISPONIBLES ====================
            let intentosVerificacion = 0;
            const MAX_INTENTOS = 6; // 6 intentos × 5 segundos = 30 segundos máximo
            
            async function verificarDocumentosDisponibles() {
                try {
                    console.log(`📄 Verificando documentos disponibles (intento ${intentosVerificacion + 1}/${MAX_INTENTOS})...`);
                    
                    const response = await fetch('/carga-masiva/verificar-documentos', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        console.log('📄 Resultado verificación:', data);
                        
                        if (data.disponibles && data.total > 0) {
                            // ¡Documentos encontrados!
                            mostrarBotonDescarga(data.total);
                            intentosVerificacion = 0; // Resetear contador
                        } else {
                            // No hay documentos aún
                            intentosVerificacion++;
                            
                            if (intentosVerificacion < MAX_INTENTOS) {
                                // Reintentar después de 5 segundos
                                console.log(`⏳ Documentos aún no disponibles. Reintentando en 5 segundos...`);
                                setTimeout(verificarDocumentosDisponibles, 5000);
                            } else {
                                // Se alcanzó el máximo de intentos
                                console.warn('⚠️ Tiempo de espera agotado. Los documentos pueden tardar más de lo esperado.');
                                mostrarMensajeEsperaDocumentos();
                            }
                        }
                    }
                } catch (error) {
                    console.error('❌ Error al verificar documentos:', error);
                    intentosVerificacion++;
                    
                    if (intentosVerificacion < MAX_INTENTOS) {
                        setTimeout(verificarDocumentosDisponibles, 5000);
                    }
                }
            }
            
            function mostrarBotonDescarga(totalDocumentos) {
                const downloadContainer = document.getElementById('download-container');
                if (downloadContainer) {
                    // Actualizar el texto con el total de documentos
                    const descriptionText = downloadContainer.querySelector('p');
                    if (descriptionText) {
                        descriptionText.textContent = `${totalDocumentos} documento(s) PDF han sido generados exitosamente`;
                    }
                    
                    // Mostrar el contenedor con animación
                    downloadContainer.style.display = 'block';
                    downloadContainer.classList.add('animate-slide-up');
                    
                    console.log('✅ Botón de descarga mostrado');
                }
            }
            
            function mostrarMensajeEsperaDocumentos() {
                const progressContainer = document.getElementById('progress-container');
                if (progressContainer) {
                    const mensaje = document.createElement('div');
                    mensaje.className = 'mt-4 p-4 bg-yellow-50 border border-yellow-300 rounded-lg text-center';
                    mensaje.innerHTML = `
                        <p class="text-yellow-800 font-semibold">⏳ Los documentos aún se están generando</p>
                        <p class="text-yellow-600 text-sm mt-2">
                            Esto puede tomar algunos minutos. 
                            <button onclick="intentosVerificacion = 0; verificarDocumentosDisponibles();" 
                                    class="text-blue-600 underline hover:text-blue-800">
                                Verificar ahora
                            </button>
                        </p>
                    `;
                    progressContainer.appendChild(mensaje);
                }
            }
            
            // Verificar al cargar la página si ya hay documentos disponibles
            if (document.getElementById('progress-container')) {
                setTimeout(() => {
                    verificarDocumentosDisponibles();
                }, 2000); // Esperar 2 segundos después de cargar
            }

            // ==================== UPPERCASE AUTOMÁTICO ====================
            const curpInput = document.getElementById('solicitante_curp');
            const rfcFisicaInput = document.getElementById('solicitante_rfc_fisica');
            const rfcMoralInput = document.getElementById('solicitante_rfc_moral');
            const representanteCurp = document.getElementById('representante_curp');
            const representanteRfc = document.getElementById('representante_rfc');
            
            [curpInput, rfcFisicaInput, rfcMoralInput, representanteCurp, representanteRfc].forEach(input => {
                if (input) {
                    input.addEventListener('input', function(e) {
                        this.value = this.value.toUpperCase();
                    });
                }
            });

            // ==================== TOGGLE REPRESENTANTE LEGAL ====================
            const tieneRepresentanteCheckbox = document.getElementById('tiene_representante');
            const representanteFields = document.getElementById('representante_fields');
            
            if (tieneRepresentanteCheckbox && representanteFields) {
                tieneRepresentanteCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        representanteFields.style.display = 'block';
                        // Hacer campos requeridos
                        document.getElementById('representante_nombre').required = true;
                        document.getElementById('representante_primer_apellido').required = true;
                        document.getElementById('representante_curp').required = true;
                        document.getElementById('representante_genero_id').required = true;
                    } else {
                        representanteFields.style.display = 'none';
                        // Quitar requerido
                        document.getElementById('representante_nombre').required = false;
                        document.getElementById('representante_primer_apellido').required = false;
                        document.getElementById('representante_curp').required = false;
                        document.getElementById('representante_genero_id').required = false;
                    }
                });
            }
        });
    </script>

</body>
</html>