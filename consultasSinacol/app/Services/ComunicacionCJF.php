<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

// use Validator;

class ComunicacionCJF
{
    public function __construct() {}

    /**
     * @param  Request  $request
     */
    public function crearDocumentoCJF()
    {

        $contents = Storage::get('/Documentos/sample.pdf');
        $base64Doc = base64_encode($contents);
        $documentos = [];
        $documento = [
            'IdDocumento' => 9,
            'Nombre' => 'ConstanciaSTyPS237.pdf',
            'Extension' => '.pdf',
            'FileBase64' => $base64Doc,
            'Longitud' => 1220,
            'Firmado' => 0,
            'Pkcs7Base64' => '',
            'FechaFirmado' => '/Date(1574962552000-0600)/',
            'ClasificacionArchivo' => 7,
        ];

        array_push($documentos, $documento);
        // $documento = array(
        //   "IdDocumento" =>12,
        //   "Nombre"=> "ConstanciaConciliacion2020000001.pdf",
        //   "Extension"=> ".pdf",
        //   "FileBase64"=> "JVBERi0xLjUKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nH1Sy0rFMBDd9yuyFm6dmbwaCIE+F+4uFFyIOx",
        //   "Longitud"=> 20540,
        //   "Firmado"=> 0,
        //   "Pkcs7Base64"=> "",
        //   "FechaFirmado"=> "",
        //   "ClasificacionArchivo"=> 7
        // );
        //
        // array_push($documentos,$documento);
        $envioDocumento = [
            'fec_envio' => '/Date(1575308817931-0600)/',
            'solicitud_Id' => 17,
            'organoImpartidorJusticia' => 1618,
            'numeroExpedienteOIJ' => 'CDMX1/CJ/I/2020/0000001',
            'identificadorExpediente' => 0,
            'idOrganoDestino' => 1494,
            'Documentos' => $documentos,
        ];
        $envioDocumento = json_encode($envioDocumento);

        return $envioDocumento;
    }

    public function enviaDocumentoCJF($envioDocumento)
    {
        try {
            // $urlEnvio = env('API_CJF');
            $urlEnvio = 'http://189.240.126.44:96/wsInterconexion/STPS/DemandaSTPSService.svc/DemandaSTPS';
            // $client = new Client();
            $client = new Client(['headers' => ['Content-Type' => 'application/json']]);
            $response = $client->post($urlEnvio, ['body' => $envioDocumento]);

            // $result = json_decode($response->getBody()->getContents());
            return $response;
        } catch (RequestException $e) {
            return $e;
        }
    }

    public function firmarDocumento() {}
}
