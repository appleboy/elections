<?php

class CandidateShell extends AppShell {

    public $uses = array('Candidate');
    public $cec2014Stack = array();

    public function main() {
        $this->cec_2014_import();
    }

    public function cec_2014_fun() {
        $nameCount = array();
        foreach (glob(__DIR__ . '/data/2014_candidates/*.csv') AS $csvFile) {
            $csvInfo = pathinfo($csvFile);
            $candidates = array();
            $fh = fopen($csvFile, 'r');
            while ($line = fgetcsv($fh, 1024)) {
                if (!isset($candidates[$line[0]])) {
                    $candidates[$line[0]] = array();
                }
                if (!isset($nameCount[$line[1]])) {
                    $nameCount[$line[1]] = array();
                }
                $nameCount[$line[1]][] = "[{$csvInfo['filename']}]{$line[0]}";
                $candidates[$line[0]][] = $line[1] . " ({$line[2]})";
            }
            fclose($fh);

            $maxCount = 0;

            foreach ($candidates AS $aCandidates) {
                $cnt = count($aCandidates);
                if ($cnt > $maxCount) {
                    $maxCount = $cnt;
                }
            }
            foreach ($candidates AS $area => $aCandidates) {
                $cnt = count($aCandidates);
                if ($cnt === 1) {
                    echo "[{$csvInfo['filename']}]{$area} - " . implode(', ', $aCandidates) . "\n";
                }
            }
        }
        foreach ($nameCount AS $name => $areas) {
            if (count($areas) > 1) {
                //echo "{$name}: " . implode(', ', $areas) . "\n";
            }
        }
    }

    public function cec_2014_import() {
        foreach (glob(__DIR__ . '/data/2014_candidates/*.csv') AS $csvFile) {
            $csvInfo = pathinfo($csvFile);
            $parentNode = $this->Candidate->Election->find('first', array(
                'conditions' => array(
                    'name' => $csvInfo['filename'],
                ),
            ));
            $tree = $this->Candidate->Election->find('threaded', array(
                'conditions' => array(
                    'lft >' => $parentNode['Election']['lft'],
                    'rght <' => $parentNode['Election']['rght'],
                ),
            ));
            $electionNodes = $this->cec_2014_import_recursive('', $tree);

            echo "{$csvInfo['filename']}\n";

            $fh = fopen($csvFile, 'r');
            while ($line = fgetcsv($fh, 1024)) {
                $electionId = '';
                switch ($csvInfo['filename']) {
                    case '村里長':
                        switch ($line[0]) {
                            case '高雄市那瑪夏區達卡努瓦':
                                $line[0] .= '里';
                                break;
                            case '彰化縣彰化市下廍里':
                                $electionId = '53c02162-cf28-4334-b5d7-5c5aacb5b862';
                                break;
                            case '彰化縣彰化市磚磘里':
                                $electionId = '53c02167-1d2c-40fd-81f4-5c5aacb5b862';
                                break;
                            case '彰化縣彰化市寶廍里':
                                $electionId = '53c0216a-bed8-4262-afe8-5c5aacb5b862';
                                break;
                        }
                        break;
                    case '直轄市山地原住民區民代表':
                        $parts = explode('選舉區', $line[0]);
                        $parts = explode('第', $parts[0]);
                        $parts[1] = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                        $line[0] = "{$parts[0]}第{$parts[1]}選舉區";
                        break;
                    case '直轄市議員':
                    case '縣市議員':
                        $parts = explode('選舉區', $line[0]);
                        $parts = explode('第', $parts[0]);
                        $parts[1] = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                        $line[0] = "{$parts[0]}第{$parts[1]}選區";
                        break;
                    case '鄉鎮市民代表':
                        $parts = explode('選舉區', $line[0]);
                        $parts = explode('第', $parts[0]);
                        switch ($parts[0]) {
                            case '嘉義縣新港鄉':
                                $parts[1] = '01';
                                break;
                            default:
                                $parts[1] = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                        }
                        $line[0] = "{$parts[0]}第{$parts[1]}選舉區";
                        break;
                }
                if (isset($electionNodes[$line[0]])) {
                    $electionId = $electionNodes[$line[0]];
                }
                if (!empty($electionId)) {
                    $candidate = $this->Candidate->find('first', array(
                        'fields' => array('Candidate.id', 'Candidate.party'),
                        'conditions' => array(
                            'CandidatesElection.Election_id' => $electionId,
                            'Candidate.name' => $line[1],
                        ),
                        'joins' => array(
                            array(
                                'table' => 'candidates_elections',
                                'alias' => 'CandidatesElection',
                                'type' => 'inner',
                                'conditions' => array(
                                    'CandidatesElection.Candidate_id = Candidate.id',
                                ),
                            ),
                        ),
                    ));
                    if (!empty($candidate['Candidate']['id'])) {
                        if ($candidate['Candidate']['party'] !== $line[2]) {
                            $candidate['Candidate']['party'] = $line[2];
                            $this->Candidate->save($candidate);
                        }
                    } else {
                        $this->Candidate->create();
                        if ($this->Candidate->save(array('Candidate' => array(
                                        'name' => $line[1],
                                        'party' => $line[2],
                            )))) {
                            $this->Candidate->CandidatesElection->create();
                            $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                                    'Candidate_id' => $this->Candidate->getInsertID(),
                                    'Election_id' => $electionId,
                            )));
                        }
                    }
                } else {
                    print_r($line);
                }
            }
        }
    }

    public function cec_2014_import_recursive($prefix = '', $data = array()) {
        $result = array();
        if (!empty($data)) {
            foreach ($data AS $item) {
                $pos = strpos($item['Election']['name'], '[');
                if (false !== $pos) {
                    $item['Election']['name'] = substr($item['Election']['name'], 0, $pos);
                }
                if (!empty($item['children'])) {
                    $result = array_merge($result, $this->cec_2014_import_recursive($prefix . $item['Election']['name'], $item['children']));
                } else {
                    $result[$prefix . $item['Election']['name']] = $item['Election']['id'];
                }
            }
        }
        return $result;
    }

    public function cec_2014() {
        $tmpPath = TMP . 'cec/2014';
        if (!file_exists($tmpPath)) {
            mkdir($tmpPath, 0777, true);
        }
        $result = array();
        $partyResult = array();
        $parties = array('中國國民黨' => 0, '新黨' => 0, '民主進步黨' => 0, '親民黨' => 0, '樹黨' => 0, '華聲黨' => 0, '綠黨' => 0, '人民最大黨' => 0, '臺灣建國黨' => 0, '台灣主義黨' => 0, '聯合黨' => 0, '勞動黨' => 0, '台灣民族黨' => 0, '大道人民黨' => 0, '台灣第一民族黨' => 0, '中華統一促進黨' => 0, '家庭黨' => 0, '三等國民公義人權自救黨' => 0, '無' => 0, '台灣團結聯盟' => 0, '人民民主陣線' => 0, '無黨團結聯盟' => 0, '中華民主向日葵憲政改革聯' => 0, '中華統一促進' => 0);
        foreach (glob(__DIR__ . '/data/2014_candidates/*.pdf') AS $pdfFile) {
            $pdfFileInfo = pathinfo($pdfFile);
            echo "processing {$pdfFileInfo['filename']}\n";
            $txtFile = $tmpPath . '/' . $pdfFileInfo['filename'] . '.txt';
            if (!file_exists($txtFile)) {
                exec("java -cp /usr/share/java/commons-logging.jar:/usr/share/java/fontbox.jar:/usr/share/java/pdfbox.jar org.apache.pdfbox.PDFBox ExtractText {$pdfFile} tmp.txt");
                copy('tmp.txt', $txtFile);
                unlink('tmp.txt');
            }
            $txtContent = file_get_contents($txtFile);
            $lines = explode('103/09/', $txtContent);
            foreach ($lines AS $line) {
                $fields = preg_split('/[\\n ]/', $line);
                $partyFound = false;
                foreach ($fields AS $k => $v) {
                    $v = trim($v);
                    if (isset($parties[$v])) {
                        $partyFound = $v;
                    }
                }
                if (false !== $partyFound) {
                    switch ($partyFound) {
                        case '中華統一促進':
                            $partyFound = '中華統一促進黨';
                            break;
                        case '家庭黨':
                            $partyFound = '天宙和平統一家庭黨';
                            break;
                        case '中華民主向日葵憲政改革聯':
                            $partyFound = '中華民主向日葵憲政改革聯盟';
                            break;
                    }
                    switch ($pdfFileInfo['filename']) {
                        case '103年直轄市議員選舉候選人登記彙總表':
                            $type = '直轄市議員';
                            switch (count($fields)) {
                                case 4:
                                    $name = '周鍾㴴';
                                    break;
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 6:
                                    if ($fields[4] !== '盟') {
                                        $name = $fields[2] . $fields[3];
                                    } else {
                                        $name = $fields[2];
                                    }
                                    break;
                                case 7:
                                    if (empty($fields[6])) {
                                        $name = $fields[2] . $fields[3] . '•' . $fields[4];
                                    } else {
                                        $name = $fields[2];
                                    }
                                    break;
                                case 8:
                                    $name = $fields[2];
                                    break;
                                case 9:
                                    $name = $fields[2];
                                    break;
                                case 12:
                                case 13:
                                    $name = $fields[2];
                                    break;
                                case 15:
                                    $name = $fields[2] . $fields[3] . '•' . $fields[4];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年縣市長選舉候選人登記彙總表':
                            $type = '縣市長';
                            $name = $fields[2];
                            break;
                        case '103年縣市議員選舉候選人登記彙總表':
                            $type = '縣市議員';
                            switch (count($fields)) {
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 6:
                                    $name = $fields[2] . $fields[3];
                                    break;
                                case 7:
                                    $name = $fields[2];
                                    break;
                                case 12:
                                case 13:
                                    $name = $fields[2];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年鄉鎮市長選舉候選人登記彙總表':
                            $type = '鄉鎮市長';
                            switch (count($fields)) {
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 6:
                                    $name = $fields[2] . $fields[3];
                                    break;
                                case 7:
                                    if (empty($fields[6])) {
                                        $name = $fields[2] . $fields[3] . '•' . $fields[4];
                                    } else {
                                        $name = $fields[2];
                                    }
                                    break;
                                case 8:
                                    $name = $fields[2] . $fields[3] . $fields[4] . '•' . $fields[5];
                                    break;
                                case 12:
                                case 13:
                                    $name = $fields[2];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年鄉鎮市民代表選舉候選人登記彙總表':
                            $type = '鄉鎮市民代表';
                            switch (count($fields)) {
                                case 4:
                                    switch ($fields[1]) {
                                        case '彰化縣社頭鄉第1選舉區':
                                            $name = '蕭圳';
                                            break;
                                        case '南投縣魚池鄉第3選舉區':
                                            $name = '劉𦰡行';
                                            break;
                                        case '雲林縣斗南鎮第2選舉區':
                                            $name = '𦰡永福';
                                            break;
                                        case '雲林縣褒忠鄉第2選舉區':
                                            $name = '張峻瑝';
                                            break;
                                        case '雲林縣臺西鄉第4選舉區':
                                            $name = '丁秋';
                                            break;
                                        case '嘉義縣太保市第3選舉區':
                                            $name = '葉啓泰';
                                            break;
                                    }
                                    break;
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 6:
                                    $name = $fields[2] . $fields[3];
                                    break;
                                case 7:
                                    if (empty($fields[6])) {
                                        $name = $fields[2] . $fields[3] . '•' . $fields[4];
                                    } else {
                                        $name = $fields[2];
                                    }
                                    break;
                                case 12:
                                case 13:
                                    $name = $fields[2];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年村里長選舉候選人登記彙總表': //村里長
                            $type = '村里長';
                            switch (count($fields)) {
                                case 4:
                                case 11:
                                    switch ($fields[1]) {
                                        case '新北市樹林區東陽里':
                                            $name = '徐木';
                                            break;
                                        case '新北市土城區學士里':
                                            $name = '陳鍈聖';
                                            break;
                                        case '張秀霞':
                                            $name = $fields[1];
                                            $fields[1] = '新北市坪林區石𥕢里';
                                            break;
                                        case '陳進益':
                                            $name = $fields[1];
                                            $fields[1] = '新北市坪林區石𥕢里';
                                            break;
                                        case '新北市貢寮區貢寮里':
                                            $name = '楊石';
                                            break;
                                        case '臺中市北區頂厝里':
                                            $name = '呂陳麗艸錦';
                                            break;
                                        case '臺中市東勢區中寧里':
                                            $name = '廖秀峰';
                                            break;
                                        case '臺中市沙鹿區斗抵里':
                                            $name = '何㳵杏';
                                            break;
                                        case '李瑞雄':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市新化區𦰡拔里';
                                            break;
                                        case '臺南市仁德區上崙里':
                                            $name = '李月眞';
                                            break;
                                        case '戴石柱':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市龍崎區石𥕢里';
                                            break;
                                        case '鄭晚福':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市龍崎區石𥕢里';
                                            break;
                                        case '臺南市東區富強里':
                                            $name = '洪瑋';
                                            break;
                                        case '林同寳':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市安南區塭南里';
                                            break;
                                        case '尤泰榮':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市安南區塭南里';
                                            break;
                                        case '黃清由':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市安南區公塭里';
                                            break;
                                        case '黃銘堂':
                                            $name = $fields[1];
                                            $fields[1] = '臺南市安南區公塭里';
                                            break;
                                        case '高雄市鳳山區善美里':
                                            $name = '蔡瑞勲';
                                            break;
                                        case '高雄市鳳山區福祥里':
                                            $name = '李登緄';
                                            break;
                                        case '彭照夫':
                                            $name = $fields[1];
                                            $fields[1] = '新竹縣竹東鎮上舘里';
                                            break;
                                        case '徐璋龍':
                                            $name = $fields[1];
                                            $fields[1] = '新竹縣竹東鎮上舘里';
                                            break;
                                        case '彭誠吉':
                                            $name = $fields[1];
                                            $fields[1] = '新竹縣竹東鎮上舘里';
                                            break;
                                        case '蔡家陞':
                                            $name = $fields[1];
                                            $fields[1] = '新竹縣竹東鎮上舘里';
                                            break;
                                        case '陳月生':
                                            $name = $fields[1];
                                            $fields[1] = '新竹縣竹東鎮上舘里';
                                            break;
                                        case '彰化縣埔心鄉埤霞村':
                                            $name = '林艸錦良';
                                            break;
                                        case '南投縣草屯鎮御史里':
                                            $name = '洪智𦰡𥕢';
                                            break;
                                        case '宜蘭縣壯圍鄉古亭村':
                                            $name = '修一';
                                            break;
                                        case '宜蘭縣大同鄉樂水村':
                                            $name = '簡進';
                                            break;
                                        case '新竹市北區中興里':
                                            $name = '洪熒𤎌';
                                            break;
                                    }
                                    break;
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 6:
                                    if (empty($fields[2])) {
                                        $name = $fields[3];
                                    } else {
                                        switch ($fields[4]) {
                                            case '家庭黨':
                                                $name = $fields[2];
                                                break;
                                            case '黨':
                                                $name = $fields[2];
                                                break;
                                            case '無':
                                                if ($fields[2] !== '里') {
                                                    $name = $fields[2] . $fields[3];
                                                } else {
                                                    $name = $fields[3];
                                                }
                                                break;
                                            case '中國國民黨':
                                                $name = $fields[2] . $fields[3];
                                                break;
                                        }
                                    }

                                    break;
                                case 7:
                                    if (empty($fields[6])) {
                                        $name = $fields[2] . $fields[3] . '•' . $fields[4];
                                    } else {
                                        $name = $fields[2];
                                    }
                                    break;
                                case 12:
                                case 13:
                                    $name = $fields[2];
                                    break;
                                case 14:
                                    $name = $fields[3];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年直轄市長選舉候選人登記彙總表': //直轄市長
                            $type = '直轄市長';
                            switch (count($fields)) {
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 7:
                                    $name = $fields[2];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年直轄市山地原住民區長選舉候選人登記彙總表':
                            $type = '直轄市山地原住民區長';
                            switch (count($fields)) {
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 7:
                                    $name = $fields[2];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        case '103年直轄市山地原住民區民代表選舉候選人登記彙總表':
                            $type = '直轄市山地原住民區民代表';
                            switch (count($fields)) {
                                case 5:
                                    $name = $fields[2];
                                    break;
                                case 6:
                                    $name = $fields[2] . $fields[3];
                                    break;
                                case 7:
                                    $name = $fields[2];
                                    break;
                                default:
                                    echo count($fields) . "\n";
                                    exit();
                            }
                            break;
                        default:
                            echo $pdfFileInfo['filename'] . "\n";
                            exit();
                    }
                    if (!isset($result[$type])) {
                        $result[$type] = array();
                    }
                    $result[$type][] = array(
                        $fields[1], //選區
                        $name,
                        $partyFound,
                        $fields[0], //登記日期
                    );
                    if (!isset($partyResult[$partyFound])) {
                        $partyResult[$partyFound] = array(
                            'count' => 0,
                            'data' => array(),
                        );
                    }
                    ++$partyResult[$partyFound]['count'];
                    $partyResult[$partyFound]['data'][] = array(
                        $type,
                        $fields[1], //選區
                        $name,
                        $fields[0], //登記日期
                    );
                }
            }
        }
        foreach ($result AS $key => $val) {
            $fh = fopen(__DIR__ . "/data/2014_candidates/{$key}.csv", 'w');
            foreach ($val AS $line) {
                fputcsv($fh, $line);
            }
            fclose($fh);
        }
        return;
        foreach ($partyResult AS $p => $d) {
            echo "{$p}: {$d['count']}\n";
        }
        foreach ($partyResult AS $p => $d) {
            if ($d['count'] < 60) {
                echo "{$p}:\n";
                foreach ($d['data'] AS $c) {
                    echo "* {$c[0]}{$c[1]} - {$c[2]}\n";
                }
                echo "\n\n";
            }
        }
    }

    public function town() {
        $cec = json_decode(file_get_contents(__DIR__ . '/data/v20100601C1D2.json'), true);
        $areaStack = array();
        foreach ($cec AS $county => $c1) {
            foreach ($c1 AS $town => $c2) {
                foreach ($c2 AS $area => $c3) {
                    foreach ($c3['candidates'] AS $candidate) {
                        if (!isset($areaStack[$county . $town])) {
                            $areaStack[$county . $town] = array();
                        }
                        $areaStack[$county . $town][$candidate['姓名']] = $area;
                    }
                }
            }
        }

        $townElectionBase = $this->Candidate->Election->find('first', array(
            'conditions' => array(
                'name' => '鄉鎮市民代表',
            ),
        ));
        $townElections = $this->Candidate->Election->find('threaded', array(
            'conditions' => array(
                'lft >' => $townElectionBase['Election']['lft'],
                'rght <' => $townElectionBase['Election']['rght'],
            ),
        ));
        $townElectionId = array();
        foreach ($townElections AS $county) {
            foreach ($county['children'] AS $city) {
                foreach ($city['children'] AS $area) {
                    $key = $county['Election']['name'] . $city['Election']['name'] . substr($area['Election']['name'], 0, strpos($area['Election']['name'], '['));
                    $townElectionId[$key] = $area['Election']['id'];
                }
            }
        }

        $fh = fopen(__DIR__ . '/data/town.csv', 'r');
        while ($line = fgetcsv($fh, 1024)) {
            if (isset($areaStack[$line[1] . $line[2]][$line[4]])) {
                $line[4] = str_replace(array('　', ' '), array('', ''), $line[4]);
                $line[0] = $areaStack[$line[1] . $line[2]][$line[4]];
                if (isset($townElectionId[$line[1] . $line[2] . $line[0]])) {
                    $this->Candidate->create();
                    if ($this->Candidate->save(array('Candidate' => array(
                                    'name' => $line[4],
                                    'party' => $line[7],
                                    'gender' => ($line[6] === '男') ? 'm' : 'f',
                                    'education' => $line[11],
                                    'experience' => $line[12],
                        )))) {
                        $this->Candidate->CandidatesElection->create();
                        $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                                'Election_id' => $townElectionId[$line[1] . $line[2] . $line[0]],
                                'Candidate_id' => $this->Candidate->getInsertID(),
                        )));
                    }
                }
            }
        }
        fclose($fh);
        //鄉鎮市長
        $townmastElectionBase = $this->Candidate->Election->find('first', array(
            'conditions' => array(
                'name' => '鄉鎮市長',
            ),
        ));
        $townmastElections = $this->Candidate->Election->find('threaded', array(
            'conditions' => array(
                'lft >' => $townmastElectionBase['Election']['lft'],
                'rght <' => $townmastElectionBase['Election']['rght'],
            ),
        ));
        $townmastElectionId = array();
        foreach ($townmastElections AS $county) {
            foreach ($county['children'] AS $city) {
                $key = $county['Election']['name'] . $city['Election']['name'];
                $townmastElectionId[$key] = $city['Election']['id'];
            }
        }
        $fh = fopen(__DIR__ . '/data/townmast.csv', 'r');
        while ($line = fgetcsv($fh, 1024)) {
            $line[4] = str_replace(array('　', ' '), array('', ''), $line[4]);
            $key = str_replace(array('台'), array('臺'), $line[1] . $line[2]);
            if (isset($townmastElectionId[$key])) {
                $this->Candidate->create();
                if ($this->Candidate->save(array('Candidate' => array(
                                'name' => $line[4],
                                'party' => $line[7],
                                'gender' => ($line[6] === '男') ? 'm' : 'f',
                                'education' => $line[11],
                                'experience' => $line[12],
                    )))) {
                    $this->Candidate->CandidatesElection->create();
                    $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                            'Election_id' => $townmastElectionId[$key],
                            'Candidate_id' => $this->Candidate->getInsertID(),
                    )));
                }
            }
        }
        fclose($fh);
    }

    public function tsu() {
        $cachePath = TMP . 'tsu';
        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        if (!file_exists($cachePath . '/list')) {
            file_put_contents($cachePath . '/list', file_get_contents('http://www.tsu.org.tw/?post_type=news&p=7208'));
        }
        $listContent = file_get_contents($cachePath . '/list');
        $listContent = substr($listContent, strpos($listContent, '<table width="634" border="0" cellspacing="0" cellpadding="0">'));
        $listContent = substr($listContent, 0, strpos($listContent, '</table>', strpos($listContent, '</table>') + 1));
        $lines = explode('</tr>', $listContent);
        foreach ($lines AS $line) {
            $fields = explode('</td>', $line);
            foreach ($fields AS $k => $v) {
                $fields[$k] = trim(strip_tags(str_replace(array('<br />', "\n\n"), array("\n", "\n"), $v)));
            }
            if (!isset($fields[2]) || false === strpos($fields[2], '現職')) {
                continue;
            } else {
                $area = substr($fields[1], 0, strpos($fields[1], '選區'));
                if (!empty($area)) {
                    $area = explode('第', $area);
                    $area[0] = str_replace(array('台', '議員'), array('臺', ''), $area[0]);
                    switch ($area[0]) {
                        case '桃園市':
                            $area[0] = '桃園縣';
                            break;
                    }
                    $area[1] = str_pad($area[1], 2, '0', STR_PAD_LEFT);
                    $e1 = $this->Candidate->Election->find('first', array(
                        'conditions' => array(
                            'parent_id' => array(
                                '53c0202f-4f58-4419-8d07-5460acb5b862',
                                '53c0202f-da0c-4e3e-bbb4-5460acb5b862',
                            ),
                            'name' => $area[0],
                        ),
                    ));
                    if (!empty($e1)) {
                        $e2 = $this->Candidate->Election->find('first', array(
                            'conditions' => array(
                                'parent_id' => $e1['Election']['id'],
                                'name LIKE' => '第' . $area[1] . '%',
                            ),
                        ));
                        if (!empty($e2)) {
                            $fields[2] = explode('臉書：', $fields[2]);
                            if ($this->Candidate->find('count', array(
                                        'conditions' => array(
                                            'name' => $fields[0],
                                            'OR' => array(
                                                'active_id IS NULL',
                                                'active_id' => 0,
                                            ),
                                        ),
                                    )) > 1) {
                                //print_r($fields);
                            } else {
                                $candidateId = $this->Candidate->field('id', array(
                                    'name' => $fields[0],
                                    'OR' => array(
                                        'active_id IS NULL',
                                        'active_id' => 0,
                                    ),
                                ));
                                if (empty($candidateId) || empty($this->Candidate->CandidatesElection->field('id', array(
                                                    'Election_id' => $e2['Election']['id'],
                                                    'Candidate_id' => $candidateId,
                                        )))) {
                                    $this->Candidate->create();
                                    if ($this->Candidate->save(array('Candidate' => array(
                                                    'name' => $fields[0],
                                                    'party' => '台灣團結聯盟',
                                                    'experience' => str_replace($fields[2][0], "\n", '\\n'),
                                                    'links' => isset($fields[2][1]) ? '臉書 ' . $fields[2][1] : '',
                                        )))) {
                                        $this->Candidate->CandidatesElection->create();
                                        $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                                                'Election_id' => $e2['Election']['id'],
                                                'Candidate_id' => $this->Candidate->getInsertID(),
                                        )));
                                    }
                                }
                            }
                        }
                    }
                } else {
                    print_r($fields);
                }
            }
        }
    }

    public function moi() {
        $srcFiles = array(
            '縣市議員' => 'http://cand.moi.gov.tw/of/ap/cand_json.jsp?electkind=0200000',
            '直轄市議員' => 'http://cand.moi.gov.tw/of/ap/cand_json.jsp?electkind=0100000'
        );
        $cachePath = TMP . 'moi';
        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        foreach ($srcFiles AS $eType => $srcFile) {
            $eTypeDb[$eType] = $this->Candidate->Election->find('first', array(
                'conditions' => array('name' => $eType),
            ));
        }
        foreach ($srcFiles AS $eType => $srcFile) {
            $cacheFile = $cachePath . '/' . md5($srcFile);
            if (!file_exists($cacheFile)) {
                file_put_contents($cacheFile, file_get_contents($srcFile));
            }
            $jsonContent = json_decode(file_get_contents($cacheFile), true);
            $counties = array();
            $zones = array();
            foreach ($jsonContent AS $c) {
                $c['cityname'] = str_replace('台', '臺', $c['cityname']);
                if ($c['cityname'] === '桃園縣') {
                    $ctype = '直轄市議員';
                } else {
                    $ctype = $eType;
                }
                if (!isset($counties[$c['cityname']])) {
                    $e = $this->Candidate->Election->find('first', array(
                        'conditions' => array(
                            'Election.parent_id' => $eTypeDb[$ctype]['Election']['id'],
                            'Election.name' => $c['cityname'],
                        ),
                    ));
                    if (!empty($e)) {
                        $counties[$c['cityname']] = $e;
                    } else {
                        echo "{$c['cityname']}\n";
                    }
                }
                $c['idname'] = str_replace(array('　'), array(''), $c['idname']);
                if (!isset($zones[$c['cityname']])) {
                    $zones[$c['cityname']] = array();
                }
                $eareaname = str_replace(array('一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '1001', '1002', '1003', '1004', '1005', '1006', '1007', '1008'), array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18'), $c['eareaname']);
                $eareaname = preg_replace('/[^0-9]/', '', $eareaname);
                $eareaname = str_pad($eareaname, 2, '0', STR_PAD_LEFT);
                if (!empty($eareaname) && !isset($zones[$c['cityname']][$eareaname])) {
                    $z = $this->Candidate->Election->find('first', array(
                        'conditions' => array(
                            'Election.parent_id' => $counties[$c['cityname']]['Election']['id'],
                            'Election.name LIKE' => "%{$eareaname}%",
                        ),
                    ));
                    if (!empty($z)) {
                        $zones[$c['cityname']][$eareaname] = $z;
                    }
                }

                if (!empty($zones[$c['cityname']][$eareaname])) {
                    $this->Candidate->create();
                    if ($this->Candidate->save(array('Candidate' => array(
                                    'name' => $c['idname'],
                                    'gender' => ($c['sex'] === '男') ? 'M' : 'F',
                                    'party' => $c['partymship'],
                                    'contacts_address' => $c['officeadress'],
                                    'contacts_phone' => $c['officetelphone'],
                                    'education' => $c['education'],
                                    'experience' => $c['profession'],
                        )))) {
                        $this->Candidate->CandidatesElection->create();
                        $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                                'Election_id' => $zones[$c['cityname']][$eareaname]['Election']['id'],
                                'Candidate_id' => $this->Candidate->getInsertID(),
                        )));
                    }
                }
            }
        }
    }

    public function villmast() {
        $baseNode = $this->Candidate->Election->children(null, true);
        $cNode = $this->Candidate->Election->find('first', array(
            'conditions' => array(
                'parent_id' => $baseNode[0]['Election']['id'],
                'name' => '村里長',
            ),
        ));
        $nodes = $this->Candidate->Election->find('all', array(
            'conditions' => array(
                'lft >' => $cNode['Election']['lft'],
                'rght <' => $cNode['Election']['rght'],
            ),
            'order' => array('Election.lft ASC'),
        ));
        $stack = array();
        foreach ($nodes AS $node) {
            if ($node['Election']['parent_id'] === $cNode['Election']['id']) {
                $county = $node['Election'];
                if (!isset($stack[$county['name']])) {
                    $stack[$county['name']] = array();
                }
            } elseif ($node['Election']['parent_id'] === $county['id']) {
                $town = $node['Election'];
                if (!isset($stack[$county['name']][$town['name']])) {
                    $stack[$county['name']][$town['name']] = array();
                }
            } else {
                $stack[$county['name']][$town['name']][$node['Election']['name']] = $node['Election']['id'];
            }
        }
        $fh = fopen(__DIR__ . '/data/villmast_excel.csv', 'r');
        fgetcsv($fh, 2048);
        fgetcsv($fh, 2048);
        while ($line = fgetcsv($fh, 2048)) {
            $line[4] = str_replace(array('　', ' '), array('', ''), $line[4]);
            $line[1] = str_replace(array('台',), array('臺',), $line[1]);
            if (isset($stack[$line[1]][$line[2]][$line[3]])) {
                $candidates = $this->Candidate->find('list', array(
                    'fields' => array('name', 'name'),
                    'joins' => array(
                        array(
                            'table' => 'candidates_elections',
                            'alias' => 'CandidatesElection',
                            'type' => 'inner',
                            'conditions' => array(
                                'CandidatesElection.Candidate_id = Candidate.id',
                                'CandidatesElection.Election_id' => $stack[$line[1]][$line[2]][$line[3]],
                            ),
                        ),
                    ),
                ));
                if (!isset($candidates[$line[4]])) {
                    $this->Candidate->create();
                    if ($this->Candidate->save(array('Candidate' => array(
                                    'name' => $line[4],
                        )))) {
                        $this->Candidate->CandidatesElection->create();
                        $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                                'Election_id' => $stack[$line[1]][$line[2]][$line[3]],
                                'Candidate_id' => $this->Candidate->getInsertID(),
                        )));
                    }
                }
            }
        }
    }

    public function suncy() {
        $accTypes = $electionTree = array();
        $baseNode = $this->Candidate->Election->children(null, true);
        $nodes = $this->Candidate->Election->children($baseNode[0]['Election']['id'], true);
        foreach ($nodes AS $node) {
            $electionTree[$node['Election']['name']] = array();
            $subNodes = $this->Candidate->Election->children($node['Election']['id'], true);
            foreach ($subNodes AS $subNode) {
                $electionTree[$node['Election']['name']][$subNode['Election']['name']] = $subNode['Election'];
            }
        }
        $fh = fopen(__DIR__ . '/data/list_new.csv', 'r');
        while ($line = fgetcsv($fh, 2048)) {
            $a = explode('擬參選人', $line[1]);
            $a[0] = substr($a[0], strpos($a[0], '年') + 3);
            $county = mb_substr($a[0], 0, 3, 'utf-8');
            $eType = mb_substr($a[0], 3, null, 'utf-8');
            switch ($county) {
                case '桃園市':
                    $county = '桃園縣';
                case '臺北市':
                case '高雄市':
                case '新北市':
                case '臺中市':
                case '臺南市':
                    $electionName = '直轄市';
                    if ($eType === '市長') {
                        $electionName .= '長';
                    } else {
                        $electionName .= $eType;
                    }
                    break;
                default:
                    $electionName = '縣市';
                    if ($eType === '議員') {
                        $electionName .= '議員';
                    } else {
                        $electionName .= '長';
                    }
                    break;
            }
            $electionId = $this->Candidate->Election->field('id', array('name' => $electionName));
            if (!empty($electionId)) {
                $eCities = $this->Candidate->Election->children($electionId);
                foreach ($eCities AS $eCity) {
                    if ($county === $eCity['Election']['name']) {
                        $candidates = $this->Candidate->find('list', array(
                            'fields' => array('name', 'name'),
                            'joins' => array(
                                array(
                                    'table' => 'candidates_elections',
                                    'alias' => 'CandidatesElection',
                                    'type' => 'inner',
                                    'conditions' => array(
                                        'CandidatesElection.Candidate_id = Candidate.id',
                                        'CandidatesElection.Election_id' => $eCity['Election']['id'],
                                    ),
                                ),
                            ),
                        ));
                        if (!isset($candidates[$line[0]])) {
                            $this->Candidate->create();
                            if ($this->Candidate->save(array('Candidate' => array(
                                            'name' => $line[0],
                                )))) {
                                $this->Candidate->CandidatesElection->create();
                                $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                                        'Election_id' => $eCity['Election']['id'],
                                        'Candidate_id' => $this->Candidate->getInsertID(),
                                )));
                            }
                        }
                    }
                }
            }
        }
    }

}
