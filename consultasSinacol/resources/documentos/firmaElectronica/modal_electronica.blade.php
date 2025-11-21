<!-- Inicio modal electronica-->
<div class="modal" id="modal-electronica" data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 0px !important;">
            <div class="modal-header" style="background: #555770; color: white; border-top-left-radius: 0px; border-top-right-radius: 0px">
                <span class="general-detalle-span totalFirmaElectronica" style="font-weight: bold; font-size: 14px; color: #ffffff;">0 documentos</span>
                <svg onclick="javascript:cancelarFirmaElectronica();" class="cursor-pointer" data-dismiss="modal" aria-label="Close" width="26px" height="26px" viewBox="0 0 26 26" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
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
            <div class="modal-body">
                <p>
                    <span class="los-datos-marcados-c">
                        Los datos marcados con asterisco (<span class="text-style-1">*</span>) son obligatorios
                    </span>
                </p>
                <form id="formulario_certificado" action={{ route('plantilla-documento.firmardocumentoPlantilla') }} method="POST" enctype="multipart/form-data" data-parsley-validate="true">
                    <div class="content-file mb-3">
                        @include('componentes.input_file', ["id_file" => "file_certificado", "labelVisibe" => '', "tituloLabel" => 'Certificado', "tipo" => 'cer', "accept" => '.cer'])
                        <input type="hidden" id="api_token" name="api_token" value="{{env('API_TOKEN_CERTIFICADO_SAT')}}">
                        <input type="hidden" name="cadena_original" id="cadena_original">
                        <input type="hidden" id="url_api_certificado" value="{{env('URL_API_REST_CERTIFICADO')}}">
                        <input type="hidden" name="rfc" id="rfc1">
                        <input type="hidden" name="curp" value="{{$curp}}">
                        <input type="hidden" name="nombre" id="nombre1" value="{{auth()->user()->persona->nombre.' '.auth()->user()->persona->primer_apellido.' '.(isset(auth()->user()->persona->segundo_apellido) ? auth()->user()->persona->segundo_apellido : '')}}">
                        <input type="hidden" name="_token" id="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="firma" id="firma">
                        <input type="hidden" name="listaparte" id="listaparte">
                        <input type="hidden" name="nombreConciliador" id="nombreConciliador" value="{{auth()->user()->persona->nombre.' '.auth()->user()->persona->primer_apellido.' '.(isset(auth()->user()->persona->segundo_apellido) ? auth()->user()->persona->segundo_apellido : '')}}">
                    </div>

                    <br>
                    @include('componentes.input_file', ["id_file" => "file_llave", "labelVisibe" => '', "tituloLabel" => 'Llave', "tipo" => 'key', "accept" => '.key'])
                    <br>
                    <div>
                        <span class="certificado">Contraseña FIEL: <span class="text-style-1">*</span></span>
                        <div>
                            <input type="password" placeholder="Captura tu contraseña FIEL" class="form-control" data-label="labelIdentificacionOficial" maxlength="10" name="password" id="password" data-parsley-required-message="Campo es requerido" required>
                        </div>
                    </div>
                    <br>
                    <div>
                        <span class="certificado">Nombre de la persona que firma:</span>
                        <div>
                            <input type="text" disabled class="form-control" data-label="labelIdentificacionOficial" name="nombre" id="nombre" data-parsley-required-message="Campo es requerido" required>
                        </div>
                    </div>
                    <br>
                    <div>
                        <span class="certificado">RFC de la persona que firma:</span>
                        <div>
                            <input type="text" disabled class="form-control" data-label="labelIdentificacionOficial" name="rfc" id="rfc" data-parsley-required-message="Campo es requerido" required>
                        </div>
                    </div>
                </form>
                <div class="alert alert-danger alert-dismissible fade show alerta-firma d-none mt-2" role="alert"></div>
            </div>
            <div class="modal-footer">
                <div class="text-right">
                    <a class="btn btnRegresar cursor-pointer" style="border: 2px solid #555770;" onclick="javascript:cancelarFirmaElectronica();"><i class="fa fa-times"></i> Cancelar </a>
                    <a class="btn cursor-pointer classFirmar" id="aceptarFirmaElectronica" style="border:2px solid {{config('colores.btn-primary-color')}}; color: {{config('colores.btn-primary-color')}}">
                        <svg width="16px" height="16px" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <title>F6FEF1A2-690E-4077-B0BC-0476FA138936</title>
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="firma-5" transform="translate(-758, -230)" fill="{{config('colores.btn-primary-color')}}" fill-rule="nonzero">
                                    <g id="Group-8" transform="translate(732, 230)">
                                        <g id="Group" transform="translate(26, 0)">
                                            <path d="M0,0 L0,4.66660156 L4.66660156,4.66660156 L4.66660156,0 L0,0 Z M3.33339844,3.33339844 L1.33339844,3.33339844 L1.33339844,1.33339844 L3.33339844,1.33339844 L3.33339844,3.33339844 Z" id="Shape"></path>
                                            <path d="M0,11.3332031 L0,16 L4.66660156,16 L4.66660156,11.3332031 L0,11.3332031 Z M3.33339844,14.6667969 L1.33339844,14.6667969 L1.33339844,12.6667969 L3.33339844,12.6667969 L3.33339844,14.6667969 Z" id="Shape"></path>
                                            <path d="M11.3333984,0 L11.3333984,4.66660156 L16,4.66660156 L16,0 L11.3333984,0 Z M14.6666016,3.33339844 L12.6666016,3.33339844 L12.6666016,1.33339844 L14.6666016,1.33339844 L14.6666016,3.33339844 Z" id="Shape"></path>
                                            <polygon id="Path" points="14.6666016 6 14.6666016 10 11.3333984 10 11.3333984 11.3332031 16 11.3332031 16 6">
                                            </polygon>
                                            <polygon id="Path" points="11.3333984 12.6667969 11.3333984 16 12.6666016 16 12.6666016 14 14.6666016 14 14.6666016 16 16 16 16 12.6667969">
                                            </polygon>
                                            <polygon id="Path" points="6 0 6 1.33339844 8.66660156 1.33339844 8.66660156 4.66660156 10 4.66660156 10 0">
                                            </polygon>
                                            <polygon id="Path" points="8.66660156 6 8.66660156 8.66679687 6 8.66679687 6 12.6667969 8.66660156 12.6667969 8.66660156 16 10 16 10 11.3332031 7.33339844 11.3332031 7.33339844 10 10 10 10 7.33339844 11.3333984 7.33339844 11.3333984 8.66679687 12.6666016 8.66679687 12.6666016 6">
                                            </polygon>
                                            <rect id="Rectangle" x="6" y="14" width="1.33339844" height="2"></rect>
                                            <rect id="Rectangle" x="2.66660156" y="8.66601562" width="2" height="1.33320313"></rect>
                                            <polygon id="Path" points="6 2.66660156 6 6 0 6 0 10 1.33339844 10 1.33339844 7.33339844 7.33339844 7.33339844 7.33339844 2.66660156">
                                            </polygon>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </svg> Firmar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fin modal electronica-->
