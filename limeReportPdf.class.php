<?php
class limeReportPdf extends TCPDF {
    
    private $leftLogo;
    private $rightLogo;

    function setLeftLogo($logo){
        $this->leftLogo = $logo;
    }

    function setRightLogo($logo){
        $this->rightLogo = $logo;
    }    

    public function Header() {
    }

    public function Footer() {
        if ($this->leftLogo)
            $this->Image($this->leftLogo, 10, 184, '',  '', '', '', 'T', false, 300);
        if ($this->rightLogo)
            $this->Image($this->rightLogo, 10, 184, '',  '', '', '', 'T', false, 300, 'R');
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 10, $this->getAliasNumPage(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->SetLineWidth(0.6);
        $this->SetDrawColor(51,51,170);
        $this->Line(10, 180, 285, 180);
    }
}
