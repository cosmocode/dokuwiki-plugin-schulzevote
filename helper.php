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

    function get() {

        $in = $this->prefer;
        $out = array();
        foreach ($this->candys as $i) {
            foreach ($this->candys as $j) {
                if ($i != $j) {
                    if ($in[$i][$j] > $in[$j][$i]) {
                        $out[$i][$j] = $in[$i][$j];
                    } else {
                        $out[$i][$j] = 0;
                    }
                } else { $out[$i][$j] = 0; }
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

    function getWinner() {
        $get = $this->get();

        foreach ($this->candys as $test) {
            echo "test $test...";
            if ($this->isWinner($test, $get)) {
                echo "winner!";
                return $test;
            }
            echo "looser!\n";
        }
        return null;
    }

    function isWinner($candy, &$get) {
        foreach ($this->candys as $other) {
            if ($candy == $other) continue;
            if ($get[$candy][$other] <= $get[$other][$candy]) {
                return false;
            }
        }
        return true;
    }

    // create a new vote to a candidate array
    // if there is already a vote it'll be deleted.
    function createVote($candidates) {
        $this->prefer = array();
        $this->candys = $candidates;
        $this->votees = array();
    }

    // get the candidates from the current vote
    function getCandidates() {

    }


}
