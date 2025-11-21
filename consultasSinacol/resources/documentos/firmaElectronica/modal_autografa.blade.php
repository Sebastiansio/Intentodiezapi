<!-- Inicio modal autografa-->
<div class="modal" id="modal-autografa" data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 0px !important;">
            <div class="modal-header" style="background: #555770; color: white; border-top-left-radius: 0px; border-top-right-radius: 0px">
                <span class="general-detalle-span" style="font-weight: bold; font-size: 14px; color: #ffffff;">Firma autógrafa</span>
                <svg onclick="javascript:cancelarFirmaAutografa();" class="cursor-pointer" data-dismiss="modal" aria-label="Close" width="26px" height="26px" viewBox="0 0 26 26" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <title>289CC8ED-7E27-402E-AFC8-5259AA013291</title>
                    <defs>
                        <path d="M13.5779221,7.77792206 C13.8540644,7.77792206 14.0779221,8.00177969 14.0779221,8.27792206 L14.0779221,11.3769221 L17.1779221,11.3779221 C17.4540644,11.3779221 17.6779221,11.6017797 17.6779221,11.8779221 L17.6779221,13.5779221 C17.6779221,13.8540644 17.4540644,14.0779221 17.1779221,14.0779221 L14.0779221,14.0769221 L14.0779221,17.1779221 C14.0779221,17.4540644 13.8540644,17.6779221 13.5779221,17.6779221 L11.8779221,17.6779221 C11.6017797,17.6779221 11.3779221,17.4540644 11.3779221,17.1779221 L11.3779221,14.0769221 L8.27792206,14.0779221 C8.00177969,14.0779221 7.77792206,13.8540644 7.77792206,13.5779221 L7.77792206,11.8779221 C7.77792206,11.6017797 8.00177969,11.3779221 8.27792206,11.3779221 L11.3779221,11.3769221 L11.3779221,8.27792206 C11.3779221,8.00177969 11.6017797,7.77792206 11.8779221,7.77792206 L13.5779221,7.77792206 Z M12.7279221,3.72792206 C7.76532206,3.72792206 3.72792206,7.76532206 3.72792206,12.7279221 C3.72792206,17.6905221 7.76532206,21.7279221 12.7279221,21.7279221 C17.6905221,21.7279221 21.7279221,17.6905221 21.7279221,12.7279221 C21.7279221,7.76532206 17.6905221,3.72792206 12.7279221,3.72792206" id="path-1"></path>
                    </defs>
                    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g id="firma-4" transform="translate(-1019, -56)">
                            <g id="27)-Icon/close-circle-fill" transform="translate(1019, 56)">
                                <mask id="mask-2" fill="white">
                                    <use xlink:href="#path-1"></use>
                                </mask>
                                <use id="🎨-Icon-Сolor" fill="#FFFFFF" transform="translate(12.7279, 12.7279) rotate(45) translate(-12.7279, -12.7279)" xlink:href="#path-1"></use>
                            </g>
                        </g>
                    </g>
                </svg>
            </div>
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="parte_id" id="parte_id" value="">
            <input type="hidden" name="clasificacion_archivo_id" id="clasificacion_archivo_id" value="">
            <input type="hidden" name="tipofirma" id="tipofirma" value="autografa">
            <div class="modal-body">
                <p>
                    <span class="constancia-de-no-con">
                        {{$nombre_archivo_firma}}
                    </span>
                </p>
                <p>
                    <span class="los-datos-marcados-c">
                        Los datos marcados con asterisco (<span class="text-style-1">*</span>) son obligatorios
                    </span>
                </p>
                <p style="margin-top:-10px;">
                    <span class="nombre-completo-de-l">
                        Nombre completo de la persona que firma<span class="text-style-1">*</span>
                    </span>
                <div style="margin-top:-15px;">
                    <span class="nombrePartes">
                        {{auth()->user()->persona->nombre.' '.auth()->user()->persona->primer_apellido.' '.(isset(auth()->user()->persona->segundo_apellido) ? auth()->user()->persona->segundo_apellido : '')}}
                    </span>
                </div>
                </p>
                <p>
                    <span class="instrucciones">
                        Instrucciones
                    </span>
                </p>
                <span class="descarga-el-docum mb-2">
                    1. Descarga el documento
                    <a class="descargar" href="\confidencialidadpersonaformato\{{$persona->curp}}" target="_blank">
                        <svg width="14" height="16" viewBox="0 0 14 16" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs>
                                <path d="M11.052 7.165a.54.54 0 0 0-.487-.288H8.42V.491c0-.27-.24-.491-.536-.491H5.742c-.296 0-.536.22-.536.491v6.386H3.062c-.21 0-.4.113-.487.288a.458.458 0 0 0 .084.527l3.752 3.93a.556.556 0 0 0 .402.167.56.56 0 0 0 .403-.168l3.752-3.93a.458.458 0 0 0 .084-.526zM.6 12.632h12.274a.6.6 0 0 1 .6.6V15.4a.6.6 0 0 1-.6.6H.6a.6.6 0 0 1-.6-.6v-2.168a.6.6 0 0 1 .6-.6z" id="7ysmq1aqsa" />
                            </defs>
                            <use fill="{{config('colores.btn-primary-color')}}" xlink:href="#7ysmq1aqsa" fill-rule="evenodd" />
                        </svg>
                        <span style="color: {{config('colores.btn-primary-color')}} !important;">Descargar</span>
                    </a>
                    <br>
                    2. Firmar el documento de manera autógrafa
                    <br>
                    3. Escanea el documento firmado
                    <br>
                    4. Carga el documento firmado<span class="text-style-1">*</span>
                </span>
                @include('componentes.input_file', ["id_file" => "file_autografa", "labelVisibe" => 'd-none', "tituloLabel" => 'Autografa', "tipo" => 'autografa', "accept" => 'application/pdf'])
            </div>
            <div class="modal-footer">
                <div class="text-right">
                    <a class="btn btnRegresar cursor-pointer" style="border: 2px solid #555770;" onclick="javascript:cancelarFirmaAutografa();"><i class="fa fa-times"></i> Cancelar </a>
                    <a class="btn cursor-pointer" id="btn_afirma" style="border:2px solid {{config('colores.btn-primary-color')}}; color: {{config('colores.btn-primary-color')}}">
                        <svg width="15" height="12" viewBox="0 0 15 12" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs>
                                <path d="M14.108 2.029 12.269.19a.65.65 0 0 0-.92 0l-4.2 4.201-2.362 2.363-1.839-1.839a.65.65 0 0 0-.92 0L.19 6.754a.65.65 0 0 0 0 .919l4.137 4.137a.65.65 0 0 0 .92 0l1.902-1.903 6.959-6.959a.65.65 0 0 0 0-.92z" id="qxtkfbhcwa" />
                            </defs>
                            <use fill="{{config('colores.btn-primary-color')}}" xlink:href="#qxtkfbhcwa" fill-rule="evenodd" />
                        </svg> Aceptar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fin modal autografa-->