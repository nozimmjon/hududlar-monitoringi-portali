<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Source data path
    |--------------------------------------------------------------------------
    |
    | Absolute or project-relative path to the directory holding region
    | workbook folders, e.g. "../data" produces region paths like
    | "../data/2. Андижон/" relative to backend/.
    |
    */
    'data_path' => env('IMPORT_DATA_PATH', base_path('../data')),
];
