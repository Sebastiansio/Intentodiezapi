<h4 class="offset-2"><i class="fa fa-cog"></i>  Creación de oficio</h4><br>

  <form action="{{ route('oficio-documento.imprimirPDF') }}" method="POST">
    @csrf
        <div class="row">
            <input type="hidden" name='id' id="id_expediente" value='{{(isset($id))?$id:''}}'>
            <input type="hidden" name='type' id="type" value='pre'>
            <div class="col-md-1"></div>
            <div class="col-md-10">
            <div id="oficio-body" name="oficio-body" class="sectionPlantilla" style="padding: 10px; border:solid 1px lightgray;" >{!! isset($plantilla['plantilla_body']) ? $plantilla['plantilla_body'] : "<br><br>" !!}</div>
            </div>
            <div class="col-md-2"></div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-1"></div>
            <div class="col-md-4">
                <input class="form-control upper" name="nombre_documento" id="nombre_documento" required placeholder="Nombre del documento" type="text" value="">
                <p class="help-block needed">Nombre del documento</p>
            </div>
        </div>

        <br>
        <br>
        <div class="form-group">
            <button class="btn btn-primary" onclick="$('#type').val('generate')"><i class="fa fa-save"></i> Guardar </button>
        </div>
    </form>
    <button class="btn btn-danger" onclick="preview()" ><i class="fa fa-eye"></i> Pre-visualizar </button>

    <!-- inicio Modal Preview-->
    <div class="modal" id="modal-preview" data-backdrop="static" data-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <input type="hidden" id="totalDocumentos">
                <input type="hidden" id="noDocumento">
                <div class="modal-body" >
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h2 style="text-align: center;" id="title-file">Previsualizaci&oacute;n</h2>
                    <div id="documentoPreviewHtml" style="margin:0 5% 0 5%; max-height:600px; border:1px solid black; overflow: scroll; padding:2%;"></div>
                </div>
                <div class="modal-footer">
                    <div class="text-right row">
                        <a class="btn btn-primary btn-sm" class="close" data-dismiss="modal" aria-hidden="true" ><i class="fa fa-times"></i> Aceptar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Fin Modal de Preview-->

@push('scripts')
    <script src='/js/tinymce/tinymce.min.js'></script>

    <script>


        var config_tmce = function(selector) {
            return {
                selector: selector,
                language: 'es_MX',
                width: "670",
                language_url: '/js/tinymce/langs/es_MX.js',
                inline: true,
                menubar: false,
                toolbar_items_size: 'small',
                plugins: [
                    'noneditable advlist autolink lists link image imagetools preview',
                    ' media table paste pagebreak'
                ],
                toolbar1: 'basicDateButton | mybutton | fontselect fontsizeselect | undo redo ' +
                '| bold italic underline| alignleft aligncenter alignright alignjustify | bullist numlist ' +
                '| outdent indent | link unlink image | table pagebreak forecolor backcolor',
                toolbar2: "",
                image_title: true,
                automatic_uploads: true,
                file_picker_types: 'image',
                font_formats: 'Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva',
                paste_as_text: true,
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
                    editor.on('init', function (ed) {
                        ed.target.editorCommands.execCommand("fontName", false, "Arial");
                    });
                    // editor.ui.registry.addButton('mybutton', {
                    //   text: 'My Custom Button',
                    //   onAction: () => alert('Button clicked!')
                    // });
                }
            };
        };
        // tinymce.init(config_tmce('#oficio-header'));
        tinymce.init(config_tmce('#oficio-body'));
        //tinymce.init(config_tmce('#oficio-footer'));
        function preview(){
            $.ajax({
            url:"/oficio-documento/imprimirPDF",
            type:"POST",
            dataType:"json",
            async:false,
            data:{
                type:'preview',
                "oficio-header": "",
                "oficio-body": tinyMCE.get('oficio-body').getContent(),
                "oficio-footer": "",
                "id": $("#id_expediente").val(),
                _token:"{{ csrf_token() }}"
            },
            success:function(data){
                try{
                    $('#documentoPreviewHtml').html("");
                    $('#documentoPreviewHtml').html(data.data);
                    $('#modal-preview').modal('show');
                    var nombre = $("#nombre_documento").val();
                    if(nombre != ""){
                       $("#title-file").html(nombre);
                    }
                }catch(error){
                    console.log(error);
                }
            }
        });
        }


    </script>
@endpush
