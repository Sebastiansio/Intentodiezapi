<?php

namespace App\Traits;

trait EstilosSpreadsheets {


    public function tituloH1()
    {
        $styleArray = [
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 14
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ],
        ];

        return $styleArray;
    }

    public function th1()
    {
        $styleArray = [
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'FFDAE3F3',
                ],
            ],
        ];

        return $styleArray;
    }
    public function boldcenter()
    {
        $styleArray = [
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ];

        return $styleArray;
    }
    public function tf1($font_size=11, $bgcolor='FFDAE3F3')
    {
        $styleArray = [
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => $font_size
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => $bgcolor,
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ]
        ];

        return $styleArray;
    }

    public function tbody()
    {
        $styleArray = [
            'font' => [
                'name' => 'Arial',
                'size' => 10
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '00000000'],
                ],
            ],
        ];
        return $styleArray;
    }


    /**
     * Se usa en el reporte operativo para el grid del body de datos
     * @return array
     */
    public function rog($font_size=14)
    {
        $styleArray = [
            'font' => [
                'bold' => false,
                'name' => 'Arial',
                'size' => $font_size
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ];
        return $styleArray;
    }
}
