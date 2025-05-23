<?php

include 'dataset.php';

$dataset = new Dataset;
$dataset->getData();
$data = $dataset->data;
$class = $dataset->class;
$label = $dataset->label;
$attributes = $dataset->attributes;

$parent = ['total' => 0];
$processedData = [];
$highestAttribute = [];
$tree = [];
$maxDepth = 8;

$tree = execute($attributes, $parent, $processedData, $highestAttribute, '', $tree, 0, $maxDepth);
printTree($tree);


function execute(array $attributes, array $parent, array $processedData, array $highestAttribute, string $parentValueName, array $tree, int $depth = 0, $maxDepth){
    global $data, $class, $label, $i;
    processData($data, $attributes, $processedData, $class, $label, $parent);
    getParentEntropy($parent);
    getEntropyAndGain($processedData, $label, $parent);
    $parent['name'] = $highestAttribute['name'] ?? 'GLOBAL';
    $parent['sub_name'] = $parentValueName;
    getHighestAttribute($processedData, $highestAttribute, $label);
    printData($processedData, $parent, $highestAttribute, $class, $label);
    
    $tree = [
        'attribute' => $highestAttribute['name'],
        'branches' => []
    ];

    
    foreach($highestAttribute['entropies'] as $valueName => $entropy){
        if($depth >= $maxDepth){
            return getMajorityLabel($data, $class);
        }
        else if(count($attributes) == 1){
            if($processedData[$attributes[0]]['gain_ratio'] == 0){
                $data = array_values($processedData[$attributes[0]]['values'])[0];
                $labelName = 'default';
                $labelValue = 0;
                foreach($label as $lbl){
                    if($data[$lbl] > $labelValue){
                        $labelName = $lbl;
                        $labelValue = $data[$lbl];
                    }
                    if($data[$lbl] == $labelValue){
                        return getMajorityLabel($data, $class);
                    }
                }
                return $labelName;
            }
        }
        else if($entropy == 0){
            foreach($highestAttribute['values'][$valueName] as $classLabel => $value){
                if($value != 0){
                    $tree['branches'][$valueName] = $classLabel;
                    break;
                }
            }
        }
        else if(count($attributes) == 0) {
            return $tree['branches'][$valueName] = 'Unknown';
        }
        else if(array_values($processedData)[0]['gain_ratio'] == 0){
            return getMajorityLabel($data, $class);
        }
        else{
            $parentValueName = $valueName;
            $newData = array_filter($data, function($item) use($highestAttribute, $valueName){
                return $item[$highestAttribute['name']] == $valueName;
            });
            $newAttributes = array_values(array_diff($attributes, [$highestAttribute['name']]));

            $oldData = $data;
            $oldAttributes = $attributes;
            $data = $newData;
            $attributes = $newAttributes;
            
            $subtree = execute($attributes, $parent, $processedData, $highestAttribute, $parentValueName, $tree, $depth + 1, $maxDepth);
            $tree['branches'][$valueName] = $subtree;
            
            $data = $oldData;
            $attributes = $oldAttributes;
        }
    }
    return $tree;
}

function processData(array &$data, array $attributes, array &$processedData, string $class, array $label, array &$parent){
    
    $item = $data[array_key_first($data)]; //sample
    $numericAttributes = [];
    foreach($item as $key => $value){
        if(is_numeric($value)){
            $numericAttributes[$key] = [];
        }
    }

    $parent = ['total' => 0];
    foreach($data as $item){
        $parent['total'] ++;
        foreach($label as $lbl){
            if($item[$class] == $lbl){
                if(!isset($parent['values'][$lbl])){
                    $parent['values'][$lbl] = 0;
                }
                $parent['values'][$lbl] ++;
            }
        }
        foreach($numericAttributes as $key => $numericAttribute){
            $numericAttributes[$key][] = $item[$key];
        }
    }
    getParentEntropy($parent);

    $choosedTreshold = [];

    foreach($numericAttributes as $attributeKey => $numericAttribute){
        usort($numericAttribute, function($a, $b){
            return $a <=> $b;
        });
        $tresholds = [];
        for($i = 0; $i < count($numericAttribute) - 1; $i ++){
            if($numericAttribute[$i + 1] > $numericAttribute[$i]){
                $tresholds[$attributeKey][] = ($numericAttribute[$i + 1] + $numericAttribute[$i]) / 2;
            }
        }

        $min_split = ceil(0.05 * count($data));
        foreach($tresholds as $key => $treshold){
            $highestTreshold = 0;
            $highestGainRatio = 0;
            foreach($treshold as $t){
                $groups = [];
                $totalItem = 0;
                $tresholdEntropy = 0;
                $splitInfo = 0;

                foreach($data as $item){
                    $totalItem ++;
                    if($item[$key] <= $t){
                        $groups[0][] = $item;
                    }
                    else {
                        $groups[1][] = $item;
                    }
                }

                 if (count($groups[0]) < $min_split || count($groups[1]) < $min_split) {
                    continue; // Skip threshold ini kalau ada sisi yang terlalu kecil
                }
                
                foreach($groups as $i => $group){
                    $GroupEntropy = 0;
                    $lblTotal = [];
                    $totalPerGroup = 0;
                    
                    foreach($label as $lbl){
                        foreach($group as $item){
                            if($item[$class] == $lbl){
                                if(!isset($lblTotal[$lbl])){
                                    $lblTotal[$lbl] = 0;
                                }
                                $lblTotal[$lbl] ++;
                                $totalPerGroup ++;
                            }
                        }
                    }
                    foreach($lblTotal as $lbl){
                        $pi = $lbl / $totalPerGroup;
                        $log = log($pi, 2);
                        $GroupEntropy -= $pi * $log;
                    }
                    $tresholdEntropy += $totalPerGroup / $totalItem * $GroupEntropy;
                    $splitInfo -= $totalPerGroup / $totalItem * log($totalPerGroup / $totalItem, 2);
                }
                
                $gain = $parent['entropy'] - $tresholdEntropy;
                 $gainRatio = $splitInfo == 0 ? 0 : $gain / $splitInfo; 
                
                if($gainRatio > $highestGainRatio){
                    $highestGainRatio = $gainRatio;
                    $highestTreshold = $t;
                }
            }
        }
        $choosedTreshold[$attributeKey] = $highestTreshold;
    }
    
    foreach($data as $key => $item){
        foreach($choosedTreshold as $attribute => $value){
            if($item[$attribute] <= $value){
                $data[$key][$attribute] = "<= $value";
            }
            else{
                $data[$key][$attribute] = "> $value";
            }
        }
    }
    
    $parent = ['total' => 0];
    $processedData = [];
    foreach($data as $item){
        $parent['total'] ++;
        $itemClass = $item[$class];
        if(!isset($parent['values'][$itemClass])){
            $parent['values'][$itemClass] = 0;
        }
        $parent['values'][$itemClass] ++;

        foreach($attributes as $attribute){
            $value = $item[$attribute];

            if(!isset($processedData[$attribute]['values'][$value]['total'])){
                $processedData[$attribute]['values'][$value]['total'] = 0; 
            }
            foreach($label as $lbl){
                if(!isset($processedData[$attribute]['values'][$value][$lbl])){
                    $processedData[$attribute]['values'][$value][$lbl] = 0;
                }
            }

            $processedData[$attribute]['values'][$value]['total'] ++;
            $processedData[$attribute]['values'][$value][$itemClass] ++;
        }
    }
}

function getParentEntropy(array &$parent){
    foreach($parent['values'] as $data){
        $pi = $data / $parent['total'];
        $log = log($pi, 2);
        if(!isset($parent['entropy'])){
            $parent['entropy'] = 0;
        }
        $parent['entropy'] -= $pi * $log;
    }
}
function getEntropyAndGain(array &$processedData, array $label, array $parent){
    foreach($processedData as $attributeName => $attribute){
        $attributeEntropy = 0;
        $splitInfo = 0;
        foreach($attribute['values'] as $valueName => $value){
            $entropy = 0;
            foreach($label as $lbl){
                if($value['total'] != 0){
                    $pi = $value[$lbl] / $value['total'];
                    if($pi == 0){
                        $entropy = -0;
                    }
                    else{
                        $log = log($pi, 2);
                        $entropy -= $pi * $log;
                    }
                }
            }
            $processedData[$attributeName]['values'][$valueName]['entropy'] = $entropy;

            // ATTRIBUTE ENTROPY
            $attributeEntropy += $value['total'] / $parent['total'] * $entropy;

            // SPLIT INFO
            $splitInfoPi = $value['total'] / $parent['total'];
            $log = log($splitInfoPi, 2);
            $splitInfo -= $splitInfoPi * $log;
        }
        $processedData[$attributeName]['split_info'] = $splitInfo;

        // GAIN
        $gain = $parent['entropy'] - $attributeEntropy;
        $processedData[$attributeName]['gain'] = $gain;
        
        // GAIN RATIO
        if ($splitInfo == 0) {
            $gainRatio = 0;
        } else {
            $gainRatio = $gain / $splitInfo;
        }

        $processedData[$attributeName]['gain_ratio'] = $gainRatio;
    }
}
function getHighestAttribute(array $processedData, array &$highestAttribute, array $label){
    $highestGainaRatio = 0;
    
    foreach($processedData as $attributeName => $attribute){
        if($attribute['gain_ratio'] > $highestGainaRatio){
            $highestGainaRatio = $attribute['gain_ratio'];
            
            $highestAttribute['values'] = [];
            $highestAttribute['entropies'] = [];
            foreach($attribute['values'] as $valueName => $value){
                foreach($label as $lbl){
                    $highestAttribute['values'][$valueName][$lbl] = $value[$lbl];
                }
                $highestAttribute['entropies'][$valueName] = $value['entropy'];
            }
            $highestAttribute['name'] = $attributeName;
            $highestAttribute['values_name'] = [];
            $highestAttribute['total'] = 0;
            foreach($attribute['values'] as $valueName => $value){
                $highestAttribute['values_name'][] = $valueName;
                $highestAttribute['total'] += $value['total'];
            }
        }
    }
}
function getMajorityLabel($data, $class) {
    $counts = [];
    foreach ($data as $item) {
        $label = $item[$class];
        $counts[$label] = ($counts[$label] ?? 0) + 1;
    }
    arsort($counts); 
    return array_key_first($counts); 
}

function printData(array $processedData, array $parent, array $highestAttribute, string $class, array $label){
    echo("\n\n");
    $colsWidth = [20,10,4,4,4,20,20,20];
    
    $row = ['Attribute', 'Value', 'S'];
    $data = [];
    foreach($label as $lbl){
        $data[] = strtoupper($lbl[0]);
    }
    $row = array_merge($row, $data);
    $row[] = 'Entropy';
    $row[] = 'Gain';
    $row[] = 'Gain Ratio';
    addRow(8, $colsWidth, 8, $row);
    
    $row = ["> ".$parent['name']." - ".$parent['sub_name'] ?? '', '', $parent['total']];
    $data = [];
    foreach($label as $lbl){
        $data[] = $parent['values'][$lbl];
    }
    $row = array_merge($row, $data);
    $row[] = $parent['entropy'];
    $row[] = '';
    $row[] = '';
    addRow(8, $colsWidth, 8, $row);

    foreach($processedData as $attributeName => $attribute){
        addRow(8, [20, 46, 20, 16], 4, [$attributeName, '', number_format($attribute['gain'], 9), number_format($attribute['gain_ratio'], 9)]);
        foreach($attribute['values'] as $valueName => $value){
            $row = ['', $valueName, $value['total']];
            $data = [];
            foreach($label as $lbl){
                $data[] = $value[$lbl];
            }
            $row = array_merge($row, $data);
            $row[] = ($value['entropy'] == 0 || $value['entropy'] == 1) ? $value['entropy'] : number_format($value['entropy'], 9);
            $row[] = '';
            $row[] = '';
            addRow(8, [20,10,4,4,4,20,40], 7, $row);
        }
    }

    echo("\n\n");
}

function addRow(int $totalCols, array $colsWidth, int $cols, $data){
    $gap = $totalCols - $cols;

    for($i = 0; $i < $cols; $i ++){
    printf("%-1s", "+");
    printf("%-" . $colsWidth[$i]."s", str_repeat("-",$colsWidth[$i]));
    }
    printf("%-".$gap."s", str_repeat("-", $gap) );
    printf("%-1s\n", "+");

    for($i = 0; $i < $cols; $i ++){
    printf("%-1s", "|");
    printf("%-" . $colsWidth[$i]."s", $data[$i]);
    }
    printf("%-".$gap."s", "" );
    printf("%-1s\n", "|");
}
function printTree($node, $prefix = '', $isLast = true) {
    echo $prefix;
    echo $isLast ? "└── " : "├── ";
    echo $node['attribute'] . PHP_EOL;

    $branches = $node['branches'];
    $count = count($branches);
    $index = 0;

    foreach ($branches as $key => $value) {
        $isLastBranch = (++$index === $count);
        echo $prefix;
        echo $isLast ? "    " : "│   ";
        echo $isLastBranch ? "└── " : "├── ";
        echo $key;

        if (is_array($value)) {
            echo PHP_EOL;
            printTree($value, $prefix . ($isLast ? "    " : "│   ") . ($isLastBranch ? "    " : "│   "), true);
        } else {
            echo " ➜ $value" . PHP_EOL;
        }
    }
}