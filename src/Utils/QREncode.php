<?php
namespace Jazor\QRCode\Utils;

class QREncode {

    public $casesensitive = true;
    public $eightbit = false;

    public $version = 0;
    public $size = 3;
    public $margin = 4;

    public $structured = 0;

    public $level = QR_ECLEVEL_L;
    public $hint = QR_MODE_8;

    public static function binarize($frame)
    {
        $len = count($frame);
        foreach ($frame as &$frameLine) {

            for($i=0; $i<$len; $i++) {
                $frameLine[$i] = (ord($frameLine[$i])&1)?'1':'0';
            }
        }

        return $frame;
    }
    public static function factory($level = QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = new QREncode();
        $enc->size = $size;
        $enc->margin = $margin;

        switch ($level.'') {
            case '0':
            case '1':
            case '2':
            case '3':
                    $enc->level = $level;
                break;
            case 'l':
            case 'L':
                    $enc->level = QR_ECLEVEL_L;
                break;
            case 'm':
            case 'M':
                    $enc->level = QR_ECLEVEL_M;
                break;
            case 'q':
            case 'Q':
                    $enc->level = QR_ECLEVEL_Q;
                break;
            case 'h':
            case 'H':
                    $enc->level = QR_ECLEVEL_H;
                break;
        }

        return $enc;
    }

    public function encodeRAW($intext, $outfile = false)
    {
        $code = new QRCode();

        if($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }

        return $code->data;
    }

    public function encode($intext, $outfile = false)
    {
        $code = new QRCode();

        if($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }

        if ($outfile!== false) {
            file_put_contents($outfile, join("\n", QREncode::binarize($code->data)));
        } else {
            return QREncode::binarize($code->data);
        }
    }
    public function imageSource($intext, $type = 'png',$saveandprint=false)
    {
        //try {

            $tab = $this->encode($intext);

            $maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2*$this->margin));

            return QRImage::image($tab, min(max(1, $this->size), $maxSize), $this->margin);

//        } catch (\Exception $e) {
//
//
//        }
    }
    public function image($intext, $type = 'png',$saveandprint=false)
    {
        try {

            $tab = $this->encode($intext);

            $maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2*$this->margin));

            return QRImage::raw($tab, $type, min(max(1, $this->size), $maxSize), $this->margin,$saveandprint);

        } catch (\Exception $e) {


        }
    }

    public function encodePNG($intext, $outfile = false,$saveandprint=false)
    {
        try {

            $tab = $this->encode($intext);
            $maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2*$this->margin));

            QRImage::png($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin,$saveandprint);

        } catch (\Exception $e) {


        }
    }
}

