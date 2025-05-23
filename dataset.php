<?php

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

class Dataset{
    public string $class = 'pcos';
    public array $label = ['yes', 'no'];
    public array $attributes = [
        'age',
        'weight',
        'height',
        'cycle',
        'cycle_lengt',
        'marriage_status',
        'pregnan',
        'no_of_aborptions',
        'weight_gain',
        'hair_growth',
        'skin_darkening',
        'hair_loss',
        'pimples',
        'fast_food',
        'exercise'
    ];
    public array $data = [];
    public function getData(){
        $inputFileName = 'PCOS DATA.csv';

        // Load file Excel
        $spreadsheet = IOFactory::load($inputFileName);
        $sheet = $spreadsheet->getActiveSheet();

        // Ambil semua data sebagai array
        $rows = $sheet->toArray();

        // Baris pertama dianggap header kolom
        $header = array_shift($rows);

        // Loop tiap baris data, gabungkan dengan header jadi associative array
        foreach($rows as $row){
            $this->data[] = array_combine($header, $row);
        }
    }
}