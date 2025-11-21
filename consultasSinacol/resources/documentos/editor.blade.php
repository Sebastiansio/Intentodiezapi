<h4 class="offset-2"><i class="fa fa-cog"></i>  Configuración de plantillas</h4><br>

<div class="row">
    <div class="col-md-6 offset-2">
        <label for="nombre-plantilla" class="control-label">Nombre de plantilla </label>
        <input 
            type="text" 
            name="nombre-plantilla" 
            id="nombre-plantilla" 
            class="form-control col-md-6" 
            placeholder="Nombre de la plantilla" 
            maxlength="60" 
            size="10" 
            autofocus 
            value="{{ old('nombre-plantilla', $plantillaDocumento->nombre_plantilla ?? '') }}"
        />
    
    </div>
    <div class="col-md-4">
        <label for="clave-nomenclatura" class="control-label">Clave nomenclatura </label>
        <input 
            type="text" 
            name="clave_nomenclatura" 
            id="clave-nomenclatura" 
            class="form-control col-md-6" 
            placeholder="Clave nomenclatura" 
            maxlength="60" 
            size="10" 
            autofocus 
            value="{{ old('clave_nomenclatura', $plantillaDocumento->clave_nomenclatura ?? '') }}"
        />
    
    </div>
</div>

<br>

<div class="row">
    <div class="col-md-8 offset-2">
        <label for="tipo-plantilla-id" class="control-label">Tipo de plantilla </label>
        <select 
            name="tipo-plantilla-id" 
            id="tipo-plantilla-id" 
            class="form-control catSelect" 
            required>
            <option value="" disabled selected>Seleccione una opción</option>
            @foreach($tipo_plantilla ?? [] as $id => $nombre)
                <option value="{{ $id }}" {{ old('tipo-plantilla-id', $plantillaDocumento->tipo_documento_id ?? '') == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                </option>
            @endforeach
        </select>

        @if ($errors->has('tipo-plantilla-id'))
            <span class="text-danger">{{ $errors->first('tipo-plantilla-id') }}</span>
        @endif

    </div>
</div>

    <br><br><br>
    <label for="nombre-plantilla" class="control-label offset-2">Contenido de plantilla </label>
    <div class="row">
        <div class="col-md-2"></div>
        <div class="col-md-8">
          <div id="objeto"></div>
            <div id="plantilla-header" class="sectionPlantilla" style="padding:10px; border:solid 1px lightgray;" contenteditable="true" >{!! isset($plantillaDocumento->plantilla_header) ? $plantillaDocumento->plantilla_header : "" !!}</div>

            <div id="plantilla-body" class="sectionPlantilla" style="padding:10px; border:solid 1px lightgray;" contenteditable="true">{!! isset($plantillaDocumento->plantilla_body) ? $plantillaDocumento->plantilla_body : "" !!}</div>

            <div id="plantilla-footer" style="padding:10px; border:solid 1px lightgray;">{!! isset($plantillaDocumento->plantilla_footer) ? $plantillaDocumento->plantilla_footer : "" !!}</div>

        </div>
        <div class="col-md-2"></div>
    </div>


@push('scripts')
    <script src='/js/tinymce/tinymce.min.js'></script>

    <script>
        var config_tmce = function(selector, objDoc = null) {
          let botonesHeader = ""
          let botonesBody = ""
          let botonesFooter = ""

          if(selector == "#plantilla-header"){
            botonesHeader = "btnHeader | ";
          }
          if(selector == "#plantilla-body"){
            botonesBody = "btnBody | btnBodyConditions | btnOtrosBody | ";
          }
          if(selector == "#plantilla-footer"){
            botonesBody = "btnFooter | ";
          }

          return {
                auto_focus: 'plantilla-header',
                selector: selector,
                document_base_url: '/public',
                relative_urls: false,
                language: 'es_MX',
                width: "785",//"670"
                language_url: '/js/tinymce/langs/es_MX.js',
                inline: true,
                menubar: false,
                toolbar_items_size: 'small',
                plugins: [
                    'noneditable advlist autolink lists link image imagetools preview',
                    //' media table advtable paste uploadimage lineheight code'
                    ' media table paste code'
                ],
                toolbar1: botonesHeader + botonesBody + 'basicDateButton | mybutton | fontselect fontsizeselect textcolor| undo redo ' +
                '| bold italic underline| alignleft aligncenter alignright alignjustify | bullist numlist ' +
                '| outdent indent | lineheightselect | table | uploadimage image | forecolor backcolor | code',
                toolbar2: "",
                // paste_data_images: true,
              	images_upload_handler: function (blobInfo, success, failure) {
              		success("data:" + blobInfo.blob().type + ";base64," + blobInfo.base64());
              	},
              	//url:'img/logo/logo-stps-786x196.png',
                image_title: true,
                automatic_uploads: true,
                file_picker_types: 'image',
                font_formats: 'Montserrat=Montserrat,sans-serif; Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;',
                paste_as_text: true,
                fontsize_formats: "8pt 10pt 12pt 14pt 18pt 24pt 36pt",
                lineheight_formats: "6px 8px 9px 10px 11px 12px 14px 16px",
                content_style: "body {font-size: 12pt;}",
                // image_list: [
                //   {title: 'LogoSTPS', value: 'https://192.168.10.10/assets/img/logo/logo-stps-786x196.png'},
                //   {title: 'Logo', value: 'https://192.168.10.10/assets/img/logo/logo-stps-786x196.png'}
                // ],
                file_picker_callback: function (cb, value, meta) {
                  var input = document.createElement('input');
                  input.setAttribute('type', 'file');
                  input.setAttribute('accept', 'image/*');
                  input.onchange = function () {
                      var file = this.files[0];
                      var reader = new FileReader();
                      reader.onload = function () {
                          var id = 'blobid' + (new Date()).getTime();
                          var blobCache = tinymce.activeEditor.editorUpload.blobCache;
                          var base64 = reader.result.split(',')[1];
                          var blobInfo = blobCache.create(id, file, base64);
                          blobCache.add(blobInfo);
                          cb(blobInfo.blobUri(), {title: file.name});
                      };
                      reader.readAsDataURL(file);
                  };
                  input.click();
                },
                setup: function (editor) {
                  //Editor Body
                  if(selector == "#plantilla-body"){
                    //Menu para variables
                    arrayMenuBody =  [];
                    var arrSubmenuBodyCounter =  [];
                    if(objDoc == null){
                      objDoc = {!! json_encode($objetoDocumento) !!};
                    }
                    $.each( objDoc, function( key, objeto ) {

                          var menu = {};
                          var arrSubmenuBody =  [];
                          var arrSubSubmenuCount =  [];
                          $.each( objeto['campos'], function( ke, column ) {
                            if(typeof(column) == 'object'){ //si el dato es array (datos laborales)
                              submenuObj = {};
                              arrSubSubmenu =  [];
                              $.each(column['columns'], function(k, dato ) {


                                submenuObj =
                                {
                                  type: 'menuitem', //nestedmenuitem menuitem
                                  text: dato,
                                  onAction: function (_) {
                                    let datos = (objeto['nombre']+"_"+column['nombre']+"_"+dato).toUpperCase();
                                    let datoId = (objeto['nombre']+"_"+column['nombre']+"_"+dato).toLowerCase();
                                    editor.insertContent('<strong class="mceNonEditable" data-nombre="'+(datoId)+'">['+datos+']</strong>&nbsp;\n');
                                  }
                                };
                                arrSubSubmenu.push(submenuObj);
                              });

                              arrSubSubmenuCount[ke] = arrSubSubmenu;
                                submenu =
                              {
                                type: 'nestedmenuitem', // menuitem
                                text: column['nombre'],
                                getSubmenuItems: function () {
                                    return arrSubSubmenuCount[ke];
                                }
                              };
                            }else{
                              submenu =
                              {
                                type: 'menuitem', //nestedmenuitem menuitem
                                text: column,
                                onAction: function (_) {
                                  let dato = (objeto['nombre']+"_"+column).toUpperCase();
                                  let datoId = (objeto['nombre']+"_"+column).toLowerCase();
                                  editor.insertContent('<strong class="mceNonEditable" data-nombre="'+(datoId)+'">['+dato+']</strong>&nbsp;\n');
                                  // editor.insertContent('<strong class="mceNonEditable" data-nombre="solicitud_fecha_ratificacion">[fecha R]</strong>&nbsp;\n');
                                }
                              };
                            }
                              arrSubmenuBody.push(submenu);
                          });
                          arrSubmenuBodyCounter[key] = arrSubmenuBody;
                          menu =
                          {
                            type: 'nestedmenuitem', //nestedmenuitem menuitem
                            text: objeto['nombre'],
                            getSubmenuItems: function () {
                                return arrSubmenuBodyCounter[key];
                            }
                          };
                          arrayMenuBody.push(menu);
                    });
                      // Menu para condiciones
                      arrayMenuCond =  [];
                      var arrSubmenuCondCount =  [];
                      var arrSubmenuObjCondCount =  [];
                      let condiciones = {!! json_encode($condicionales)  !!};
                      // let condiciones = [{'tipoCondicion':'Si', 'datos':[{'nombre':'Genero', 'valores':['Masculino','Femenino'] },{'nombre':'Tipo Persona','valores':['Moral','Fisica']}] }, { 'tipoCondicion':'Repetir', 'datos':['Tipo Persona']}];
                        $.each( condiciones, function( key, condicion ) {
                              var menu = {};
                              var arrSubmenuCond =  [];
                              $.each( condicion['values'], function( k, column ) {
                                var arrSubmenuObjCond =  [];
                                $.each( column['catalogos'], function(i,cat) {
                                  submenuObj = {
                                    type: 'menuitem', //nestedmenuitem
                                    text: cat['catalogo'],
                                    onAction: function (_) {
                                      let condString = "";
                                      $.each(cat['val'], function( j,val){
                                        let valu = (val != "") ? '_'+val : "";

                                        if (cat['catalogo'] === 'Notificación existosa' || cat['catalogo'] === 'Comparecio') {
                                          condString = condicion['tipoCondicion']+'_'+column['nombre'];
                                        } else {
                                          condString = condicion['tipoCondicion']+'_'+column['nombre']+'_'+cat['catalogo'];
                                        }

                                        editor.insertContent('<strong class="mceNonEditable" data-nombre="">['+(condString+valu).toUpperCase()+']</strong><br>&nbsp;');
                                      });
                                      if(cat['catalogo'] == "Notifica"){
                                        editor.insertContent('<strong class="mceNonEditable" data-nombre="">[SI_NO_NOTIFICA]</strong><br>&nbsp;');
                                      }else if(cat['catalogo'] == "Ratificada"){
                                        editor.insertContent('<strong class="mceNonEditable" data-nombre="">[SI_SOLICITUD_NO_RATIFICADA]</strong><br>&nbsp;');
                                      }else if(cat['catalogo'] == "Virtual"){
                                        editor.insertContent('<strong class="mceNonEditable" data-nombre="">[SI_SOLICITUD_NO_VIRTUAL]</strong><br>&nbsp;');
                                      }else if(cat['catalogo'] == "Atiende"){
                                        editor.insertContent('<strong class="mceNonEditable" data-nombre="">[SI_CENTRO_NO_ATIENDE_VIRTUAL]</strong><br>&nbsp;');
                                      }
                                      if (cat['catalogo'] === 'Notificación existosa') {
                                          condString += '_FUE_NOTIFICADO';
                                      }
                                      if (cat['catalogo'] === 'Comparecio') {
                                          condString += '_COMPARECIO';
                                      }
                                      editor.insertContent('<strong class="mceNonEditable" data-nombre="">[FIN_'+(condString).toUpperCase()+']</strong>&nbsp;\n');
                                    }
                                  }
                                  arrSubmenuObjCond.push(submenuObj);
                                });
                                arrSubmenuObjCondCount[k] = arrSubmenuObjCond;

                                  submenu =
                                  {
                                    type: 'nestedmenuitem', //nestedmenuitem
                                    text: column['nombre'],
                                    getSubmenuItems: function(){
                                      return arrSubmenuObjCondCount[k];
                                    }
                                  };
                                  arrSubmenuCond.push(submenu);
                              });
                              arrSubmenuCondCount[key] = arrSubmenuCond;

                              if(condicion['tipoCondicion']== 'Si'){
                              menu =
                              {
                                type: 'nestedmenuitem', // menuitem
                                text: condicion['tipoCondicion'],
                                getSubmenuItems: function () {
                                    return arrSubmenuCondCount[key];
                                }
                              };
                            }else {
                              menu =
                              {
                                type: 'menuitem', //
                                text: condicion['tipoCondicion'],
                                onAction: function (_) {
                                  editor.insertContent('<strong class="mceNonEditable" data-nombre="">['+(condicion['tipoCondicion']).toUpperCase()+']</strong><br>&nbsp;');
                                  editor.insertContent('<strong class="mceNonEditable" data-nombre="">[FIN_'+(condicion['tipoCondicion']).toUpperCase()+']</strong>&nbsp;\n');
                                }
                              };
                            }
                            arrayMenuCond.push(menu);
                        });
                  }
                    editor.on('init', function (ed) {
                        ed.target.editorCommands.execCommand("fontName", false, "Arial");
                        // ed.editorCommands.execCommand(ed,'img/logo/logo-stps-786x196.png')
                    });

                    editor.ui.registry.addButton('btnHeader', {
                      text: 'Logo',
                      onAction: function (_) {
                        editor.insertContent('<img style="width:35%;"" src="https://192.168.10.10/assets/img/logo/logo-stps-786x196.png"></img>');
                      }
                      // onAction: () => alert('Button clicked!')
                    });
                    editor.ui.registry.addMenuButton('btnBody', {
                    //     type: 'menubutton',
                        text: 'Variables',
                        // icon: false,
                      fetch: function (callback) {
                        var items = //[
                          arrayMenuBody;
                        callback(items);
                      }
                    });
                    editor.ui.registry.addMenuButton('btnBodyConditions', {
                    //     type: 'menubutton',
                        text: 'Condiciones',
                        fetch: function (callback) {
                          var items = //[
                            arrayMenuCond;
                          callback(items);
                        }
                        // type: 'menuitem', //nestedmenuitem menuitem
                        // onAction: function (_) {
                        //   editor.insertContent('<strong class="mceNonEditable" data-nombre="'+(datoId)+'">['+dato+']</strong>&nbsp;\n');
                        //   // editor.insertContent('<strong class="mceNonEditable" data-nombre="solicitud_fecha_ratificacion">[fecha R]</strong>&nbsp;\n');
                        // }
                        // icon: false,
                      // fetch: function (callback) {
                      //   var items = //[
                      //     arrayMenuBody;
                      //   callback(items);
                      // }
                    });
                    // editor.ui.registry.addButton('btnOtrosBody', {
                    //   text: 'Fecha Actual',
                    //   onAction: function (_) {
                    //     editor.insertContent('<strong class="mceNonEditable" data-nombre="fecha_actual">[FECHA_ACTUAL]</strong>');
                    //   }
                    //   // onAction: () => alert('Button clicked!')
                    // });
                    editor.ui.registry.addMenuButton('btnOtrosBody', {
                      text: 'Otros',
                      fetch: function (callback) {
                        var itemsF = [
                          {
                              type: 'menuitem',
                              text: 'Fecha Actual',
                              tooltip: 'Insert Current Date',
                              onAction: function (_) {
                                editor.insertContent('<strong class="mceNonEditable" data-nombre="fecha_actual">[FECHA_ACTUAL]</strong>');
                                // const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio","Julio", "Agosto", "Septiembre", "Octubre", "Noivembre", "Diciembre"];
                                // let today = new Date();
                                // let dd = today.getDate();
                                // let mm = monthNames[today.getMonth()];
                                // let yyyy = today.getFullYear();
                                // today = dd+' de '+mm+' de '+yyyy;
                                // editor.insertContent( today );
                              }
                          },
                          {
                              type: 'menuitem',
                              text: 'Hora Actual',
                              onAction: function (_) {
                                editor.insertContent('<strong class="mceNonEditable" data-nombre="hora_actual">[HORA_ACTUAL]</strong>');
                              }
                          },
                          {
                              type: 'menuitem',
                              text: 'Clave Nomenclatura',
                              onAction: function (_) {
                                editor.insertContent('<strong class="mceNonEditable" data-nombre="clave_nomenclatura">[CLAVE_NOMENCLATURA]</strong>');
                              }
                          },
                          {
                            type: 'menuitem',
                            text: 'QR Público',
                            onAction: function (_) {
                              editor.insertContent('<p>Verifica la autenticidad de este documento con este Código QR</p><strong class="mceNonEditable" data-nombre="qr_publico">[QR_PUBLICO]</strong><p>Este documento contiene datos personales por lo que la protección del código QR es responsabilidad de quien resguarda el documento. Compartir el código QR puede incurrir en violaciones a la protección de datos personales</p>');
                            }
                          },
                          {
                            type: 'menuitem',
                            text: 'Iterar Citados CNC', //Constancia de No Conciliación
                            onAction: function (_) {
                              editor.insertContent('<strong class="mceNonEditable" data-nombre="iterar_citados">[ITERAR_CITADOS]</strong>');
                            }
                          },
                        ]
                        callback(itemsF);
                      }
                    });
                    editor.ui.registry.addMenuButton('btnFooter', {
                      text: 'Pie de Pagina',
                      fetch: function (callback) {
                        var itemsF = [
                          {
                              type: 'menuitem',
                              text: 'Fecha',
                              tooltip: 'Insert Current Date',
                              onAction: function (_) {
                                const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio","Julio", "Agosto", "Septiembre", "Octubre", "Noivembre", "Diciembre"];
                                let today = new Date();
                                let dd = today.getDate();
                                let mm = monthNames[today.getMonth()];
                                let yyyy = today.getFullYear();
                                today = dd+' de '+mm+' de '+yyyy;
                                editor.insertContent( today );
                              }
                          },
                          {
                              type: 'menuitem',
                              text: 'Lugar',
                              onAction: function (_) {
                                editor.insertContent("Ciudad de Mexico ");
                              }
                          },
                          {
                              type: 'menuitem',
                              text: 'Clave Nomenclatura',
                              onAction: function (_) {
                                editor.insertContent('<strong class="mceNonEditable" data-nombre="clave_nomenclatura">[CLAVE_NOMENCLATURA]</strong>');
                              }
                          },
                        ]
                        callback(itemsF);
                      }
                    });
                }
            };
        };

        $('#tipo-plantilla-id').change(function() {
          $.ajax({
              url:"/api/plantilla-documento/cargarVariables",
              type:"POST",
              data:{
                  id:$('#tipo-plantilla-id').val()
              },
              dataType:"json",
              success:function(data){
                try{

                  if(data != null && data != ""){
                    tinymce.execCommand('mceRemoveEditor', true, "plantilla-body");
                    tinymce.init(config_tmce('#plantilla-body',data));
                  }
                }catch(error){
                  console.log(error);
                }
              }
          });
        });

        tinymce.init(config_tmce('#plantilla-header'));
        tinymce.init(config_tmce('#plantilla-body'));
        tinymce.init(config_tmce('#plantilla-footer'));
    </script>
@endpush
