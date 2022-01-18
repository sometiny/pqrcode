<?php
namespace Jazor\QRCode\Utils;

class QRSplit {

    public $dataStr = '';
    public $input;
    public $modeHint;

    public function __construct($dataStr, $input, $modeHint)
    {
        $this->dataStr  = $dataStr;
        $this->input    = $input;
        $this->modeHint = $modeHint;
    }

    public static function isdigitat($str, $pos)
    {
        if ($pos >= strlen($str))
            return false;

        return ((ord($str[$pos]) >= ord('0'))&&(ord($str[$pos]) <= ord('9')));
    }

    public static function isalnumat($str, $pos)
    {
        if ($pos >= strlen($str))
            return false;

        return (QRInput::lookAnTable(ord($str[$pos])) >= 0);
    }

    public function identifyMode($pos)
    {
        if ($pos >= strlen($this->dataStr))
            return -1;

        $c = $this->dataStr[$pos];

        if(self::isdigitat($this->dataStr, $pos)) {
            return QR_MODE_NUM;
        } else if(self::isalnumat($this->dataStr, $pos)) {
            return QR_MODE_AN;
        } else if($this->modeHint == QR_MODE_KANJI) {

            if ($pos+1 < strlen($this->dataStr))
            {
                $d = $this->dataStr[$pos+1];
                $word = (ord($c) << 8) | ord($d);
                if(($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
                    return QR_MODE_KANJI;
                }
            }
        }

        return QR_MODE_8;
    }

    public function eatNum()
    {
        $ln = QRSpec::lengthIndicator(QR_MODE_NUM, $this->input->getVersion());

        $p = 0;
        while(self::isdigitat($this->dataStr, $p)) {
            $p++;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if($mode == QR_MODE_8) {
            $dif = QRInput::estimateBitsModeNum($run) + 4 + $ln
                 + QRInput::estimateBitsMode8(1)
                 - QRInput::estimateBitsMode8($run + 1);
            if($dif > 0) {
                return $this->eat8();
            }
        }
        if($mode == QR_MODE_AN) {
            $dif = QRInput::estimateBitsModeNum($run) + 4 + $ln
                 + QRInput::estimateBitsModeAn(1)
                 - QRInput::estimateBitsModeAn($run + 1);
            if($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(QR_MODE_NUM, $run, str_split($this->dataStr));
        if($ret < 0)
            return -1;

        return $run;
    }

    public function eatAn()
    {
        $la = QRSpec::lengthIndicator(QR_MODE_AN,  $this->input->getVersion());
        $ln = QRSpec::lengthIndicator(QR_MODE_NUM, $this->input->getVersion());

        $p = 0;

        while(self::isalnumat($this->dataStr, $p)) {
            if(self::isdigitat($this->dataStr, $p)) {
                $q = $p;
                while(self::isdigitat($this->dataStr, $q)) {
                    $q++;
                }

                $dif = QRInput::estimateBitsModeAn($p)
                     + QRInput::estimateBitsModeNum($q - $p) + 4 + $ln
                     - QRInput::estimateBitsModeAn($q);

                if($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }

        $run = $p;

        if(!self::isalnumat($this->dataStr, $p)) {
            $dif = QRInput::estimateBitsModeAn($run) + 4 + $la
                 + QRInput::estimateBitsMode8(1)
                  - QRInput::estimateBitsMode8($run + 1);
            if($dif > 0) {
                return $this->eat8();
            }
        }

        $ret = $this->input->append(QR_MODE_AN, $run, str_split($this->dataStr));
        if($ret < 0)
            return -1;

        return $run;
    }

    public function eatKanji()
    {
        $p = 0;

        while($this->identifyMode($p) == QR_MODE_KANJI) {
            $p += 2;
        }

        $ret = $this->input->append(QR_MODE_KANJI, $p, str_split($this->dataStr));
        if($ret < 0)
            return -1;

        return $ret;
    }

    public function eat8()
    {
        $la = QRSpec::lengthIndicator(QR_MODE_AN, $this->input->getVersion());
        $ln = QRSpec::lengthIndicator(QR_MODE_NUM, $this->input->getVersion());

        $p = 1;
        $dataStrLen = strlen($this->dataStr);

        while($p < $dataStrLen) {

            $mode = $this->identifyMode($p);
            if($mode == QR_MODE_KANJI) {
                break;
            }
            if($mode == QR_MODE_NUM) {
                $q = $p;
                while(self::isdigitat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = QRInput::estimateBitsMode8($p)
                     + QRInput::estimateBitsModeNum($q - $p) + 4 + $ln
                     - QRInput::estimateBitsMode8($q);
                if($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else if($mode == QR_MODE_AN) {
                $q = $p;
                while(self::isalnumat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = QRInput::estimateBitsMode8($p)
                     + QRInput::estimateBitsModeAn($q - $p) + 4 + $la
                     - QRInput::estimateBitsMode8($q);
                if($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }

        $run = $p;
        $ret = $this->input->append(QR_MODE_8, $run, str_split($this->dataStr));

        if($ret < 0)
            return -1;

        return $run;
    }

    public function splitString()
    {
        while (strlen($this->dataStr) > 0)
        {
            if($this->dataStr == '')
                return 0;

            $mode = $this->identifyMode(0);

            switch ($mode) {
                case QR_MODE_NUM: $length = $this->eatNum(); break;
                case QR_MODE_AN:  $length = $this->eatAn(); break;
                case QR_MODE_KANJI: $length = $this->eatKanji(); break;
                default: $length = $this->eat8(); break;

            }

            if($length == 0) return 0;
            if($length < 0)  return -1;

            $this->dataStr = substr($this->dataStr, $length);
        }
    }

    public function toUpper()
    {
        $stringLen = strlen($this->dataStr);
        $p = 0;

        while ($p<$stringLen) {
            $mode = self::identifyMode(substr($this->dataStr, $p));
            if($mode == QR_MODE_KANJI) {
                $p += 2;
            } else {
                if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
                    $this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
                }
                $p++;
            }
        }

        return $this->dataStr;
    }

    public static function splitStringToQRInput($string, QRInput $input, $modeHint, $casesensitive = true)
    {
        if(is_null($string) || $string == '\0' || $string == '') {
            throw new \Exception('empty string!!!');
        }

        $split = new QRSplit($string, $input, $modeHint);

        if(!$casesensitive)
            $split->toUpper();

        return $split->splitString();
    }
}
