<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
require_once(DOKU_INC.'inc/plugin.php');

class helper_plugin_schulzevote extends DokuWiki_Plugin {

    function __construct() {
        global $ID;
        $data = p_get_metadata($ID, 'schulzevote');
        $this->prefer = $data['prefer'];
        $this->candys = $data['candys'];
        $this->votees = $data['votees'];

/* Ambiguous, but B > C and D > A
        $this->prefer = array(
'A' => array('A' => 0, 'B' => 5, 'C' => 5, 'D' => 3),
'B' => array('A' => 4, 'B' => 0, 'C' => 7, 'D' => 5),
'C' => array('A' => 4, 'B' => 2, 'C' => 0, 'D' => 5),
'D' => array('A' => 6, 'B' => 4, 'C' => 4, 'D' => 0),
);
        $this->candys = array(
'A' => 'A',
'B' => 'B',
'C' => 'C',
'D' => 'D',
);
*/

/* E > A > C > B > D
        $this->prefer = array(
'A' => array('A' => 0, 'B' => 20, 'C' => 26, 'D' => 30, 'E' => 22),
'B' => array('A' => 25, 'B' => 0, 'C' => 16, 'D' => 33, 'E' => 18),
'C' => array('A' => 19, 'B' => 29, 'C' => 0, 'D' => 17, 'E' => 24),
'D' => array('A' => 15, 'B' => 12, 'C' => 28, 'D' => 0, 'E' => 14),
'E' => array('A' => 23, 'B' => 27, 'C' => 21, 'D' => 31, 'E' => 0),
);
        $this->candys = array(
'A' => 'A',
'B' => 'B',
'C' => 'C',
'D' => 'D',
'E' => 'E',
);
*/
    }

    function __destruct() {
        global $ID;
        p_set_metadata($ID, array('schulzevote' => array('prefer' => $this->prefer, 'candys' => $this->candys, 'votees' => $this->votees)));
    }

    // run a vote $data = array('a' => 1, 'b' => 2, 'c' => 2, 'd' => 3)
    // user need to be logged in
    // recalc the winner and prefer matrix
    function vote($data) {
        global $ID;

        foreach ($data as $k => $v) {
            foreach($data as $k2 => $v2) {
                if ($v < $v2) {
                    ++$this->prefer[$k][$k2];
                }
            }
        }
        $this->votees[] = $_SERVER['REMOTE_USER'];
    }

    function hasVoted() {
        return in_array($_SERVER['REMOTE_USER'], $this->votees);
    }

    /* Return strength of the strongest path */
    function get() {

        $in = $this->prefer;
        $out = array();
        foreach ($this->candys as $i) {
            foreach ($this->candys as $j) {
                if ($i != $j && $in[$i][$j] > $in[$j][$i]) {
                    $out[$i][$j] = $in[$i][$j];
                } else {
                    $out[$i][$j] = 0;
                }
            }
        }

        foreach ($this->candys as $i) {
            foreach ($this->candys as $j) {
                if ($i!=$j) {
                    foreach ($this->candys as $k) {
                        if ($i!=$k) {
                            if ($j!=$k) {
                                $out[$j][$k] = max($out[$j][$k], min($out[$j][$i], $out[$i][$k]));
                            }
                        }
                    }
                }
            }
        }
        return $out;
    }

    function getRanking() {
        $get = $this->get();

        $ret = array();
        while (count($get) > 0) {
            $winners = array();
            foreach (array_keys($get) as $test) {
                if ($this->isWinnerCandidate($test, $get)) {
                    $winners[] = $test;
                }
            }
            if (count($winners) == 0) {
                $winners = array_keys($get);
            }
            foreach($winners as $winner) {
                unset($get[$winner]);
            }
            $ret[] = $winners;
        }
        return $ret;
    }

    function getWinner() {
        $get = $this->get();
#dbg($get);

        foreach ($this->candys as $test) {
#            echo "test $test...";
            if ($this->isWinner($test, $get)) {
#                echo "winner!";
                return $test;
            }
#            echo "looser!\n";
        }
        return null;
    }

    function isWinnerCandidate($candy, $get) {
        foreach ($this->candys as $other) {
            if ($candy == $other) continue;
            if ($get[$candy][$other] < $get[$other][$candy]) {
                return false;
            }
        }
        return true;
    }

    function isWinner($candy, $get) {
        foreach ($this->candys as $other) {
            if ($candy == $other) continue;
            if ($get[$candy][$other] <= $get[$other][$candy]) {
                return false;
            }
        }
        return true;
    }

    // create a new vote to a candidate array
    function createVote($candidates) {
        if ($candidates !== $this->candys) {
            $this->prefer = array();
            $this->candys = $candidates;
            $this->votees = array();
        }
    }
}
