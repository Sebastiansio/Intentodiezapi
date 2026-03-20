<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Expedientes - SINACOL</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(30, 64, 175, 0.15);
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }

        .btn-logout {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .header h1 {
            color: white;
            font-size: 32px;
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            font-weight: 400;
        }

        .search-card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 10px;
        }

        .tab {
            flex: 1;
            padding: 14px 24px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #64748b;
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
        }

        .tab.active {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
        }

        .tab:hover:not(.active) {
            background: white;
            color: #1e40af;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 64, 175, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .btn-export {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
            margin-left: 10px;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.4);
        }

        .btn-export:active {
            transform: translateY(0);
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .results-card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            display: none;
            border: 1px solid #e2e8f0;
        }

        .results-card.show {
            display: block;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .results-count {
            font-size: 20px;
            color: #1e293b;
            font-weight: 700;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }

        th {
            padding: 16px 20px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-size: 14px;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        td strong {
            color: #1e293b;
            font-weight: 700;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-warning {
            background: #fed7aa;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .badge-danger {
            background: #fecaca;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 4px solid #f1f5f9;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            display: none;
            background: #fee2e2;
            color: #991b1b;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
            font-weight: 500;
        }

        .error-message.show {
            display: block;
        }

        .no-results {
            text-align: center;
            padding: 80px 20px;
            color: #94a3b8;
        }

        .no-results svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        .no-results h3 {
            color: #475569;
            font-size: 20px;
            margin-bottom: 8px;
        }

        .no-results p {
            color: #94a3b8;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{{ url('/logout') }}" class="btn-logout">🚪 Cerrar Sesión</a>
            <h1>📊 Consulta de Expedientes SINACOL</h1>
            <p>Sistema Nacional de Conciliación - Reportes y Consultas</p>
        </div>

        <div class="search-card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('expediente')">
                    � Búsqueda por Expediente
                </button>
                <button class="tab" onclick="switchTab('temporalidad')">
                    📅 Búsqueda por Periodo
                </button>
            </div>

            <div class="error-message" id="errorMessage"></div>

            <!-- Tab: Búsqueda por Expediente -->
            <div class="tab-content active" id="tab-expediente">
                <form id="formExpediente" onsubmit="buscarPorExpediente(event)">
                    <div class="form-group">
                        <label for="numero_expediente">📋 Número de Expediente</label>
                        <input 
                            type="text" 
                            id="numero_expediente" 
                            name="numero_expediente" 
                            placeholder="Ej: AGU/CI/2024/055250"
                            required
                        >
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">🔍 Buscar Expediente</button>
                        <button type="reset" class="btn btn-secondary" onclick="limpiarResultados()">✕ Limpiar</button>
                    </div>
                </form>
            </div>

            <!-- Tab: Búsqueda por Temporalidad -->
            <div class="tab-content" id="tab-temporalidad">
                <form id="formTemporalidad" onsubmit="buscarPorTemporalidad(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">📅 Fecha de Inicio</label>
                            <input 
                                type="date" 
                                id="fecha_inicio" 
                                name="fecha_inicio" 
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="fecha_fin">📅 Fecha de Fin</label>
                            <input 
                                type="date" 
                                id="fecha_fin" 
                                name="fecha_fin" 
                                required
                            >
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">📊 Generar Reporte</button>
                        <button type="reset" class="btn btn-secondary" onclick="limpiarResultados()">✕ Limpiar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loading -->
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="color: #64748b; font-weight: 500;">Buscando expedientes...</p>
        </div>

        <!-- Results -->
        <div class="results-card" id="resultsCard">
            <div class="results-header">
                <div class="results-count" id="resultsCount">0 expedientes encontrados</div>
                <button class="btn btn-export" onclick="exportarExcel()">📥 Exportar Excel</button>
            </div>
            <div class="table-container">
                <table id="resultsTable">
                    <thead>
                        <tr>
                            <th>N° Expediente</th>
                            <th>Fecha Apertura</th>
                            <th>Fecha Cierre</th>
                            <th>Tipo Trámite</th>
                            <th>Tipo Solicitud</th>
                            <th>Trabajador</th>
                            <th>Empresa</th>
                            <th>Resultado</th>
                            <th>Asesor</th>
                            <th>Conciliador</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let currentData = [];

        function switchTab(tabName) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Mostrar el tab seleccionado
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');

            // Limpiar resultados
            limpiarResultados();
        }

        function buscarPorExpediente(event) {
            event.preventDefault();
            const numeroExpediente = document.getElementById('numero_expediente').value;
            const url = `/api/reporte-expedientes?numero_expediente=${encodeURIComponent(numeroExpediente)}`;
            realizarBusqueda(url);
        }

        function buscarPorTemporalidad(event) {
            event.preventDefault();
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const url = `/api/reporte-expedientes?fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
            realizarBusqueda(url);
        }

        async function realizarBusqueda(url) {
            // Ocultar error anterior
            document.getElementById('errorMessage').classList.remove('show');
            
            // Mostrar loading
            document.getElementById('loading').classList.add('show');
            document.getElementById('resultsCard').classList.remove('show');

            try {
                const response = await fetch(url);
                const data = await response.json();

                // Ocultar loading
                document.getElementById('loading').classList.remove('show');

                if (data.success) {
                    currentData = data.data;
                    mostrarResultados(data.data);
                } else {
                    mostrarError(data.message || 'Error al obtener los datos');
                }
            } catch (error) {
                document.getElementById('loading').classList.remove('show');
                mostrarError('Error de conexión: ' + error.message);
            }
        }

        function mostrarResultados(data) {
            const resultsBody = document.getElementById('resultsBody');
            const resultsCount = document.getElementById('resultsCount');
            const resultsCard = document.getElementById('resultsCard');

            // Actualizar contador
            resultsCount.textContent = `${data.length} expediente${data.length !== 1 ? 's' : ''} encontrado${data.length !== 1 ? 's' : ''}`;

            // Limpiar tabla
            resultsBody.innerHTML = '';

            if (data.length === 0) {
                resultsBody.innerHTML = `
                    <tr>
                        <td colspan="10" class="no-results">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3>No se encontraron expedientes</h3>
                            <p>Intenta ajustar los criterios de búsqueda</p>
                        </td>
                    </tr>
                `;
            } else {
                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${item.numero_expediente}</strong></td>
                        <td>${item.fecha_apertura}</td>
                        <td>${item.fecha_cierre}</td>
                        <td><span class="badge badge-info">${item.tipo_tramite}</span></td>
                        <td>${item.tipo_solicitud}</td>
                        <td>${item.nombre_trabajador || 'N/A'}</td>
                        <td>${item.nombre_empresa || 'N/A'}</td>
                        <td>${getBadgeResultado(item.resultado_audiencia)}</td>
                        <td>${item.asesor_atendio || 'N/A'}</td>
                        <td>${item.conciliador_atendio || 'N/A'}</td>
                    `;
                    resultsBody.appendChild(row);
                });
            }

            // Mostrar resultados
            resultsCard.classList.add('show');
        }

        function getBadgeResultado(resultado) {
            if (!resultado || resultado === 'Sin Resultado / Pendiente') {
                return `<span class="badge badge-warning">${resultado || 'Pendiente'}</span>`;
            }
            if (resultado.includes('Hubo convenio')) {
                return `<span class="badge badge-success">${resultado}</span>`;
            }
            if (resultado.includes('No hubo convenio')) {
                return `<span class="badge badge-danger">${resultado}</span>`;
            }
            if (resultado.includes('Archivado')) {
                return `<span class="badge badge-info">${resultado}</span>`;
            }
            return `<span class="badge badge-info">${resultado}</span>`;
        }

        function mostrarError(mensaje) {
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = mensaje;
            errorMessage.classList.add('show');
        }

        function limpiarResultados() {
            document.getElementById('resultsCard').classList.remove('show');
            document.getElementById('errorMessage').classList.remove('show');
            currentData = [];
        }

        function exportarExcel() {
            if (currentData.length === 0) {
                alert('No hay datos para exportar');
                return;
            }

            // Crear CSV
            let csv = 'Número Expediente,Fecha Apertura,Fecha Cierre,Tipo Trámite,Tipo Solicitud,Trabajador,Empresa,Resultado,Asesor,Conciliador\n';
            
            currentData.forEach(item => {
                csv += `"${item.numero_expediente}",`;
                csv += `"${item.fecha_apertura}",`;
                csv += `"${item.fecha_cierre}",`;
                csv += `"${item.tipo_tramite}",`;
                csv += `"${item.tipo_solicitud}",`;
                csv += `"${item.nombre_trabajador || 'N/A'}",`;
                csv += `"${item.nombre_empresa || 'N/A'}",`;
                csv += `"${item.resultado_audiencia}",`;
                csv += `"${item.asesor_atendio || 'N/A'}",`;
                csv += `"${item.conciliador_atendio || 'N/A'}"\n`;
            });

            // Descargar
            const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `expedientes_${new Date().getTime()}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Establecer fecha actual como máximo
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_inicio').setAttribute('max', today);
            document.getElementById('fecha_fin').setAttribute('max', today);
        });
    </script>
</body>
</html>
