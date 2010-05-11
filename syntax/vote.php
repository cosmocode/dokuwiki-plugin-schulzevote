<?php
/**
 * Syntax Plugin:
 * A basic voting system between multiple candidates.
 *
 * the result is determined by the schulze method
 *
 * syntax:
 * <vote right 2010-04-10>
 *  candaidate A
 *  candaidate B
 *  candaidate C
 * </vote>
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_schulzevote_vote extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 155; }

    function connectTo($mode) {
         $this->Lexer->addSpecialPattern('<vote[ a-z0-9-]*?>\n.*?\n</vote>',$mode,'plugin_schulzevote_vote');
    }

    function handle($match, $state, $pos, &$handler){
        $lines = explode("\n", $match);

        $opts = array();
        // Determine date from syntax
        $opts['date'] = null;

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $lines[0], $opts['date'])) {

            $opts['date'] = strtotime($opts['date'][0]);
            if ($opts['date'] === false || $opts['date'] === -1) {
                $opts['date'] = null;
            }
        }

        // Determine align informations
        $opts['align'] = null;
        if (preg_match('/(left|right|center)/',$lines[0], $align)) {
            $opts['align'] = $align[0];
        }

        unset($lines[count($lines)-1]);
        unset($lines[0]);

        $candidates = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $candidates[] = $line;
            }
        }

        $hlp = plugin_load('helper', 'schulzevote');
        $hlp->createVote($candidates);

        return array('candy' => $candidates, 'opts' => $opts);
    }

    function render($mode, &$renderer, $data) {

        if ($mode != 'xhtml') return false;

        if (isset($_POST['vote']) && checkSecurityToken()) {
            $this->_handlepost($data);
        }
        $this->_html($renderer, $data);
    }

    function _html(&$renderer, $data) {

        global $ID;

        // set alignment
        $align = "";
        if ($data['opts']['align'] !== null) {
            $align = ' ' . $data['opts']['align'];
        }

        $hlp = plugin_load('helper', 'schulzevote');
        // check if the vote is over.
        $open = ($data['opts']['date'] !== null) && ($data['opts']['date'] > time());
        if ($open) {
            $renderer->info['cache'] = false;
            if (is_null($_SERVER['REMOTE_USER'])) {
                $open = false;
                $closemsg = 'You need to login in order to vote. Currently leading is ' .  $hlp->getWinner();
            } elseif ($hlp->hasVoted()) {
                $open = false;
                $closemsg = 'You have voted already. Currently leading is ' .  $hlp->getWinner();
            }
        } else {
            $closemsg = 'Vote is over, winner is ' . $hlp->getWinner();
        }

        if ($open) {
            $form = new Doku_Form(array('class' => 'plugin_schulzevote_vote'.$align));
            $form->addHidden('id', $ID);

            foreach ($data['candy'] as $n => $candy) {
                $form->addElement(form_makeTextField('vote[' . $n . ']', isset($_POST['vote']) ? $_POST['vote'][$n] : '', $candy));
            }
            $form->addElement(form_makeButton('submit','', 'Vote!'));
            $form->addElement('Currently leading is ' .  $hlp->getWinner());
            $renderer->doc .=  $form->getForm();
        } else {
            $renderer->doc .= '<div class="plugin_schulzevote_vote' .$align. '">';
            foreach ($data['candy'] as $candy) {
                $renderer->doc .= '<p>' . hsc($candy) . '</p>';
            }
            $renderer->doc .= '<p>' . $closemsg . '</p>';
            $renderer->doc .= '</div>';
        }

        return true;
    }

    function _handlepost($data) {
        $hlp = plugin_load('helper', 'schulzevote');
        $hlp->vote(array_combine($data['candy'], $_POST['vote']));
        msg('Voted', 1);
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
