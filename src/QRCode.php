<?php
namespace Jazor\QRCode;

use Jazor\QRCode\Utils\FrameFiller;
use Jazor\QRCode\Utils\QREncode;
use Jazor\QRCode\Utils\QRInput;
use Jazor\QRCode\Utils\QRMask;
use Jazor\QRCode\Utils\QRRawCode;
use Jazor\QRCode\Utils\QRSpec;
use Jazor\QRCode\Utils\QRSplit;

class QRCode
{

    public $version;
    public $width;
    public $data;

    public function encodeMask(QRInput $input, $mask)
    {
        if ($input->getVersion() < 0 || $input->getVersion() > QRSPEC_VERSION_MAX) {
            throw new \Exception('wrong version');
        }
        if ($input->getErrorCorrectionLevel() > QR_ECLEVEL_H) {
            throw new \Exception('wrong level');
        }

        $raw = new QRRawCode($input);


        $version = $raw->version;
        $width = QRSpec::getWidth($version);
        $frame = QRSpec::newFrame($version);

        $filler = new FrameFiller($width, $frame);
        if (is_null($filler)) {
            return NULL;
        }

        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }


        unset($raw);

        $j = QRSpec::getRemainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);

        $maskObj = new QRMask();
        if ($mask < 0) {

            if (QR_FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask($width, $frame, (intval(QR_DEFAULT_MASK) % 8), $input->getErrorCorrectionLevel());
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

        if ($masked == NULL) {
            return NULL;
        }

        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;

        return $this;
    }

    public function encodeInput(QRInput $input)
    {
        return $this->encodeMask($input, -1);
    }

    public function encodeString8bit($string, $version, $level)
    {
        if ($string == NULL) {
            throw new \Exception('empty string!');
        }

        $input = new QRInput($version, $level);
        if ($input == NULL) return NULL;

        $ret = $input->append($input, QR_MODE_8, strlen($string));
        if ($ret < 0) {
            unset($input);
            return NULL;
        }
        return $this->encodeInput($input);
    }

    public function encodeString($string, $version, $level, $hint, $casesensitive)
    {

        if ($hint != QR_MODE_8 && $hint != QR_MODE_KANJI) {
            throw new \Exception('bad hint');
        }

        $input = new QRInput($version, $level);
        if ($input == NULL) return NULL;

        $ret = QRSplit::splitStringToQRInput($string, $input, $hint, $casesensitive);
        if ($ret < 0) {
            return NULL;
        }

        return $this->encodeInput($input);
    }

    /**
     * encode and output
     * @param $text
     * @param bool $outfile
     * @param int $level
     * @param int $size
     * @param int $margin
     * @param bool $saveAndPrint
     */
    public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 1, $saveAndPrint = false)
    {
        $enc = QREncode::factory($level, $size, $margin);
        $enc->encodePNG($text, $outfile, $saveAndPrint);
    }


    /**
     * encode and get image resource
     * @param $text
     * @param string $type
     * @param int $level
     * @param int $size
     * @param int $margin
     * @param bool $saveAndPrint
     * @return false|resource
     */
    public static function resource($text, $type = 'png', $level = QR_ECLEVEL_L, $size = 3, $margin = 1, $saveAndPrint = false)
    {
        $enc = QREncode::factory($level, $size, $margin);
        return $enc->imageSource($text, $type, $saveAndPrint);
    }

    /**
     * encode and get image data
     * @param $text
     * @param string $type
     * @param int $level
     * @param int $size
     * @param int $margin
     * @param bool $saveAndPrint
     * @return false|string
     */
    public static function image($text, $type = 'png', $level = QR_ECLEVEL_L, $size = 3, $margin = 1, $saveAndPrint = false)
    {
        $enc = QREncode::factory($level, $size, $margin);
        return $enc->image($text, $type, $saveAndPrint);
    }

    /**
     * get encoded source, eg. 01011010101101010101101
     * @param $text
     * @param bool $outfile
     * @param int $level
     * @param int $size
     * @param int $margin
     * @return string[] 01011010101101010101101
     */
    public static function text($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 1)
    {
        $enc = QREncode::factory($level, $size, $margin);
        return $enc->encode($text, $outfile);
    }

    /**
     * @param $text
     * @param bool $outfile
     * @param int $level
     * @param int $size
     * @param int $margin
     * @return string[]
     */
    public static function raw($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 1)
    {
        $enc = QREncode::factory($level, $size, $margin);
        return $enc->encodeRAW($text, $outfile);
    }
}
