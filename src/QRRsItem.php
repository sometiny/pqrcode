<?php
namespace Jazor\QRCode;

class QRRsItem {

    public $mm;
    public $nn;
    public $alpha_to = array();
    public $index_of = array();
    public $genpoly = array();
    public $nroots;
    public $fcr;
    public $prim;
    public $iprim;
    public $pad;
    public $gfpoly;

    public function modnn($x)
    {
        while ($x >= $this->nn) {
            $x -= $this->nn;
            $x = ($x >> $this->mm) + ($x & $this->nn);
        }

        return $x;
    }

    public static function init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
    {

        $rs = null;

        if($symsize < 0 || $symsize > 8)                     return $rs;
        if($fcr < 0 || $fcr >= (1<<$symsize))                return $rs;
        if($prim <= 0 || $prim >= (1<<$symsize))             return $rs;
        if($nroots < 0 || $nroots >= (1<<$symsize))          return $rs;
        if($pad < 0 || $pad >= ((1<<$symsize) -1 - $nroots)) return $rs;

        $rs = new QRRsItem();
        $rs->mm = $symsize;
        $rs->nn = (1<<$symsize)-1;
        $rs->pad = $pad;

        $rs->alpha_to = array_fill(0, $rs->nn+1, 0);
        $rs->index_of = array_fill(0, $rs->nn+1, 0);

        $NN =& $rs->nn;
        $A0 =& $NN;

        $rs->index_of[0] = $A0;
        $rs->alpha_to[$A0] = 0;
        $sr = 1;

        for($i=0; $i<$rs->nn; $i++) {
            $rs->index_of[$sr] = $i;
            $rs->alpha_to[$i] = $sr;
            $sr <<= 1;
            if($sr & (1<<$symsize)) {
                $sr ^= $gfpoly;
            }
            $sr &= $rs->nn;
        }

        if($sr != 1){

            $rs = NULL;
            return $rs;
        }

        $rs->genpoly = array_fill(0, $nroots+1, 0);

        $rs->fcr = $fcr;
        $rs->prim = $prim;
        $rs->nroots = $nroots;
        $rs->gfpoly = $gfpoly;

        for($iprim=1;($iprim % $prim) != 0;$iprim += $rs->nn)
        ;

        $rs->iprim = (int)($iprim / $prim);
        $rs->genpoly[0] = 1;

        for ($i = 0,$root=$fcr*$prim; $i < $nroots; $i++, $root += $prim) {
            $rs->genpoly[$i+1] = 1;

            for ($j = $i; $j > 0; $j--) {
                if ($rs->genpoly[$j] != 0) {
                    $rs->genpoly[$j] = $rs->genpoly[$j-1] ^ $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[$j]] + $root)];
                } else {
                    $rs->genpoly[$j] = $rs->genpoly[$j-1];
                }
            }

            $rs->genpoly[0] = $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[0]] + $root)];
        }

        for ($i = 0; $i <= $nroots; $i++)
            $rs->genpoly[$i] = $rs->index_of[$rs->genpoly[$i]];

        return $rs;
    }

    public function encode_rs_char($data, &$parity)
    {
        $MM       =& $this->mm;
        $NN       =& $this->nn;
        $ALPHA_TO =& $this->alpha_to;
        $INDEX_OF =& $this->index_of;
        $GENPOLY  =& $this->genpoly;
        $NROOTS   =& $this->nroots;
        $FCR      =& $this->fcr;
        $PRIM     =& $this->prim;
        $IPRIM    =& $this->iprim;
        $PAD      =& $this->pad;
        $A0       =& $NN;

        $parity = array_fill(0, $NROOTS, 0);

        for($i=0; $i< ($NN-$NROOTS-$PAD); $i++) {

            $feedback = $INDEX_OF[$data[$i] ^ $parity[0]];
            if($feedback != $A0) {

                $feedback = $this->modnn($NN - $GENPOLY[$NROOTS] + $feedback);

                for($j=1;$j<$NROOTS;$j++) {
                    $parity[$j] ^= $ALPHA_TO[$this->modnn($feedback + $GENPOLY[$NROOTS-$j])];
                }
            }

            array_shift($parity);
            if($feedback != $A0) {
                array_push($parity, $ALPHA_TO[$this->modnn($feedback + $GENPOLY[0])]);
            } else {
                array_push($parity, 0);
            }
        }
    }
}
