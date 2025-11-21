<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class CurpService
{
    /**
     * Llama al servicio por CURP y valida si la petición fue exitosa.
     */
    public function getDataByCurp(string $curp)
    {
        $httpClient = new Client;

        $url = config('services.curp.url');
        $token = config('services.curp.token');

        try {

            Log::info('Consulta SERVICIO DE CURP: '.$url."?curp=$curp");
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ], 'form_params' => [
                    'curp' => $curp,
                ],
                'verify' => false,
                'timeout' => config('services.curp.timeout_response', 2),
                'connect_timeout' => config('services.curp.timeout_connection', 2),
            ]);

            Log::info('Response: '.$response->getBody());

            $data = json_decode($response->getBody(), true);

            if (isset($data['statusoper']) and $data['statusoper'] === 'EXITOSO') {
                return [
                    'success' => true,
                    'data' => $data['datos'][0],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Operación fallida',
                ];
            }

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return [
                'success' => false,
                'message' => 'Error al conectar con la API RENAPO',
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'message' => 'Error a la solicitud a la API RENAPO',
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
