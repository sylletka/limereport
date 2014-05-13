<?php

include("../pchart2/class/pData.class.php");
include("../pchart2/class/pDraw.class.php");
include("../pchart2/class/pImage.class.php");
include("../pchart2/class/pRadar.class.php");
include("../phpmailer/class.phpmailer.php");
include("../tcpdf/tcpdf.php");

class limeReport{
    private $graphsDir;
    private $id;
    private $sid;
    private $host;
    private $database;
    private $user;
    private $password;
    private $bccs;
    private $leftLogo;
    private $rightLogo;
    private $limeReportPdf;
    private $mysqli;
    private $basePath;
    private $survey;
    private $questions;
    private $scaleSettings=array();
    private $chartConfig=array();
    private $radarConfig=array();
    private $graphFunctions=array();
    private $dataFunctions=array();

    public $charts;
    public $data;
    public $tables;

    function limeReport(){
//        $this->limeReportPdf = new limeReportPdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    }

    function setLeftLogo($logo){
        $this->limeReportPdf->setLeftLogo($logo);
    }

    function setRightLogo($logo){
        $this->limeReportPdf->setRightLogo($logo);
    }

    function setGraphsDir($graphsDir){
        $this->graphsDir = $graphsDir;
    }

    function setGraphsPath($graphsPath){
        $this->graphsPath = $graphsPath;
    }

    function setId($id){
        $this->id = $id;
    }

    function setSid($sid){
        $this->sid = $sid;
    }

    function setBasePath($basePath){
        $this->basePath = $basePath;
    }

    function setBccs($bccs){
        $this->bccs = $bccs;
    }

    function setScaleSettings($scaleSettings){
        $this->scaleSettings = array_merge($this->scaleSettings,$scaleSettings);
    }

    function setChartConfig($chartConfig){
        $this->chartConfig = array_merge($this->chartConfig,$chartConfig);
    }

    function setRadarConfig($radarConfig){
        $this->radarConfig = array_merge($this->radarConfig,$radarConfig);
    }

    function unsetScaleSettings($scaleSettings=array()){
        if (count($scaleSettings)>0){
            foreach ($scaleSettings as $scaleSetting){
                unset ($this->scaleSettings[$scaleSetting]);
            }
        } else {
            $this->scaleSettings = array();
        }
    }

    function unsetChartConfig($chartConfig=array()){
        if (count($chartConfig)>0){
            foreach ($chartConfig as $scaleSetting){
                unset ($this->chartConfig[$scaleSetting]);
            }
        } else {
            $this->chartConfig = array();
        }
    }

    function unsetRadarConfig($radarConfig=array()){
        if (count($radarConfig)>0){
            foreach ($radarConfig as $scaleSetting){
                unset ($this->radarConfig[$scaleSetting]);
            }
        } else {
            $this->radarConfig = array();
        }
    }


    function getData(){
        define ('BASEPATH', $this->basePath . '/application/config');
        $config= (include BASEPATH . '/config.php');
        $connectionString=explode(';',$config['components']['db']['connectionString']);
        $db = array();
        foreach ($connectionString as $value){
            $parse=preg_split("/=/", $value);
            if (count($parse) == 2) {$db[$parse[0]] = $parse[1];}
        }
        $this->mysqli = new mysqli($db['mysql:host'], $config['components']['db']['username'], $config['components']['db']['password'], $db['dbname']);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }
        $this->mysqli->query("SET NAMES utf8");
        $query = sprintf(
            "SELECT * FROM survey_%d WHERE id = %d",
            $this->sid,
            $this->id
        );
        $result = $this->mysqli->query($query);
        while($field = $result->fetch_array(MYSQLI_ASSOC)){
            $this->survey=$field;
        }
        $query = sprintf(
            "SELECT *, concat(sid,'X',gid,'X',qid) AS 'column' FROM questions WHERE sid = %d AND type NOT IN ('T')",
            $this->sid
        );
        echo $query . "<br>";
        $result = $this->mysqli->query($query);
        while($field = $result->fetch_array(MYSQLI_ASSOC)){
            $this->questions[$field['qid']]=$field;
        }
//        var_dump($this->questions);
        $query = sprintf(
            "SELECT qid, code,answer FROM answers WHERE qid IN (%s)",
            implode(',',array_keys($this->questions))
        );
        echo $query . "<br>";
        $result = $this->mysqli->query($query);
        while($field = $result->fetch_array(MYSQLI_ASSOC)){
            if (!array_key_exists('answers',$this->questions[$field['qid']])){
                $this->questions[$field['qid']]['answers']=array(
                    $field['code'] => $field['answer']
                );
            } else {
                $this->questions[$field['qid']]['answers'][$field['code']] = $field['answer'];
            }
        }
        $query = sprintf(
            "SELECT *, concat(sid,'X',gid,'X',parent_qid,title) AS 'column' FROM questions WHERE sid = %d AND parent_qid != 0",
            $this->sid
        );
        echo $query. "<br>";
        $result = $this->mysqli->query($query);
        while($field = $result->fetch_array(MYSQLI_ASSOC)){
            echo 'qid=' . $field['qid'] . "<br>";
            echo 'parent_qid=' . $field['parent_qid'] . "<br>";
            if (!array_key_exists('answers',$this->questions[$field['parent_qid']])){
                $this->questions[$field['parent_qid']]['answers']=array(
                    $field['column'] => $field['question']
                );
            } else {
                $this->questions[$field['parent_qid']]['answers'][$field['column']] = $field['question'];
            }

        }
        foreach ($this->questions as $question) {
            switch ($question['type']){
                case ';':
                    break;
                case 'M':
                    $answers=array();
                    foreach ($question['answers'] as $column=>$answer){
                        if ($this->survey[$column] == 'Y'){
                            $answers[]=$answer;
                        }
                    }
                    $this->data[$question['title']] = implode(', ',$answers);
                    break;
                case 'L':
                case '!':
                case 'O':
                    $this->data[$question['title']] = $question['answers'][$this->survey[$question['column']]];
                    break;
                default:
                    $this->data[$question['title']] = $this->survey[$question['column']];

            }
        }
        $this->mysqli->close();
        return ($this->data);
    }

    function renderTable($tableId) {
        if (array_key_exists('thead',$this->tables[$tableId])){
            if (array_key_exists('cells',$this->tables[$tableId]['thead'])){
                foreach ($this->tables[$tableId]['thead']['cells'] as $hcell){
                    @$headercells .= $this->renderElement('th',@$hcell['content'],@$hcell['attributes']);
                }
            }
            $headerrow= $this->renderElement('tr',$headercells,@$this->tables[$tableId]['thead']['attributes']);
        }
        if (array_key_exists('tbody',$this->tables[$tableId])){
            foreach ($this->tables[$tableId]['tbody'] as $row){
                $rowcells="";

                foreach (@$row['cells'] as $rcell){
                    $rowcells .= $this->renderElement('td',@$rcell['content'],@$rcell['attributes']);
                }
                @$bodyrows .= $this->renderElement('tr',$rowcells,@$row['attributes']);
            }
        }
        $thead = $this->renderElement('thead',$headerrow,@$this->tables[$tableId]['header']['attributes']);
        $tbody = $this->renderElement('tbody',$bodyrows);
        $table = $this->renderElement('table',$thead.$tbody,@$this->tables[$tableId]['attributes']);
        return $table;
    }

    public function renderElement($element, $content=NULL,$attributes=array()){
        $elementattributes=array();
        if ($attributes){
            foreach ($attributes as $attribute=>$value){
                $elementattributes[]= $attribute . '="' . $value . '"';
            }
        }
        if (count($elementattributes)>0)
            $renderedattrs = " " . implode(' ',$elementattributes);
        if ($element == 'img'){
            $string = '<%1$s%2$s/>%4$s';
        } else {
            $string = '<%1$s%2$s>%4$s%3$s%4$s</%1$s>%4$s';
        }
        $output=sprintf(
            $string,
            $element,
            @$renderedattrs,
            $content,
            PHP_EOL
        );
        return $output;
    }

    function defineChart($chartId,$data,$type='bar',$width=600,$height=400){
        $labels=array();
        $points=array();
        foreach ($data as $label=>$value){
            $points[]=$value;
            $labels[]=$label;
        }
        $this->charts[$chartId]=array();
        $this->charts[$chartId]['type'] = $type;
        $this->charts[$chartId]['data'] = new pData();
        switch ($type){
            case 'bar':
                $this->charts[$chartId]['data']->addPoints($points,"Points");
                $this->charts[$chartId]['data']->setSerieDescription("Points","Points");
                $this->charts[$chartId]['data']->setSerieOnAxis("Points",0);
                $this->charts[$chartId]['data']->addPoints($labels,"Labels");
                $this->charts[$chartId]['data']->setSerieDescription("Labels","Labels");
                $this->charts[$chartId]['data']->setAbscissa("Labels");
                $this->charts[$chartId]['data']->setAxisPosition(0,AXIS_POSITION_LEFT);
                break;
            case 'radar':
                $this->charts[$chartId]['data']->addPoints($points, "ScoreA");
                $this->charts[$chartId]['data']->addPoints($labels, "Labels");
                $this->charts[$chartId]['data']->setAbscissa("Labels");
                $this->charts[$chartId]['radar'] =  new pRadar();
                break;
        }
        $this->charts[$chartId]['image']=new pImage($width, $height, $this->charts[$chartId]['data']);
        $this->charts[$chartId]['image']->setFontProperties(array(
            "FontName" => "inc/pchart2/fonts/MankSans.ttf",
            "FontSize" => 12
        ));
        $this->charts[$chartId]['image']->setGraphArea(($width/2), 30, ($width - 30), ($height - 30));
        $this->charts[$chartId]['file'] = $this->graphsDir . "/" . $this->id . "-" . $chartId . "-graph.png";
    }

    function genereateChart($chartId){
        $chart = $this->charts[$chartId];
        foreach ($this->graphFunctions as $function){
            call_user_func_array(array($chart['image'],$function['function']),$function['arguments']);
        }
        foreach ($this->dataFunctions as $function){
            call_user_func_array(array($chart['data'],$function['function']),$function['arguments']);
        }
        switch ($chart['type']){
            case 'bar':
                $chart['image']->drawScale($this->scaleSettings);
                $chart['image']->drawBarChart($this->chartConfig);
                break;
            case 'radar':
                $chart['radar']->drawRadar($chart['image'], $chart['data'], $this->radarConfig);
        }
        $chart['image']->Render($chart['file']);
    }

    function renderChart($chartId){
        $img = $this->renderElement('img','',array('src'=>$this->graphsPath . "/" . $this->id . "-" . $chartId . "-graph.png"));
        return $img;
    }

    function registerGraphFunction($function,$arguments){
        $this->graphFunctions[]=array(
            'function' => $function,
            'arguments' => $arguments
        );
        end($this->graphFunctions);
        return key($this->graphFunctions);
    }

    function unregisterGraphFunction($functionKey){
        unset ($this->graphFunctions[$functionKey]);
    }

    function registerDataFunction($function,$arguments){
        $this->dataFunctions[]=array(
            'function' => $function,
            'arguments' => $arguments
        );
        end($this->dataFunctions);
        return key($this->dataFunctions);
    }

    function unregisterDataFunction($functionKey){
        unset ($this->dataFunctions[$functionKey]);
    }


    function pdf_add_copyright($text){
        $this->limeReportPdf->StartTransform();
        $this->limeReportPdf->SetFont('helvetica', '', 9, '', 'default', 1);
        $this->limeReportPdf->Rotate(-90);
        $this->limeReportPdf->Cell(0,0,$text,0,1,'',0,'');
        $this->limeReportPdf->StopTransform();
    }

    function pdf_add_title($text){
        $this->limeReportPdf->LinearGradient(0,0,300,17, array(200), array(255));
        $this->limeReportPdf->SetFont('helvetica', 'B', 26, '', 'default', 1);
        $this->limeReportPdf->writeHTMLCell(300, 30, 0, 2, $text, '', 1, 0, true, '', true);
    }

    function purgeOldGraphs(){
        if ($handle = opendir($this->graphsDir)) {
            while (false !== ($entry = readdir($handle))) {
                if ((preg_match('/.*\.png/', $entry)) && ( filemtime($this->graphsDir . "\\" . $entry) < strtotime("-1 days"))) {
                    unlink( $this->graphsDir .  "\\" . $entry);
                }
            }
            closedir($handle);
        }
    }
}
