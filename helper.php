<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
require_once(DOKU_INC.'inc/plugin.php');

class helper_plugin_schulzevote {

    // run a vote $data = ABCD
    // user need to be loged in
    // recalc the winner and prefer matrix
    function vote($data) {
        global $ID;
        
    }

    // get the vote from a user
    function getVote() {

    }

    // get the winner
    function getWinner() {

    }

    // create a new vote to a candidate array
    // if there is already a vote it'll be deleted.
    function createVote($candidates) {

    }

    // get the candidates from the current vote
    function getCandidates() {

    }


}
