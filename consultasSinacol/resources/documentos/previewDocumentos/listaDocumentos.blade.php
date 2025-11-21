<table id="tabla-detalle" style="width:100%;" class="table table-general-td mt-4 span-general-table td-vertical ">
    <thead class="head-general">
        <tr>
            <th class="all" style="text-align: left;">Documento a generar</th>
            <th class="all" style="text-align: left;">Estatus</th>
            <th class="all">Firmar</th>
            <th class="all">Acciones</th>
        </tr>
    </thead>
    <tbody class="general-body">
        @foreach($listVistaPrevia as $vistaPrevia)
        <tr class="odd gradeX">
            <td style="text-align: left;"><span class="general-folio-span" id="documento_{{$vistaPrevia['parte_solicitante_id']}}" style="font-weight: bold; font-size: 10px;">{{$vistaPrevia['nombreDocto']}}</span></td>
            <td style="text-align: left;">
                <span class="general-detalle-span {{$vistaPrevia['status'] == 'No aplica' ? 'd-none': ($vistaPrevia['status'] == 'Por firmar' ? '' : 'd-none')}}" style="font-weight: bold; font-size: 10px;">{{$vistaPrevia['status']}}</span>
                <span class="firmado-firma-autg {{$vistaPrevia['status'] == 'No aplica' ? 'd-none': ($vistaPrevia['status'] != 'Por firmar' ? '' : 'd-none')}}">
                    <svg class="{{$vistaPrevia['status'] == 'No aplica' ? 'd-none': ($vistaPrevia['status'] != 'Por firmar' ? ($vistaPrevia['status'] == 'autografa' ? 'd-none' : '') : 'd-none')}}" width="12px" height="12px" viewBox="0 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                        <title>3139F82D-26EA-4D7E-B708-D2F17AE801EF</title>
                        <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <g id="documentos-expediente" transform="translate(-1010, -355)">
                                <g id="Group-7" transform="translate(1010, 355)">
                                    <rect id="Rectangle" fill="#5B8DEF" x="0" y="0" width="20" height="20"></rect>
                                    <g id="Group" transform="translate(3, 3)" fill="#FFFFFF" fill-rule="nonzero">
                                        <path d="M0,0 L0,4.08327637 L4.08327637,4.08327637 L4.08327637,0 L0,0 Z M2.91672363,2.91672363 L1.16672363,2.91672363 L1.16672363,1.16672363 L2.91672363,1.16672363 L2.91672363,2.91672363 Z" id="Shape"></path>
                                        <path d="M0,9.91655273 L0,14 L4.08327637,14 L4.08327637,9.91655273 L0,9.91655273 Z M2.91672363,12.8334473 L1.16672363,12.8334473 L1.16672363,11.0834473 L2.91672363,11.0834473 L2.91672363,12.8334473 Z" id="Shape"></path>
                                        <path d="M9.91672363,0 L9.91672363,4.08327637 L14,4.08327637 L14,0 L9.91672363,0 Z M12.8332764,2.91672363 L11.0832764,2.91672363 L11.0832764,1.16672363 L12.8332764,1.16672363 L12.8332764,2.91672363 Z" id="Shape"></path>
                                        <polygon id="Path" points="12.8332764 5.25 12.8332764 8.75 9.91672363 8.75 9.91672363 9.91655273 14 9.91655273 14 5.25"></polygon>
                                        <polygon id="Path" points="9.91672363 11.0834473 9.91672363 14 11.0832764 14 11.0832764 12.25 12.8332764 12.25 12.8332764 14 14 14 14 11.0834473"></polygon>
                                        <polygon id="Path" points="5.25 0 5.25 1.16672363 7.58327637 1.16672363 7.58327637 4.08327637 8.75 4.08327637 8.75 0"></polygon>
                                        <polygon id="Path" points="7.58327637 5.25 7.58327637 7.58344727 5.25 7.58344727 5.25 11.0834473 7.58327637 11.0834473 7.58327637 14 8.75 14 8.75 9.91655273 6.41672363 9.91655273 6.41672363 8.75 8.75 8.75 8.75 6.41672363 9.91672363 6.41672363 9.91672363 7.58344727 11.0832764 7.58344727 11.0832764 5.25"></polygon>
                                        <rect id="Rectangle" x="5.25" y="12.25" width="1.16672363" height="1.75"></rect>
                                        <rect id="Rectangle" x="2.33327637" y="7.58276367" width="1.75" height="1.16655273"></rect>
                                        <polygon id="Path" points="5.25 2.33327637 5.25 5.25 0 5.25 0 8.75 1.16672363 8.75 1.16672363 6.41672363 6.41672363 6.41672363 6.41672363 2.33327637"></polygon>
                                    </g>
                                </g>
                            </g>
                        </g>
                    </svg>
                    <svg class="{{$vistaPrevia['status'] == 'No aplica' ? 'd-none': ($vistaPrevia['status'] != 'Por firmar' ? ($vistaPrevia['status'] == 'autografa' ? '' : 'd-none') : 'd-none')}}" width="12px" height="12px" viewBox="0 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                        <title>002EE974-81D2-479F-B8DD-329268EF3BAD</title>
                        <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <g id="documentos-expediente" transform="translate(-1161, -133)">
                                <g id="Group-3" transform="translate(953, 133)">
                                    <g id="Group-5" transform="translate(208, 0)">
                                        <rect id="Rectangle-Copy-2" fill="#FDAC42" x="0" y="0" width="20" height="20"></rect>
                                        <g id="Group-4" transform="translate(2, 4)" fill="#FFFFFF" fill-rule="nonzero">
                                            <path d="M2.70168409,2.9274461 C3.48489567,0.510996002 4.88495839,-0.598541801 6.71799271,0.322112264 C8.5491854,1.24186507 8.60084544,3.16115534 7.17570824,4.93629249 C6.29097098,6.03833566 4.92882692,7.0102663 3.31697692,7.68962163 C3.23934519,8.70040103 3.20000989,9.79888662 3.20000989,10.9612663 C3.20000989,11.3557782 2.88164425,11.6756757 2.48899959,11.6756757 C2.09626049,11.6756757 1.77789485,11.355873 1.77789485,10.9613612 C1.77789485,9.99782645 1.80396098,9.07475323 1.8562349,8.20328863 C1.52710282,8.29720872 1.1914542,8.37893818 0.850705692,8.447386 C0.465616432,8.52475149 0.0909630679,8.273871 0.013945216,7.88704356 C-0.0630726359,7.50016868 0.186680533,7.12387167 0.571769793,7.04650618 C1.05583478,6.94926568 1.52757503,6.82204665 1.98104065,6.66949765 C2.13417915,5.20756977 2.3741103,3.93813064 2.70168409,2.9274461 Z M6.08196975,1.59989985 C5.23071235,1.17237369 4.60559749,1.66777846 4.0539589,3.36972435 C3.81695547,4.1009729 3.62807049,5.00203656 3.48881503,6.03677033 C4.5678205,5.48544042 5.46587415,4.79033688 6.06865335,4.03949793 C6.98890099,2.89324591 6.96604591,2.04393317 6.08196975,1.59989985 Z" id="Combined-Shape"></path>
                                            <path d="M7.29064475,8.57446273 L8.73542599,6.91587187 L8.80096908,7.44263076 C8.87402035,8.02982107 9.40714821,8.44629501 9.99170001,8.37291401 C10.2368727,8.34212909 10.4637708,8.22681609 10.6337201,8.04666028 L11.2952897,7.34524796 L11.7169282,7.94918261 C11.9777312,8.32272842 12.4454104,8.48936543 12.8822069,8.36447069 L15.4833892,7.62065203 C15.861112,7.51264392 16.0801713,7.11746802 15.9726486,6.73808778 C15.8651258,6.3586601 15.4717728,6.13861196 15.0940501,6.24662007 L12.7361044,6.92085248 L12.2097764,6.16702516 C12.1687411,6.10830139 12.1219921,6.05384671 12.0701431,6.00446751 C11.6425075,5.59748045 10.9673854,5.61579012 10.5622271,6.04535595 L10.118914,6.51538332 L10.0483655,5.94854237 C10.0159245,5.68779553 9.88922991,5.44810956 9.69236454,5.2750689 C9.24900426,4.8853954 8.57515708,4.9305055 8.18723455,5.37586698 L6.22046966,7.63379135 C5.9618389,7.9306832 5.99182439,8.38197398 6.28733494,8.64172469 C6.58279828,8.90142798 7.03206121,8.87135457 7.29064475,8.57446273 Z" id="signature_x2C_-name_x2C_-person_x2C_-distinctive_x2C_-identification_x2C_-authorization-path"></path>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </g>
                    </svg> Firmado
                </span>
            </td>
            <td>
                @if($vistaPrevia['status'] != 'No aplica')
                <div class="{{$vistaPrevia['status'] == 'No aplica' ? 'd-none': ($vistaPrevia['status'] == 'Por firmar' ? '' : 'd-none')}}">
                    <div data-placement="top" style="display: inline-block;">
                        <input type="radio" id="firma_electronica_{{$vistaPrevia['parte_solicitante_id']}}" data-id="{{$vistaPrevia['parte_solicitante_id']}}" name="firma_{{$vistaPrevia['parte_solicitante_id']}}" data-tipo="electronica" class="radio_input cursor-pointer classFirma">
                        <span onclick="javascript:checkRadioFirma('firma_electronica_{{$vistaPrevia['parte_solicitante_id']}}');" class="general-detalle-span cursor-pointer" data-id="firma_electronica_{{$vistaPrevia['parte_solicitante_id']}}" style="font-weight: bold; font-size: 10px; margin-left: 5px;">
                            <svg width="16px" height="16px" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <title>F6FEF1A2-690E-4077-B0BC-0476FA138936</title>
                                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="firma-5" transform="translate(-758, -230)" fill="#555770" fill-rule="nonzero">
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
                            </svg> <strong style="margin-left: 5px;">Firma electrónica</strong>
                        </span>
                    </div>
                    <div data-placement="top" style="display: inline-block;">
                        <input type="radio" id="firma_autografa_{{$vistaPrevia['parte_solicitante_id']}}" data-id="{{$vistaPrevia['parte_solicitante_id']}}" name="firma_{{$vistaPrevia['parte_solicitante_id']}}" data-tipo="manual" class="ml-2 radio_input cursor-pointer classFirma">
                        <span onclick="javascript:checkRadioFirma('firma_autografa_{{$vistaPrevia['parte_solicitante_id']}}');" class="general-detalle-span cursor-pointer" data-id="firma_autografa_{{$vistaPrevia['parte_solicitante_id']}}" style="font-weight: bold; font-size: 10px; margin-left: 5px;">
                            <svg width="21px" height="15px" viewBox="0 0 21 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <title>C9B40915-7671-4D2D-BDC1-C12EAD57927D</title>
                                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="firma-5" transform="translate(-924, -231)" fill="#555770" fill-rule="nonzero">
                                        <g id="Group-8" transform="translate(732, 230)">
                                            <g id="Group-7" transform="translate(166, 0)">
                                                <g id="Group-4" transform="translate(26, 1)">
                                                    <path d="M3.47091358,3.76095506 C4.47712291,0.65648792 6.27581459,-0.768959952 8.63075452,0.413824783 C10.9833285,1.59545166 11.0496973,4.06120651 9.21879184,6.34176466 C8.08215022,7.75758401 6.33217348,9.0062449 4.26139396,9.87902779 C4.16165875,11.1775985 4.11112382,12.5888474 4.11112382,14.0821824 C4.11112382,14.5890205 3.70211241,15 3.19767309,15 C2.69311244,15 2.28410103,14.5891424 2.28410103,14.0823043 C2.28410103,12.8444298 2.31758876,11.6585371 2.38474623,10.5389472 C1.96190292,10.6596084 1.53068769,10.7646081 1.09292051,10.8525445 C0.598187777,10.9519377 0.116862275,10.6296259 0.0179157289,10.1326601 C-0.081030817,9.63563337 0.239832629,9.15219624 0.734565359,9.05280307 C1.35645441,8.92787605 1.96250959,8.76443493 2.54508695,8.56845184 C2.74182738,6.6902806 3.05007226,5.05940394 3.47091358,3.76095506 Z M7.81364169,2.05542689 C6.72001239,1.50617454 5.91691345,2.14263205 5.20821109,4.32915975 C4.90372751,5.26861101 4.66106278,6.42622752 4.4821582,7.75557299 C5.8683805,7.04726721 7.02212999,6.15425224 7.79653382,5.18963276 C8.97879641,3.71701731 8.94943398,2.62588636 7.81364169,2.05542689 Z" id="Combined-Shape"></path>
                                                    <path d="M9.36645333,11.0158028 L11.2225959,8.88497428 L11.3068006,9.56171313 C11.4006511,10.3160896 12.0855724,10.8511429 12.836559,10.7568687 C13.1515379,10.7173186 13.4430389,10.5691734 13.6613765,10.3377233 L14.5113097,9.43660328 L15.0529981,10.2124915 C15.3880574,10.6923942 15.9888953,10.9064764 16.5500575,10.7460214 L19.8918542,9.79042101 C20.377123,9.65166059 20.6585535,9.14396934 20.5204166,8.6565711 C20.3822797,8.16911193 19.8769303,7.8864112 19.3916615,8.02517162 L16.3623563,8.89137298 L15.686171,7.92291427 C15.6334521,7.84747053 15.5733926,7.7775114 15.5067811,7.71407284 C14.9573882,7.19120752 14.0900437,7.21473037 13.5695279,7.76660314 L12.9999937,8.37045774 L12.9093585,7.64222457 C12.8676808,7.30723731 12.7049134,6.99930742 12.4519961,6.77699824 C11.8824013,6.27637603 11.0166949,6.33432998 10.5183222,6.90649578 L7.9915756,9.80730139 C7.65930691,10.1887249 7.69782994,10.7685082 8.07747892,11.1022158 C8.45706723,11.4358623 9.03424531,11.3972264 9.36645333,11.0158028 Z" id="signature_x2C_-name_x2C_-person_x2C_-distinctive_x2C_-identification_x2C_-authorization-path">
                                                    </path>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg> <strong style="margin-left: 2px;">Firma autógrafa</strong>
                        </span>
                    </div>
                </div>
                @endif
            </td>
            <td>
                <div title="Firma autógrafa" data-toggle="tooltip" data-placement="top" style="display: inline-block;">
                    <span class="general-detalle-span cursor-pointer d-none" onclick="javascript:modalAutografa(this, {{$vistaPrevia['parte_solicitante_id'] ??-1}}, {{$vistaPrevia['clasificacion_archivo_id']}}, {{$vistaPrevia['id']}}, {{$vistaPrevia['plantilla_id']}}, {{$vistaPrevia['parte_solicitado_id'] ?? -1}});" id="firmar_{{$vistaPrevia['parte_solicitante_id']}}" style="color: {{config('colores.btn-primary-color')}}">
                        <svg width="21px" height="15px" viewBox="0 0 21 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <title>C9B40915-7671-4D2D-BDC1-C12EAD57927D</title>
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="firma-5" transform="translate(-924, -231)" fill="{{config('colores.btn-primary-color')}}" fill-rule="nonzero">
                                    <g id="Group-8" transform="translate(732, 230)">
                                        <g id="Group-7" transform="translate(166, 0)">
                                            <g id="Group-4" transform="translate(26, 1)">
                                                <path d="M3.47091358,3.76095506 C4.47712291,0.65648792 6.27581459,-0.768959952 8.63075452,0.413824783 C10.9833285,1.59545166 11.0496973,4.06120651 9.21879184,6.34176466 C8.08215022,7.75758401 6.33217348,9.0062449 4.26139396,9.87902779 C4.16165875,11.1775985 4.11112382,12.5888474 4.11112382,14.0821824 C4.11112382,14.5890205 3.70211241,15 3.19767309,15 C2.69311244,15 2.28410103,14.5891424 2.28410103,14.0823043 C2.28410103,12.8444298 2.31758876,11.6585371 2.38474623,10.5389472 C1.96190292,10.6596084 1.53068769,10.7646081 1.09292051,10.8525445 C0.598187777,10.9519377 0.116862275,10.6296259 0.0179157289,10.1326601 C-0.081030817,9.63563337 0.239832629,9.15219624 0.734565359,9.05280307 C1.35645441,8.92787605 1.96250959,8.76443493 2.54508695,8.56845184 C2.74182738,6.6902806 3.05007226,5.05940394 3.47091358,3.76095506 Z M7.81364169,2.05542689 C6.72001239,1.50617454 5.91691345,2.14263205 5.20821109,4.32915975 C4.90372751,5.26861101 4.66106278,6.42622752 4.4821582,7.75557299 C5.8683805,7.04726721 7.02212999,6.15425224 7.79653382,5.18963276 C8.97879641,3.71701731 8.94943398,2.62588636 7.81364169,2.05542689 Z" id="Combined-Shape"></path>
                                                <path d="M9.36645333,11.0158028 L11.2225959,8.88497428 L11.3068006,9.56171313 C11.4006511,10.3160896 12.0855724,10.8511429 12.836559,10.7568687 C13.1515379,10.7173186 13.4430389,10.5691734 13.6613765,10.3377233 L14.5113097,9.43660328 L15.0529981,10.2124915 C15.3880574,10.6923942 15.9888953,10.9064764 16.5500575,10.7460214 L19.8918542,9.79042101 C20.377123,9.65166059 20.6585535,9.14396934 20.5204166,8.6565711 C20.3822797,8.16911193 19.8769303,7.8864112 19.3916615,8.02517162 L16.3623563,8.89137298 L15.686171,7.92291427 C15.6334521,7.84747053 15.5733926,7.7775114 15.5067811,7.71407284 C14.9573882,7.19120752 14.0900437,7.21473037 13.5695279,7.76660314 L12.9999937,8.37045774 L12.9093585,7.64222457 C12.8676808,7.30723731 12.7049134,6.99930742 12.4519961,6.77699824 C11.8824013,6.27637603 11.0166949,6.33432998 10.5183222,6.90649578 L7.9915756,9.80730139 C7.65930691,10.1887249 7.69782994,10.7685082 8.07747892,11.1022158 C8.45706723,11.4358623 9.03424531,11.3972264 9.36645333,11.0158028 Z" id="signature_x2C_-name_x2C_-person_x2C_-distinctive_x2C_-identification_x2C_-authorization-path">
                                                </path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </svg>
                    </span>
                </div>
                <div title="{{$vistaPrevia['status'] == 'No aplica' ? 'Ver documento previo': ($vistaPrevia['status'] != 'Por firmar' ? 'Ver documento firmado' : 'Ver documento previo')}}" data-toggle="tooltip" data-placement="top" style="display: inline-block;">
                    <a id="ver_documento" onclick="javascript:ver_documento({{$vistaPrevia['plantilla_id']}}, {{$vistaPrevia['parte_solicitante_id'] ??-1}}, {{$vistaPrevia['parte_solicitado_id'] ?? -1}}, '{{$vistaPrevia['uuid'] ?? -1}}', 'pdf', '{{$vistaPrevia['nombreDocto']}}');" data-idplantilla="{{$vistaPrevia['plantilla_id']}}" data-solicitanteid="{{$vistaPrevia['parte_solicitante_id']}}" data-solicitadoid="{{$vistaPrevia['parte_solicitado_id']}}" class="m-1">
                        <svg width="19" viewBox="0 0 19 12" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="fill: {{config('colores.btn-primary-color')}} !important; cursor: pointer;">
                            <defs>
                                <path d="M8.57 4.715c-.708 0-1.285.577-1.285 1.286 0 .708.577 1.285 1.285 1.285.71 0 1.286-.577 1.286-1.285 0-.71-.577-1.286-1.286-1.286M8.57 9c-1.654 0-3-1.345-3-3 0-1.653 1.346-2.999 3-2.999 1.655 0 3 1.346 3 3s-1.345 3-3 3m8.458-3.427c-.548-.953-3.567-5.73-8.69-5.57C3.601.124.845 4.299.113 5.574a.862.862 0 0 0 0 .853C.653 7.367 3.567 12 8.592 12c.07 0 .14 0 .21-.003 4.738-.12 7.494-4.294 8.226-5.57a.862.862 0 0 0 0-.853" id="mofe1g4eja" />
                            </defs>
                            <use xlink:href="#mofe1g4eja" transform="translate(.961)" fill-rule="evenodd" />
                        </svg>
                    </a>
                </div>
                <div title="Cambiar firma autógrafa" data-toggle="tooltip" data-placement="top" style="display: inline-block;" class="ml-2 {{$vistaPrevia['status'] == 'No aplica' ? 'd-none': ($vistaPrevia['status'] != 'Por firmar' ? '' : 'd-none')}}">
                    <span class="general-detalle-span cursor-pointer" onclick="javascript:modalAutografa(this, {{$vistaPrevia['parte_solicitante_id'] ??-1}}, {{$vistaPrevia['clasificacion_archivo_id']}}, {{$vistaPrevia['id']}}, {{$vistaPrevia['plantilla_id']}}, {{$vistaPrevia['parte_solicitado_id'] ?? -1}});" id="cambiar_{{$vistaPrevia['parte_solicitante_id']}}" style="color: {{config('colores.btn-primary-color')}}">
                        <svg width="16px" height="18px" viewBox="0 0 16 18" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <title>BF745219-00A4-4581-9CED-0AF6B33687F0</title>
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="firma-7" transform="translate(-1114, -229)" fill="{{config('colores.btn-primary-color')}}" fill-rule="nonzero">
                                    <g id="Group-8" transform="translate(1114, 226)">
                                        <g id="Group" transform="translate(0, 3)">
                                            <path d="M8.28297518,7.15498351 L8.28297518,8.05998231 C8.28297518,8.34741211 8.34037872,8.58856201 8.45367526,8.77642822 C8.60995576,9.03570557 8.86648652,9.18429567 9.15776154,9.18429567 C9.44313143,9.18429567 9.72671601,9.04531859 10.0005503,8.77148438 L12.9896555,5.78237913 C13.6459513,5.12608338 13.6459513,4.05821229 12.9896555,3.4019165 L10.0005503,0.412811297 C9.72671601,0.13883973 9.44313143,0 9.15776154,0 C8.72229095,0 8.28297518,0.347717285 8.28297518,1.12431336 L8.28297518,1.94622803 C4.03923126,2.19685363 0.526078204,5.45361328 0.00395294265,9.71754455 C-0.0252981472,9.95567323 0.110108329,10.1836395 0.333268146,10.2719422 C0.396439532,10.296936 0.462220181,10.309021 0.527176837,10.309021 C0.691697083,10.309021 0.851273517,10.2319794 0.952897052,10.0931396 C2.35337921,8.17959593 4.60200594,7.03715516 6.9679117,7.03715516 C7.40626619,7.03715516 7.84709259,7.07670594 8.28297518,7.15498351 L8.28297518,7.15498351 Z" id="Path"></path>
                                            <path d="M15.4073343,7.72805784 C15.1841745,7.63961794 14.9295663,7.71322634 14.7877054,7.90699767 C13.3873605,9.82040407 11.1387339,10.9628448 8.77282806,10.9628448 C8.33447356,10.9628448 7.89364717,10.9232941 7.45776458,10.8450165 L7.45776458,9.94001769 C7.45776458,9.16342165 7.01831145,8.81570433 6.58297822,8.81570433 C6.29760833,8.81570433 6.01402374,8.95468141 5.74005221,9.22865295 L2.75094697,12.2176209 C2.09478854,12.8739166 2.09478854,13.9417877 2.75108429,14.5982208 L5.74005221,17.5871887 C6.01402374,17.8611603 6.29760833,18 6.58297822,18 C7.01831145,18 7.45776458,17.6522827 7.45776458,16.8756866 L7.45776458,16.053772 C11.7016458,15.8031464 15.2146616,12.5463867 15.7367868,8.28259277 C15.7660379,8.04432677 15.6306314,7.81636046 15.4073343,7.72805784 L15.4073343,7.72805784 Z" id="Path"></path>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </svg><strong style="font-weight: bold; font-size: 10px; margin-left: 2px;">Cambiar firma</strong>
                    </span>
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>