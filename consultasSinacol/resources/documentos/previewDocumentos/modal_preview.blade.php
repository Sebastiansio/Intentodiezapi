<!-- inicio Modal Preview-->
<div class="modal" id="modal-preview" data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: 0px !important;">
            <div class="modal-header" style="background: #555770; color: white; border-top-left-radius: 0px; border-top-right-radius: 0px">
                <h5 class="modal-title"><span id="textPreview"></span> - {{$audiencia->expediente->folio}}</h5>
                <svg class="cursor-pointer" data-dismiss="modal" aria-label="Close" width="26px" height="26px" viewBox="0 0 26 26" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
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
                <div id="documentoPreviewHtml" style="max-height:600px; border:1px solid black; overflow: scroll; padding:2%;">
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fin Modal de Preview-->