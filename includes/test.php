<h1>Testsida</h1>

<?php

$file_dir_path = dirname(__DIR__) . '/admin/uploads';
$dir_raw_content = scandir($file_dir_path);
$file = array_diff($dir_raw_content, ['.', '..']);
$file_path = $file_dir_path . '/' . $file[2];

$delimeter = "\t";
$schema = [];

$handle = fopen($file_path, 'r');

$start_reading = false;
while(($row = fgetcsv($handle, 0 , $delimeter)) !== false){
    if(!$start_reading){
        if(str_starts_with($row[0], 'PK (7100)')){
            $start_reading = true;
        }
        continue;
    }
    $schema[] = $row;
}

if(new DateTime("17:59") > new DateTime("17:00")){
    echo "SANT";
} else {
    echo "FALSKT";
}
//     echo "{$booking} är större än {$limit}";
// } else {
//     echo "{$booking} är inte större än {$limit}";
// }

echo '<pre>'; 
print_r($schema);
echo '</pre>';





